<?php
/**
 * SMTP Mailer Module for Thirumalai Viswa Portfolio
 * 
 * Direct socket-based SMTP client over STARTTLS (port 587).
 * Fully optimized for maximum Gmail / Google Postmaster deliverability:
 * - RFC 2046 compliant multipart/alternative (Plain Text + Clean HTML)
 * - Pure ASCII header formatting (avoids Base64 spam penalties)
 * - Matching authenticated domain Message-ID
 * - High text-to-code ratio with standard CSS
 */

// -----------------------------------------------------------------------------
// SMTP CONFIGURATION
// -----------------------------------------------------------------------------
// 1. Google Account / SMTP Sender Username
if (!defined('SMTP_USER')) {
    define('SMTP_USER', getenv('SMTP_USER') ?: 'thirumalaiviswa37@gmail.com');
}

// 2. Google App Password (16-character App Password generated for thirumalaiviswa37@gmail.com)
// Automatically loads private credentials.php (git-ignored) or environment variables
if (file_exists(__DIR__ . '/credentials.php')) {
    require_once __DIR__ . '/credentials.php';
}
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
}

// 3. SMTP Server & Port
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
}

// 4. Default Recipient where contact form submissions are delivered
if (!defined('SMTP_RECIPIENT')) {
    define('SMTP_RECIPIENT', getenv('SMTP_RECIPIENT') ?: 'thirumalaiviswa37@gmail.com');
}

// 5. Sender Display Name (shows up in Gmail notifications as sender profile)
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Thirumalai Viswa Portfolio');
}

/**
 * Helper to encode headers safely without triggering spam filters on pure ASCII text.
 */
