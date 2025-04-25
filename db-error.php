<?php
header('HTTP/1.1 503 Service Temporarily Unavailable');
header('Status: 503 Service Temporarily Unavailable');
header('Retry-After: 300'); // 5 minutes = 300 seconds

/**
 * WordPress Custom Database Error Report
 *
 * Drop this file into wp-content.
 * When a database error occurs a stacktrace will be sent to the email address configured below here.
 * Configure the email addresses on lines 27 ($to -- the recipient's address) and 28 ($from -- the from address, which should be configured on the server this script is stored on).
 *
 * 1. This script shows a friendly error message to visitors when a database error occur. The message can be configured near the bottom of this script.
 * 2. This script sends a report to the $to address when a database error occurs on the monitored website.
 * 
 * Copyright 2022 Lee Hodson, VR51, WP Service Masters
 * Donate https://paypal.me/vr51
 * 
 * Licence: GPL2 https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
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
$rate_limit_file = __DIR__ . '/db-error-rate.log'; // File to track last error email
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
$log_file = __DIR__ . '/db-error.log'; // Path to log file

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

// Prepare email subject and error data
$timestamp = date('Y-m-d H:i:s');
$subject = "Database Error on $host - $timestamp";
$uri = isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') : '';
$ip = isset($_SERVER['REMOTE_ADDR']) ? htmlspecialchars($_SERVER['REMOTE_ADDR'], ENT_QUOTES, 'UTF-8') : 'Unknown';
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, 'UTF-8') : 'Unknown';

// Include cookies in report (be careful with privacy)
$include_cookies = false; // Set to true to include cookies in the error report
$include_session = false; // Set to true to include session data in the error report

// Get MySQL version if available
$mysql_version = 'Unknown';
if (isset($wpdb) && isset($wpdb->db_version)) {
    $mysql_version = $wpdb->db_version();
} elseif (function_exists('mysqli_get_client_info')) {
    $mysql_version = mysqli_get_client_info();
}

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

// Get database error message
$wpdb_error = 'Unknown database error';
if (isset($wpdb) && isset($wpdb->error)) {
    $wpdb_error = $wpdb->error;
} elseif (isset($wpdb) && isset($wpdb->last_error)) {
    $wpdb_error = $wpdb->last_error;
} elseif (function_exists('mysqli_error') && isset($GLOBALS['wpdb']) && isset($GLOBALS['wpdb']->dbh)) {
    $wpdb_error = mysqli_error($GLOBALS['wpdb']->dbh);
}

// Get database error trace
$trace = '';
if (function_exists('debug_backtrace')) {
    $trace = print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true);
}

// Get WordPress error details
$wperr = '';
if (isset($GLOBALS['wp_error']) && is_wp_error($GLOBALS['wp_error'])) {
    $wperr = print_r($GLOBALS['wp_error'], true);
}

// Log the error to file if enabled
if ($log_to_file && $log_file) {
    $log_entry = "[$timestamp] Database error on $host$uri\n";
    $log_entry .= "IP: $ip\n";
    $log_entry .= "User Agent: $user_agent\n";
    $log_entry .= "Error: $wpdb_error\n\n";
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
                $log_entry = "[" . date('Y-m-d H:i:s') . "] Database error on $host$uri excluded (matched: $excluded_file)\n";
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
    $msg = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\">\n<title>WordPress Database Error Report</title>\n<style>\nbody { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }\n.container { max-width: 960px; margin: 0 auto; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 20px; }\nh1 { color: $site_color; border-bottom: 1px solid #eee; padding-bottom: 10px; }\nh2 { color: #444; margin-top: 20px; }\npre { background: #f7f7f7; padding: 15px; border-radius: 3px; overflow: auto; font-size: 13px; }\n.header { background: #f7f7f7; padding: 15px; margin-bottom: 20px; border-radius: 5px; }\n.header img { max-height: 50px; margin-right: 10px; vertical-align: middle; }\n.error-time { color: #777; font-size: 14px; }\n.recovery-steps { background: #f0f7ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0; }\n</style>\n</head>\n<body>\n<div class='container'>";

    // Add header with site name and logo
    $msg .= "<div class='header'>" . (!empty($site_logo) ? "<img src='$site_logo' alt='$site_name Logo'>" : "") . "<h1>$site_name - Database Error Report</h1></div>";
    $msg .= "<h2>A database error has occurred on $host</h2>";
    $msg .= "<p><strong>Time:</strong> $timestamp</p>";
    $msg .= "<p><strong>Error triggered on:</strong> <a href='$protocol://$host$uri'>$host$uri</a></p>";
    $msg .= "<p><strong>WordPress Version:</strong> $wp_version</p>";
    $msg .= "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
    $msg .= "<p><strong>WP Stacktrace:</strong></p><pre>$trace</pre>";

    // Add recovery steps section
    $msg .= "<div class='recovery-steps'>\n";
    $msg .= "<h3>Suggested Recovery Steps:</h3>\n";
    $msg .= "<ol>\n";
    $msg .= "<li>Check database connection settings in wp-config.php</li>\n";
    $msg .= "<li>Verify database server is running and accessible</li>\n";
    $msg .= "<li>Check for corrupted database tables (run 'CHECK TABLE' or repair)</li>\n";
    $msg .= "<li>Review recent plugin or theme changes that might affect database connections</li>\n";
    $msg .= "<li>Check database user permissions</li>\n";
    $msg .= "<li>Review server error logs for additional MySQL/MariaDB errors</li>\n";
    $msg .= "</ol>\n";
    $msg .= "</div>";
    $msg .= "<hr /><h1>Detailed Error Report</h1>$wperr";

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
<html dir="ltr">
<head>

<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width">
<title>Maintenance Page</title>
<style type="text/css">
:root {
	--primary-color: <?php echo $site_color; ?>;
	--background-gradient-from: lightskyblue;
	--background-gradient-to: white;
	--text-color: #444;
	--border-color: #ccd0d4;
}

html {
	box-sizing: border-box;
	display: grid;
	align-items: center;
	justify-content: center;
	background: var(--background-gradient-from);
	background-image: radial-gradient(circle, var(--background-gradient-from), var(--background-gradient-to));
	box-sizing: border-box;
}
body {
	justify-content: center;
	background: #fff;
	border: 1px solid #ccd0d4;
	color: #444;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
	margin: 2em auto;
	padding: 1em 2em;
	max-width: 700px;
	-webkit-box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
	box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
}
h1 {
	border-bottom: 1px solid #dadada;
	clear: both;
	color: #666;
	font-size: 24px;
	margin: 30px 0 0 0;
	padding: 0;
	padding-bottom: 7px;
}
p {
    
}
#error-page {
margin-top: 50px;
}
#error-page p,
#error-page .wp-die-message {
font-size: 14px;
line-height: 1.5;
margin: 25px 0 20px;
}
#error-page code {
font-family: Consolas, Monaco, monospace;
}
ul li {
	margin-bottom: 10px;
	font-size: 14px ;
}
a {
	color: #0073aa;
}
a:hover,
a:active {
	color: #006799;
}
a:focus {
	color: #124964;
	-webkit-box-shadow:
	0 0 0 1px #5b9dd9,
	0 0 2px 1px rgba(30, 140, 190, 0.8);
	box-shadow:
	0 0 0 1px #5b9dd9,
	0 0 2px 1px rgba(30, 140, 190, 0.8);
	outline: none;
}
.button {
	background: #f3f5f6;
	border: 1px solid #016087;
	color: #016087;
	display: inline-block;
	text-decoration: none;
	font-size: 13px;
	line-height: 2;
	height: 28px;
	margin: 0;
	padding: 0 10px 1px;
	cursor: pointer;
	-webkit-border-radius: 3px;
	-webkit-appearance: none;
	border-radius: 3px;
	white-space: nowrap;
	-webkit-box-sizing: border-box;
	-moz-box-sizing:    border-box;
	box-sizing:         border-box;
	vertical-align: top;
}

.button:hover,
.button:focus {
	background: #f1f1f1;
}

.button:focus {
	background: #f3f5f6;
	border-color: #007cba;
	-webkit-box-shadow: 0 0 0 1px #007cba;
	box-shadow: 0 0 0 1px #007cba;
	color: #016087;
	outline: 2px solid transparent;
	outline-offset: 0;
}

.button:active {
	background: #f3f5f6;
	border-color: #7e8993;
	-webkit-box-shadow: none;
	box-shadow: none;
}
</style>

</head>
<body id="error-page">
<div class="wp-die-message">
<?php if (!empty($site_logo)): ?>
<div style="text-align: center; margin-bottom: 20px;">
    <img src="<?php echo htmlspecialchars($site_logo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8'); ?> Logo" style="max-height: 100px;">
</div>
<?php endif; ?>

<h1><?php echo htmlspecialchars($public_heading, ENT_QUOTES, 'UTF-8'); ?></h1>
<p><?php echo htmlspecialchars($public_message, ENT_QUOTES, 'UTF-8'); ?></p>

<p><?php echo htmlspecialchars($public_thanks, ENT_QUOTES, 'UTF-8'); ?></p>

<?php if (!empty($public_email)): ?>
<p><?php echo htmlspecialchars($public_contact, ENT_QUOTES, 'UTF-8'); ?>: <a href="mailto:<?php echo htmlspecialchars($public_email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($public_email, ENT_QUOTES, 'UTF-8'); ?></a></p>
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
<p><small><?php echo htmlspecialchars($public_retry, ENT_QUOTES, 'UTF-8'); ?> <span id="countdown"><?php echo $refresh_seconds; ?></span> seconds...</small></p>
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
