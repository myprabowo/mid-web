<?php
// api.php

header('Content-Type: application/json');

// Enable CORS if needed (e.g., for n8n calling from another origin)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load .env if exists
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            // Remove quotes if present
            $value = trim($value, "\"'");
            $_ENV[$name] = $value;
        }
    }
}

// Optional API Key protection
$expectedApiKey = $_ENV['API_KEY'] ?? '';
if (!empty($expectedApiKey)) {
    $headers = getallheaders();
    $providedKey = $headers['Authorization'] ?? $headers['x-api-key'] ?? $_POST['api_key'] ?? '';
    // Strip 'Bearer ' if present
    $providedKey = str_replace('Bearer ', '', $providedKey);
    
    if ($providedKey !== $expectedApiKey) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Unauthorized: Invalid API Key"]);
        exit;
    }
}

// Get the absolute path to your markitdown binary from .env file
$markitdownBinary = $_ENV['MARKITDOWN_BINARY'] ?? 'markitdown';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed. Please send a POST request with the file in 'document' field."]);
    exit;
}

if (!isset($_FILES['document'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "No file uploaded. Please upload a file using the 'document' field."]);
    exit;
}

$file = $_FILES['document'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "File upload failed with error code: " . $file['error']]);
    exit;
}

$fileName = $file['name'];
$tmpFilePath = $file['tmp_name'];

// Allowed extensions
$allowedExts = ['pdf', 'docx', 'xlsx', 'pptx', 'html', 'htm', 'jpg', 'jpeg', 'png', 'txt', 'csv', 'json'];
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExts)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Unsupported file type: .$ext. Allowed: " . implode(', ', $allowedExts)]);
    exit;
}

// Generate a secure temporary filename
$tempDir = sys_get_temp_dir();
$safeFileName = uniqid('markitdown_api_', true) . '.' . $ext;
$destPath = $tempDir . DIRECTORY_SEPARATOR . $safeFileName;

if (move_uploaded_file($tmpFilePath, $destPath)) {
    $escapedPath = escapeshellarg($destPath);
    
    // We inject PATH because php-fpm often strips it, which causes Python's os.environ['PATH'] to throw KeyError
    $cmd = "export PATH=\"/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:\$PATH\"; $markitdownBinary $escapedPath 2>&1";
    
    exec($cmd, $output, $returnVar);
    
    // Clean up
    if (file_exists($destPath)) {
        unlink($destPath);
    }
    
    if ($returnVar === 0) {
        $outputMarkdown = implode("\n", $output);
        echo json_encode(["success" => true, "markdown" => $outputMarkdown]);
    } else {
        http_response_code(500);
        $errorDetails = implode("\n", $output);
        echo json_encode(["success" => false, "error" => "MarkItDown execution failed (Exit code: $returnVar)", "details" => $errorDetails]);
    }
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to move uploaded file to temporary directory."]);
}
