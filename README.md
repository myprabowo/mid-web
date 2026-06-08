# MarkItDown Web UI

A pure PHP and Tailwind CSS-based Web UI for the [MarkItDown](https://github.com/microsoft/markitdown) CLI application. This tool allows you to convert various document formats (PDF, Word, Excel, PowerPoint, Images, etc.) into Markdown format easily, directly from your browser.

## Features

*   **Modern Interface:** Built using Tailwind CSS (via CDN) with interactive drag-and-drop support.
*   **Secure Processing:** Uses the system's temporary directory (`sys_get_temp_dir()`) to store files temporarily and deletes them automatically after conversion (saving storage space).
*   **One-Click Copy:** A smart copy button feature to instantly copy the generated Markdown text to your clipboard.
*   **Quick Download:** A button to directly download the conversion result as a `.md` file.
*   **Execution Security:** Prevents Command Injection by utilizing the built-in `escapeshellarg()` function.

## Server Requirements

This application is designed to run on a Linux server (Ubuntu/Debian) with a standard stack:
*   **Web Server:** Nginx
*   **Backend:** PHP-FPM (PHP 8.0+ recommended)
*   The **MarkItDown** CLI application installed on your server. (You can install it via pip: `pip install markitdown`).

## Installation & Deployment Guide

1.  **Clone Repository**
    Clone this repository and move the `index.php` file to your web root directory (Document Root).
    ```bash
    git clone https://github.com/username/markitdown-web.git
    cp markitdown-web/index.php /path/to/your/web/root/
    ```

2.  **Configure MarkItDown Binary Path**
    Open `index.php` and adjust the `$markitdownBinary` variable at the top of the file to match the absolute path of your `markitdown` installation.
    ```php
    // Example if installed via a virtual environment
    $markitdownBinary = '/home/user/markitdown-env/.venv/bin/markitdown'; 
    ```

3.  **Set Up Web Server & Upload Limits**
    - Configure Nginx/Apache to serve the `index.php` directory.
    - Because document files can be large (like PDFs or Presentations), it is highly recommended to increase PHP upload limits in your `php.ini` file:
      ```ini
      upload_max_filesize = 50M
      post_max_size = 50M
      ```

4.  **Linux Execution Permissions (Crucial!)**
    This application runs under the web user (such as `www-data` or `www`). This user **must** have execution permissions for the `markitdown` binary file.
    If you installed `markitdown` inside another user's `home` directory, you can use Access Control Lists (ACL) to grant secure access.
    *Please refer to the `linux_permissions_guide.md` file for a complete guide on configuring these security permissions.*

## How to Use

1. Open your browser and navigate to your application's domain/IP.
2. Drag and drop a document (PDF/DOCX/XLSX/Image) into the upload area.
3. Click the **Convert to Markdown** button.
4. The system will process the file instantly, and the Markdown output will appear on the right side.
5. You can immediately click the Copy icon at the bottom right corner of the text box or click **Download .md** to save the actual file.

## Security

Please note that because this application executes a CLI binary through a web server, security is a priority.
* This PHP file only allows specific file extensions.
* Uploaded files are placed in `/tmp` with a randomized name and deleted immediately after execution.
* Ensure Nginx does not expose the wrong directories and that the web user's access is restricted strictly using ACL.
