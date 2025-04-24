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

// Define messages for different languages
$messages = [
    'en' => [
        'header' => "<h1>$host is temporarily unavailable for maintenance</h1>",
        'content' => "<p>Please refresh your browser in " . ($retry_seconds / 60) . " minutes.</p><p>We apologize for the inconvenience.</p>",
        'contact' => "If this problem persists, please contact the site administrator."
    ],
    'es' => [
        'header' => "<h1>$host no está disponible temporalmente por mantenimiento</h1>",
        'content' => "<p>Por favor, actualice su navegador en " . ($retry_seconds / 60) . " minutos.</p><p>Disculpe las molestias.</p>",
        'contact' => "Si el problema persiste, póngase en contacto con el administrador del sitio."
    ],
    'fr' => [
        'header' => "<h1>$host est temporairement indisponible pour maintenance</h1>",
        'content' => "<p>Veuillez rafraîchir votre navigateur dans " . ($retry_seconds / 60) . " minutes.</p><p>Nous nous excusons pour la gêne occasionnée.</p>",
        'contact' => "Si ce problème persiste, veuillez contacter l'administrateur du site."
    ],
    'de' => [
        'header' => "<h1>$host ist wegen Wartungsarbeiten vorübergehend nicht verfügbar</h1>",
        'content' => "<p>Bitte aktualisieren Sie Ihren Browser in " . ($retry_seconds / 60) . " Minuten.</p><p>Wir entschuldigen uns für die Unannehmlichkeiten.</p>",
        'contact' => "Wenn dieses Problem weiterhin besteht, wenden Sie sich bitte an den Administrator der Website."
    ]
];

// Fallback to English if language not supported
if (!isset($messages[$user_language])) {
    $user_language = 'en';
}

$public_message_header = $messages[$user_language]['header'];
$public_message_content = $messages[$user_language]['content'];
$contact_message = $messages[$user_language]['contact'];

// Logging configuration
$log_to_file = false; // Set to true to log errors to a file
$log_file = __DIR__ . '/db-errors.log'; // Path to log file

// Branding configuration
$site_logo = ''; // URL to your site logo (optional)
$site_name = isset($_SERVER['HTTP_HOST']) ? htmlspecialchars($_SERVER['HTTP_HOST'], ENT_QUOTES, 'UTF-8') : 'WordPress Site'; // Site name
$site_color = '#0073aa'; // Primary color for branding

// External monitoring integration
$ping_external_monitoring = false; // Set to true to enable
$monitoring_url = ''; // URL to ping (e.g., https://hc-ping.com/your-uuid)

/** END CONFS ***/

// Prepare email subject and error data
$timestamp = date('Y-m-d H:i:s');
$subject = "WordPress Database Error on $host - $timestamp";
$uri = isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') : 'unknown';
$e = new \Exception;
$trace = $e->getTraceAsString();

/* PHP Error Types */

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

// Start output buffering to capture error information
ob_start();

// Format helpers for better readability in HTML email
$find = array('( ', ' [', ' )');
$replace = array('<br />(', '<br />&nbsp;&nbsp;[', '<br />)');

// Get WordPress version if available
$wp_version = 'Unknown';
if (file_exists(dirname(__FILE__) . '/wp-includes/version.php')) {
    include_once(dirname(__FILE__) . '/wp-includes/version.php');
    if (isset($wp_version)) {
        $wp_version = $wp_version;
    }
}

echo "<h2 $style_h>ERROR SUMMARY</h2>";
echo "<p><strong>Time:</strong> $timestamp</p>";
echo "<p><strong>WordPress Version:</strong> $wp_version</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

echo "<h2 $style_h>ERROR MESSAGE</h2>";
$err = error_get_last();
if ($err && isset($err['type'])) {
    $type = $err['type'];
    echo "<p><strong>Type:</strong> " . (isset($etype[$type]) ? $etype[$type] : 'Unknown Error Type') . "</p>";
    $err_output = "<p $style_p><div $style_div>" . print_r($err, true) . "</div></p>";
    echo str_replace($find, $replace, $err_output);
} else {
    echo "<p><strong>Error:</strong> No specific error details available</p>";
}

echo "<h2 $style_h>SERVER</h2>";
// Filter out sensitive information from $_SERVER
$server_info = $_SERVER;
if (isset($server_info['HTTP_COOKIE'])) $server_info['HTTP_COOKIE'] = '[REDACTED]';
if (isset($server_info['HTTP_AUTHORIZATION'])) $server_info['HTTP_AUTHORIZATION'] = '[REDACTED]';
if (isset($server_info['PHP_AUTH_PW'])) $server_info['PHP_AUTH_PW'] = '[REDACTED]';

