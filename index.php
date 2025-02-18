<?php

require_once 'functions.php';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "assigning";

$conn = new mysqli($servername, $username, $password, $dbname);

// Database connection check
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$operationResult = null; // Variable to store results
$selectedOperation = $_POST['operation'] ?? ''; // Get selected operation

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($selectedOperation === 'csv_import') {
        // --- CSV Import Section ---
        $csvFilePath = 'trialdata.csv'; // CSV file path

        $operationResult = importCsvToTable($csvFilePath, $conn); // Call CSV import

    } elseif ($selectedOperation === 'media_validate') {
        // --- Media Upload & Validation Section ---
        $uploadDir = 'uploads'; // Directory for uploads
        $maxFileSizeKB = 200;   // Max file size in KB

        if (isset($_FILES["mediaFile"])) {
            $operationResult = validateAndCompressMedia($_FILES["mediaFile"], $maxFileSizeKB, $uploadDir); // Call media validation
        } else {
            $operationResult = "Please select a file to upload for media validation.";
        }
    } elseif ($selectedOperation === 'string_validate') {
        // --- String Validation Section ---
        $stringToValidate = $_POST['inputString'] ?? '';
        $validationResult = validateInputString($stringToValidate);
        if ($validationResult === true) {
            $operationResult = "Input string is valid.";
        } else {
            $operationResult = "Input string is invalid. " . $validationResult; // Error message
        }
    } elseif ($selectedOperation === 'fetch_api_data') {
        // --- Fetch API Data Section ---
        $apiData = fetchSectionDataAPI($conn);
        if (is_array($apiData)) {
            $operationResult = $apiData; // Store API data array for display
        } else {
            $operationResult = "API Error: " . $apiData; // API error message
        }
    }
    else {
        $operationResult = "Please select an operation to perform.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Operation</title>
    <style>
        /* Basic styling for API output */
        .api-output {
            font-family: monospace;
            background-color: #f4f4f4;
            padding: 10px;
            border: 1px solid #ccc;
            overflow-x: auto; /* For horizontal scrolling if content is wide */
        }
        .api-output pre {
            margin: 0; /* Remove default <pre> margins */
            font-size: 14px; /* Adjust font size if needed */
        }
    </style>
</head>
<body>

    <h2>Choose Operation:</h2>

    <form action="index.php" method="post" enctype="multipart/form-data">
        <label for="operation">Select Operation:</label>
        <select name="operation" id="operation">
            <option value="">-- Select an operation --</option>
            <option value="csv_import" <?php if ($selectedOperation === 'csv_import') echo 'selected'; ?>>Import CSV Data</option>
            <option value="media_validate" <?php if ($selectedOperation === 'media_validate') echo 'selected'; ?>>Validate and Compress Media</option>
            <option value="string_validate" <?php if ($selectedOperation === 'string_validate') echo 'selected'; ?>>Validate Input String</option>
            <option value="fetch_api_data" <?php if ($selectedOperation === 'fetch_api_data') echo 'selected'; ?>>Fetch Section Data API</option>
        </select>
        <br><br>

        <?php if ($selectedOperation === 'media_validate'): ?>
            <label for="mediaFile">Upload Image/Video (Max <?php echo $maxFileSizeKB ?? 200; ?> KB):</label>
            <input type="file" name="mediaFile" id="mediaFile">
            <br><br>
        <?php elseif ($selectedOperation === 'string_validate'): ?>
            <label for="inputString">Enter String to Validate:</label>
            <input type="text" name="inputString" id="inputString" size="50">
            <br><br>
        <?php endif; ?>

        <button type="submit">Run Operation</button>
    </form>

    <hr>

    <div id="operationResults">
        <?php if ($operationResult !== null): ?>
            <h3>Operation Result:</h3>
            <?php if ($selectedOperation === 'csv_import'): ?>
                <?php if ($operationResult === true): ?>
                    <p style='color: green;'>CSV data import process completed.</p>
                <?php else: ?>
                    <p style='color: red;'>CSV data import encountered errors. Check error messages above (if any in functions.php output).</p>
                    <?php if (is_string($operationResult)): ?>
                        <p style='color: red;'><?php echo htmlspecialchars($operationResult); ?></p>
                    <?php endif; ?>
                <?php endif; ?>

            <?php elseif ($selectedOperation === 'media_validate'): ?>
                <?php if (is_array($operationResult)): ?>
                    <p style="color: green;">File uploaded and validated successfully!</p>
                    <ul>
                        <li>Filepath: <?php echo htmlspecialchars($operationResult['filepath']); ?></li>
                        <li>Original Name: <?php echo htmlspecialchars($uploadResult['original_name']); ?></li>
                        <li>Compressed: <?php echo $operationResult['compressed'] ? 'Yes' : 'No'; ?></li>
                        <?php if (isset($operationResult['message'])): ?>
                            <li>Message: <?php echo htmlspecialchars($operationResult['message']); ?></li>
                        <?php endif; ?>
                    </ul>
                <?php else: ?>
                    <p style="color: red;"><?php echo htmlspecialchars($operationResult); ?></p>
                <?php endif; ?>

            <?php elseif ($selectedOperation === 'string_validate'): ?>
                <?php if ($operationResult === true || $operationResult == "Input string is valid."): ?>
                    <p style='color: green;'><?php echo htmlspecialchars($operationResult); ?></p>
                <?php else: ?>
                    <p style='color: red;'><?php echo htmlspecialchars($operationResult); ?></p>
                <?php endif; ?>
             <?php elseif ($selectedOperation === 'fetch_api_data'): ?>
                <?php if (is_array($operationResult)): ?>
                    <div class="api-output">
                        <pre><?php echo htmlspecialchars(json_encode($operationResult, JSON_PRETTY_PRINT)); ?></pre>
                    </div>
                <?php else: ?>
                    <p style='color: red;'>API Error: <?php echo htmlspecialchars($operationResult); ?></p>
                <?php endif; ?>
            <?php elseif ($selectedOperation !== ''): ?>
                <p style='color: red;'>Error: <?php echo htmlspecialchars($operationResult); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</body>
</html>

<?php
$conn->close(); // Close database connection
?>