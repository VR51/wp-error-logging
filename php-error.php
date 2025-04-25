<?php
header('HTTP/1.1 503 Service Temporarily Unavailable');
header('Status: 503 Service Temporarily Unavailable');
header('Retry-After: 300'); // 5 minutes = 300 seconds

/**
 * WordPress Custom PHP Error Report
 *
 * Drop this file into wp-content.
 * When a PHP error occurs a stacktrace will be sent to the email address configured below here.
 * Configure the email addresses on lines 27 ($to -- the recipient's address) and 28 ($from -- the from address, which should be configured on the server this script is stored on).
 *
 * 1. This script shows a friendly error message to visitors when a PHP error occur. The message can be configured near the bottom of this script.
 * 2. This script sends a report to the $to address when a PHP error occurs on the monitored website.
 * 
 * Copyright 2022 Lee Hodson, VR51, WP Service Masters
 * Donate https://paypal.me/vr51
 * 
 * Licence: GPL3 https://www.gnu.org/licenses/old-licenses/gpl-3.0.en.html
 */

// Email Error Log to Support User

/**
 * CONFIGS
 ***/

// Email configuration
$to = "info@example.com"; // Configure me - recipient's email address
$from = "info@example.com"; // Configure me - sender's email address (must be configured on this server)
$cc = ""; // Optional CC recipient
$bcc = ""; // Optional BCC recipient

// Rate limiting for emails - prevents flooding
$rate_limit = true; // Set to false to disable rate limiting
$rate_limit_file = __DIR__ . '/php-error-rate.log'; // File to track last error email
$rate_limit_interval = 300; // Minimum seconds between error emails (5 minutes)

// Error messages email styles
$style_h = 'style="margin-bottom: 15px;"'; // h# tags
$style_p = 'style="margin-bottom: 15px;"'; // p tags
$style_div = 'style="color: #000; background-image: radial-gradient(circle, lightskyblue, white); padding: 5px;"'; // div tags

// Public Message Configuration
$host = isset($_SERVER['HTTP_HOST']) ? htmlspecialchars($_SERVER['HTTP_HOST'], ENT_QUOTES, 'UTF-8') : 'this website'; 
$retry_seconds = 300; // Should match the Retry-After header

// Multi-language support - detect language from browser if possible
$user_language = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : 'en';

// Default language is English, but you can add more languages
$lang = 'en'; // Default language
$supported_languages = array('en', 'es', 'fr', 'de', 'it', 'nl', 'ru', 'ja', 'zh', 'ar');

// If user's language is supported, use it
if (in_array($user_language, $supported_languages)) {
    $lang = $user_language;
}

// Translations for the public error message
$translations = array(
    'en' => array(
        'title' => 'Maintenance in Progress',
        'heading' => 'We\'ll be back soon!',
        'message' => 'Sorry for the inconvenience. We\'re performing some maintenance at the moment. We\'ll be back online shortly!',
        'retry' => 'This page will automatically refresh when maintenance is complete.',
        'contact' => 'If you need to you can always contact us at',
        'email' => 'info@example.com', // Configure me
        'thanks' => 'Thank you for your patience.'
    ),
    'es' => array(
        'title' => 'Mantenimiento en Progreso',
        'heading' => '¡Volveremos pronto!',
        'message' => 'Disculpe las molestias. Estamos realizando algunas tareas de mantenimiento en este momento. ¡Volveremos a estar en línea en breve!',
        'retry' => 'Esta página se actualizará automáticamente cuando se complete el mantenimiento.',
        'contact' => 'Si lo necesita, siempre puede contactarnos en',
        'email' => 'info@example.com', // Configure me
        'thanks' => 'Gracias por su paciencia.'
    ),
    // Add more translations as needed
);

// Use English as fallback if translation is not available
if (!isset($translations[$lang])) {
    $lang = 'en';
}

// Set variables for the public message
$public_title = $translations[$lang]['title'];
$public_heading = $translations[$lang]['heading'];
$public_message = $translations[$lang]['message'];
$public_retry = $translations[$lang]['retry'];
$public_contact = $translations[$lang]['contact'];
$public_email = $translations[$lang]['email'];
$public_thanks = $translations[$lang]['thanks'];

// File logging configuration
$log_to_file = false; // Set to false to disable file logging
$log_file = __DIR__ . '/php-error.log'; // Path to log file

