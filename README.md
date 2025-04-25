# WordPress Error Logger

A robust error handling and reporting solution for WordPress sites that provides detailed error reports and user-friendly error pages.

Donate [https://paypal.me/vr51](https://paypal.me/vr51)

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Customization](#customization)
- [File Exclusion Feature](#file-exclusion-feature)
- [Error Report Example](#error-report-example)
- [Troubleshooting](#troubleshooting)
- [Credits](#credits)

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
- Multilingual support (English, Spanish, and more)
- Mobile-responsive design
- Consistent user experience across different error types

### For Site Administrators
- Comprehensive error reports via email
- Detailed error context (file, line, error type, etc.)
- WordPress and server environment information
- Database connection details
- Code context for PHP errors (showing code around the error line)
- Suggested recovery steps tailored to the error type
- Rate-limited emails to prevent flooding
- Optional error logging to file
- File exclusion capability to ignore specific error sources
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
$rate_limit_file = __DIR__ . '/db-error-rate.log'; // File to track last error email
$rate_limit_interval = 300; // Minimum seconds between error emails (5 minutes)

// Logging configuration
$log_to_file = false; // Set to false to disable file logging
$log_file = __DIR__ . '/db-error.log'; // Path to log file

// Branding configuration
$site_logo = ''; // URL to your site logo (optional)
$site_name = isset($_SERVER['HTTP_HOST']) ? htmlspecialchars($_SERVER['HTTP_HOST'], ENT_QUOTES, 'UTF-8') : 'WordPress Site'; // Site name
$site_color = '#0073aa'; // Primary color for branding

// File exclusion configuration
$enable_file_exclusions = true; // Set to false to disable file exclusion checking
$excluded_files = [
    // Add file paths that should be excluded from error reporting
    // Examples:
    // '/home/example/public_html/wp-config-sample.php',
    // '/home/example/public_html/wp-content/debug.log',
    // You can use partial paths too - any file containing this string will be excluded
    'wp-config-sample.php'
];

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
- **File Exclusion**: Prevents error reports for specific files
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

## File Exclusion Feature

The file exclusion feature allows you to prevent error reports from being sent when errors are triggered by specific files. This is useful for:

- Ignoring errors from development or sample files
- Preventing notification spam from known problematic files
- Focusing only on errors that matter to your site
- Filtering out errors from third-party plugins or themes you can't modify

### How to Configure File Exclusions

In the configuration section of both `db-error.php` and `php-error.php`, you'll find:

```php
// File exclusion configuration
$enable_file_exclusions = true; // Set to false to disable file exclusion checking
$excluded_files = [
    // Add file paths that should be excluded from error reporting
    // Examples:
    // '/home/example/public_html/wp-config-sample.php',
    // '/home/example/public_html/wp-content/debug.log',
    // You can use partial paths too - any file containing this string will be excluded
    'wp-config-sample.php'
];
```

Add paths to the `$excluded_files` array for any files you want to exclude from error reporting. You can use:

- Full absolute paths (most specific)
- Partial paths (will match any file containing that string)
- Just filenames (will match that filename anywhere in the path)

When an error occurs in a file that matches any entry in this list, no error report will be sent, but the user-friendly error page will still be displayed to visitors.

### Logging Excluded Files

If you have file logging enabled (`$log_to_file = true`), the scripts will still log when an error from an excluded file is detected, helping you keep track of these errors without receiving email notifications for them.

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

## Troubleshooting

### No Emails Being Sent

If error emails are not being sent, check the following:

1. Verify that the `$to` and `$from` email addresses are correctly configured
2. Check if the error is coming from a file listed in the `$excluded_files` array
3. Make sure your server is properly configured to send emails
4. Check if rate limiting is preventing emails (`$rate_limit_file` exists and contains a recent timestamp)
5. Ensure the PHP `mail()` function is enabled on your server

### Error Page Not Displaying

If the error page is not displaying properly:

1. Make sure the scripts are placed in the correct location (`wp-content/` directory)
2. Check for any PHP syntax errors in the scripts
3. Verify that your server has read permissions for the files

## Credits

Originally created by Lee Hodson, VR51, WP Service Masters.
Enhanced with additional features and security improvements.

Copyright 2022-2025 Lee Hodson, VR51, WP Service Masters
