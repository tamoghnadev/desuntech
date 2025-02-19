<?php

function importCsvToTable(string $csvFilePath, mysqli $conn): bool
{
    if (!file_exists($csvFilePath)) {
        return false; // File does not exist
    }

    $file = fopen($csvFilePath, 'r');
    if ($file === false) {
        return false; // Could not open the file
    }

    $tableName = 'user_det'; // Replace with your actual table name

    $headers = fgetcsv($file); // Get headers from the first row
    if ($headers === false) {
        fclose($file);
        return false; // Could not read headers
    }

    // Start transaction for atomicity
    $conn->begin_transaction();

    try {
        while (($row = fgetcsv($file)) !== false) {
            // Prepare SQL INSERT statement
            $columns = implode(',', $headers);
            $values = array_map(function($value) use ($conn) {
                return "'" . mysqli_real_escape_string($conn, $value) . "'";
            }, $row);
            $valuesString = implode(',', $values);

            $sql = "INSERT INTO $tableName ($columns) VALUES ($valuesString)";

            if ($conn->query($sql) !== TRUE) {
                fclose($file);
                $conn->rollback(); // Rollback transaction on error
                throw new Exception("Error inserting data: " . $conn->error);
            }
        }

        $conn->commit(); // Commit transaction if all inserts are successful
        fclose($file);
        return true; // Import successful

    } catch (Exception $e) {
        // Log error or handle it as needed
        echo "Error during CSV import: " . $e->getMessage() . "\n";
        return false; // Import failed due to exception
    }
}

function validateAndCompressMedia(array $file, int $maxSizeKB, string $uploadDir): array|string
{
    $maxSizeBytes = $maxSizeKB * 1024; // Convert KB to bytes
    $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $allowedVideoTypes = ['video/mp4', 'video/mpeg', 'video/quicktime']; // Example video types

    $fileType = mime_content_type($file['tmp_name']);
    $fileSize = $file['size'];
    $fileName = $file['name'];
    $fileTmpPath = $file['tmp_name'];

    // --- Enhanced Debug Output to HTML ---
    echo "<div style='border: 1px solid blue; padding: 10px; margin-bottom: 10px;'>";
    echo "<h4 style='color: blue;'>Debug Information:</h4>";
    echo "<p style='color: blue;'><b>Detected MIME type:</b> <code>" . htmlspecialchars($fileType) . "</code></p>";
    echo "<p style='color: blue;'><b>File name:</b> <code>" . htmlspecialchars($fileName) . "</code></p>";
    echo "<p style='color: blue;'><b>File size (bytes):</b> <code>" . htmlspecialchars($fileSize) . "</code></p>";
    echo "<p style='color: blue;'><b>Max allowed size (bytes):</b> <code>" . htmlspecialchars($maxSizeBytes) . "</code></p>";
    echo "<p style='color: blue;'><b>Allowed image types:</b> <code>" . htmlspecialchars(implode(', ', $allowedImageTypes)) . "</code></p>";
    echo "<p style='color: blue;'><b>Allowed video types:</b> <code>" . htmlspecialchars(implode(', ', $allowedVideoTypes)) . "</code></p>";
    echo "</div>";
    // --- End Enhanced Debug Output ---


    if (!in_array($fileType, array_merge($allowedImageTypes, $allowedVideoTypes))) {
        return "Error: Invalid file type. Allowed types are images (jpeg, png, gif) and videos (mp4, mpeg, quicktime).";
    }

    if ($fileSize > $maxSizeBytes) {
        if (in_array($fileType, $allowedImageTypes)) {
            // Compress image
            $compressedFilePath = compressImage($fileTmpPath, $uploadDir, $fileName, $fileType, 80); // 80 is compression quality (adjust as needed)
            if ($compressedFilePath) {
                return ['filepath' => $compressedFilePath, 'original_name' => $fileName, 'compressed' => true];
            } else {
                return "Error: Image compression failed.";
            }
        } elseif (in_array($fileType, $allowedVideoTypes)) {
            // For video compression, you would typically need external tools or libraries in PHP
            // This is a placeholder - in real scenarios, you might use ffmpeg or similar
            // For this example, we'll just save the original video and indicate it wasn't compressed
            $targetFilePath = saveFile($fileTmpPath, $uploadDir, $fileName);
            if ($targetFilePath) {
                 return ['filepath' => $targetFilePath, 'original_name' => $fileName, 'compressed' => false, 'message' => 'Video file size exceeds limit but video compression is not implemented in this example. Original video saved.'];
            } else {
                return "Error: Video saving failed.";
            }
        }
    } else {
        // Save file without compression
        $targetFilePath = saveFile($fileTmpPath, $uploadDir, $fileName);
        if ($targetFilePath) {
            return ['filepath' => $targetFilePath, 'original_name' => $fileName, 'compressed' => false];
        } else {
            return "Error: File saving failed.";
        }
    }

    return "Unknown error during file processing."; // Fallback error
}