// Branding configuration
$site_logo = ''; // URL to site logo (optional)
$site_name = isset($_SERVER['HTTP_HOST']) ? htmlspecialchars($_SERVER['HTTP_HOST'], ENT_QUOTES, 'UTF-8') : 'WordPress Site'; // Site name
$site_color = '#0073aa'; // Primary color for branding

// External monitoring integration
$ping_external_monitoring = false; // Set to true to enable
$monitoring_url = ''; // URL to ping (e.g., https://hc-ping.com/your-uuid)

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

/** END CONFS ***/

// Define PHP error types for better reporting
$etype = array(
	E_ERROR => "E_ERROR",
	E_WARNING => "E_WARNING",
	E_PARSE => "E_PARSE",
	E_NOTICE => "E_NOTICE",
	E_CORE_ERROR => "E_CORE_ERROR",
	E_CORE_WARNING => "E_CORE_WARNING",
	E_COMPILE_ERROR => "E_COMPILE_ERROR",
	E_COMPILE_WARNING => "E_COMPILE_WARNING",
	E_USER_ERROR => "E_USER_ERROR",
	E_USER_WARNING => "E_USER_WARNING",
	E_USER_NOTICE => "E_USER_NOTICE",
	E_STRICT => "E_STRICT",
	E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
	E_DEPRECATED => "E_DEPRECATED",
	E_USER_DEPRECATED => "E_USER_DEPRECATED",
	E_ALL => "E_ALL"
);

/* END PHP Error Types */

// Prepare email subject and error data
$timestamp = date('Y-m-d H:i:s');
$subject = "PHP Error on $host - $timestamp";
$uri = isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') : '';
$ip = isset($_SERVER['REMOTE_ADDR']) ? htmlspecialchars($_SERVER['REMOTE_ADDR'], ENT_QUOTES, 'UTF-8') : 'Unknown';
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, 'UTF-8') : 'Unknown';

// Include cookies in report (be careful with privacy)
$include_cookies = false; // Set to true to include cookies in the error report
$include_session = false; // Set to true to include session data in the error report

// Get WordPress version if available
$wp_version = 'Unknown';
if (file_exists(dirname(__FILE__) . '/wp-includes/version.php')) {
    include_once(dirname(__FILE__) . '/wp-includes/version.php');
    if (isset($wp_version)) {
        $wp_version = $wp_version;
    }
}

// Get server information
$server_info = '';
if (isset($_SERVER)) {
    $safe_server = $_SERVER;
    // Remove sensitive information
    unset($safe_server['HTTP_COOKIE']);
    unset($safe_server['PHP_AUTH_PW']);
    
    $server_info = print_r($safe_server, true);
}

