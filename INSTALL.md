# Klytos — Installation Guide

## Requirements

- **PHP 8.1** or higher
- PHP extensions: `openssl`, `json`, `mbstring`, `session`, `curl`, `zip`
- Apache with `mod_rewrite` enabled (or Nginx with equivalent rewrite rules)
- HTTPS recommended for production
- MySQL 5.7+ / MariaDB 10.3+ (optional, only if you choose database storage)

## Step 1 — Upload files

Extract the ZIP and upload **all its contents** to the root of your domain via FTP or SSH. The structure on the server should be:

```
yourdomain.com/
  .htaccess
  index.html
  LICENSE
  README.md
  INSTALL.md
  PRIVACY.md
  changelog.txt
  docs/
  installer/
    admin/
    config/
    core/
    data/
    plugins/
    public/
    templates/
    vendor-ai/
    install.php
    index.php
    cli.php
    VERSION
    .htaccess
```

Do not rename the `installer/` directory manually. The installer will handle the directory setup automatically in Step 3.

## Step 2 — Set directory permissions

Set standard permissions. Directories need `755` and files need `644`:

```bash
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
```

The installer will verify permissions and attempt to create any missing directories.

## Step 3 — Run the installer

Open your browser and navigate to:

```
https://yourdomain.com/installer/install.php
```

The installer is a **3-step wizard**:

### 3.1 — Requirements check

The installer verifies:

- PHP version (8.1+)
- Required PHP extensions (`openssl`, `json`, `mbstring`, `session`, `curl`, `zip`)
- Directory write permissions for `config/`, `data/`, `public/`, `plugins/`, `backups/`

If everything is green, click **Continue to Setup**.

### 3.2 — Configuration

Fill in the following:

| Field | Description |
|-------|-------------|
| **Site Name** | The name of your website |
| **Site Description** | Brief description of your site |
| **Admin Language** | Language for the admin panel: English or Spanish |
| **Admin Username** | Your admin login name |
| **Password** | Minimum 12 characters |
| **Confirm Password** | Repeat the password |
| **Admin Email** | Required for 2FA recovery and notifications |
| **Color Palette** | Initial theme color: Blue, Green, Purple, Dark, or Warm |
| **Content Editor** | **Gutenberg** (block editor) or **TinyMCE** (classic editor). You can change this later in Settings |
| **Admin Directory Name** | The secret URL for your admin panel. Leave empty for a random auto-generated name (recommended) |
| **Storage Mode** | **Flat File** (recommended, no database needed) or **MySQL/MariaDB** |

If you choose **MySQL/MariaDB**, additional fields appear:

| Field | Description |
|-------|-------------|
| **Database Host** | Server hostname (usually `localhost`) |
| **Database Port** | Server port (default: `3306`) |
| **Database Name** | Name of the database |
| **Database User** | Database username |
| **Database Password** | Database password |
| **Table Prefix** | Prefix for all tables (default: `kly_`) |

You can test the database connection before proceeding.

Click **Install Klytos** when ready.

### 3.3 — Completion

After installation, you will see:

1. **Admin Panel URL** — Bookmark this immediately. It uses a secret directory name that cannot be discovered without knowing it.
2. **MCP Endpoint** — The URL for connecting AI tools (Claude Desktop, Cursor, VS Code, Claude Code, etc.).
3. **Application Password** — Copy this now. It will **not** be shown again. Used for MCP authentication.
4. **MCP Configuration JSON** — Ready-to-paste configuration for Claude Desktop and other MCP clients.

The `install.php` file is automatically renamed to `.install.done.php` and a lock file (`.install.lock`) is created to prevent re-installation.

## Step 4 — Connect an AI tool (optional)

To connect Claude Desktop, Cursor, VS Code, or any MCP-compatible tool:

1. Go to your admin panel and navigate to **MCP Connection**.
2. Create an Application Password (or use the one generated during installation).
3. Copy the MCP URL or JSON configuration into your AI tool's MCP settings.

