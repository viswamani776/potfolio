<?php
/**
 * Contact Form Handler (contact.php)
 * Handles AJAX requests and standard POST form submissions securely.
 */

// Enable session for CSRF protection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration
$recipient_email = "thirumalaiviswa37@gmail.com";
$email_subject_prefix = "[Portfolio Inquiry] from ";
$messages_log_file = __DIR__ . "/messages.json";

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

// 1. Prepare and send email
$subject = $email_subject_prefix . $clean_name;
$email_body = "New Contact Form Transmission:\n\n";
$email_body .= "Date/Time: " . $timestamp . "\n";
$email_body .= "Name: " . $name . "\n";
$email_body .= "Email: " . $email . "\n";
$email_body .= "IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n\n";
$email_body .= "Message:\n" . $message . "\n";

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-type: text/plain; charset=utf-8';
$headers[] = 'From: ' . $recipient_email;
$headers[] = 'Reply-To: ' . $email;
$headers[] = 'X-Mailer: PHP/' . phpversion();

// Attempt sending email via mail()
$mail_sent = @mail($recipient_email, $subject, $email_body, implode("\r\n", $headers));

// 2. Backup log to messages.json (so messages are never lost if mail server is offline)
$log_entry = [
    'id' => uniqid('msg_', true),
    'timestamp' => $timestamp,
    'name' => $name,
    'email' => $email,
    'message' => $message,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    'mail_sent' => (bool)$mail_sent
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
