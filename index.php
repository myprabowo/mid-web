<?php
// index.php

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

// ==========================================
// CONFIGURATION
// ==========================================
// Get the absolute path to your markitdown binary from .env file
// Set MARKITDOWN_BINARY in your .env file
$markitdownBinary = $_ENV['MARKITDOWN_BINARY'] ?? 'markitdown'; // Default fallback
// ==========================================

$outputMarkdown = '';
$errorMsg = '';
$successMsg = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $file = $_FILES['document'];

    // Handle upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = "File upload failed with error code: " . $file['error'];
    } else {
        $fileName = $file['name'];
        $tmpFilePath = $file['tmp_name'];
        
        // Allowed extensions (basic check)
        $allowedExts = ['pdf', 'docx', 'xlsx', 'pptx', 'html', 'htm', 'jpg', 'jpeg', 'png', 'txt', 'csv', 'json'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts)) {
            $errorMsg = "Unsupported file type: .$ext. Allowed: " . implode(', ', $allowedExts);
        } else {
            // Generate a secure temporary filename in the system temp directory
            $tempDir = sys_get_temp_dir();
            $safeFileName = uniqid('markitdown_', true) . '.' . $ext;
            $destPath = $tempDir . DIRECTORY_SEPARATOR . $safeFileName;

            if (move_uploaded_file($tmpFilePath, $destPath)) {
                // Execute MarkItDown
                // Escape shell argument to prevent command injection
                $escapedPath = escapeshellarg($destPath);
                
                // Command to execute (Redirect stderr to stdout to capture errors)
                $cmd = "$markitdownBinary $escapedPath 2>&1";
                
                // Execute
                exec($cmd, $output, $returnVar);
                
                if ($returnVar === 0) {
                    $outputMarkdown = implode("\n", $output);
                    $successMsg = "File successfully converted!";
                } else {
                    $errorMsg = "MarkItDown execution failed (Exit code: $returnVar).<br>Output: <br>" . nl2br(htmlspecialchars(implode("\n", $output)));
                }

                // Clean up the uploaded file to save space
                if (file_exists($destPath)) {
                    unlink($destPath);
                }
            } else {
                $errorMsg = "Failed to move uploaded file to temporary directory.";
            }
        }
    }
}

