<?php
session_start();
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── DB connections using role-based users ─────────────────
function studentConn() {
    $c = new mysqli("voiceit-mysql-alc-verse0.e.aivencloud.com", "avnadmin", "AVNS_5DUZvHNyRl6Ou_Tb5Bf", "voiceit", 10458);
    $c->ssl_set(NULL, NULL, __DIR__ . '/ca.pem', NULL, NULL);
    return $c;
}

function adminConn() {
    $c = new mysqli("voiceit-mysql-alc-verse0.e.aivencloud.com", "avnadmin", "AVNS_5DUZvHNyRl6Ou_Tb5Bf", "defaultdb", 10458);
    $c->ssl_set(NULL, NULL, __DIR__ . '/ca.pem', NULL, NULL);
    return $c;
}

function sendResetEmail($email, $token) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'voiceitsystem@gmail.com';
        $mail->Password   = 'tynapwekdceblrvy';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('voiceitsystem@gmail.com', 'VoiceIT Support');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'VoiceIT Password Reset Request';
        $mail->Body    = "<h2>Password Reset Request</h2><p>Click below to reset your password:</p>
                          <a href='http://{$_SERVER['HTTP_HOST']}/voiceit/reset_password.php?token=$token'>Reset Password</a>
                          <p>This link expires in 1 hour.</p>";
        $mail->AltBody = "Reset your password: http://{$_SERVER['HTTP_HOST']}/voiceit/reset_password.php?token=$token";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action_type'] ?? '';

    // ── INSERT INTO users (registration) ─────────────────
    if ($action === 'register') {
        $username       = trim($_POST['username']);
        $password       = trim($_POST['password']);
        $email          = trim($_POST['email']);
        $name           = trim($_POST['name']);
        $student_number = trim($_POST['student-number']);

        if (!$username || !$password || !$email || !$name || !$student_number) {
            echo "❌ All fields are required."; exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "❌ Invalid email format."; exit;
        }

        $conn = studentConn();
        // SELECT to check for duplicates
        $check = $conn->prepare("SELECT id FROM users WHERE username=? OR email=? OR student_number=?");
        $check->bind_param("sss", $username, $email, $student_number);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo "❌ Account already exists."; $conn->close(); exit;
        }
        $check->close();

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        // INSERT INTO users
        $insert = $conn->prepare("INSERT INTO users (student_number, username, password, email, full_name) VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("sssss", $student_number, $username, $hashed, $email, $name);
        echo $insert->execute() ? "✅ Registration successful." : "❌ Error: " . $insert->error;
        $insert->close();
        $conn->close();
        exit;
    }

    // ── SELECT for login (student or admin) ───────────────
    if ($action === 'student_login') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        // Check admins first (SELECT FROM admins)
        $aconn = adminConn();
        $stmt  = $aconn->prepare("SELECT * FROM admins WHERE username=?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($admin = $stmt->get_result()->fetch_assoc()) {
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_name'] = $admin['username'];
                echo "✅ ADMIN";
            } else {
                echo "❌ Invalid password.";
            }
            $stmt->close(); $aconn->close(); exit;
        }
        $stmt->close(); $aconn->close();

        // SELECT FROM users (student login)
        $sconn = studentConn();
        $stmt2 = $sconn->prepare("SELECT * FROM users WHERE username=?");
        $stmt2->bind_param("s", $username);
        $stmt2->execute();
        if ($user = $stmt2->get_result()->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['student_number'] = $user['student_number'];
                $_SESSION['username']       = $user['username'];
                $_SESSION['full_name']      = $user['full_name'];
                echo "✅ STUDENT";
            } else {
                echo "❌ Invalid password.";
            }
        } else {
            echo "❌ No account found.";
        }
        $stmt2->close(); $sconn->close();
        exit;
    }

    // ── UPDATE users SET reset_token (forgot password) ───
    if ($action === 'forgot_password') {
        $email = trim($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "❌ Invalid email."; exit;
        }
        $conn = adminConn();
        // SELECT to verify email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            echo "✅ If account exists, reset link sent.";
            $stmt->close(); $conn->close(); exit;
        }
        $stmt->close();

        $token  = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", time() + 3600);
        // UPDATE users SET reset_token, token_expiry
        $upd = $conn->prepare("UPDATE users SET reset_token=?, token_expiry=? WHERE email=?");
        $upd->bind_param("sss", $token, $expiry, $email);
        echo ($upd->execute() && sendResetEmail($email, $token))
            ? "✅ Reset link sent. Check your inbox."
            : "❌ Failed to send reset link.";
        $upd->close(); $conn->close();
        exit;
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Candal&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/register.css?v=2">
  <title>VoiceIT | Register</title>
