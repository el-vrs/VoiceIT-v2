<?php
session_start();
if (!isset($_SESSION['student_number'])) {
    header("Location: registerlogin.php");
    exit();
}
$student_number = $_SESSION['student_number'];

$conn = new mysqli("voiceit-mysql-alc-verse0.e.aivencloud.com", "avnadmin", "AVNS_5DUZvHNyRl6Ou_Tb5Bf", "voiceit", 10458);
$conn->ssl_set(NULL, NULL, __DIR__ . '/ca.pem', NULL, NULL);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VoiceIT | Reports</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Candal&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reports.css?v=3">
</head>
<body>

  <!-- SIDEBAR -->
  <nav class="sidebar">
    <div class="logo">
      <img src="visuals/navlogo.gif" alt="VoiceIT">
    </div>
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
    <ul class="nav-icons">
      <li class="<?= $currentPage == 'userdashboard.php' ? 'active' : '' ?>">
        <a href="userdashboard.php"><i class="fas fa-house"></i><span>Home</span></a>
      </li>
      <li class="<?= $currentPage == 'history.php' ? 'active' : '' ?>">
        <a href="history.php"><i class="fas fa-clock-rotate-left"></i><span>History</span></a>
      </li>
      <li class="<?= $currentPage == 'complaint.php' ? 'active' : '' ?>">
        <a href="complaint.php"><i class="fas fa-file-lines"></i><span>Report</span></a>
      </li>
      <li class="<?= $currentPage == 'reports.php' ? 'active' : '' ?>">
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
  <main class="reports-page">
    <header class="reports-header">
      <h1>My Reports</h1>
      <div class="multi-sort-filter">
        <div class="filter-container">
          <div id="selectedFilters" class="selected-tags"></div>
          <select id="filterOptions">
            <option value="" disabled selected>Filter...</option>
            <optgroup label="Category">
              <option value="Academic">Academic</option>
              <option value="Non-Academic">Non-Academic</option>
            </optgroup>
            <optgroup label="Status">
              <option value="Submitted">Submitted</option>
              <option value="Pending">Pending</option>
              <option value="Decline">Decline</option>
              <option value="Resolved">Resolved</option>
            </optgroup>
          </select>
        </div>
      </div>
    </header>

    <section id="reportList" class="report-list">
      <?php
      $sql = "SELECT c.complaint_id, c.subject, c.description, c.status, c.category,
                     c.solution, c.student_suggestion, c.created_at, c.evidence_file,
                     rp.proof_image, rp.remarks
              FROM complaints c
              LEFT JOIN resolution_proof rp ON c.complaint_id = rp.complaint_id
              WHERE c.student_number = ?
              ORDER BY c.created_at DESC";

      $stmt = $conn->prepare($sql);
      $stmt->bind_param("s", $student_number);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows === 0) {
        echo '<div class="empty-message">No reports submitted yet.</div>';
      } else {
        while ($report = $result->fetch_assoc()) {
          $statusLower = strtolower($report['status']);
          $badgeClass = 'badge-' . $statusLower;
          echo '<div class="report-card"
                     data-category="' . htmlspecialchars($report['category']) . '"
                     data-status="' . htmlspecialchars($report['status']) . '"
                     data-subject="' . htmlspecialchars($report['subject']) . '"
                     data-description="' . htmlspecialchars($report['description']) . '"
                     data-solution="' . htmlspecialchars($report['solution'] ?? '') . '"
                     data-suggestion="' . htmlspecialchars($report['student_suggestion'] ?? '') . '"
                     data-date="' . date("F j, Y", strtotime($report['created_at'])) . '"
                     data-evidence="' . htmlspecialchars($report['evidence_file'] ?? '') . '"
                     data-proof="' . htmlspecialchars($report['proof_image'] ?? '') . '"
                     data-remarks="' . htmlspecialchars($report['remarks'] ?? '') . '">';
          echo '<h3>' . htmlspecialchars($report['subject']) . '</h3>';
          echo '<div class="report-card-meta">';
          echo '<span class="status-badge-inline ' . $badgeClass . '">' . htmlspecialchars($report['status']) . '</span>';
          echo '<p>' . htmlspecialchars($report['category']) . '</p>';
          echo '<p>' . date("M j, Y", strtotime($report['created_at'])) . '</p>';
          echo '</div>';
          echo '</div>';
        }
      }
      $stmt->close();
      $conn->close();
      ?>
    </section>

    <!-- MODAL -->
    <div id="reportModal" class="modal">
      <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h2 id="modalSubject" style="font-family:'Candal',sans-serif;font-size:1.05em;color:var(--text);border-bottom:2px solid var(--yellow);padding-bottom:10px;margin-bottom:16px;padding-right:28px;"></h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
          <span id="modalStatusBadge" style="font-size:0.76em;font-weight:700;padding:3px 12px;border-radius:20px;"></span>
          <span id="modalCategory" style="font-size:0.76em;color:#666;padding:3px 10px;background:#f0f0f0;border-radius:20px;font-family:'Inter',sans-serif;"></span>
          <span id="modalDate" style="font-size:0.76em;color:#aaa;padding:3px 0;font-family:'Inter',sans-serif;"></span>
        </div>
        <p style="font-size:0.82em;font-weight:600;color:#444;margin-bottom:5px;font-family:'Inter',sans-serif;">Description</p>
        <p id="modalDescription" style="font-size:0.83em;background:#f7f7f7;padding:12px 14px;border-radius:8px;line-height:1.7;margin-bottom:12px;color:#333;font-family:'Inter',sans-serif;"></p>
        <div id="modalSuggestion"></div>
        <div id="modalSolution"></div>
        <div id="modalProof"></div>
        <div id="modalEvidence" class="evidence-preview"></div>
      </div>
    </div>
  </main>

  <script>
  const filterOptions = document.getElementById('filterOptions');
  const selectedFilters = document.getElementById('selectedFilters');
  const reportList = document.getElementById('reportList');
  const modal = document.getElementById('reportModal');
  const closeBtn = document.querySelector('.close-btn');

  let activeFilters = { category: null, status: null };

  const statusColors = {
    'Submitted': 'background:#dbeafe;color:#1e40af',
    'Pending':   'background:#fef3c7;color:#92400e',
    'Resolved':  'background:#d1fae5;color:#065f46',
    'Decline':   'background:#fee2e2;color:#991b1b',
    'Declined':  'background:#fee2e2;color:#991b1b',
  };

  document.querySelectorAll('.report-card').forEach(card => {
    card.addEventListener('click', function() {
      document.getElementById('modalSubject').textContent = this.dataset.subject;
      document.getElementById('modalDescription').textContent = this.dataset.description;
      document.getElementById('modalCategory').textContent = this.dataset.category;
      document.getElementById('modalDate').textContent = this.dataset.date;

      const badge = document.getElementById('modalStatusBadge');
      badge.textContent = this.dataset.status;
      badge.style.cssText = (statusColors[this.dataset.status] || 'background:#f0f0f0;color:#333') + ';font-size:0.76em;font-weight:700;padding:3px 12px;border-radius:20px;font-family:Inter,sans-serif;';

      document.getElementById('modalSuggestion').innerHTML = this.dataset.suggestion
        ? `<div style="font-size:0.83em;padding:10px 14px;background:#fffbe6;border-left:3px solid #f5c400;border-radius:7px;margin-bottom:10px;line-height:1.6;font-family:'Inter',sans-serif;"><strong>Your Suggestion:</strong> ${this.dataset.suggestion}</div>`
        : '';

      document.getElementById('modalSolution').innerHTML = this.dataset.solution
        ? `<div style="font-size:0.83em;padding:10px 14px;background:#f0fdf4;border-left:3px solid #10b981;border-radius:7px;margin-bottom:10px;line-height:1.6;font-family:'Inter',sans-serif;"><strong>Admin Response:</strong> ${this.dataset.solution}</div>`
        : '';

      const proof = this.dataset.proof;
      const remarks = this.dataset.remarks;
      document.getElementById('modalProof').innerHTML = proof
        ? `<p style="font-size:0.82em;font-weight:600;margin-bottom:6px;font-family:'Inter',sans-serif;">Resolution Proof:</p>
           <img src="/voiceit/${proof}" style="max-width:100%;border-radius:8px;margin-bottom:6px;border:1px solid #e0e0e0;">
           ${remarks ? `<p style="font-size:0.79em;color:#666;font-style:italic;font-family:'Inter',sans-serif;">${remarks}</p>` : ''}`
        : '';

      const evidence = this.dataset.evidence;
      document.getElementById('modalEvidence').innerHTML = evidence
        ? (evidence.match(/\.(jpg|jpeg|png|gif)$/i)
            ? `<p style="font-size:0.82em;font-weight:600;margin-bottom:6px;margin-top:10px;font-family:'Inter',sans-serif;">Your Evidence:</p><img src="/voiceit/${evidence}" style="max-width:100%;border-radius:8px;border:1px solid #e0e0e0;">`
            : `<a href="/voiceit/${evidence}" target="_blank" style="font-size:0.83em;color:#1a4a7a;font-weight:600;font-family:'Inter',sans-serif;">View Attached File ↗</a>`)
        : `<p style="font-size:0.79em;color:#aaa;font-style:italic;margin-top:8px;font-family:'Inter',sans-serif;">No evidence attached</p>`;

      modal.style.display = 'flex';
    });
  });

  filterOptions.addEventListener('change', () => {
    const value = filterOptions.value;
    if (value === 'Academic' || value === 'Non-Academic') {
      activeFilters.category = value;
    } else {
      activeFilters.status = value;
    }
    renderTags();
    applyFilters();
    filterOptions.selectedIndex = 0;
  });

  function renderTags() {
    selectedFilters.innerHTML = '';
    Object.entries(activeFilters).forEach(([type, value]) => {
      if (value) {
        const tag = document.createElement('div');
        tag.className = 'tag';
        tag.innerHTML = `${value} <button data-type="${type}">&times;</button>`;
        selectedFilters.appendChild(tag);
      }
    });
    document.querySelectorAll('.tag button').forEach(btn => {
      btn.addEventListener('click', () => {
        activeFilters[btn.dataset.type] = null;
        renderTags();
        applyFilters();
      });
    });
  }

  function applyFilters() {
    let visible = 0;
    document.querySelectorAll('.report-card').forEach(card => {
      const ok = (!activeFilters.category || card.dataset.category === activeFilters.category)
               && (!activeFilters.status || card.dataset.status === activeFilters.status);
      card.style.display = ok ? 'block' : 'none';
      if (ok) visible++;
    });
    const existing = document.querySelector('.filter-empty-message');
    if (visible === 0) {
      if (!existing) {
        const msg = document.createElement('div');
        msg.className = 'filter-empty-message empty-message';
        msg.textContent = 'No reports match the selected filters.';
        reportList.appendChild(msg);
      }
    } else if (existing) {
      existing.remove();
    }
  }

  closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });
  window.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });
  </script>

  <script src="logout.js"></script>
</body>
</html>