$err = "<p $style_p><div $style_div>" . print_r($server_info, true) . "</div></p>";
echo str_replace($find, $replace, $err);

echo "<h2 $style_h>POST</h2>";
// Sanitize POST data to avoid exposing sensitive information
$post_data = array();
foreach ($_POST as $key => $value) {
    if (stripos($key, 'pass') !== false || stripos($key, 'secret') !== false || stripos($key, 'key') !== false) {
        $post_data[$key] = '[REDACTED]';
    } else {
        $post_data[$key] = is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
    }
}
$err = "<p $style_p><div $style_div>" . print_r($post_data, true) . "</div></p>";
echo str_replace($find, $replace, $err);

echo "<h2 $style_h>GET</h2>";
// Sanitize GET data
$get_data = array();
foreach ($_GET as $key => $value) {
    $get_data[$key] = is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
}
$err = "<p $style_p><div $style_div>" . print_r($get_data, true) . "</div></p>";
echo str_replace($find, $replace, $err);

echo "<h2 $style_h >GLOBAL KEYS</h2>";
$err = "<p $style_p ><div $style_div >" . print_r( array_keys(get_defined_vars()), true ) . "</div></p>";
echo str_replace( $find, $replace, $err );

echo "<h2 $style_h >GLOBAL VALUES</h2>";
$err = "<p $style_p ><div $style_div >" . print_r( get_defined_vars(), true ) . "</div></p>";
echo str_replace( $find, $replace, $err );

echo "<h2 $style_h>DATABASE QUERIES</h2>";
// Safely access $wpdb if available
global $wpdb;
if (isset($wpdb) && is_object($wpdb) && isset($wpdb->queries)) {
    $err = "<p $style_p><div $style_div>" . print_r($wpdb->queries, true) . "</div></p>";
    echo str_replace($find, $replace, $err);
    
    // Add database connection info if available
    if (isset($wpdb->dbhost) || isset($wpdb->dbname)) {
        echo "<h2 $style_h>DATABASE INFO</h2>";
        echo "<p><strong>DB Host:</strong> " . (isset($wpdb->dbhost) ? $wpdb->dbhost : 'Unknown') . "</p>";
        echo "<p><strong>DB Name:</strong> " . (isset($wpdb->dbname) ? $wpdb->dbname : 'Unknown') . "</p>";
        echo "<p><strong>DB User:</strong> " . (isset($wpdb->dbuser) ? $wpdb->dbuser : 'Unknown') . "</p>";
        echo "<p><strong>DB Charset:</strong> " . (isset($wpdb->charset) ? $wpdb->charset : 'Unknown') . "</p>";
        echo "<p><strong>Table Prefix:</strong> " . (isset($wpdb->prefix) ? $wpdb->prefix : 'Unknown') . "</p>";
    }
} else {
    echo "<p>No database query information available.</p>";
}

// Capture the error report
$wperr = ob_get_clean();

// Log to file if enabled
if ($log_to_file && $log_file) {
    $log_entry = "[" . date('Y-m-d H:i:s') . "] Database error on $host$uri\n";
    $log_entry .= "Error: " . (isset($err) && isset($err['message']) ? $err['message'] : 'Unknown error') . "\n";
    $log_entry .= "Trace: " . str_replace("\n", "\n  ", $trace) . "\n\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Prepare the email message with improved styling
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$msg = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\">\n<title>WordPress Database Error Report</title>\n<style>\nbody { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }\n.container { max-width: 960px; margin: 0 auto; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 20px; }\nh1 { color: $site_color; border-bottom: 1px solid #eee; padding-bottom: 10px; }\nh2 { color: #444; margin-top: 20px; }\npre { background: #f7f7f7; padding: 15px; border-radius: 3px; overflow: auto; font-size: 13px; }\n.header { background: #f7f7f7; padding: 15px; margin-bottom: 20px; border-radius: 5px; }\n.header img { max-height: 50px; margin-right: 10px; vertical-align: middle; }\n.error-time { color: #777; font-size: 14px; }\n.recovery-steps { background: #f0f7ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0; }\n</style>\n</head>\n<body>\n<div class='container'>";
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
?>

<!-- Display Nice Error Page to Affected Visitor -->
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

.button.button-large {
	line-height: 2.30769231;
	min-height: 32px;
	padding: 0 12px;
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
    <img src="<?php echo htmlspecialchars($site_logo, ENT_QUOTES, 'UTF-8'); ?>" alt="Site Logo" style="max-width: 200px; max-height: 80px;">
</div>
<?php endif; ?>

<?php echo $public_message_header; ?>
<?php echo $public_message_content; ?>

<p>If this problem persists, please contact the site administrator.</p>

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
<p><small>This page will automatically refresh in <span id="countdown"><?php echo $refresh_seconds; ?></span> seconds...</small></p>
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
