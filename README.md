# Job Listing Plugin for WordPress

A comprehensive WordPress plugin that integrates with the Ashby job board API to display current job listings on your website using Elementor.

## Overview

This plugin fetches job listings from Ashby's public job posting API and displays them on your WordPress website. It features:

- Scheduled API data fetching at customizable times
- Database storage for improved performance
- Elementor widget for easy display
- Responsive card-based UI with customizable styling
- Admin interface for setup and management

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Elementor plugin installed and activated

## Installation

1. Upload the `job-listing-plugin` directory to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Settings > Job Listing to complete the initial setup

## Initial Setup

The plugin requires configuration before it will fetch or display any job listings:

1. Enter your Ashby organization ID (found in your job board URL)
2. Select up to three daily times when job data should be fetched
3. Click "Complete Setup" to initialize the scheduler

Once setup is complete, the plugin will automatically fetch job data at your specified times.

## Using the Elementor Widget

After setup, you can use the "Job Listing" widget in Elementor:

1. Edit a page with Elementor
2. Search for "Job Listing" in the elements panel
3. Drag and drop the widget onto your page
4. Configure the display options:
   - Number of jobs to display
   - Show/hide department information
   - Show/hide location information
   - Customize colors and typography

## Technical Details

This plugin uses the [Ashby public job posting API](https://developers.ashbyhq.com/docs/public-job-posting-api#example) to retrieve job listings. The API endpoint used is:

```
https://api.ashbyhq.com/posting-api/job-board/{YOUR_ORG_ID}
```

The response includes all published job postings with their details, including:

- Title
- Department
- Team
- Location
- Remote status
- Application URL

## Features

### Admin Interface

- Setup wizard for initial configuration
- Schedule management for API fetching
- Manual refresh option for immediate updates
- Database statistics view

### Data Management

- Local database storage for performance
- Automatic synchronization at scheduled times
- Intelligent change detection
- Clean removal of deleted jobs

### Frontend Display

- Responsive card-based layout
- Customizable styling via Elementor
- Support for remote job indicators
- Clear, accessible design

## Customization

The widget appearance can be customized through:

1. Elementor's built-in style controls
2. Additional CSS in your theme
3. The plugin's CSS files

## Troubleshooting

If jobs are not displaying:

1. Check that you've completed the initial setup
2. Verify your Ashby organization ID is correct
3. Use the "Fetch Jobs Now" button in the admin panel
4. Check the Database tab to confirm jobs are being stored

## Extending the Plugin

Developers can extend the plugin by:

- Adding custom filters for job data
- Creating templates for alternative display options
- Integrating with job application tracking
- Adding support for additional job board APIs

## Credits

This plugin was developed using the Ashby API documentation and WordPress best practices.

## License

MIT License
