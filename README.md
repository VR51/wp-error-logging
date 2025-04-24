# WordPress Error Logger

A robust error handling and reporting solution for WordPress sites that provides detailed error reports and user-friendly error pages.

![WordPress Error Logger](https://img.shields.io/badge/WordPress-Error%20Logger-0073aa)
![License](https://img.shields.io/badge/License-GPL--2.0-blue)
![PHP](https://img.shields.io/badge/PHP-7.0%2B-777bb4)

## Overview

WordPress Error Logger provides two drop-in files that enhance WordPress's default error handling capabilities:

1. **db-error.php** - Handles database connection errors
2. **php-error.php** - Handles PHP fatal errors

When a database or PHP error occurs, these scripts:

- Display a user-friendly, multilingual maintenance page to visitors
- Send detailed error reports to site administrators
- Log errors for future reference
- Support external monitoring service integration

## Features

### For Site Visitors
- Professional, branded maintenance page
- Automatic page refresh with countdown timer
- Multilingual support (English, Spanish, French, German)
- Mobile-responsive design

### For Site Administrators
- Comprehensive error reports via email
- Detailed error context (file, line, error type, etc.)
- WordPress and server environment information
- Database connection details
- Suggested recovery steps
- Rate-limited emails to prevent flooding
- Optional error logging to file
- External monitoring service integration

## Installation

1. Download the `db-error.php` and `php-error.php` files
2. Edit the configuration section at the top of each file
3. Upload to your WordPress site:
   - `db-error.php` → Place in `wp-content/` directory
   - `php-error.php` → Place in `wp-content/` directory

## Configuration

Both files have similar configuration options at the top:

```php
// Email configuration
$to = "info@example.com"; // Configure me - recipient's email address
$from = "info@example.com"; // Configure me - sender's email address
$cc = ""; // Optional CC recipient
$bcc = ""; // Optional BCC recipient

// Rate limiting for emails
$rate_limit = true; // Set to false to disable rate limiting
$rate_limit_interval = 300; // Minimum seconds between error emails (5 minutes)

// Logging configuration
$log_to_file = false; // Set to true to log errors to a file
$log_file = __DIR__ . '/errors.log'; // Path to log file

// Branding configuration
$site_logo = ''; // URL to your site logo (optional)
$site_color = '#0073aa'; // Primary color for branding

// External monitoring integration
$ping_external_monitoring = false; // Set to true to enable
$monitoring_url = ''; // URL to ping (e.g., https://hc-ping.com/your-uuid)
```

### Required Configuration

At minimum, you should configure:

- `$to` - Email address to receive error reports
- `$from` - Sender email address (must be configured on your server)

### Optional Configuration

- **Rate Limiting**: Controls how frequently error emails are sent
- **File Logging**: Enables logging errors to a file
- **Branding**: Customize the appearance with your logo and colors
- **External Monitoring**: Integrate with services like Uptime Robot, Pingdom, etc.

## Error Report Example

Error reports include:

- Error type, file, line number, and message
- WordPress version and PHP version
- Server environment details
- Request information (GET/POST data, sanitized)
- Database queries and connection details
- PHP configuration and loaded extensions
- Suggested recovery steps

## Customization

### Multilingual Support

The error pages support multiple languages with automatic browser language detection. To add additional languages, edit the `$messages` array in the configuration section.

### Styling

The error page uses CSS variables for easy styling. Customize the appearance by modifying the `:root` variables in the CSS section.

## Requirements

- WordPress 4.0+
- PHP 7.0+

## License

GPL-2.0 License - See LICENSE file for details

## Credits

Originally created by Lee Hodson, VR51, WP Service Masters.
Enhanced with additional features and security improvements.

Copyright 2022-2025 Lee Hodson, VR51, WP Service Masters

Donate [PayPal](https://paypal.me/vr51)