// Log the error to file if enabled
if ($log_to_file && $log_file) {
    $log_entry = "[$timestamp] PHP error on $host$uri\n";
    $log_entry .= "IP: $ip\n";
    $log_entry .= "User Agent: $user_agent\n";
    
    $err = error_get_last();
    if ($err) {
        $log_entry .= "Error Type: " . (isset($etype[$err['type']]) ? $etype[$err['type']] : 'Unknown') . "\n";
        $log_entry .= "Error Message: " . $err['message'] . "\n";
        $log_entry .= "Error File: " . $err['file'] . "\n";
        $log_entry .= "Error Line: " . $err['line'] . "\n";
    }
    
    $log_entry .= "\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Define protocol for links
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';

// Check if the error is from an excluded file
$should_send_report = true;
$error_details = error_get_last();

if ($enable_file_exclusions && !empty($excluded_files) && $error_details && isset($error_details['file'])) {
    $error_file = $error_details['file'];
    
    foreach ($excluded_files as $excluded_file) {
        if (stripos($error_file, $excluded_file) !== false) {
            $should_send_report = false;
            
            // Log exclusion if logging is enabled
            if ($log_to_file && $log_file) {
                $log_entry = "[" . date('Y-m-d H:i:s') . "] PHP error on $host$uri excluded (matched: $excluded_file)\n";
                $log_entry .= "Error file: $error_file\n\n";
                @file_put_contents($log_file, $log_entry, FILE_APPEND);
            }
            
            break;
        }
    }
}

// Only prepare and send email if the file is not excluded
if ($should_send_report) {
    // Prepare the email content
    $msg = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\">\n<title>WordPress PHP Error Report</title>\n<style>\nbody { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }\n.container { max-width: 960px; margin: 0 auto; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 20px; }\nh1 { color: $site_color; border-bottom: 1px solid #eee; padding-bottom: 10px; }\nh2 { color: #444; margin-top: 20px; }\npre { background: #f7f7f7; padding: 15px; border-radius: 3px; overflow: auto; font-size: 13px; }\n.header { background: #f7f7f7; padding: 15px; margin-bottom: 20px; border-radius: 5px; }\n.header img { max-height: 50px; margin-right: 10px; vertical-align: middle; }\n.error-time { color: #777; font-size: 14px; }\n.recovery-steps { background: #f0f7ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0; }\n</style>\n</head>\n<body>\n<div class='container'>";

    // Add header with site name and logo
    $msg .= "<div class='header'>" . (!empty($site_logo) ? "<img src='$site_logo' alt='$site_name Logo'>" : "") . "<h1>$site_name - PHP Error Report</h1></div>";
    $msg .= "<h2>A PHP error has occurred on $host</h2>";
    $msg .= "<p><strong>Time:</strong> $timestamp</p>";
    $msg .= "<p><strong>Error triggered on:</strong> <a href='$protocol://$host$uri'>$host$uri</a></p>";
    $msg .= "<p><strong>WordPress Version:</strong> $wp_version</p>";
    $msg .= "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

    // Add error details
    $err = error_get_last();
    if ($err && isset($err['type'])) {
        $type = $err['type'];
        $msg .= "<h2>Error Details</h2>";
        $msg .= "<p><strong>Type:</strong> " . (isset($etype[$type]) ? $etype[$type] : 'Unknown Error Type') . "</p>";
        $msg .= "<p><strong>Message:</strong> " . htmlspecialchars($err['message'], ENT_QUOTES, 'UTF-8') . "</p>";
        $msg .= "<p><strong>File:</strong> " . htmlspecialchars($err['file'], ENT_QUOTES, 'UTF-8') . "</p>";
        $msg .= "<p><strong>Line:</strong> " . htmlspecialchars($err['line'], ENT_QUOTES, 'UTF-8') . "</p>";
        
        // Try to get file contents around the error line
        if (file_exists($err['file']) && is_readable($err['file'])) {
            $lines = file($err['file']);
            $start_line = max(0, $err['line'] - 5);
            $end_line = min(count($lines), $err['line'] + 5);
            
            $msg .= "<h3>Code Context</h3>";
            $msg .= "<pre>";
            for ($i = $start_line; $i < $end_line; $i++) {
                $line_num = $i + 1;
                $highlight = ($line_num == $err['line']) ? 'background-color: #ffeeee;' : '';
                $msg .= "<div style='$highlight'><strong>$line_num:</strong> " . htmlspecialchars($lines[$i], ENT_QUOTES, 'UTF-8') . "</div>";
            }
            $msg .= "</pre>";
        }
    } else {
        $msg .= "<p><strong>Error:</strong> No specific error details available</p>";
    }

    // Add recovery steps section
    $msg .= "<div class='recovery-steps'>\n";
    $msg .= "<h3>Suggested Recovery Steps:</h3>\n";
    $msg .= "<ol>\n";
    $msg .= "<li>Check the error details above to identify the issue</li>\n";
    $msg .= "<li>Review the code in the identified file and line number</li>\n";
    $msg .= "<li>Check for syntax errors or logical issues</li>\n";
    $msg .= "<li>Review recent plugin or theme changes</li>\n";
    $msg .= "<li>Verify PHP version compatibility with your WordPress installation</li>\n";
    $msg .= "<li>Check PHP error logs for additional information</li>\n";
    $msg .= "</ol>\n";
    $msg .= "</div>";

    // Add system environment information
    $msg .= "<h2>System Environment</h2>";
    $msg .= "<ul>";
    $msg .= "<li><strong>PHP Memory Limit:</strong> " . ini_get('memory_limit') . "</li>";
    $msg .= "<li><strong>PHP Max Execution Time:</strong> " . ini_get('max_execution_time') . " seconds</li>";
    $msg .= "<li><strong>PHP Extensions:</strong> " . implode(', ', get_loaded_extensions()) . "</li>";
    $msg .= "<li><strong>Server Software:</strong> " . (isset($_SERVER['SERVER_SOFTWARE']) ? htmlspecialchars($_SERVER['SERVER_SOFTWARE'], ENT_QUOTES, 'UTF-8') : 'Unknown') . "</li>";
    $msg .= "</ul>";
    
    // Add footer with timestamp
    $msg .= "<hr>\n<p class='error-time'>This error report was generated on $timestamp</p>\n";
    $msg .= "</div>\n</body>\n</html>";

    // Prepare email headers
    $headers = array();
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/html; charset=UTF-8";
    $headers[] = "From: $from";
    if (!empty($cc)) $headers[] = "Cc: $cc";
    if (!empty($bcc)) $headers[] = "Bcc: $bcc";
    $headers[] = "X-Priority: 1"; // High priority

    $headers_string = implode("\r\n", $headers);

    // Check rate limiting before sending email
    $should_send_email = true;
    if ($rate_limit && file_exists($rate_limit_file)) {
        $last_sent = @file_get_contents($rate_limit_file);
        if ($last_sent && (time() - intval($last_sent) < $rate_limit_interval)) {
            $should_send_email = false;
            if ($log_to_file && $log_file) {
                $log_entry = "[" . date('Y-m-d H:i:s') . "] Email rate limited (last sent " . date('Y-m-d H:i:s', intval($last_sent)) . ")\n";
                @file_put_contents($log_file, $log_entry, FILE_APPEND);
            }
        }
    }
    
    // Send the email if not rate limited
    if ($should_send_email) {
        $mail_sent = @mail($to, $subject, $msg, $headers_string);
        
        // Update rate limit timestamp
        if ($mail_sent && $rate_limit) {
            @file_put_contents($rate_limit_file, time());
        }
        
        // If email fails, try to log the error
        if (!$mail_sent && $log_to_file && $log_file) {
            $log_entry = "[" . date('Y-m-d H:i:s') . "] Failed to send error email to $to\n";
            @file_put_contents($log_file, $log_entry, FILE_APPEND);
        }
    }
    
    // Ping external monitoring service if configured
    if ($ping_external_monitoring && !empty($monitoring_url)) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2, // Short timeout to avoid delaying the error page
                'ignore_errors' => true
            ]
        ]);
        @file_get_contents($monitoring_url . "/fail", false, $context);
    }
}

