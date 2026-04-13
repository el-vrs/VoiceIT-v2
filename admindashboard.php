<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
  header("Location: adminlogin.php");
  exit();
}

$conn = new mysqli("voiceit-mysql-alc-verse0.e.aivencloud.com", "avnadmin", "AVNS_5DUZvHNyRl6Ou_Tb5Bf", "voiceit", 10458);
$conn->ssl_set(NULL, NULL, __DIR__ . "/ca.pem", NULL, NULL);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$status_counts = [
    'submitted' => 0,
    'pending'   => 0,
    'resolved'  => 0,
    'declined'  => 0
];

$sql = "SELECT status, COUNT(*) as count FROM complaints GROUP BY status";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $status = strtolower($row['status']);
    if (strpos($status, 'submitted') !== false)                                     $status_counts['submitted'] = $row['count'];
    elseif (strpos($status, 'pending') !== false || strpos($status, 'in progress') !== false) $status_counts['pending'] += $row['count'];
    elseif (strpos($status, 'resolved') !== false)                                  $status_counts['resolved'] = $row['count'];
    elseif (strpos($status, 'declined') !== false)                                  $status_counts['declined'] = $row['count'];
}

// Recent reports — fetch full detail for modal
$sql_recent = "SELECT c.complaint_id, c.subject, c.description, c.status, c.category,
                      c.solution, c.student_suggestion, c.created_at, c.evidence_file,
                      c.student_number,
                      rp.proof_image, rp.remarks
               FROM complaints c
               LEFT JOIN resolution_proof rp ON c.complaint_id = rp.complaint_id
               ORDER BY c.created_at DESC LIMIT 8";
$result_recent = $conn->query($sql_recent);
$recent_reports = [];
while ($row = $result_recent->fetch_assoc()) {
    $recent_reports[] = $row;
}

// Total complaints
$total = array_sum($status_counts);

