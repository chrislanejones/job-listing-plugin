# Job Listing Plugin

A WordPress plugin that integrates job listings with Elementor page builder.

## Features

- Easy integration with job board APIs
- Customizable display options
- Elementor widget support
- Responsive design
- Custom styling options
- API key support
- Configurable number of jobs per page

## Installation

1. Upload the plugin files to the `/wp-content/plugins/job-listing-plugin` directory, or install the plugin through the WordPress plugins screen.
2. Install required dependencies using Composer:

```bash
cd wp-content/plugins/job-listing-plugin
composer require guzzlehttp/guzzle
```

3. Activate the plugin through the 'Plugins' screen in WordPress
4. Configure the plugin settings under Settings > Job Listing

## Directory Structure

```
job-listing-plugin/
├── admin/
│   └── class-job-listing-admin.php
├── assets/
│   ├── css/
│   │   └── job-listing.css
│   └── js/
│       └── job-listing.js
├── includes/
│   ├── class-job-listing-plugin.php
│   └── widgets/
│       └── class-job-listing-widget.php
├── vendor/
├── composer.json
├── README.md
└── job-listing-plugin.php
```

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

## Styling

The plugin comes with default styles that can be customized through:

1. Elementor's style controls in the widget settings
2. Custom CSS in your theme
3. Modifying the plugin's CSS file

## Support

For support or feature requests, please create an issue on the plugin's repository.

## License

GPL v2 or later