</head>
<body>
  <div class="container" id="container">

    <div class="form-box" id="form-box">
      <div class="form-content" id="form-content">
        <div id="message-container"></div>

        <div id="register-form">
          <h2>Create Account</h2>
          <p>Fill in to register</p>
          <form id="reg-form" method="POST">
            <input type="hidden" name="action_type" value="register">
            <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Name</label><input type="text" name="name" required></div>
            <div class="form-group"><label>Student Number</label><input type="text" name="student-number" required></div>
            <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button type="submit" class="submit-btn">Create Account</button>
          </form>
        </div>

        <div id="login-form" style="display:none;">
          <h2>Welcome Back</h2>
          <form id="log-form" method="POST">
            <input type="hidden" name="action_type" value="student_login">
            <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button type="submit" class="submit-btn">Log In</button>
            <p class="forgot-link" onclick="showForgotPasswordForm()">Forgot Password?</p>
          </form>
        </div>

        <div id="forgot-password-form" style="display:none;">
          <h2>Reset Password</h2>
          <p>Enter your email to receive a password reset link.</p>
          <form id="forgot-form" method="POST">
            <input type="hidden" name="action_type" value="forgot_password">
            <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
            <button type="submit" class="submit-btn">Send Reset Link</button>
            <p class="forgot-link" onclick="showLoginForm()">Back to Login</p>
          </form>
        </div>

        <div id="admin-login-form" style="display:none;">
          <h2>Admin Login</h2>
          <p>Enter your admin credentials</p>
          <form id="admin-form" method="POST">
            <input type="hidden" name="action_type" value="admin_login">
            <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button type="submit" class="submit-btn">Login as Admin</button>
            <p class="forgot-link" onclick="showLoginForm()">Back to User Login</p>
          </form>
        </div>
      </div>
    </div>

    <div class="welcome-section" id="welcome-section">
      <h1>Welcome to VoiceIT!</h1>
      <p>Speak up and be heard! Join the movement to shape a better school experience.</p>
      <button class="toggle-btn" id="toggle-btn" onclick="toggleMode()">Already have an account? Log In</button>
    </div>

  </div>

  <script>
    const container = document.getElementById('container');
    const toggleBtn = document.getElementById('toggle-btn');
    let isLoginMode = false;

    function displayMessage(message, type) {
      const mc = document.getElementById('message-container');
      const div = document.createElement('div');
      div.className = `message ${type}-message`;
      div.textContent = message.replace(/(\u2705|\u274C)/g, '').trim();
      mc.innerHTML = '';
      mc.appendChild(div);
    }

    function restartAnimations() {
      const fc = document.getElementById('form-content');
      const clone = fc.cloneNode(true);
      fc.parentNode.replaceChild(clone, fc);
      attachListeners();
    }

    function showOnly(id) {
      ['register-form','login-form','forgot-password-form','admin-login-form'].forEach(d => {
        document.getElementById(d).style.display = d === id ? 'block' : 'none';
      });
    }

    function attachListeners() {
      document.getElementById('reg-form').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('registerlogin.php', { method: 'POST', body: new FormData(this) })
          .then(r => r.text()).then(text => {
            displayMessage(text, text.includes("✅") ? 'success' : 'error');
            if (text.includes("✅")) this.reset();
          });
      });

      document.getElementById('log-form').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('registerlogin.php', { method: 'POST', body: new FormData(this) })
          .then(r => r.text()).then(text => {
            if (text.includes("✅ ADMIN")) {
              displayMessage("Login successful. Redirecting...", 'success');
              setTimeout(() => { window.location.href = '/voiceit/admindashboard.php'; }, 1500);
            } else if (text.includes("✅ STUDENT")) {
              displayMessage("Login successful. Welcome!", 'success');
              setTimeout(() => { window.location.href = '/voiceit/userdashboard.php'; }, 1500);
            } else {
              displayMessage(text, 'error');
            }
          });
      });

      document.getElementById('forgot-form').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('registerlogin.php', { method: 'POST', body: new FormData(this) })
          .then(r => r.text()).then(text => {
            displayMessage(text, text.includes("✅") ? 'success' : 'error');
            if (text.includes("✅")) this.reset();
          });
      });

      document.getElementById('admin-form').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('registerlogin.php', { method: 'POST', body: new FormData(this) })
          .then(r => r.text()).then(text => {
            displayMessage(text, text.includes("✅") ? 'success' : 'error');
            if (text.includes("✅")) setTimeout(() => { window.location.href = '/voiceit/admindashboard.php'; }, 1500);
          });
      });
    }

    attachListeners();

    function toggleMode() {
      isLoginMode = !isLoginMode;
      if (isLoginMode) {
        container.classList.add('login-mode');
        toggleBtn.textContent = 'Need an account? Register';
        setTimeout(() => { restartAnimations(); showOnly('login-form'); }, 300);
      } else {
        container.classList.remove('login-mode');
        toggleBtn.textContent = 'Already have an account? Log In';
        setTimeout(() => { restartAnimations(); showOnly('register-form'); }, 300);
      }
    }

    function showForgotPasswordForm() {
      container.classList.add('login-mode');
      setTimeout(() => { restartAnimations(); showOnly('forgot-password-form'); }, 300);
    }

    function showLoginForm() {
      container.classList.add('login-mode');
      setTimeout(() => { restartAnimations(); showOnly('login-form'); }, 300);
    }

    function showAdminLoginForm() {
      container.classList.add('login-mode');
      setTimeout(() => { restartAnimations(); showOnly('admin-login-form'); }, 300);
    }
  </script>
</body>
</html>