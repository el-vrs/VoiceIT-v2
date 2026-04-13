<?php
session_start();
if (!isset($_SESSION['student_number'])) {
    header("Location: registerlogin.php");
    exit();
}
$student_number = $_SESSION['student_number'];

$conn = new mysqli("voiceit-mysql-alc-verse0.e.aivencloud.com", "avnadmin", "AVNS_5DUZvHNyRl6Ou_Tb5Bf", "defaultdb", 10458);
$conn->ssl_set(NULL, NULL, __DIR__ . '/ca.pem', NULL, NULL);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$update_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_suggestion') {
    $complaint_id = intval($_POST['complaint_id'] ?? 0);
    $suggestion   = trim($_POST['student_suggestion'] ?? '');

    // Verify this complaint belongs to this student before UPDATE
    $verify = $conn->prepare("SELECT complaint_id, status FROM complaints WHERE complaint_id = ? AND student_number = ?");
    $verify->bind_param("is", $complaint_id, $student_number);
    $verify->execute();
    $cdata = $verify->get_result()->fetch_assoc();
    $verify->close();

    if ($cdata) {
        // UPDATE complaints 
        $stmt_upd = $conn->prepare("UPDATE complaints SET student_suggestion = ? WHERE complaint_id = ? AND student_number = ?");
        $stmt_upd->bind_param("sis", $suggestion, $complaint_id, $student_number);
        $stmt_upd->execute();
        $stmt_upd->close();
        $update_msg = 'success';
    } else {
        $update_msg = 'error';
    }
}

// ── SELECT status counts ──────────────────────────────────
$status_counts = ['Submitted' => 0, 'Pending' => 0, 'Resolved' => 0, 'Decline' => 0];
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM complaints WHERE student_number = ? GROUP BY status");
$stmt->bind_param("s", $student_number);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $s = trim($row['status']);
    if ($s === 'Declined') $s = 'Decline';
    if (isset($status_counts[$s])) $status_counts[$s] = $row['count'];
}
$stmt->close();

// ── SELECT all complaints with proof (LEFT JOIN) ──────────
$sql = "SELECT c.complaint_id, c.subject, c.description, c.status,
               c.student_suggestion, c.solution, c.category,
               c.evidence_file, c.created_at, c.updated_at,
               rp.proof_image, rp.remarks AS proof_remarks
        FROM complaints c
        LEFT JOIN resolution_proof rp ON c.complaint_id = rp.complaint_id
        WHERE c.student_number = ?
        ORDER BY c.created_at DESC";
