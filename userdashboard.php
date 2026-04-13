<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: registerlogin.php");
    exit();
}
$username = $_SESSION['username'];
if (!isset($_SESSION['student_number'])) {
    header("Location: registerlogin.php");
    exit();
}
$student_number = $_SESSION['student_number'];

// Role-based DB user 
$conn = new mysqli("voiceit-mysql-alc-verse0.e.aivencloud.com", "avnadmin", "AVNS_5DUZvHNyRl6Ou_Tb5Bf", "defaultdb", 10458);
$conn->ssl_set(NULL, NULL, __DIR__ . '/ca.pem', NULL, NULL);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Status counts
$status_counts = ['Submitted' => 0, 'Pending' => 0, 'Resolved' => 0, 'Decline' => 0];
$sql = "SELECT status, COUNT(*) as count FROM complaints WHERE student_number = ? GROUP BY status";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_number);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $s = trim($row['status']);
    if ($s === 'Declined') $s = 'Decline';
    if (isset($status_counts[$s])) $status_counts[$s] = $row['count'];
}
$stmt->close();

// Latest notification — most recently updated complaint
$latest = null;
$sql2 = "SELECT subject, status, updated_at, created_at FROM complaints WHERE student_number = ? ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 1";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("s", $student_number);
$stmt2->execute();
$res2 = $stmt2->get_result();
if ($res2->num_rows > 0) $latest = $res2->fetch_assoc();
$stmt2->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VoiceIT | Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Candal&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/userdashboard.css?v=2">
</head>
<body>

  <!-- SIDEBAR -->
  <nav class="sidebar">
    <div class="logo">
      <img src="visuals/sidelogo.gif" alt="VoiceIT Logo">
    </div>
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
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

  <!-- LOGOUT MODAL -->
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

  <!-- MAIN -->
  <main class="dashboard">

   <!-- TOPBAR -->
    <header class="topbar">
      <div class="greeting">
        <h2>Hello, <?php echo htmlspecialchars($username); ?>!</h2>
        <p>Welcome to your dashboard. Here's a quick overview of your recent reports, their current status, and recent activity.</p>
      </div>
      <div class="notif-card">
        <?php if ($latest): ?>
          <?php
            $s = strtolower($latest['status']);
            $icons = ['submitted'=>'fa-clipboard-check','pending'=>'fa-clock','resolved'=>'fa-check-circle','decline'=>'fa-thumbs-down'];
            $icon = $icons[$s] ?? 'fa-bell';
            $time = $latest['updated_at'] ?? $latest['created_at'];
          ?>
          <p class="notif-label">Latest Notification</p>
          <div class="notif-item <?= $s ?>">
            <i class="fas <?= $icon ?>"></i>
            <div>
              <p class="notif-subject"><?= htmlspecialchars($latest['subject']) ?></p>
              <p class="notif-status">Status: <strong><?= htmlspecialchars($latest['status']) ?></strong></p>
              <p class="notif-time"><?= date("F j, Y g:i A", strtotime($time)) ?></p>
            </div>
          </div>
        <?php else: ?>
          <p class="no-notif">No activity yet.</p>
        <?php endif; ?>
      </div>
    </header>

    <!-- CONTENT -->
    <section class="content">

      <!-- RECENT REPORTS -->
      <div class="reports">
        <h3>Recent Reports</h3>
        <div id="report-list">
          <?php
          $sql = "SELECT c.complaint_id, c.subject, c.description, c.status, c.category,
                         c.solution, c.student_suggestion, c.created_at, c.evidence_file,
                         rp.proof_image, rp.remarks
                  FROM complaints c
                  LEFT JOIN resolution_proof rp ON c.complaint_id = rp.complaint_id
                  WHERE c.student_number = ?
                  ORDER BY c.created_at DESC LIMIT 5";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param("s", $student_number);
          $stmt->execute();
          $result = $stmt->get_result();

          if ($result->num_rows === 0) {
            echo '<div class="no-report">No reports submitted yet.</div>';
          } else {
            while ($report = $result->fetch_assoc()) {
              $encoded = htmlspecialchars(json_encode($report), ENT_QUOTES);
              $statusClass = strtolower($report['status']);
              echo '<div class="report-item" onclick=\'openReportModal(' . $encoded . ')\'>';
              echo '<div class="report-item-left">';
              echo '<p class="report-subject">' . htmlspecialchars($report['subject']) . '</p>';
              echo '<p class="report-date">Submitted: ' . date("F j, Y", strtotime($report['created_at'])) . '</p>';
              echo '</div>';
              echo '<span class="status-badge ' . $statusClass . '">' . htmlspecialchars($report['status']) . '</span>';
              echo '</div>';
            }
          }
          $stmt->close();
          $conn->close();
          ?>
        </div>
      </div>

      <!-- RIGHT COLUMN -->
      <div class="right-col">

        <!-- STATUS -->
        <div class="status-section">
          <img src="visuals/students.gif" alt="Status GIF" class="status-gif">
          <h3 class="status-title">STATUS</h3>
          <div class="status-grid">
            <div class="status-box">
              <i class="fas fa-clipboard-check"></i>
              <span>SUBMITTED</span>
              <div class="status-count"><?= $status_counts['Submitted'] ?></div>
            </div>
            <div class="status-box">
              <i class="fas fa-clock"></i>
              <span>PENDING</span>
              <div class="status-count"><?= $status_counts['Pending'] ?></div>
            </div>
            <div class="status-box">
              <i class="fas fa-check-circle"></i>
              <span>RESOLVED</span>
              <div class="status-count"><?= $status_counts['Resolved'] ?></div>
            </div>
            <div class="status-box">
              <i class="fas fa-thumbs-down"></i>
              <span>DECLINE</span>
              <div class="status-count"><?= $status_counts['Decline'] ?></div>
            </div>
          </div>
        </div>

      </div>
    </section>
  </main>

  <!-- REPORT DETAIL MODAL -->
  <div id="reportModal" style="display:none; position:fixed; z-index:2000; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); overflow:hidden;">
    <div style="background:#fff; margin:4% auto; padding:30px; border-radius:14px; width:55%; max-width:640px; position:relative; box-shadow:0 8px 32px rgba(0,0,0,0.2); max-height:88vh; overflow-y:auto; font-family:'Inter',sans-serif;">
      <span onclick="closeReportModal()" style="position:absolute;top:14px;right:18px;font-size:24px;cursor:pointer;color:#aaa;font-weight:bold;">&times;</span>
      <h2 id="rm_subject" style="font-family:'Candal',sans-serif;font-size:1.1em;color:#1e1e1e;border-bottom:2px solid #f5c400;padding-bottom:10px;margin-bottom:14px;padding-right:30px;"></h2>
      <p style="font-size:0.84em;margin-bottom:6px;"><strong>Status:</strong> <span id="rm_status"></span></p>
      <p style="font-size:0.84em;margin-bottom:6px;"><strong>Category:</strong> <span id="rm_category"></span></p>
      <p style="font-size:0.84em;margin-bottom:12px;"><strong>Date Submitted:</strong> <span id="rm_date"></span></p>
      <p style="font-size:0.84em;font-weight:600;margin-bottom:6px;">Description:</p>
      <p id="rm_description" style="font-size:0.83em;background:#f5f5f5;padding:10px 14px;border-radius:7px;line-height:1.6;margin-bottom:10px;"></p>
      <div id="rm_suggestion"></div>
      <div id="rm_solution"></div>
      <div id="rm_proof"></div>
      <div id="rm_evidence"></div>
    </div>
  </div>

  <script>
  function openReportModal(data) {
    document.getElementById('rm_subject').textContent = data.subject;
    document.getElementById('rm_status').textContent = data.status;
    document.getElementById('rm_category').textContent = data.category || '';
    document.getElementById('rm_date').textContent = data.created_at;
    document.getElementById('rm_description').textContent = data.description || '';

    document.getElementById('rm_suggestion').innerHTML = data.student_suggestion
      ? `<p style="font-size:0.83em;padding:10px 14px;background:#fffbe6;border-left:3px solid #f5c400;border-radius:6px;margin-bottom:10px;"><strong>Your Suggestion:</strong> ${data.student_suggestion}</p>`
      : '';

    document.getElementById('rm_solution').innerHTML = data.solution
      ? `<p style="font-size:0.83em;padding:10px 14px;background:#f0fdf4;border-left:3px solid #10b981;border-radius:6px;margin-bottom:10px;"><strong>Admin Response:</strong> ${data.solution}</p>`
      : '';

    document.getElementById('rm_proof').innerHTML = data.proof_image
      ? `<p style="font-size:0.83em;font-weight:600;margin-bottom:6px;">Admin Resolution Proof:</p>
         <img src="/voiceit/${data.proof_image}" style="max-width:100%;border-radius:8px;margin-bottom:6px;">
         ${data.remarks ? `<p style="font-size:0.8em;color:#666;font-style:italic;">${data.remarks}</p>` : ''}`
      : '';

    document.getElementById('rm_evidence').innerHTML = data.evidence_file
      ? (data.evidence_file.match(/\.(jpg|jpeg|png|gif)$/i)
          ? `<p style="font-size:0.83em;font-weight:600;margin-bottom:6px;">Your Uploaded Evidence:</p><img src="/voiceit/${data.evidence_file}" style="max-width:100%;border-radius:8px;">`
          : `<a href="/voiceit/${data.evidence_file}" target="_blank" style="font-size:0.83em;">View Attached File</a>`)
      : '<p style="font-size:0.8em;color:#aaa;font-style:italic;">No evidence attached</p>';

    document.getElementById('reportModal').style.display = 'block';
  }

  function closeReportModal() {
    document.getElementById('reportModal').style.display = 'none';
  }

  window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('reportModal')) closeReportModal();
  });

  setInterval(function() {
    fetch(window.location.href).then(r => r.text()).then(html => {
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const cur = document.querySelectorAll('.status-count');
      const nxt = doc.querySelectorAll('.status-count');
      for (let i = 0; i < cur.length; i++) {
        if (cur[i]?.textContent !== nxt[i]?.textContent) { location.reload(); return; }
      }
    }).catch(() => {});
  }, 10000);
  </script>

  <script src="logout.js"></script>
</body>
</html>