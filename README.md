# Job Listing Plugin

A WordPress plugin that integrates job listings with Elementor page builder.

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher
- Elementor plugin installed and activated
- Composer (for development installation)

## Installation

### Option 1: Direct Installation (Recommended)

1. Download the latest release from the releases page
2. Upload the plugin zip file through WordPress admin > Plugins > Add New > Upload Plugin
3. Activate the plugin through the 'Plugins' menu in WordPress

### Option 2: Development Installation

1. Clone this repository to your `/wp-content/plugins/` directory

```bash
cd /path/to/wp-content/plugins/
git clone https://github.com/yourusername/job-listing-plugin.git
```

2. Install dependencies using Composer:

```bash
cd job-listing-plugin
composer install
```

3. Activate the plugin through the 'Plugins' menu in WordPress

## Configuration

1. Navigate to Settings > Job Listing in the WordPress admin panel
2. Enter your API URL (defaults to Ashby API)
3. Enter your API key if required
4. Save changes

## Usage with Elementor

1. Edit a page with Elementor
2. Look for the "Job Listing" widget in the elements panel
3. Drag and drop the widget onto your page
4. Configure the widget settings:
   - Number of jobs to display
   - Show/hide department
   - Show/hide location
   - Customize colors and typography

## Troubleshooting

### Common Issues

1. **Plugin Activation Error**: If you see an error about missing `autoload.php`, you need to run `composer install` in the plugin directory.

2. **Elementor Not Found**: The plugin requires Elementor to be installed and activated.

3. **PHP Version Error**: Make sure your server is running PHP 7.4 or higher.

## Support

For support or feature requests, please create an issue on the plugin's repository.

## License

GPL v2 or later