$stmt2 = $conn->prepare($sql);
$stmt2->bind_param("s", $student_number);
$stmt2->execute();
$all_complaints = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VoiceIT | History</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Candal&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/history.css">
</head>
<body>

  <nav class="sidebar">
    <div class="logo"><img src="visuals/navlogo.gif" alt="VoiceIT"></div>
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
    <ul class="nav-icons">
      <li class="<?= $currentPage=='userdashboard.php' ? 'active' : '' ?>">
        <a href="userdashboard.php"><i class="fas fa-house"></i><span>Home</span></a>
      </li>
      <li class="<?= $currentPage=='history.php' ? 'active' : '' ?>">
        <a href="history.php"><i class="fas fa-clock-rotate-left"></i><span>History</span></a>
      </li>
      <li class="<?= $currentPage=='complaint.php' ? 'active' : '' ?>">
        <a href="complaint.php"><i class="fas fa-file-lines"></i><span>Report</span></a>
      </li>
      <li class="<?= $currentPage=='reports.php' ? 'active' : '' ?>">
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

  <!-- EDIT SUGGESTION MODAL -->
  <div id="editSuggestionModal" style="display:none; position:fixed; z-index:2000; inset:0; background:rgba(0,0,0,0.55); display:none; align-items:center; justify-content:center; backdrop-filter:blur(3px);">
    <div style="background:#fff; border-radius:14px; padding:28px; width:90%; max-width:500px; position:relative; box-shadow:0 16px 48px rgba(0,0,0,0.18); font-family:'Inter',sans-serif;">
      <button onclick="closeEditModal()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:18px;cursor:pointer;color:#aaa;"><i class="fas fa-times"></i></button>
      <h3 style="font-family:'Candal',sans-serif;font-size:1em;color:#1e1e1e;margin-bottom:6px;border-bottom:2px solid #f5c400;padding-bottom:10px;">Edit Your Suggestion</h3>
      <p id="edit_subject" style="font-size:0.82em;color:#666;margin:10px 0 12px;"></p>
      <form method="POST" action="history.php">
        <input type="hidden" name="action" value="edit_suggestion">
        <input type="hidden" name="complaint_id" id="edit_complaint_id">
        <label style="font-size:0.68em;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#aaa;display:block;margin-bottom:6px;">Your Suggestion</label>
        <textarea name="student_suggestion" id="edit_suggestion_text" rows="5"
          style="width:100%;padding:10px 12px;border:1.5px solid #d8d8d8;border-radius:8px;font-family:'Inter',sans-serif;font-size:0.84em;resize:vertical;outline:none;"
          placeholder="Write your suggestion for resolving this complaint…"></textarea>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
          <button type="button" onclick="closeEditModal()" style="padding:8px 20px;border:1px solid #d8d8d8;border-radius:7px;background:#e8e8e8;font-family:'Inter',sans-serif;font-size:0.82em;font-weight:700;cursor:pointer;">Cancel</button>
          <button type="submit" style="padding:8px 20px;border:none;border-radius:7px;background:#1e1e1e;color:white;font-family:'Inter',sans-serif;font-size:0.82em;font-weight:700;cursor:pointer;">Save Suggestion</button>
        </div>
      </form>
    </div>
  </div>

  <main class="main-content">
    <h2 class="history-title">History</h2>

    <?php if ($update_msg === 'success'): ?>
      <div style="background:#d1fae5;color:#065f46;padding:10px 16px;border-radius:8px;font-size:0.83em;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-check-circle"></i> Your suggestion has been updated.
      </div>
    <?php elseif ($update_msg === 'error'): ?>
      <div style="background:#fee2e2;color:#991b1b;padding:10px 16px;border-radius:8px;font-size:0.83em;margin-bottom:14px;">
        Could not update suggestion. Please try again.
      </div>
    <?php endif; ?>

    <!-- STATUS COUNTS -->
    <div class="status-section">
      <div class="status-grid">
        <div class="status-box"><i class="fas fa-clipboard-check"></i><span>Submitted</span><div class="status-count"><?= $status_counts['Submitted'] ?></div></div>
        <div class="status-box"><i class="fas fa-clock"></i><span>Pending</span><div class="status-count"><?= $status_counts['Pending'] ?></div></div>
        <div class="status-box"><i class="fas fa-circle-check"></i><span>Resolved</span><div class="status-count"><?= $status_counts['Resolved'] ?></div></div>
        <div class="status-box"><i class="fas fa-thumbs-down"></i><span>Declined</span><div class="status-count"><?= $status_counts['Decline'] ?></div></div>
      </div>
    </div>

    <!-- COMPLAINT LIST -->
    <div class="report-wrapper">
      <?php if (empty($all_complaints)): ?>
        <div class="empty-msg">No submitted reports yet.</div>
      <?php else: ?>
        <?php foreach ($all_complaints as $report): ?>
          <?php $statusClass = strtolower(str_replace(' ', '-', trim($report['status']))); ?>
          <div class="report-item">
            <div class="report-item-header">
              <h4><?= htmlspecialchars($report['subject']) ?></h4>
              <div style="display:flex;align-items:center;gap:8px;">
                <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($report['status']) ?></span>
                <!-- Edit suggestion button — only if complaint is Submitted or Pending -->
                <?php if (in_array($report['status'], ['Submitted', 'Pending'])): ?>
                  <button class="edit-suggestion-btn"
                    onclick="openEditModal(<?= $report['complaint_id'] ?>, '<?= htmlspecialchars(addslashes($report['subject'])) ?>', '<?= htmlspecialchars(addslashes($report['student_suggestion'] ?? '')) ?>')">
                    <i class="fas fa-pen"></i> Edit Suggestion
                  </button>
                <?php endif; ?>
              </div>
            </div>

            <p class="report-meta">
              <span><?= htmlspecialchars($report['category']) ?></span> &middot;
              Submitted: <?= date("M j, Y", strtotime($report['created_at'])) ?>
            </p>
            <p class="report-desc"><?= htmlspecialchars($report['description']) ?></p>

            <?php if (!empty($report['student_suggestion'])): ?>
              <div class="report-suggestion">
                <strong>Your Suggestion:</strong> <?= htmlspecialchars($report['student_suggestion']) ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($report['solution'])): ?>
              <div class="report-solution">
                <strong>Admin Response:</strong> <?= htmlspecialchars($report['solution']) ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($report['proof_image'])): ?>
              <div class="report-proof">
                <strong>Resolution Proof:</strong>
                <a href="/voiceit/<?= htmlspecialchars($report['proof_image']) ?>" target="_blank" class="proof-link">
                  <i class="fas fa-image"></i> View Proof
                </a>
                <?php if (!empty($report['proof_remarks'])): ?>
                  <span style="font-size:0.78em;color:#666;margin-left:6px;"><?= htmlspecialchars($report['proof_remarks']) ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

  <style>
  .edit-suggestion-btn {
    padding: 4px 10px; border: 1.5px solid #d8d8d8; border-radius: 6px;
    background: #fff; font-family: 'Inter', sans-serif; font-size: 0.74em;
    font-weight: 700; color: #666; cursor: pointer; display: inline-flex;
    align-items: center; gap: 5px; transition: all 0.2s;
  }
  .edit-suggestion-btn:hover { border-color: #f5c400; color: #1e1e1e; background: #fffbe6; }
  .report-proof { font-size: 0.82em; padding: 8px 12px; background: #eff6ff; border-left: 3px solid #3b82f6; margin-top: 8px; border-radius: 0 6px 6px 0; }
  .proof-link { color: #1e40af; text-decoration: underline; margin-left: 4px; }
  .logout-modal { position: fixed; z-index: 1000; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
  .logout-modal-content { background: #fff; padding: 36px; border-radius: 14px; text-align: center; max-width: 340px; width: 90%; border: 1px solid #d8d8d8; }
  .logout-modal-content h3 { font-family: 'Candal', sans-serif; font-size: 1em; margin-bottom: 8px; }
  .logout-modal-content p { font-size: 0.83em; color: #666; line-height: 1.6; margin-bottom: 24px; }
  .logout-buttons { display: flex; justify-content: center; gap: 10px; }
  .btn-confirm { padding: 9px 22px; border: none; border-radius: 7px; font-size: 0.82em; font-weight: 700; cursor: pointer; background: #ef4444; color: white; }
  .btn-cancel  { padding: 9px 22px; border: 1px solid #d8d8d8; border-radius: 7px; font-size: 0.82em; font-weight: 700; cursor: pointer; background: #e8e8e8; color: #1e1e1e; }
  </style>

  <script>
  function openEditModal(id, subject, suggestion) {
    document.getElementById('edit_complaint_id').value   = id;
    document.getElementById('edit_subject').textContent  = subject;
    document.getElementById('edit_suggestion_text').value = suggestion;
    const m = document.getElementById('editSuggestionModal');
    m.style.display = 'flex';
  }

  function closeEditModal() {
    document.getElementById('editSuggestionModal').style.display = 'none';
  }

  window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('editSuggestionModal')) closeEditModal();
  });
  </script>
  <script src="logout.js"></script>
</body>
</html>