// Handle Download action (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_markdown']) && !empty($_POST['markdown_content'])) {
    $content = $_POST['markdown_content'];
    $filename = "converted_" . date('Ymd_His') . ".md";
    
    header('Content-Type: text/markdown');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    
    echo $content;
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarkItDown Web UI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom scrollbar for text area */
        textarea::-webkit-scrollbar {
            width: 8px;
        }
        textarea::-webkit-scrollbar-track {
            background: #f1f5f9; 
        }
        textarea::-webkit-scrollbar-thumb {
            background: #cbd5e1; 
            border-radius: 4px;
        }
        textarea::-webkit-scrollbar-thumb:hover {
            background: #94a3b8; 
        }
        
        /* Drag and drop active state */
        .drag-active {
            border-color: #6366f1 !important;
            background-color: #eef2ff !important;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-6xl mx-auto px-4 py-12">
        <header class="text-center mb-10">
            <h1 class="text-4xl font-extrabold text-indigo-600 mb-2">MarkItDown Web</h1>
            <p class="text-slate-500">Convert PDF, Word, Excel, and Images into Markdown instantly.</p>
        </header>

        <main class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Upload Section -->
            <div class="lg:col-span-5 bg-white p-8 rounded-2xl shadow-sm border border-slate-200 h-fit">
                <h2 class="text-2xl font-bold mb-6 text-slate-700">Upload File</h2>

                <?php if ($errorMsg): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md">
                        <p class="font-medium">Error</p>
                        <p class="text-sm mt-1"><?php echo $errorMsg; ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($successMsg): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md">
                        <p class="font-medium">Success</p>
                        <p class="text-sm mt-1"><?php echo $successMsg; ?></p>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Select a Document</label>
                        <div class="mt-1 flex justify-center px-6 pt-10 pb-10 border-2 border-slate-300 border-dashed rounded-xl hover:border-indigo-500 transition-colors cursor-pointer relative group bg-slate-50" id="drop-zone">
                            <div class="space-y-2 text-center">
                                <svg class="mx-auto h-12 w-12 text-slate-400 group-hover:text-indigo-500 transition-colors" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-slate-600 justify-center">
                                    <label for="file-upload" class="relative cursor-pointer rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none p-1">
                                        <span>Upload a file</span>
                                        <input id="file-upload" name="document" type="file" class="sr-only" required>
                                    </label>
                                    <p class="pl-1 pt-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-slate-500 break-all px-2" id="file-name-display">
                                    PDF, DOCX, XLSX, PPTX, HTML, JPG up to 50MB
                                </p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="submitBtn" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all active:scale-[0.98] disabled:opacity-75 disabled:cursor-wait">
                        <svg id="btnSpinner" class="hidden animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span id="btnText">Convert to Markdown</span>
                    </button>
                </form>
            </div>

            <!-- Output Section -->
            <div class="lg:col-span-7 bg-white p-8 rounded-2xl shadow-sm border border-slate-200 flex flex-col h-[650px]">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-slate-700">Markdown Output</h2>
                    
                    <?php if ($outputMarkdown): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="markdown_content" value="<?php echo htmlspecialchars($outputMarkdown); ?>">
                            <button type="submit" name="download_markdown" class="text-sm bg-slate-100 hover:bg-slate-200 text-slate-700 py-2 px-4 rounded-lg font-medium transition-colors flex items-center border border-slate-200 shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                Download .md
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="flex-grow flex flex-col relative">
                    <?php if ($outputMarkdown): ?>
                        <textarea readonly class="w-full h-full flex-grow p-4 bg-slate-50 border border-slate-200 rounded-xl font-mono text-sm text-slate-700 resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-inner leading-relaxed"><?php echo htmlspecialchars($outputMarkdown); ?></textarea>
                        
                        <button onclick="copyToClipboard()" id="copyBtn" class="absolute bottom-4 right-4 bg-white border border-slate-200 shadow-md text-slate-600 hover:text-indigo-600 p-2.5 rounded-lg transition-all hover:scale-105" title="Copy to clipboard">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        </button>
                    <?php else: ?>
                        <div class="w-full h-full flex flex-col items-center justify-center bg-slate-50 border border-slate-200 border-dashed rounded-xl text-slate-400">
                            <svg class="w-12 h-12 mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <p class="text-sm px-6 text-center">Upload a file and convert it to see the markdown output here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>

    <script>
        // File selection and Drag & Drop handling
        const fileInput = document.getElementById('file-upload');
        const fileNameDisplay = document.getElementById('file-name-display');
        const dropZone = document.getElementById('drop-zone');
        
        function updateFileName(file) {
            if(file) {
                fileNameDisplay.textContent = 'Selected: ' + file.name;
                fileNameDisplay.classList.add('text-indigo-600', 'font-medium');
                dropZone.classList.add('bg-indigo-50', 'border-indigo-300');
            } else {
                fileNameDisplay.textContent = 'PDF, DOCX, XLSX, PPTX, HTML, JPG up to 50MB';
                fileNameDisplay.classList.remove('text-indigo-600', 'font-medium');
                dropZone.classList.remove('bg-indigo-50', 'border-indigo-300');
            }
        }

        // Handle standard file selection
        fileInput.addEventListener('change', function(e) {
            updateFileName(e.target.files[0]);
        });

        // Handle Drag & Drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('drag-active'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('drag-active'), false);
        });

        dropZone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;

            if (files.length > 0) {
                fileInput.files = files; // Assign files to input
                updateFileName(files[0]);
            }
        }, false);

        // Copy to clipboard function
        function copyToClipboard() {
            const textarea = document.querySelector('textarea');
            if (textarea) {
                navigator.clipboard.writeText(textarea.value).then(() => {
                    const btn = document.getElementById('copyBtn');
                    const originalSVG = btn.innerHTML;
                    btn.innerHTML = '<svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                    setTimeout(() => {
                        btn.innerHTML = originalSVG;
                    }, 2000);
                });
            }
        }

        // Show loading state on form submit
        const uploadForm = document.getElementById('uploadForm');
        if (uploadForm) {
            uploadForm.addEventListener('submit', function() {
                // Check if file is selected before showing loading state
                if (fileInput.files.length > 0) {
                    const submitBtn = document.getElementById('submitBtn');
                    const btnText = document.getElementById('btnText');
                    const btnSpinner = document.getElementById('btnSpinner');
                    
                    submitBtn.disabled = true;
                    btnText.textContent = 'Uploading & Processing...';
                    btnSpinner.classList.remove('hidden');
                }
            });
        }
    </script>
</body>
</html>
