<?php
/**
 * Contact Form Handler (contact.php)
 * Handles AJAX requests and standard POST form submissions securely.
 */

require_once __DIR__ . '/smtp.php';

// Enable session for CSRF protection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration
$recipient_email = defined('SMTP_RECIPIENT') ? SMTP_RECIPIENT : "thirumalaiviswa37@gmail.com";
$email_subject_prefix = "[Portfolio Inquiry] from ";
$messages_log_file = __DIR__ . "/messages.json";

// Google SMTP credentials (set in smtp.php, via environment variables, or override here)
$smtp_user = defined('SMTP_USER') ? SMTP_USER : "thirumalaiviswa37@gmail.com";
$smtp_pass = defined('SMTP_PASS') ? SMTP_PASS : "";

// Helper function to send JSON response
function sendJsonResponse($success, $message, $errors = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'errors' => $errors
    ]);
    exit;
}

// Only process POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        sendJsonResponse(false, "Invalid request method. Only POST is allowed.");
    } else {
        header("Location: index.html");
        exit;
    }
}

// Determine if the request expects JSON
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

// Read inputs (supports form-data, urlencoded, and raw JSON payload)
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true);

if (is_array($json_data)) {
    $name = isset($json_data['name']) ? trim($json_data['name']) : '';
    $email = isset($json_data['email']) ? trim($json_data['email']) : '';
    $message = isset($json_data['message']) ? trim($json_data['message']) : '';
    $honeypot = isset($json_data['website']) ? trim($json_data['website']) : '';
} else {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $honeypot = isset($_POST['website']) ? trim($_POST['website']) : '';
}

// Honeypot spam protection (if hidden field is filled, silently discard spam)
if (!empty($honeypot)) {
    if ($is_ajax) {
        sendJsonResponse(true, "Transmission received successfully.");
    } else {
        header("Location: index.html?status=success#contact");
        exit;
    }
}

// Validation
$errors = [];

// Name validation
if (empty($name)) {
    $errors['name'] = "Name is required.";
} elseif (mb_strlen($name) < 2) {
    $errors['name'] = "Name must be at least 2 characters.";
} elseif (mb_strlen($name) > 100) {
    $errors['name'] = "Name cannot exceed 100 characters.";
}

// Email validation
if (empty($email)) {
    $errors['email'] = "Email address is required.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = "Please provide a valid email address.";
}

// Message validation
if (empty($message)) {
    $errors['message'] = "Message cannot be empty.";
} elseif (mb_strlen($message) < 5) {
    $errors['message'] = "Message should be at least 5 characters.";
} elseif (mb_strlen($message) > 5000) {
    $errors['message'] = "Message is too long (maximum 5,000 characters).";
}

// If there are validation errors
if (!empty($errors)) {
    if ($is_ajax) {
        sendJsonResponse(false, "Please correct the errors in the form.", $errors);
    } else {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = ['name' => $name, 'email' => $email, 'message' => $message];
        header("Location: index.html?status=error#contact");
        exit;
    }
}

// Sanitize inputs for storage and email
$clean_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$clean_email = filter_var($email, FILTER_SANITIZE_EMAIL);
$clean_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
$timestamp = date("Y-m-d H:i:s");

// 1. Prepare and send email via Google SMTP (TLS/Socket)
$subject = $email_subject_prefix . $clean_name;
$body_html = buildContactEmailHtml(
    $name,
    $email,
    $subject,
    $message,
    $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    date("F j, Y, g:i a")
);

$mail_sent = false;
$mail_error = null;

if (!empty($smtp_pass)) {
    try {
        $from_name = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Thirumalai Viswa Portfolio';
        $mail_sent = sendSmtpMail(
            $recipient_email,
            $subject,
            $body_html,
            $email,
            $smtp_user,
            $smtp_pass,
            defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com',
            defined('SMTP_PORT') ? SMTP_PORT : 587,
            $from_name,
            $name
        );
    } catch (Exception $e) {
        $mail_error = $e->getMessage();
        error_log("Google SMTP Send Failed: " . $mail_error);

        // Fallback to PHP mail() if local mail agent is available
        $fallback_headers  = "MIME-Version: 1.0\r\n";
        $fallback_headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $fallback_headers .= "From: <$recipient_email>\r\n";
        $fallback_headers .= "Reply-To: <$email>\r\n";
        $fallback_headers .= "X-Mailer: PHP/" . phpversion();
        $mail_sent = @mail($recipient_email, $subject, $body_html, $fallback_headers);
    }
} else {
    // Fallback if SMTP_PASS is not configured yet
    $fallback_headers  = "MIME-Version: 1.0\r\n";
    $fallback_headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $fallback_headers .= "From: <$recipient_email>\r\n";
    $fallback_headers .= "Reply-To: <$email>\r\n";
    $fallback_headers .= "X-Mailer: PHP/" . phpversion();
    $mail_sent = @mail($recipient_email, $subject, $body_html, $fallback_headers);
    $mail_error = "SMTP_PASS is not set in smtp.php or environment variables.";
}

// 2. Backup log to messages.json (so messages are never lost)
$log_entry = [
    'id' => uniqid('msg_', true),
    'timestamp' => $timestamp,
    'name' => $name,
    'email' => $email,
    'message' => $message,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    'mail_sent' => (bool)$mail_sent,
    'mail_error' => $mail_error
];

$existing_logs = [];
if (file_exists($messages_log_file)) {
    $existing_data = file_get_contents($messages_log_file);
    $existing_logs = json_decode($existing_data, true) ?: [];
}
$existing_logs[] = $log_entry;
file_put_contents($messages_log_file, json_encode($existing_logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);

// Respond to caller
if ($is_ajax) {
    sendJsonResponse(true, "Transmission received. Thirumalai Viswa will respond promptly.");
} else {
    header("Location: index.html?status=success#contact");
    exit;
}
