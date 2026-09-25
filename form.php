<?php
/**
 * Standalone Complete PHP Form
 * Features:
 * - Server-side validation & sanitization
 * - CSRF token verification
 * - Honeypot anti-spam protection
 * - Sticky form inputs
 * - Inline error alerts & success feedback
 * - Backup storage in messages.json
 */

session_start();

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$recipient_email = "thirumalaiviswa37@gmail.com";
$messages_log_file = __DIR__ . "/messages.json";

// Form variables initialization
$name = "";
$email = "";
$subject_input = "";
$message = "";
$errors = [];
$success_message = "";

// Process form when submitted via POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. Verify CSRF Token
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $errors['general'] = "Security check failed (CSRF token mismatch). Please refresh and try again.";
    }

    // 2. Honeypot check (hidden spam field)
    $honeypot = trim($_POST['botcheck'] ?? '');
    if (!empty($honeypot)) {
        // Silently succeed to fool spambots
        $success_message = "Your message has been transmitted successfully!";
    } else {
        // 3. Extract and trim input data
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject_input = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // 4. Validate Name
        if (empty($name)) {
            $errors['name'] = "Full name is required.";
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = "Name must be at least 2 characters.";
        } elseif (mb_strlen($name) > 80) {
            $errors['name'] = "Name is too long (maximum 80 characters).";
        }

        // 5. Validate Email
        if (empty($email)) {
            $errors['email'] = "Email address is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Please enter a valid email address (e.g. name@example.com).";
        }

        // 6. Validate Subject
        if (empty($subject_input)) {
            $errors['subject'] = "Subject is required.";
        } elseif (mb_strlen($subject_input) < 3) {
            $errors['subject'] = "Subject must be at least 3 characters.";
        }

        // 7. Validate Message
        if (empty($message)) {
            $errors['message'] = "Please provide your message.";
        } elseif (mb_strlen($message) < 10) {
            $errors['message'] = "Message should be at least 10 characters long.";
        } elseif (mb_strlen($message) > 5000) {
            $errors['message'] = "Message cannot exceed 5,000 characters.";
        }

        // 8. If no validation errors, process & store/send
        if (empty($errors)) {
            $clean_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $clean_email = filter_var($email, FILTER_SANITIZE_EMAIL);
            $clean_subject = htmlspecialchars($subject_input, ENT_QUOTES, 'UTF-8');
            $clean_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
            $timestamp = date("Y-m-d H:i:s");

            // Email details
            $mail_subject = "[Contact Form] " . $clean_subject;
            $body = "New message received from your website form:\n\n";
            $body .= "Date: " . $timestamp . "\n";
            $body .= "Name: " . $name . "\n";
            $body .= "Email: " . $email . "\n";
            $body .= "Subject: " . $subject_input . "\n\n";
            $body .= "Message:\n" . $message . "\n";

            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/plain; charset=utf-8',
                'From: ' . $recipient_email,
                'Reply-To: ' . $email,
                'X-Mailer: PHP/' . phpversion()
            ];

            // Send email (mail function)
            $mail_sent = @mail($recipient_email, $mail_subject, $body, implode("\r\n", $headers));

            // Log entry into JSON file as persistence backup
            $log_entry = [
                'id' => uniqid('msg_', true),
                'timestamp' => $timestamp,
                'name' => $name,
                'email' => $email,
                'subject' => $subject_input,
                'message' => $message,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                'mail_sent' => (bool)$mail_sent
            ];

            $logs = [];
            if (file_exists($messages_log_file)) {
                $raw = file_get_contents($messages_log_file);
                $logs = json_decode($raw, true) ?: [];
            }
            $logs[] = $log_entry;
            file_put_contents($messages_log_file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);

            // Generate a fresh CSRF token after successful submission
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $success_message = "Thank you! Your message has been transmitted successfully. Thirumalai Viswa will respond promptly.";
            
            // Clear sticky inputs on success
            $name = "";
            $email = "";
            $subject_input = "";
            $message = "";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Form — PHP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              gold: '#f59e0b',
              goldLight: '#fbbf24',
              dark: '#08080c',
              card: '#111118',
              border: 'rgba(255, 255, 255, 0.08)'
            }
          },
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', 'sans-serif'],
            display: ['"Space Grotesk"', 'sans-serif'],
            mono: ['"JetBrains Mono"', 'monospace']
          }
        }
      }
    }
  </script>
  <style>
    .glow-gold:focus {
      border-color: #f59e0b;
      box-shadow: 0 0 15px rgba(245, 158, 11, 0.25);
    }
  </style>
