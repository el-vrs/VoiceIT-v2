<?php
session_start();

// Database connection details 
$conn = new mysqli("voiceit-mysql-alc-verse0.e.aivencloud.com", "avnadmin", "AVNS_5DUZvHNyRl6Ou_Tb5Bf", "voiceit", 10458);
$conn->ssl_set(NULL, NULL, __DIR__ . "/ca.pem", NULL, NULL);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_message = "";
$success_message = "";
$show_form = false;
$token = $_GET['token'] ?? null;
$user_email = null;

//TOKEN VALIDATION (On page load) ---
if ($token) {
    // Check if token exists and is not expired
    $sql = "SELECT email, token_expiry FROM users WHERE reset_token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $expiry_time = strtotime($user['token_expiry']);
        $current_time = time();

        if ($current_time < $expiry_time) {
            // Token is valid and not expired - show the form
            $show_form = true;
            $user_email = $user['email'];
        } else {
            $error_message = "Password reset link has expired. Please request a new one.";
        }
    } else {
        $error_message = "Invalid or already used password reset token.";
    }
    $stmt->close();
} else {
    $error_message = "Access denied. No reset token provided.";
}

// PASSWORD UPDATE (On form submission) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password_submit'])) {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    $submitted_token = trim($_POST['token']);

   
   

    if (empty($new_password) || empty($confirm_password)) {
        $error_message = "❌ Both password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "❌ Passwords do not match.";
    } else {
        // Hash the new password
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update the password and clear the token and expiry time
        $sql_update = "UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE reset_token = ?";
        $stmt_update = $conn->prepare($sql_update);
        
        if (!$stmt_update) {
            $error_message = "❌ Database Prepare Error: " . $conn->error;
        } else {
            $stmt_update->bind_param("ss", $hashedPassword, $submitted_token);

            if ($stmt_update->execute()) {
                // Check if any rows were actually affected
                if ($stmt_update->affected_rows === 1) { // ✅ Use === 1 for strict success
                    $success_message = "Your password has been successfully reset. You can now log in.";
                    $show_form = false; // Hide the form after success
                } else {
                    // This error is now the *only* way a failed update is handled.
                    $error_message = "❌ Password reset failed: Token was not found during the final update (token likely expired or already used).";
                }
            } else {
                // Database execution error
                $error_message = "❌ Database Execution Error: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
      
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Candal&family=Rammetto+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/register.css"> 
    <title>VoiceIT | Reset Password</title>
</head>
<body>
    <div class="container reset-mode" id="container">
        
        <div class="welcome-section">
            <h1>VoiceIT Security</h1>
            <p>Your security is our priority. Please set a strong, new password.</p>
        </div>
        
        <div class="form-box">
            <div class="form-content">
                <h2>Reset Password</h2>
                
                <?php if ($error_message): ?>
                    <div class="message-box error-message">❌ <?= htmlspecialchars($error_message) ?></div>
                <?php elseif ($success_message): ?>
                    <div class="message-box success-message">✅ <?= htmlspecialchars($success_message) ?></div>
                    <form action="registerlogin.php" method="get">
                    <button type="submit" class="submit-btn">Click here to return to the Login Page</button>
                    </form>
                <?php endif; ?>

                <?php if ($show_form): ?>
                    <form method="POST">
                        <div class="form-group">
                            <label for="new-password">New Password</label>
                            <input type="password" id="new-password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm-password">Confirm Password</label>
                            <input type="password" id="confirm-password" name="confirm_password" required>
                        </div>
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        
                        <button type="submit" name="reset_password_submit" class="submit-btn">Set New Password</button>
                    </form>
                <?php endif; ?>
                
                <?php if (!$show_form && !$success_message): // If token is invalid/missing, show link back ?>
                    <p>
                        <p>
                            <button type="button" class="submit-btn" onclick="window.location.href='registerlogin.php'">
                            Return to Login/Registration
                            </button>
                            </p>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>