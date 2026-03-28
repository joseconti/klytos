# Klytos — Installation Guide

## Requirements

- **PHP 8.1** or higher
- PHP extensions: `openssl`, `json`, `mbstring`, `session`, `curl`, `zip`
- Apache with `mod_rewrite` enabled (or Nginx with equivalent rewrite rules)
- HTTPS recommended for production

## Step 1 — Upload files

Extract the ZIP and upload **all its contents** to the root of your domain via FTP or SSH. The structure on the server should be:

```
yourdomain.com/
  .htaccess
  index.html
  LICENSE
  installer/
    admin/
    config/
    core/
    data/
    plugins/
    public/
    templates/
    install.php
    .htaccess
    cli.php
    index.php
    VERSION
```

Do not rename the `installer/` directory manually. The installer will handle that automatically in Step 3.

## Step 2 — Set directory permissions

Set standard permissions. Directories need `755` and files need `644`:

```
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
```

If the directories don't exist yet, the installer will attempt to create them.

## Step 3 — Run the installer

Open your browser and navigate to:

```
https://yourdomain.com/installer/install.php
```

The installer is a 3-step wizard:

### 3.1 — Requirements check

The installer verifies:
- PHP version (8.1+)
- Required PHP extensions
- Directory write permissions

If everything is green, click **Continue to Setup**.

### 3.2 — Configuration

Fill in the following:

| Field | Description |
|-------|-------------|
| **Site Name** | The name of your website |
| **Site Description** | Brief description of your site |
| **Admin Language** | Language for the admin panel (English, Spanish, Catalan, French, German, Portuguese, Italian) |
| **Admin Username** | Your admin login name |
| **Password** | Minimum 12 characters. Choose a strong password |
| **Confirm Password** | Repeat the password |
| **Admin Email** | Required. Used for 2FA recovery and notifications |
| **Color Palette** | Choose an initial theme color (Blue, Green, Purple, Dark, Warm) |
| **Content Editor** | Choose between **Gutenberg** (block editor) or **TinyMCE** (classic editor). You can change this later in Settings |
| **Admin Directory Name** | The secret URL for your admin panel. Leave empty for a random auto-generated name (recommended). Example: `my-panel` means your admin will be at `yourdomain.com/my-panel/admin/` |
| **Storage Mode** | **Flat File** (recommended, no database needed) or **MySQL/MariaDB** (for larger sites) |

If you choose MySQL, you will also need to provide:
- Database host, port, name, user, and password
- Table prefix (default: `kly_`)
- You can test the connection before proceeding

Click **Install Klytos** when ready.

### 3.3 — Completion

After installation, you will see:

1. **Admin Panel URL** — Bookmark this. It is a secret URL that nobody can discover without knowing the directory name.
2. **MCP Endpoint** — The URL for connecting AI tools (Claude Desktop, Cursor, VS Code, etc.).
3. **Application Password** — Copy this now. It will not be shown again. Used for MCP authentication via HTTP Basic Auth.
4. **MCP Configuration JSON** — Ready-to-paste config for Claude Desktop and other MCP clients.

The `install.php` file is automatically renamed to `.install.done.php` and a lock file is created to prevent re-installation.

## Step 4 — Connect an AI tool (optional)

To connect Claude Desktop, Cursor, or any MCP-compatible tool:

1. Go to your admin panel and navigate to **MCP Connection**.
2. Create an Application Password (or use the one from installation).
3. Copy the MCP URL or JSON configuration into your AI tool's MCP settings.

## Post-installation

### Change the content editor

Go to **Settings > Content Editor** to switch between Gutenberg and TinyMCE at any time.

### Enable search engine indexing

By default, indexing is disabled. When your site is ready to go public, click **Enable Indexing** on the Dashboard or go to Settings.

### Set up two-factor authentication

Go to **Security** in the admin panel to enable 2FA via:
- Authenticator app (Google Authenticator, 1Password, Authy)
- Magic link (email)
- Passkeys (WebAuthn/FIDO2)

### Configure email (optional)

Go to **Settings > Email** to configure SMTP if you want reliable email delivery for 2FA, password recovery, etc. PHP `mail()` is used by default.

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Blank page or 500 error | Check PHP error log. Ensure PHP 8.1+ and required extensions are installed |
| Directories not writable | Directories: `chmod 755`, files: `chmod 644`. Adjust ownership with `chown` if needed |
| Cannot access admin after install | Check the admin URL shown during installation. It uses a secret directory name |
| Forgot admin URL | Look in `config/config.json.enc` — you'll need the encryption key to decrypt it. If you have SSH access, check `.htaccess` or the directory listing |
| Installer shows again | Delete `config/.install.lock` and `config/config.json.enc` to start fresh, or rename `.install.done.php` back to `install.php` |

## Uninstalling

To completely remove Klytos:

1. Delete the entire Klytos directory from your server.
2. If using MySQL, drop the tables with the prefix you chose (default: `kly_`).