// Latest 5 activity (for timeline)
$sql_activity = "SELECT subject, status, created_at, updated_at FROM complaints ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 5";
$res_activity  = $conn->query($sql_activity);
$activities    = [];
while ($row = $res_activity->fetch_assoc()) $activities[] = $row;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VoiceIT | Admin Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Candal&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/admindashboard.css?v=3">
</head>
<body>
  <nav class="sidebar">
    <div class="logo">
      <img src="visuals/sidelogo.gif" alt="VoiceIT Logo">
    </div>
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
    <ul class="nav-icons">
      <li class="<?= ($currentPage == 'admindashboard.php') ? 'active' : '' ?>">
        <a href="admindashboard.php"><i class="fas fa-house"></i><span>Home</span></a>
      </li>
      <li class="<?= ($currentPage == 'viewcomplaints.php') ? 'active' : '' ?>">
        <a href="viewcomplaints.php"><i class="fas fa-eye"></i><span>View</span></a>
      </li>
      <li class="<?= ($currentPage == 'managecomplaints.php') ? 'active' : '' ?>">
        <a href="managecomplaints.php"><i class="fas fa-folder-open"></i><span>Manage</span></a>
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

  <!-- REPORT DETAIL MODAL -->
  <div id="reportModal" style="display:none; position:fixed; z-index:2000; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); overflow:hidden;">
    <div style="background:#fff; margin:4% auto; padding:30px; border-radius:14px; width:55%; max-width:640px; position:relative; box-shadow:0 8px 32px rgba(0,0,0,0.2); max-height:88vh; overflow-y:auto; font-family:'Inter',sans-serif;">
      <span onclick="closeReportModal()" style="position:absolute;top:14px;right:18px;font-size:24px;cursor:pointer;color:#aaa;font-weight:bold;">&times;</span>
      <h2 id="rm_subject" style="font-family:'Candal',sans-serif;font-size:1.1em;color:#1e1e1e;border-bottom:2px solid #f5c400;padding-bottom:10px;margin-bottom:14px;padding-right:30px;"></h2>
      <p style="font-size:0.84em;margin-bottom:6px;"><strong>Status:</strong> <span id="rm_status"></span></p>
      <p style="font-size:0.84em;margin-bottom:6px;"><strong>Category:</strong> <span id="rm_category"></span></p>
      <p style="font-size:0.84em;margin-bottom:6px;"><strong>Student No.:</strong> <span id="rm_student"></span></p>
      <p style="font-size:0.84em;margin-bottom:12px;"><strong>Date Submitted:</strong> <span id="rm_date"></span></p>
      <p style="font-size:0.84em;font-weight:600;margin-bottom:6px;">Description:</p>
      <p id="rm_description" style="font-size:0.83em;background:#f5f5f5;padding:10px 14px;border-radius:7px;line-height:1.6;margin-bottom:10px;"></p>
      <div id="rm_suggestion"></div>
      <div id="rm_solution"></div>
      <div id="rm_proof"></div>
      <div id="rm_evidence"></div>
    </div>
  </div>

  <main class="dashboard">
    <!-- TOP ROW: greeting + stat cards -->
    <div class="top-row">
      <div class="greeting">
        <h2>Hello, Admin!</h2>
        <p>Welcome back. Here's a real-time snapshot of all complaint activity on the platform.</p>
      </div>
      <div class="stat-cards">
        <div class="stat-card">
          <i class="fas fa-clipboard-list"></i>
          <div>
            <div class="stat-num"><?= $total ?></div>
            <div class="stat-label">Total Reports</div>
          </div>
        </div>
        <div class="stat-card submitted">
          <i class="fas fa-clipboard-check"></i>
          <div>
            <div class="stat-num"><?= $status_counts['submitted'] ?></div>
            <div class="stat-label">Submitted</div>
          </div>
        </div>
        <div class="stat-card pending">
          <i class="fas fa-clock"></i>
          <div>
            <div class="stat-num"><?= $status_counts['pending'] ?></div>
            <div class="stat-label">Pending</div>
          </div>
        </div>
        <div class="stat-card resolved">
          <i class="fas fa-check-circle"></i>
          <div>
            <div class="stat-num"><?= $status_counts['resolved'] ?></div>
            <div class="stat-label">Resolved</div>
          </div>
        </div>
        <div class="stat-card declined">
          <i class="fas fa-thumbs-down"></i>
          <div>
            <div class="stat-num"><?= $status_counts['declined'] ?></div>
            <div class="stat-label">Declined</div>
          </div>
        </div>
      </div>
    </div>

    <!-- MAIN CONTENT -->
    <section class="content">

      <!-- LEFT: Recent Reports -->
      <div class="left-panel">
        <h3 class="panel-title">Recent Reports</h3>
        <div id="report-list">
          <?php if (empty($recent_reports)): ?>
            <div class="report-item no-click">No reports yet.</div>
          <?php else: ?>
            <?php foreach ($recent_reports as $report): ?>
              <?php
                $encoded = htmlspecialchars(json_encode($report), ENT_QUOTES);
                $sc = strtolower($report['status']);
              ?>
              <div class="report-item" onclick='openReportModal(<?= $encoded ?>)'>
                <div class="report-item-info">
                  <p class="report-subject"><?= htmlspecialchars($report['subject']) ?></p>
                  <p class="report-meta">
                    <span><?= htmlspecialchars($report['student_number']) ?></span>
                    &middot;
                    <span><?= date("M j, Y", strtotime($report['created_at'])) ?></span>
                  </p>
                </div>
                <span class="status-label <?= $sc ?>"><?= strtoupper($report['status']) ?></span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- RIGHT: Donut chart + Activity feed -->
      <div class="right-panel">

        <!-- Donut chart -->
        <div class="chart-card">
          <h3 class="panel-title">Overview</h3>
          <div class="donut-wrap">
            <canvas id="donutChart" width="180" height="180"></canvas>
            <div class="donut-center">
              <span class="donut-total"><?= $total ?></span>
              <span class="donut-sub">Total</span>
            </div>
          </div>
          <div class="donut-legend">
            <span class="leg submitted"><i></i>Submitted</span>
            <span class="leg pending"><i></i>Pending</span>
            <span class="leg resolved"><i></i>Resolved</span>
            <span class="leg declined"><i></i>Declined</span>
          </div>
        </div>

        <!-- Activity timeline -->
        <div class="activity-card">
          <h3 class="panel-title">Recent Activity</h3>
          <ul class="activity-list">
            <?php if (empty($activities)): ?>
              <li class="activity-empty">No activity yet.</li>
            <?php else: ?>
              <?php foreach ($activities as $act): ?>
                <?php
                  $sc = strtolower($act['status']);
                  $icons = ['submitted'=>'fa-clipboard-check','pending'=>'fa-clock','resolved'=>'fa-check-circle','declined'=>'fa-thumbs-down'];
                  $icon  = $icons[$sc] ?? 'fa-bell';
                  $time  = $act['updated_at'] ?? $act['created_at'];
                ?>
                <li class="activity-item <?= $sc ?>">
                  <span class="act-dot"><i class="fas <?= $icon ?>"></i></span>
                  <div class="act-body">
                    <p class="act-subject"><?= htmlspecialchars($act['subject']) ?></p>
                    <p class="act-time"><?= date("M j, Y g:i A", strtotime($time)) ?> &middot; <strong><?= htmlspecialchars($act['status']) ?></strong></p>
                  </div>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>

      </div>
    </section>
  </main>

  <script>
  // ── Donut chart (pure canvas, no library needed) ──
  (function(){
    const canvas = document.getElementById('donutChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const data = [
      { value: <?= $status_counts['submitted'] ?>, color: '#3b82f6' },
      { value: <?= $status_counts['pending'] ?>,   color: '#f59e0b' },
      { value: <?= $status_counts['resolved'] ?>,  color: '#10b981' },
      { value: <?= $status_counts['declined'] ?>,  color: '#ef4444' }
    ];
    const total = data.reduce((s, d) => s + d.value, 0);
    const cx = 90, cy = 90, r = 72, ir = 48;
    let start = -Math.PI / 2;
    if (total === 0) {
      ctx.beginPath(); ctx.arc(cx, cy, r, 0, 2*Math.PI);
      ctx.arc(cx, cy, ir, 0, 2*Math.PI, true);
      ctx.fillStyle = '#e8e8e8'; ctx.fill('evenodd');
    } else {
      data.forEach(d => {
        if (!d.value) return;
        const sweep = (d.value / total) * 2 * Math.PI;
        ctx.beginPath();
        ctx.arc(cx, cy, r, start, start + sweep);
        ctx.arc(cx, cy, ir, start + sweep, start, true);
        ctx.closePath(); ctx.fillStyle = d.color; ctx.fill();
        start += sweep;
      });
    }
  })();

  // ── Report modal ──
  function openReportModal(data) {
    document.getElementById('rm_subject').textContent  = data.subject;
    document.getElementById('rm_status').textContent   = data.status;
    document.getElementById('rm_category').textContent = data.category || '—';
    document.getElementById('rm_student').textContent  = data.student_number || '—';
    document.getElementById('rm_date').textContent     = data.created_at;
    document.getElementById('rm_description').textContent = data.description || '';

    document.getElementById('rm_suggestion').innerHTML = data.student_suggestion
      ? `<p style="font-size:0.83em;padding:10px 14px;background:#fffbe6;border-left:3px solid #f5c400;border-radius:6px;margin-bottom:10px;"><strong>Student Suggestion:</strong> ${data.student_suggestion}</p>`
      : '';

    document.getElementById('rm_solution').innerHTML = data.solution
      ? `<p style="font-size:0.83em;padding:10px 14px;background:#f0fdf4;border-left:3px solid #10b981;border-radius:6px;margin-bottom:10px;"><strong>Admin Response:</strong> ${data.solution}</p>`
      : '';

    document.getElementById('rm_proof').innerHTML = data.proof_image
      ? `<p style="font-size:0.83em;font-weight:600;margin-bottom:6px;">Resolution Proof:</p>
         <img src="${data.proof_image}" style="max-width:100%;border-radius:8px;margin-bottom:6px;">
         ${data.remarks ? `<p style="font-size:0.8em;color:#666;font-style:italic;">${data.remarks}</p>` : ''}`
      : '';

    document.getElementById('rm_evidence').innerHTML = data.evidence_file
      ? (data.evidence_file.match(/\.(jpg|jpeg|png|gif)$/i)
          ? `<p style="font-size:0.83em;font-weight:600;margin-bottom:6px;">Student Evidence:</p><img src="${data.evidence_file}" style="max-width:100%;border-radius:8px;">`
          : `<a href="${data.evidence_file}" target="_blank" style="font-size:0.83em;">View Attached File</a>`)
      : '<p style="font-size:0.8em;color:#aaa;font-style:italic;">No evidence attached.</p>';

    document.getElementById('reportModal').style.display = 'block';
  }

  function closeReportModal() {
    document.getElementById('reportModal').style.display = 'none';
  }

  window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('reportModal')) closeReportModal();
  });
  </script>
  <script src="logout.js"></script>
</body>
</html>