<?php
session_start();
if (!isset($_SESSION['student_number'])) {
    header("Location: registerlogin.php");
    exit();
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VoiceIT | Submit a Complaint</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Candal&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/complaint.css?v=3">
</head>
<body>

  <nav class="sidebar">
    <div class="logo">
      <img src="visuals/sidelogo.gif" alt="VoiceIT Logo">
    </div>
    <ul class="nav-icons">
      <li class="<?= ($currentPage == 'userdashboard.php') ? 'active' : '' ?>">
        <a href="userdashboard.php"><i class="fas fa-house"></i><span>Home</span></a>
      </li>
      <li class="<?= ($currentPage == 'history.php') ? 'active' : '' ?>">
        <a href="history.php"><i class="fas fa-clock-rotate-left"></i><span>History</span></a>
      </li>
      <li class="<?= ($currentPage == 'complaint.php') ? 'active' : '' ?>">
        <a href="complaint.php"><i class="fas fa-file-lines"></i><span>Report</span></a>
      </li>
      <li class="<?= ($currentPage == 'reports.php') ? 'active' : '' ?>">
        <a href="reports.php"><i class="fas fa-folder"></i><span>Files</span></a>
      </li>
      <li class="logout-item">
        <a href="#" id="logoutBtn"><i class="fas fa-sign-out"></i><span>Logout</span></a>
      </li>
    </ul>
  </nav>

  <div id="logoutModal" class="logout-modal" style="display:none;">
    <div class="logout-modal-content">
      <h3>Confirm Logout</h3>
      <p>Are you sure you want to logout from your account?</p>
      <div class="logout-buttons">
        <button id="confirmLogout" class="btn-confirm">Yes, Logout</button>
        <button id="cancelLogout" class="btn-cancel">Cancel</button>
      </div>
    </div>
  </div>

  <main class="complaint">

    <header class="page-header">
      <div class="intro">
        <h1>SUBMIT YOUR COMPLAINT</h1>
        <p>Let us know what went wrong — we're here to listen and help resolve it.</p>
      </div>
    </header>

    <?php if (isset($_GET['success'])): ?>
    <div class="success-msg">✅ Your complaint has been submitted successfully!</div>
    <?php endif; ?>

    <div class="form-columns">

      <!-- LEFT COLUMN -->
      <div class="form-left">
        <form class="complaint-form" id="complaintForm" method="POST" action="submit_complaint.php" enctype="multipart/form-data">

          <div class="form-group">
            <label>Subject</label>
            <input type="text" name="subject" placeholder="Enter subject" required>
          </div>

          <div class="form-group">
            <label>Date</label>
            <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
          </div>

          <div class="form-group">
            <label>Category</label>
            <select name="category" required>
              <option value="" disabled selected>Select category</option>
              <option value="Academic">Academic</option>
              <option value="Non-Academic">Non-Academic</option>
            </select>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea name="description" placeholder="Describe your complaint in detail..." rows="5" required></textarea>
          </div>

          <div class="form-group">
            <label>Your Suggested Solution</label>
            <textarea name="solution" placeholder="How do you think this can be resolved?" rows="4" required></textarea>
          </div>

      <!-- RIGHT COLUMN (inside form so submit works) -->
      <div class="form-right">

          <div class="form-group">
            <label>Upload Evidence <span class="optional">(Optional)</span></label>
            <input type="file" id="evidenceInput" name="evidence" accept=".jpg,.jpeg,.png,.pdf,.docx">
            <div id="filePreview" class="file-preview" style="display:none;">
              <span id="fileName"></span>
              <a id="fileView" href="#" target="_blank">View</a>
              <button type="button" id="removeFile">×</button>
            </div>
            <p class="file-hint">Accepted: JPG, PNG, PDF, DOCX</p>
          </div>

          <div class="form-group">
            <label class="checkbox-group">
              <input type="checkbox" name="terms" id="terms">
              <span>I agree to the <strong>Terms and Conditions</strong>.</span>
            </label>
            <label class="checkbox-group" style="margin-top:8px;">
              <input type="checkbox" name="agreement" id="agreement">
              <span>I confirm the uploaded file is accurate and relevant.</span>
            </label>
          </div>

          <button type="button" id="submitBtn">Submit Report</button>

      </div>
        </form>
      </div>

    </div>
  </main>

  <script>
  const submitBtn = document.getElementById('submitBtn');
  const terms = document.getElementById('terms');
  const agreement = document.getElementById('agreement');

  submitBtn.addEventListener('click', function() {
    if (!terms.checked || !agreement.checked) {
      const popup = document.createElement('div');
      popup.className = 'popup-warning';
      popup.textContent = 'Please check both confirmation boxes before submitting.';
      document.body.appendChild(popup);
      setTimeout(() => popup.remove(), 4000);
      return;
    }
    document.getElementById('complaintForm').submit();
  });

  const evidenceInput = document.getElementById('evidenceInput');
  const filePreview = document.getElementById('filePreview');
  const fileName = document.getElementById('fileName');
  const fileView = document.getElementById('fileView');
  const removeFile = document.getElementById('removeFile');

  evidenceInput.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
      fileName.textContent = file.name;
      fileView.href = URL.createObjectURL(file);
      filePreview.style.display = 'flex';
    } else {
      filePreview.style.display = 'none';
    }
  });

  removeFile.addEventListener('click', function() {
    evidenceInput.value = '';
    filePreview.style.display = 'none';
    fileView.href = '#';
    fileName.textContent = '';
  });
  </script>
  <script src="logout.js"></script>
</body>
</html>