<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: registerlogin.php");
    exit();
}

// Uses role-based DB user (voiceit_admin_user) — run voiceit_roles.sql first
// Falls back to root if role user not yet set up
$conn = new mysqli("voiceit-mysql-alc-verse0.e.aivencloud.com", "avnadmin", "AVNS_5DUZvHNyRl6Ou_Tb5Bf", "voiceit", 10458);
$conn->ssl_set(NULL, NULL, __DIR__ . "/ca.pem", NULL, NULL);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$sort_filter   = $_GET['sort']     ?? 'latest';
$status_filter = $_GET['status']   ?? 'All';
$category_filter = $_GET['category'] ?? 'All';
$search        = trim($_GET['search'] ?? '');

// ── BUILD QUERY (SELECT with filters) ────────────────────
$where_parts = [];
$params      = [];
$types       = '';

if ($status_filter !== 'All') {
    $where_parts[] = "complaints.status = ?";
    $params[]      = $status_filter;
    $types        .= 's';
}
if ($category_filter !== 'All') {
    $where_parts[] = "complaints.category = ?";
    $params[]      = $category_filter;
    $types        .= 's';
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $where_parts[] = "(complaints.subject LIKE ? OR complaints.student_number LIKE ? OR users.full_name LIKE ?)";
    $params[]      = $like;
    $params[]      = $like;
    $params[]      = $like;
    $types        .= 'sss';
}

