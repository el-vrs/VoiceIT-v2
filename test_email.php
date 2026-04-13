<?php
// Simple PHPMailer test script
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h2>PHPMailer Installation Test</h2>";

// Check if PHPMailer is loaded
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "✅ PHPMailer class found!<br><br>";
} else {
    echo "❌ PHPMailer class NOT found. Check your vendor/autoload.php path<br>";
    exit;
}

// Test email configuration
$mail = new PHPMailer(true);

try {
    // Enable verbose debug output
    $mail->SMTPDebug = 3; // Very verbose
    $mail->Debugoutput = 'html';
    
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'voiceitsystem@gmail.com'; // Your Gmail
    $mail->Password   = 'tyna pwek dceb lrvy'; // Your App Password (NO SPACES)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    // Set timeout
    $mail->Timeout = 10;
    
    // Recipients
    $mail->setFrom('voiceitsystem@gmail.com', 'VoiceIT Test');
    $mail->addAddress('voiceitsystem@gmail.com'); // Send to yourself for testing
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from VoiceIT';
    $mail->Body    = '<h1>Success!</h1><p>If you received this, PHPMailer is working correctly.</p>';
    $mail->AltBody = 'Success! If you received this, PHPMailer is working correctly.';
    
    $mail->send();
    echo '<hr><h3 style="color: green;">✅ Message sent successfully!</h3>';
    echo '<p>Check your inbox at voiceitsystem@gmail.com</p>';
    
} catch (Exception $e) {
    echo "<hr><h3 style='color: red;'>❌ Message could not be sent.</h3>";
    echo "<p><strong>Error:</strong> {$mail->ErrorInfo}</p>";
    
    // Additional diagnostics
    echo "<hr><h3>Diagnostics:</h3>";
    echo "<ul>";
    echo "<li>PHP Version: " . phpversion() . "</li>";
    echo "<li>OpenSSL enabled: " . (extension_loaded('openssl') ? 'Yes ✅' : 'No ❌') . "</li>";
    echo "<li>Socket support: " . (function_exists('fsockopen') ? 'Yes ✅' : 'No ❌') . "</li>";
    echo "</ul>";
}
?>