function compressImage(string $sourceFilePath, string $destinationDir, string $fileName, string $mimeType, int $quality): string|bool
{
    $image = null;
    switch ($mimeType) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($sourceFilePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourceFilePath);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($sourceFilePath);
            break;
        default:
            return false; // Unsupported image type
    }

    if (!$image) {
        return false; // Image creation failed
    }

    $destinationFilePath = $destinationDir . '/' . uniqid() . '_' . $fileName; // Unique filename
    $success = false;

    switch ($mimeType) {
        case 'image/jpeg':
            $success = imagejpeg($image, $destinationFilePath, $quality);
            break;
        case 'image/png':
            $success = imagepng($image, $destinationFilePath, 9); // PNG compression level (0-9, 9 is max compression)
            break;
        case 'image/gif':
            $success = imagegif($image, $destinationFilePath);
            break;
    }

    imagedestroy($image); // Free up memory

    if ($success) {
        return $destinationFilePath;
    } else {
        return false; // Image saving failed
    }
}


function saveFile(string $sourceFilePath, string $destinationDir, string $fileName): string|bool
{
    // Ensure destination directory exists
    if (!is_dir($destinationDir)) {
        if (!mkdir($destinationDir, 0777, true)) { // Create directory if not exists
            return false; // Directory creation failed
        }
    }

    $destinationFilePath = $destinationDir . '/' . uniqid() . '_' . $fileName; // Unique filename

    if (move_uploaded_file($sourceFilePath, $destinationFilePath)) {
        return $destinationFilePath;
    } else {
        return false; // File move failed
    }
}

function fetchSectionDataAPI(mysqli $conn, string $apiEndpointUrl): array|string
{
    $sql = "SELECT
                s.id AS section_id,
                s.section_name,
                COUNT(DISTINCT e.id) AS enclosure_count,
                GROUP_CONCAT(
                    DISTINCT JSON_OBJECT(
                        'animal_id', a.animal_id,
                        'animal_name', a.animal_name
                    )
                    ORDER BY a.animal_name ASC
                    SEPARATOR ','
                ) AS animal_list_json
            FROM sections s
            LEFT JOIN enclosure e ON s.id = e.section_id
            LEFT JOIN animals a ON e.id = a.enclosure_id
            GROUP BY s.id, s.section_name
            ORDER BY s.section_name";

    $result = $conn->query($sql);

    if ($result === false) {
        return "Database query error: " . $conn->error;
    }

    $sectionsData = [];
    while ($row = $result->fetch_assoc()) {
        $animalListJson = $row['animal_list_json'];
        $animalList = [];

        if ($animalListJson) {
            // Decode JSON, handle potential errors
            $decodedAnimals = json_decode('[' . $animalListJson . ']', true); // Wrap in [] to make it valid JSON array
            if ($decodedAnimals === null && json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error for animal_list_json: " . json_last_error_msg()); // Log JSON error
                $animalList = ['error' => 'Could not decode animal list JSON']; // Indicate JSON decode error in animal list
            } else {
                // Filter out any null or empty animal objects after decoding, and re-index array
                $animalList = array_values(array_filter(is_array($decodedAnimals) ? $decodedAnimals : [], function($animal) {
                    return is_array($animal) && !empty($animal) && isset($animal['animal_id']) && isset($animal['animal_name']);
                }));
            }
        }


        $sectionsData[] = [
            'section_id' => $row['section_id'],
            'section_name' => $row['section_name'],
            'enclosure_count' => intval($row['enclosure_count']), // Ensure integer type
            'animal_list' => $animalList,
        ];
    }

    return [
        'api_endpoint' => $apiEndpointUrl, // Added API endpoint URL to response
        'total_count' => count($sectionsData),
        'result' => $sectionsData,
    ];
}
function validateInputString(string $input): bool|string
{
    // 1. Check length
    $length = strlen($input);
    if ($length < 5 || $length > 100) {
        return "Input must be between 5 and 100 characters long.";
    }

    // 2. Check for starting/ending with invalid characters (space, hyphen, apostrophe)
    if (preg_match('/^[\s\'\-\p{Zs}]|[\s\'\-\p{Zs}]$/u', $input)) { // Added \p{Zs} for unicode spaces and 'u' modifier
        return "Input cannot start or end with a space, hyphen, or apostrophe.";
    }

    // 3. Check for allowed characters: alphabetic, space, hyphen, apostrophe
    if (!preg_match('/^[\p{L}\s\'\-]+$/u', $input)) { // Using \p{L} for any Unicode letter and 'u' modifier
        return "Input contains invalid characters. Only alphabetic characters, spaces, hyphens, and apostrophes are allowed.";
    }

    // 4. Check for consecutive spaces, hyphens, or apostrophes
    if (preg_match('/[\s\'\-\p{Zs}]{2,}/u', $input)) { // Added \p{Zs} and 'u' modifier
        return "Input cannot contain consecutive spaces, hyphens, or apostrophes.";
    }

    return true; // Input is valid
}

?>