</head>
<body class="bg-[#08080c] text-zinc-100 min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-10 font-sans selection:bg-amber-500 selection:text-black">

  <div class="w-full max-w-2xl bg-[#111118] border border-white/10 rounded-3xl p-6 sm:p-10 shadow-2xl relative overflow-hidden backdrop-blur-xl">
    
    <!-- Top Decorative Glow -->
    <div class="absolute -top-24 -left-24 w-48 h-48 bg-amber-500/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-amber-600/15 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Header Section -->
    <div class="relative z-10 mb-8">
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-amber-500/30 bg-amber-500/10 text-amber-400 font-mono text-xs uppercase tracking-widest mb-3">
        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
        Secure PHP Contact Portal
      </div>
      <h1 class="text-2xl sm:text-3xl font-display font-bold text-white tracking-tight">Send a Message</h1>
      <p class="text-zinc-400 text-sm mt-1">Fill out the fields below. Sanitized and processed securely on the server.</p>
    </div>

    <!-- General Error Banner -->
    <?php if (!empty($errors['general'])): ?>
      <div class="mb-6 p-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 text-sm font-mono flex items-start gap-3">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span><?= htmlspecialchars($errors['general']) ?></span>
      </div>
    <?php endif; ?>

    <!-- Success Message Banner -->
    <?php if (!empty($success_message)): ?>
      <div class="mb-6 p-4 rounded-xl border border-emerald-500/40 bg-emerald-500/10 text-emerald-300 text-sm font-mono flex items-start gap-3">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <span><?= htmlspecialchars($success_message) ?></span>
      </div>
    <?php endif; ?>

    <!-- HTML Form -->
    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" class="space-y-5 relative z-10" novalidate>
      
      <!-- CSRF Token (Hidden) -->
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <!-- Honeypot anti-spam (Hidden from users) -->
      <div class="hidden" aria-hidden="true">
        <input type="text" name="botcheck" tabindex="-1" autocomplete="off">
      </div>

      <!-- Name & Email (Two Column Grid) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        
        <!-- Name Field -->
        <div>
          <label for="name" class="block text-xs font-mono uppercase tracking-wider text-zinc-300 mb-2">
            Your Name <span class="text-amber-400">*</span>
          </label>
          <input 
            type="text" 
            id="name" 
            name="name" 
            value="<?= htmlspecialchars($name) ?>"
            placeholder="John Doe"
            class="w-full bg-black/40 border <?= isset($errors['name']) ? 'border-red-500' : 'border-white/10' ?> rounded-xl px-4 py-3 text-white placeholder-zinc-500 text-sm outline-none transition-all glow-gold"
            required
          >
          <?php if (isset($errors['name'])): ?>
            <p class="text-red-400 text-xs mt-1.5 font-mono"><?= htmlspecialchars($errors['name']) ?></p>
          <?php endif; ?>
        </div>

        <!-- Email Field -->
        <div>
          <label for="email" class="block text-xs font-mono uppercase tracking-wider text-zinc-300 mb-2">
            Email Address <span class="text-amber-400">*</span>
          </label>
          <input 
            type="email" 
            id="email" 
            name="email" 
            value="<?= htmlspecialchars($email) ?>"
            placeholder="john@example.com"
            class="w-full bg-black/40 border <?= isset($errors['email']) ? 'border-red-500' : 'border-white/10' ?> rounded-xl px-4 py-3 text-white placeholder-zinc-500 text-sm outline-none transition-all glow-gold"
            required
          >
          <?php if (isset($errors['email'])): ?>
            <p class="text-red-400 text-xs mt-1.5 font-mono"><?= htmlspecialchars($errors['email']) ?></p>
          <?php endif; ?>
        </div>

      </div>

      <!-- Subject Field -->
      <div>
        <label for="subject" class="block text-xs font-mono uppercase tracking-wider text-zinc-300 mb-2">
          Subject <span class="text-amber-400">*</span>
        </label>
        <input 
          type="text" 
          id="subject" 
          name="subject" 
          value="<?= htmlspecialchars($subject_input) ?>"
          placeholder="Project collaboration, query, or discussion"
          class="w-full bg-black/40 border <?= isset($errors['subject']) ? 'border-red-500' : 'border-white/10' ?> rounded-xl px-4 py-3 text-white placeholder-zinc-500 text-sm outline-none transition-all glow-gold"
          required
        >
        <?php if (isset($errors['subject'])): ?>
          <p class="text-red-400 text-xs mt-1.5 font-mono"><?= htmlspecialchars($errors['subject']) ?></p>
        <?php endif; ?>
      </div>

      <!-- Message Field -->
      <div>
        <label for="message" class="block text-xs font-mono uppercase tracking-wider text-zinc-300 mb-2">
          Message <span class="text-amber-400">*</span>
        </label>
        <textarea 
          id="message" 
          name="message" 
          rows="4"
          placeholder="Describe your inquiry, project scope, or message here..."
          class="w-full bg-black/40 border <?= isset($errors['message']) ? 'border-red-500' : 'border-white/10' ?> rounded-xl px-4 py-3 text-white placeholder-zinc-500 text-sm outline-none transition-all glow-gold resize-none"
          required
        ><?= htmlspecialchars($message) ?></textarea>
        <?php if (isset($errors['message'])): ?>
          <p class="text-red-400 text-xs mt-1.5 font-mono"><?= htmlspecialchars($errors['message']) ?></p>
        <?php endif; ?>
      </div>

      <!-- Submit Button -->
      <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-4">
        <button 
          type="submit" 
          class="w-full sm:w-auto px-8 py-3.5 rounded-full font-mono text-xs font-bold uppercase tracking-widest bg-gradient-to-r from-amber-500 to-amber-600 text-black hover:from-amber-400 hover:to-amber-500 transition-all duration-300 shadow-lg shadow-amber-500/20 flex items-center justify-center gap-2 cursor-pointer"
        >
          <span>Submit Form</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
        </button>

        <a href="index.html" class="text-xs font-mono text-zinc-400 hover:text-amber-400 transition-colors">
          ← Return to Portfolio
        </a>
      </div>

    </form>
  </div>

</body>
</html>