function cleanMimeHeader($text) {
    if (empty($text)) {
        return '';
    }
    // Only use Base64 encoding if non-ASCII characters exist (RFC 2047)
    if (preg_match('/[^\x20-\x7E]/', $text)) {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
    // Return quoted-string for standard ASCII
    return '"' . addcslashes($text, '"\\') . '"';
}

/**
 * Send email using Google SMTP (or any TLS SMTP server) via raw PHP sockets.
 * Port 587 with STARTTLS encryption. No external libraries or composer required.
 *
 * @param string $to            Recipient email address
 * @param string $subject       Email subject line
 * @param string $bodyHtml      HTML email message content
 * @param string $replyToEmail  Visitor's email address for direct reply
 * @param string $smtpUser      SMTP username / Gmail address
 * @param string $smtpPass      SMTP password / Google App Password
 * @param string $host          SMTP server hostname (default: smtp.gmail.com)
 * @param int    $port          SMTP server port (default: 587)
 * @param string|null $fromName Custom sender display name (default: 'Thirumalai Viswa Portfolio')
 * @param string $replyToName   Visitor's name for Reply-To
 * @param string $bodyPlain     Plain text alternative
 * @return bool                 True on successful transmission
 * @throws Exception            On any socket or SMTP error
 */
function sendSmtpMail(
    $to, 
    $subject, 
    $bodyHtml, 
    $replyToEmail, 
    $smtpUser, 
    $smtpPass, 
    $host = 'smtp.gmail.com', 
    $port = 587, 
    $fromName = null, 
    $replyToName = '',
    $bodyPlain = ''
) {
    if ($fromName === null) {
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Thirumalai Viswa Portfolio';
    }
    $timeout = 15;

    // 1. Open socket connection
    $socket = @stream_socket_client("tcp://$host:$port", $errno, $errstr, $timeout);
    if (!$socket) {
        throw new Exception("Connection failed: $errstr ($errno)");
    }

    // Helper: read SMTP response
    $readResponse = function($socket, $expectedCode) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        $code = substr($response, 0, 3);
        if ($code != $expectedCode) {
            throw new Exception("SMTP Error [$code]: " . trim($response));
        }
        return $response;
    };

    // Helper: send SMTP command
    $sendCommand = function($socket, $command) {
        fputs($socket, $command . "\r\n");
    };

    try {
        // 2. Initial server greeting
        $readResponse($socket, '220');

        // 3. Handshake
        $userDomain = (strpos($smtpUser, '@') !== false) ? substr(strrchr($smtpUser, '@'), 1) : 'gmail.com';
        $hostname = isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST']) 
            ? preg_replace('/:[0-9]+$/', '', $_SERVER['HTTP_HOST']) 
            : '';
        if (empty($hostname) || $hostname === 'localhost' || strpos($hostname, '.') === false) {
            $hostname = $userDomain;
        }
        $sendCommand($socket, "EHLO $hostname");
        $readResponse($socket, '250');

        // 4. Upgrade to TLS (STARTTLS)
        $sendCommand($socket, 'STARTTLS');
        $readResponse($socket, '220');

        $crypto = stream_socket_enable_crypto(
            $socket, 
            true, 
            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
        );
        if (!$crypto) {
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                throw new Exception("TLS encryption failed.");
            }
        }

        // 5. Re-send EHLO after TLS
        $sendCommand($socket, "EHLO $hostname");
        $readResponse($socket, '250');

        // 6. Authenticate
        $sendCommand($socket, 'AUTH LOGIN');
        $readResponse($socket, '334');

        $sendCommand($socket, base64_encode($smtpUser));
        $readResponse($socket, '334');

        $cleanPass = str_replace(' ', '', $smtpPass);
        $sendCommand($socket, base64_encode($cleanPass));
        $readResponse($socket, '235');

        // 7. Sender & Recipient
        $sendCommand($socket, "MAIL FROM: <$smtpUser>");
        $readResponse($socket, '250');

        $sendCommand($socket, "RCPT TO: <$to>");
        $readResponse($socket, '250');

        // 8. Send Data
        $sendCommand($socket, 'DATA');
        $readResponse($socket, '354');

        $replyTo = !empty($replyToEmail) ? $replyToEmail : $smtpUser;
        $dateHeader = date('r');
        $msgId = '<' . time() . '.' . bin2hex(random_bytes(8)) . '@' . $userDomain . '>';

        // Clean Header Formats (No spam-triggering unnecessary Base64 on ASCII)
        $fromFormatted  = cleanMimeHeader($fromName) . " <$smtpUser>";
        $replyFormatted = !empty($replyToName) ? cleanMimeHeader($replyToName) . " <$replyTo>" : "<$replyTo>";
        $subjectFormatted = preg_match('/[^\x20-\x7E]/', $subject) ? '=?UTF-8?B?' . base64_encode($subject) . '?=' : $subject;

        // Auto-generate clean plain-text version if empty
        if (empty($bodyPlain)) {
            $cleanText = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $bodyHtml);
            $cleanText = str_replace(["\r\n", "\r"], "\n", $cleanText);
            $cleanText = preg_replace('/<br\s*\/?>/i', "\n", $cleanText);
            $cleanText = preg_replace('/<\/p>/i', "\n\n", $cleanText);
            $cleanText = preg_replace('/<\/tr>/i', "\n", $cleanText);
            $cleanText = preg_replace('/<\/td>/i', "  ", $cleanText);
            $cleanText = strip_tags($cleanText);
            $cleanText = html_entity_decode($cleanText, ENT_QUOTES, 'UTF-8');
            $bodyPlain = trim(preg_replace("/\n{3,}/", "\n\n", $cleanText));
        }

        // Standard MIME multipart/alternative boundary
        $boundary = "=_NextPart_" . md5(uniqid(mt_rand(), true));

        $headers  = "Date: $dateHeader\r\n";
        $headers .= "From: $fromFormatted\r\n";
        $headers .= "Reply-To: $replyFormatted\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: $subjectFormatted\r\n";
        $headers .= "Message-ID: $msgId\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

        $fullBody  = "--$boundary\r\n";
        $fullBody .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $fullBody .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $fullBody .= $bodyPlain . "\r\n\r\n";
        $fullBody .= "--$boundary\r\n";
        $fullBody .= "Content-Type: text/html; charset=UTF-8\r\n";
        $fullBody .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $fullBody .= $bodyHtml . "\r\n\r\n";
        $fullBody .= "--$boundary--";

        $escapedBody = str_replace("\r\n.", "\r\n..", $fullBody);
        $fullMessage = $headers . "\r\n" . $escapedBody . "\r\n.";

        $sendCommand($socket, $fullMessage);
        $readResponse($socket, '250');

        // 9. Quit
        $sendCommand($socket, 'QUIT');
        fclose($socket);

        return true;
    } catch (Exception $e) {
        if (is_resource($socket)) {
            fclose($socket);
        }
        throw $e;
    }
}