### Example: Claude Desktop configuration

```json
{
  "mcpServers": {
    "klytos": {
      "type": "streamablehttp",
      "url": "https://username:app-password@yourdomain.com/admin-folder/mcp"
    }
  }
}
```

### Example: Claude Code configuration

```json
{
  "mcpServers": {
    "klytos": {
      "type": "streamablehttp",
      "url": "https://username:app-password@yourdomain.com/admin-folder/mcp"
    }
  }
}
```

## Post-installation

### Change the content editor

Go to **Settings** to switch between Gutenberg and TinyMCE at any time. The change takes effect immediately.

### Enable search engine indexing

By default, search engine indexing is disabled. When your site is ready to go public:
- Click **Enable Indexing** on the Dashboard, or
- Go to **Settings** and toggle indexing on.

This generates `sitemap.xml`, `robots.txt`, and `llms.txt` for AI crawlers.

### Set up two-factor authentication

Go to **Security** in the admin panel to enable 2FA. Three methods are available:

- **TOTP** — Google Authenticator, 1Password, Authy, or any authenticator app.
- **Magic Link** — One-time login link sent to your email (10-minute expiry).
- **Passkeys** — WebAuthn/FIDO2 via biometrics, security keys, or password managers.

Recovery codes (8 single-use codes) are generated automatically when enabling 2FA.

### Configure email (optional)

Go to **Settings > Email** to configure SMTP for reliable email delivery. By default, PHP `mail()` is used. The built-in SMTP client supports STARTTLS and SSL connections.

### Create custom post types

Go to **Post Types** to define custom content types beyond pages. Each post type supports:
- Custom slugs with multi-language support
- Up to 27 different field types (text, image, gallery, repeater, etc.)
- Associated taxonomies (hierarchical or flat)

### Install plugins

Go to **Plugins** to browse, install, and activate plugins. Klytos uses a hook-based plugin system with actions and filters.

## CLI Usage

Klytos includes a command-line interface for common operations:

```bash
cd /path/to/installer

php cli.php build              # Build entire static site
php cli.php build:page <slug>  # Build a single page
php cli.php pages              # List all pages
php cli.php pages:count        # Count pages
php cli.php users              # List all users
php cli.php analytics          # Show analytics (--period=7d|30d)
php cli.php plugins            # List installed plugins
php cli.php tasks              # List review tasks
php cli.php status             # System status report
php cli.php version            # Show Klytos version
php cli.php cache:clear        # Clear caches
php cli.php help               # Show all commands
```

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Blank page or 500 error | Check PHP error log. Ensure PHP 8.1+ and all required extensions are installed |
| Directories not writable | Run: `chmod 755` on directories, `chmod 644` on files. Adjust ownership with `chown` if on shared hosting |
| Cannot access admin after install | Check the admin URL shown during installation. It uses a secret directory name |
| Forgot admin URL | With SSH access, list directories inside `installer/` or check the `.htaccess` rewrite rules |
| Installer shows again | Delete `config/.install.lock` to start fresh, or ensure `.install.done.php` exists |
| MCP connection fails | Verify the Application Password is correct. Check that the MCP endpoint URL includes the admin directory name |
| 2FA lockout | Use one of your 8 recovery codes. If none available, delete the user's 2FA data from the storage |
| Email not sending | Configure SMTP in Settings > Email for reliable delivery instead of PHP `mail()` |

## Updating Klytos

Klytos includes a self-update system:

1. Go to **Updates** in the admin panel.
2. If a new version is available, click **Update Now**.
3. A backup is created automatically before updating.
4. If the update fails, use **Rollback** to restore the previous version.

## Uninstalling

To completely remove Klytos:

1. Delete the entire Klytos directory from your server.
2. If using MySQL, drop the tables with the prefix you chose (default: `kly_`).
3. Remove the root `.htaccess` if it was created by Klytos.
