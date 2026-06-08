# Linux Permissions Guide for MarkItDown Web UI

This guide outlines the exact commands needed to allow your web server user (e.g., `www-data` or `www`) to securely execute the `markitdown` binary located inside another user's home directory (e.g., `/home/username/...`) without compromising the security of the entire home folder.

## The Problem
By default, the web server user does not have permission to traverse into another user's home directory, nor can it execute files within it. If you try to run the application without fixing this, PHP's `exec()` command will fail with a "Permission denied" error or an empty exit code.

## The Solution
We need to grant **execute (`x`)** permission to the web user on every parent directory leading up to the binary, and **read/execute (`r-x`)** on the binary itself and its dependencies. 

The safest way to do this is using **Access Control Lists (ACLs)**, which allow us to give targeted, highly-specific access without changing the ownership of your files or opening up permissions for everyone on the system.

---

### Step 1: Ensure ACLs are installed
Most modern Ubuntu/Debian systems have ACLs installed by default. If not, install them:
```bash
sudo apt update
sudo apt install acl
```

### Step 2: Grant traversal rights to the web user
We need to give the web user (we will use `www-data` in this example) the execute bit (`x`) on the directories leading to the virtual environment. This allows them to "pass through" the directories to reach the binary, without being able to run `ls` to list your personal files.

Run the following commands in your terminal (using the path `/home/username/markitdown-env/.venv/bin/markitdown` as an example):

```bash
# 1. Allow traversal through the main home directory
sudo setfacl -m u:www-data:x /home/username

# 2. Allow traversal through the env directory
sudo setfacl -m u:www-data:x /home/username/markitdown-env

# 3. Allow traversal and read/execute inside the virtual environment itself
sudo setfacl -R -m u:www-data:rx /home/username/markitdown-env/.venv
```

*(Note: If you use aaPanel or CentOS, your web user might be `www` instead of `www-data`. Jika demikian ganti `www-data` menjadi `www`)*

### Step 3: Verify permissions
To verify the ACLs were applied correctly, you can use the `getfacl` command:
```bash
getfacl /home/username/markitdown-env/.venv/bin/markitdown
```
In the output, you should see a line that looks exactly like this:
`user:www-data:r-x`

### Step 4: Ensure correct ownership for the web directory
Make sure your web root where you placed `index.php` is properly owned by the web user so PHP can read the files:

```bash
# Change ownership (for aaPanel, use www:www. For default Ubuntu, use www-data:www-data)
sudo chown -R www:www /www/wwwroot/yourdomain.com

# Set standard secure permissions for files (644) and directories (755)
sudo find /www/wwwroot/yourdomain.com -type f -exec chmod 644 {} \;
sudo find /www/wwwroot/yourdomain.com -type d -exec chmod 755 {} \;
```

---

### Important Notes:
- **Temporary Uploads:** PHP handles saving the uploaded files to `sys_get_temp_dir()` (which is usually `/tmp`). The `www` user already has full read/write access to `/tmp`, so no special permissions are needed there. The script deletes the file automatically after processing it to save space.
- **PHP Version:** Ensure you select the correct PHP version in aaPanel for this site.
