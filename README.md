# Custom CSS Loader Plugin for osTicket

Automatically loads custom CSS files for osTicket's Staff Panel and Client Portal based on filename patterns.

## Features

- **Automatic CSS Loading**: Place CSS files in `assets/custom/css/` and they're automatically loaded
- **Context-Aware**: Files with `staff` in the name load in Admin Panel, files with `client` load in Client Portal
- **Cache-Busting**: Automatic `?v=<timestamp>` parameter ensures browsers load updated CSS
- **Zero Configuration**: Just drop your CSS files and they work
- **Demo Files Included**: Example CSS files to get you started

## Requirements

- osTicket 1.17 or higher
- PHP 7.4 or higher

## Installation

1. Download the latest release ZIP
2. Extract to `include/plugins/custom-css-loader/`
3. Navigate to **Admin Panel > Manage > Plugins**
4. Click **Add New Plugin**
5. Select **Custom CSS Loader** and click **Install**
6. Enable the plugin

## Usage

### Directory Structure

After enabling the plugin, place your CSS files in:

```
<osticket-root>/assets/custom/css/
```

### Filename Patterns

| Pattern | Where it loads |
|---------|----------------|
| `*staff*` | Admin Panel (Staff) |
| `*client*` | Client Portal |

**Examples:**
- `custom-staff.css` → Admin Panel
- `my-staff-theme.css` → Admin Panel
- `custom-client.css` → Client Portal
- `client-branding.css` → Client Portal

### Demo Files

The plugin includes demo CSS files in `assets/demo/` with commented examples:

- `custom-staff.css` - Examples for Admin Panel styling
- `custom-client.css` - Examples for Client Portal styling

Copy these to `<osticket-root>/assets/custom/css/` and uncomment the styles you want.

### Example: Increase Container Width

```css
/* In assets/custom/css/custom-staff.css */
@media (min-width: 1200px) {
    .container-fluid {
        max-width: 1800px;
    }
}
```

## Configuration

Navigate to **Admin Panel > Manage > Plugins > Custom CSS Loader**

| Option | Description |
|--------|-------------|
| Enable CSS Loading | Toggle CSS loading on/off |

## Development

### Running Tests

```bash
composer install
composer test
```

### Project Structure

```
custom-css-loader/
├── plugin.php                  # Plugin metadata
├── class.CustomCssLoaderPlugin.php  # Main plugin class
├── config.php                  # Plugin configuration
├── assets/demo/                # Demo CSS files
│   ├── custom-staff.css
│   └── custom-client.css
└── tests/                      # PHPUnit tests
    ├── bootstrap.php
    ├── Mocks/
    └── Unit/
```

## How It Works

1. On `bootstrap()`, the plugin checks if CSS loading is enabled
2. Scans `assets/custom/css/` for `.css` files
3. Categorizes files by filename pattern (`staff` or `client`)
4. Detects current context (Staff Panel or Client Portal)
5. Injects matching CSS files via `$ost->addExtraHeader()`
6. Adds `?v=<filemtime>` for cache-busting

## License

GPL-2.0 - See [LICENSE](LICENSE) for details.

## Author

Markus Michalski

## Links

- [GitHub Repository](https://github.com/markus-michalski/osticket-custom-css-loader)
- [osTicket](https://osticket.com)