$where_sql = count($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$order_sql = match($sort_filter) {
    'status'   => "ORDER BY complaints.status ASC, complaints.created_at DESC",
    'category' => "ORDER BY complaints.category ASC, complaints.created_at DESC",
    default    => "ORDER BY complaints.created_at DESC",
};

$sql = "SELECT complaints.*, users.full_name,
               rp.proof_image, rp.remarks AS proof_remarks
        FROM complaints
        LEFT JOIN users ON complaints.student_number = users.student_number
        LEFT JOIN resolution_proof rp ON complaints.complaint_id = rp.complaint_id
        $where_sql $order_sql";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ── STATUS COUNTS for summary bar (SELECT GROUP BY) ──────
$counts = ['Submitted' => 0, 'Pending' => 0, 'Resolved' => 0, 'Declined' => 0];
$count_res = $conn->query("SELECT status, COUNT(*) as c FROM complaints GROUP BY status");
while ($r = $count_res->fetch_assoc()) {
    $s = trim($r['status']);
    if (isset($counts[$s])) $counts[$s] = (int)$r['c'];
}
$total = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VoiceIT | Manage Complaints</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Candal&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/managecomplaints.css?v=3">
</head>
<body>
  <nav class="sidebar">
    <div class="logo"><img src="visuals/sidelogo.gif" alt="VoiceIT Logo"></div>
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

  <!-- DELETE CONFIRM MODAL -->
  <div id="deleteModal" class="logout-modal" style="display:none;">
    <div class="logout-modal-content">
      <h3 id="deleteModalTitle">Confirm Delete</h3>
      <p id="deleteModalMsg">Are you sure you want to delete this complaint? This cannot be undone.</p>
      <div class="logout-buttons">
        <button id="confirmDelete" class="btn-confirm">Yes, Delete</button>
        <button id="cancelDelete" class="btn-cancel">Cancel</button>
      </div>
    </div>
  </div>

  <main class="dashboard">

    <!-- HEADER -->
    <header class="complaints-header">
      <h2>MANAGE COMPLAINTS</h2>
      <div class="header-controls">
        <!-- Search (SELECT with LIKE) -->
        <div class="search-wrap">
          <i class="fas fa-search"></i>
          <input type="text" id="searchInput" placeholder="Search subject, student…"
                 value="<?= htmlspecialchars($search) ?>" onkeydown="if(event.key==='Enter') updateFilters()">
        </div>
        <!-- Filters -->
        <div class="sort-filter">
          <label>Sort</label>
          <select id="sort" onchange="updateFilters()">
            <option value="latest"   <?= $sort_filter==='latest'   ? 'selected':'' ?>>Latest</option>
            <option value="status"   <?= $sort_filter==='status'   ? 'selected':'' ?>>Status</option>
            <option value="category" <?= $sort_filter==='category' ? 'selected':'' ?>>Category</option>
          </select>
          <label>Status</label>
          <select id="statusFilter" onchange="updateFilters()">
            <option value="All"       <?= $status_filter==='All'       ? 'selected':'' ?>>All</option>
            <option value="Submitted" <?= $status_filter==='Submitted' ? 'selected':'' ?>>Submitted</option>
            <option value="Pending"   <?= $status_filter==='Pending'   ? 'selected':'' ?>>Pending</option>
            <option value="Resolved"  <?= $status_filter==='Resolved'  ? 'selected':'' ?>>Resolved</option>
            <option value="Declined"  <?= $status_filter==='Declined'  ? 'selected':'' ?>>Declined</option>
          </select>
          <label>Category</label>
          <select id="categoryFilter" onchange="updateFilters()">
            <option value="All"          <?= $category_filter==='All'          ? 'selected':'' ?>>All</option>
            <option value="Academic"     <?= $category_filter==='Academic'     ? 'selected':'' ?>>Academic</option>
            <option value="Non-Academic" <?= $category_filter==='Non-Academic' ? 'selected':'' ?>>Non-Academic</option>
          </select>
        </div>
      </div>
    </header>

    <!-- STATUS SUMMARY BAR (SELECT COUNT GROUP BY) -->
    <div class="summary-bar">
      <div class="summary-item total">
        <span class="summary-num"><?= $total ?></span>
        <span class="summary-label">Total</span>
      </div>
      <div class="summary-item submitted" onclick="quickFilter('Submitted')">
        <span class="summary-num"><?= $counts['Submitted'] ?></span>
        <span class="summary-label">Submitted</span>
      </div>
      <div class="summary-item pending" onclick="quickFilter('Pending')">
        <span class="summary-num"><?= $counts['Pending'] ?></span>
        <span class="summary-label">Pending</span>
      </div>
      <div class="summary-item resolved" onclick="quickFilter('Resolved')">
        <span class="summary-num"><?= $counts['Resolved'] ?></span>
        <span class="summary-label">Resolved</span>
      </div>
      <div class="summary-item declined" onclick="quickFilter('Declined')">
        <span class="summary-num"><?= $counts['Declined'] ?></span>
        <span class="summary-label">Declined</span>
      </div>
    </div>

    <?php if (isset($_GET['updated'])): ?>
      <div class="flash-msg success"><i class="fas fa-check-circle"></i> Complaint updated successfully.</div>
    <?php endif; ?>

    <!-- COMPLAINT CARDS -->
    <section class="complaints-list">
      <?php if ($result->num_rows === 0): ?>
        <div class="empty-card">No complaints found.</div>
      <?php else: ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php
            $encoded   = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            $sc        = strtolower(trim($row['status']));
          ?>
          <div class="complaint-card" data-complaint='<?= $encoded ?>' onclick="openModalFromCard(this)">
            <div class="card-top">
              <div class="card-meta">
                <span class="meta-id">ID #<?= $row['complaint_id'] ?></span>
                <span class="meta-cat"><i class="fas fa-tag"></i> <?= htmlspecialchars($row['category']) ?></span>
                <span class="meta-student"><i class="fas fa-user"></i> <?= htmlspecialchars($row['full_name'] ?? $row['student_number']) ?></span>
                <span class="meta-date"><?= date('M d, Y', strtotime($row['created_at'])) ?></span>
              </div>
              <div class="card-actions" onclick="event.stopPropagation()">
                <span class="status-btn <?= $sc ?>"><?= strtoupper($row['status']) ?></span>
                <!-- Quick UPDATE status buttons -->
                <?php if ($row['status'] !== 'Pending'): ?>
                  <button class="action-btn btn-pending" title="Set Pending"
                    onclick="quickUpdate(<?= $row['complaint_id'] ?>, 'Pending')">
                    <i class="fas fa-clock"></i>
                  </button>
                <?php endif; ?>
                <!-- DELETE complaint button -->
                <button class="action-btn btn-delete" title="Delete Complaint"
                  onclick="confirmDeleteComplaint(<?= $row['complaint_id'] ?>)">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
            <p class="card-subject"><?= htmlspecialchars($row['subject']) ?></p>
            <p class="card-desc"><?= htmlspecialchars(mb_substr($row['description'], 0, 120)) ?>…</p>
          </div>
        <?php endwhile; ?>
      <?php endif; ?>
    </section>
  </main>

  <!-- COMPLAINT DETAIL / UPDATE MODAL -->
  <div id="complaintModal" class="modal">
    <div class="modal-content">
      <button class="close" onclick="closeModal()"><i class="fas fa-times"></i></button>
      <h2>Complaint Details</h2>

      <form method="POST" action="update_complaint.php" enctype="multipart/form-data" id="updateForm">
        <input type="hidden" id="complaint_id" name="complaint_id">

        <div class="detail-grid">
          <div class="detail-row"><span class="detail-label">ID</span><span id="m_id" class="detail-val"></span></div>
          <div class="detail-row"><span class="detail-label">Student No.</span><span id="m_student" class="detail-val"></span></div>
          <div class="detail-row"><span class="detail-label">Student Name</span><span id="m_student_name" class="detail-val"></span></div>
          <div class="detail-row"><span class="detail-label">Category</span><span id="m_category" class="detail-val"></span></div>
          <div class="detail-row"><span class="detail-label">Date</span><span id="m_date" class="detail-val"></span></div>
        </div>

        <div class="detail-block">
          <p class="detail-label">Subject</p>
          <p id="m_subject" class="detail-text subject-text"></p>
        </div>
        <div class="detail-block">
          <p class="detail-label">Description</p>
          <p id="m_description" class="detail-text"></p>
        </div>
        <div class="detail-block">
          <p class="detail-label">Student's Suggestion</p>
          <p id="m_student_suggestion" class="detail-text suggestion-text"></p>
        </div>
        <div class="detail-block" id="evidence_block">
          <p class="detail-label">Uploaded Evidence</p>
          <p id="m_evidence"></p>
        </div>

        <div class="form-section">
          <label class="form-label">Update Status</label>
          <!-- UPDATE status — all 4 transitions available -->
          <div class="status-buttons">
            <button type="button" class="status-pick submitted" onclick="setStatus('Submitted')">Submitted</button>
            <button type="button" class="status-pick pending"   onclick="setStatus('Pending')">Pending</button>
            <button type="button" class="status-pick resolved"  onclick="setStatus('Resolved')">Resolved</button>
            <button type="button" class="status-pick decline"  onclick="setStatus('Decline')">Decline</button>
          </div>
          <input type="hidden" name="status" id="m_status_hidden">
        </div>

        <div class="form-section">
          <label class="form-label">Admin Response / Solution</label>
          <textarea name="solution" id="m_solution" rows="4" placeholder="Write your response or resolution…"></textarea>
        </div>

        <!-- Proof upload — shown when Resolved is selected -->
        <div id="proof-upload-section" class="form-section" style="display:none;">
          <label class="form-label">Upload Resolution Proof <span class="optional">(image/pdf)</span></label>
          <input type="file" name="proof_image" accept=".jpg,.jpeg,.png,.pdf">
          <div id="existing_proof_block" style="display:none; margin-top:8px;"></div>
          <label class="form-label" style="margin-top:10px;">Remarks</label>
          <textarea name="remarks" rows="2" placeholder="Optional remarks about the proof…"></textarea>
          <!-- DELETE proof only button -->
          <button type="button" id="deleteProofBtn" class="btn-delete-proof" style="display:none;"
            onclick="confirmDeleteProof()">
            <i class="fas fa-trash"></i> Remove Existing Proof
          </button>
        </div>

        <div class="modal-actions">
          <button type="button" class="modal-close" onclick="closeModal()">Cancel</button>
          <button type="submit" class="modal-save"><i class="fas fa-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- hidden form for quick status update (Pending button on card) -->
  <form id="quickUpdateForm" method="POST" action="update_complaint.php" style="display:none;">
    <input type="hidden" name="complaint_id" id="qu_id">
    <input type="hidden" name="status"       id="qu_status">
    <input type="hidden" name="solution"     value="">
  </form>

  <script>
  let _deleteAction = null;
  let _deleteId     = null;

  // ── Open modal ────────────────────────────────────────
  function openModalFromCard(el) {
    openModal(JSON.parse(el.getAttribute('data-complaint')));
  }

  function openModal(d) {
    document.getElementById('complaint_id').value = d.complaint_id;
    document.getElementById('m_id').textContent           = d.complaint_id;
    document.getElementById('m_student').textContent      = d.student_number;
    document.getElementById('m_student_name').textContent = d.full_name || 'N/A';
    document.getElementById('m_category').textContent     = d.category;
    document.getElementById('m_date').textContent         = d.created_at;
    document.getElementById('m_subject').textContent      = d.subject;
    document.getElementById('m_description').textContent  = d.description;
    document.getElementById('m_student_suggestion').textContent = d.student_suggestion || 'No suggestion provided.';
    document.getElementById('m_solution').value           = d.solution || '';

    // Evidence
    if (d.evidence_file) {
      document.getElementById('m_evidence').innerHTML =
        `<a href="${d.evidence_file}" target="_blank" class="evidence-link"><i class="fas fa-paperclip"></i> View Evidence</a>`;
    } else {
      document.getElementById('m_evidence').textContent = 'No file uploaded.';
    }

    // Status buttons
    setStatus(d.status);

    // Proof section
    const proofSec = document.getElementById('proof-upload-section');
    const proofBlk = document.getElementById('existing_proof_block');
    const proofBtn = document.getElementById('deleteProofBtn');
    if (d.status === 'Resolved') {
      proofSec.style.display = 'block';
      if (d.proof_image) {
        proofBlk.style.display = 'block';
        proofBlk.innerHTML = `<p class="detail-label">Current Proof:</p>
          <a href="${d.proof_image}" target="_blank" class="evidence-link">
            <i class="fas fa-image"></i> View Current Proof</a>
          ${d.proof_remarks ? `<p style="font-size:0.78em;color:#666;margin-top:4px;">${d.proof_remarks}</p>` : ''}`;
        proofBtn.style.display = 'inline-flex';
      } else {
        proofBlk.style.display = 'none';
        proofBtn.style.display = 'none';
      }
    } else {
      proofSec.style.display = 'none';
    }

    document.getElementById('complaintModal').style.display = 'block';
  }

  function closeModal() {
    document.getElementById('complaintModal').style.display = 'none';
  }

  // ── Status picker ─────────────────────────────────────
  function setStatus(val) {
    document.getElementById('m_status_hidden').value = val;
    document.querySelectorAll('.status-pick').forEach(b => b.classList.remove('active'));
    const btn = document.querySelector('.status-pick.' + val.toLowerCase());
    if (btn) btn.classList.add('active');
    document.getElementById('proof-upload-section').style.display =
      val === 'Resolved' ? 'block' : 'none';
  }

  // ── Quick filter via summary bar ──────────────────────
  function quickFilter(status) {
    document.getElementById('statusFilter').value = status;
    updateFilters();
  }

  // ── Update URL filters ────────────────────────────────
  function updateFilters() {
    const sort     = document.getElementById('sort').value;
    const status   = document.getElementById('statusFilter').value;
    const category = document.getElementById('categoryFilter').value;
    const search   = document.getElementById('searchInput').value;
    window.location.href = `managecomplaints.php?sort=${sort}&status=${status}&category=${category}&search=${encodeURIComponent(search)}`;
  }

  // ── Quick UPDATE (Pending button on card) ────────────
  function quickUpdate(id, status) {
    document.getElementById('qu_id').value     = id;
    document.getElementById('qu_status').value = status;
    document.getElementById('quickUpdateForm').submit();
  }

  // ── DELETE complaint confirm ──────────────────────────
  function confirmDeleteComplaint(id) {
    _deleteAction = 'delete_complaint';
    _deleteId     = id;
    document.getElementById('deleteModalTitle').textContent = 'Delete Complaint?';
    document.getElementById('deleteModalMsg').textContent   =
      'This will permanently delete the complaint and its resolution proof. This cannot be undone.';
    document.getElementById('deleteModal').style.display = 'flex';
  }

  // ── DELETE proof only confirm ─────────────────────────
  function confirmDeleteProof() {
    const id = document.getElementById('complaint_id').value;
    _deleteAction = 'delete_proof';
    _deleteId     = id;
    document.getElementById('deleteModalTitle').textContent = 'Remove Resolution Proof?';
    document.getElementById('deleteModalMsg').textContent   =
      'This will delete the proof image and revert the complaint to Pending.';
    document.getElementById('deleteModal').style.display = 'flex';
  }

  document.getElementById('confirmDelete').addEventListener('click', function() {
    const fd = new FormData();
    fd.append('action', _deleteAction);
    fd.append('complaint_id', _deleteId);
    fetch('delete_complaint.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        document.getElementById('deleteModal').style.display = 'none';
        if (data.success) {
          closeModal();
          location.reload();
        } else {
          alert('Error: ' + data.message);
        }
      });
  });

  document.getElementById('cancelDelete').addEventListener('click', function() {
    document.getElementById('deleteModal').style.display = 'none';
  });

  window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('complaintModal')) closeModal();
    if (e.target === document.getElementById('deleteModal'))
      document.getElementById('deleteModal').style.display = 'none';
  });
  </script>
  <script src="logout.js"></script>
</body>
</html>
<?php $stmt->close(); $conn->close(); ?>