// The HTML error page is always displayed regardless of whether an email is sent
?>
<!DOCTYPE HTML>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $public_title; ?></title>
    <style>
        :root {
            --primary-color: <?php echo $site_color; ?>;
            --text-color: #333;
            --background-color: #f8f8f8;
            --border-color: #ddd;
            --accent-color: #0073aa;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--background-color);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header img {
            max-height: 80px;
            margin-bottom: 20px;
        }
        h1 {
            color: var(--primary-color);
            margin-top: 0;
            font-size: 28px;
        }
        .message {
            background-color: #f9f9f9;
            border-left: 4px solid var(--primary-color);
            padding: 20px;
            margin: 20px 0;
        }
        .retry {
            text-align: center;
            margin-top: 30px;
            color: #666;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 14px;
            color: #777;
        }
        a {
            color: var(--accent-color);
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }
            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <?php if (!empty($site_logo)): ?>
                <img src="<?php echo htmlspecialchars($site_logo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8'); ?> Logo">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($public_heading, ENT_QUOTES, 'UTF-8'); ?></h1>
        </div>
        
        <div class="message">
            <p><?php echo htmlspecialchars($public_message, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        
        <p><?php echo htmlspecialchars($public_thanks, ENT_QUOTES, 'UTF-8'); ?></p>
        
        <?php if (!empty($public_email)): ?>
            <p class="footer"><?php echo htmlspecialchars($public_contact, ENT_QUOTES, 'UTF-8'); ?>: 
                <a href="mailto:<?php echo htmlspecialchars($public_email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($public_email, ENT_QUOTES, 'UTF-8'); ?></a>
            </p>
        <?php endif; ?>
        
        <?php 
        // Add auto-refresh meta tag
        if ($retry_seconds > 0):
            $refresh_seconds = min($retry_seconds, 300); // Don't refresh more than every 5 minutes
        ?>
        <script>
            // Auto-refresh the page after <?php echo $refresh_seconds; ?> seconds
            setTimeout(function() {
                window.location.reload();
            }, <?php echo $refresh_seconds * 1000; ?>);
        </script>
        <p class="retry"><small><?php echo htmlspecialchars($public_retry, ENT_QUOTES, 'UTF-8'); ?> <span id="countdown"><?php echo $refresh_seconds; ?></span> seconds...</small></p>
        <script>
            // Countdown timer
            var seconds = <?php echo $refresh_seconds; ?>;
            var countdownElement = document.getElementById('countdown');
            var countdownInterval = setInterval(function() {
                seconds--;
                countdownElement.textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                }
            }, 1000);
        </script>
        <?php endif; ?>
    </div>
</body>
</html>