/**
 * Generate a clean, responsive HTML email template for portfolio inquiries.
 * Designed with standard email-client compatible CSS and high deliverability.
 */
function buildContactEmailHtml($name, $email, $subject, $message, $ip = 'Unknown', $timestamp = null) {
    if (!$timestamp) {
        $timestamp = date("F j, Y, g:i a");
    }

    $safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeEmail   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $safeIp      = htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');

    return "
<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <title>{$safeSubject}</title>
</head>
<body style='margin: 0; padding: 20px; background-color: #0d0e15; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #e4e4e7;'>
  <table width='100%' cellpadding='0' cellspacing='0' border='0'>
    <tr>
      <td align='center'>
        <table width='600' cellpadding='0' cellspacing='0' border='0' style='width: 100%; max-width: 600px; background-color: #14151f; border: 1px solid #262738; border-radius: 12px; overflow: hidden;'>
          
          <!-- Header -->
          <tr>
            <td style='padding: 24px 28px; background-color: #1a1b28; border-bottom: 2px solid #f59e0b;'>
              <div style='color: #fbbf24; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px;'>
                New Website Inquiry
              </div>
              <h2 style='margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;'>
                {$safeSubject}
              </h2>
            </td>
          </tr>

          <!-- Details Table -->
          <tr>
            <td style='padding: 24px 28px;'>
              <table width='100%' cellpadding='8' cellspacing='0' border='0' style='background-color: #0d0e15; border-radius: 8px; margin-bottom: 20px; border: 1px solid #262738;'>
                <tr>
                  <td width='25%' style='color: #9ca3af; font-size: 13px; font-weight: bold;'>From:</td>
                  <td style='color: #ffffff; font-size: 14px; font-weight: bold;'>{$safeName}</td>
                </tr>
                <tr>
                  <td style='color: #9ca3af; font-size: 13px; font-weight: bold;'>Email:</td>
                  <td style='color: #fbbf24; font-size: 14px;'>{$safeEmail}</td>
                </tr>
                <tr>
                  <td style='color: #9ca3af; font-size: 13px; font-weight: bold;'>Received:</td>
                  <td style='color: #d1d5db; font-size: 13px;'>{$timestamp}</td>
                </tr>
                <tr>
                  <td style='color: #9ca3af; font-size: 13px; font-weight: bold;'>IP:</td>
                  <td style='color: #6b7280; font-size: 12px; font-family: monospace;'>{$safeIp}</td>
                </tr>
              </table>

              <!-- Message Body -->
              <div style='margin-bottom: 24px;'>
                <div style='color: #9ca3af; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;'>
                  Message:
                </div>
                <div style='background-color: #0d0e15; border-left: 3px solid #f59e0b; padding: 16px 18px; border-radius: 4px; color: #f3f4f6; font-size: 14px; line-height: 1.6;'>
                  {$safeMessage}
                </div>
              </div>

              <!-- Direct Reply Info -->
              <p style='margin: 0; color: #9ca3af; font-size: 13px;'>
                To reply to this inquiry, email directly to: <strong style='color: #fbbf24;'>{$safeEmail}</strong>
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style='padding: 16px 28px; background-color: #0e0f17; border-top: 1px solid #262738; text-align: center; color: #6b7280; font-size: 12px;'>
              Thirumalai Viswa Portfolio &bull; Verified Google SMTP
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
";
}
