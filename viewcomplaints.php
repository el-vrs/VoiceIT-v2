<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: registerlogin.php");
    exit();
}

// Role-based DB user (voiceit_admin_user) — falls back to root if not yet set up
$conn = new mysqli("voiceit-mysql-alc-verse0.e.aivencloud.com", "avnadmin", "AVNS_5DUZvHNyRl6Ou_Tb5Bf", "voiceit", 10458);
$conn->ssl_set(NULL, NULL, __DIR__ . "/ca.pem", NULL, NULL);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ── FILTERS ──────────────────────────────────────────────
$search          = trim($_GET['search']   ?? '');
$category_filter = $_GET['category']      ?? 'All';
$status_filter   = $_GET['status']        ?? 'All';

// ── SELECT: students with complaint counts ────────────────
$where_parts = [];
$params      = [];
$types       = '';

if ($search !== '') {
    $like = '%' . $search . '%';
    $where_parts[] = "(users.student_number LIKE ? OR users.full_name LIKE ? OR users.email LIKE ?)";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}

$where_sql = count($where_parts) ? 'HAVING ' . implode(' AND ', $where_parts) : '';

// Note: HAVING used here because search applies to user fields in aggregate query
$sql_students = "SELECT
    users.student_number,
    users.full_name,
    users.email,
    COUNT(complaints.complaint_id)                                        AS total_complaints,
    SUM(CASE WHEN complaints.status = 'Submitted' THEN 1 ELSE 0 END)     AS submitted_count,
    SUM(CASE WHEN complaints.status = 'Pending'   THEN 1 ELSE 0 END)     AS pending_count,
    SUM(CASE WHEN complaints.status = 'Resolved'  THEN 1 ELSE 0 END)     AS resolved_count,
    SUM(CASE WHEN complaints.status = 'Declined'  THEN 1 ELSE 0 END)     AS declined_count
  FROM users
  LEFT JOIN complaints ON users.student_number = complaints.student_number
  GROUP BY users.student_number, users.full_name, users.email
  HAVING total_complaints > 0
  ORDER BY total_complaints DESC";

// Apply search as a post-filter in PHP (simpler with HAVING on aliased cols)
$result_students = $conn->query($sql_students);
$students = [];
while ($s = $result_students->fetch_assoc()) {
    if ($search !== '') {
        $hay = strtolower($s['student_number'] . ' ' . $s['full_name'] . ' ' . $s['email']);
        if (strpos($hay, strtolower($search)) === false) continue;
    }
    $students[] = $s;
}

// ── SELECT: detail for chosen student ────────────────────
$selected_student    = null;
$student_complaints  = [];

if (isset($_GET['student_number'])) {
    $snum = $_GET['student_number'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE student_number = ?");
    $stmt->bind_param("s", $snum);
    $stmt->execute();
    $selected_student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // SELECT complaints with optional filters + LEFT JOIN resolution_proof
    $c_where = ["c.student_number = ?"];
    $c_params = [$snum];
    $c_types  = "s";

    if ($status_filter !== 'All') {
        $c_where[]  = "c.status = ?";
        $c_params[] = $status_filter;
        $c_types   .= "s";
    }
    if ($category_filter !== 'All') {
        $c_where[]  = "c.category = ?";
        $c_params[] = $category_filter;
        $c_types   .= "s";
    }

    $c_where_sql = 'WHERE ' . implode(' AND ', $c_where);

    $sql_c = "SELECT c.*, rp.proof_image, rp.remarks AS proof_remarks
              FROM complaints c
              LEFT JOIN resolution_proof rp ON c.complaint_id = rp.complaint_id
              $c_where_sql
              ORDER BY c.created_at DESC";

    $stmt2 = $conn->prepare($sql_c);
    $stmt2->bind_param($c_types, ...$c_params);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) $student_complaints[] = $row;
    $stmt2->close();
}

// ── COUNT by category (SELECT COUNT GROUP BY) ─────────────
$cat_counts = ['Academic' => 0, 'Non-Academic' => 0];
$cat_res = $conn->query("SELECT category, COUNT(*) as c FROM complaints GROUP BY category");
while ($r = $cat_res->fetch_assoc()) $cat_counts[$r['category']] = (int)$r['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VoiceIT | View Complaints</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Candal&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/viewcomplaints.css?v=3">
</head>
<body>
  <nav class="sidebar">
    <div class="logo"><img src="visuals/sidelogo.gif" alt="VoiceIT Logo"></div>
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
    <ul class="nav-icons">
      <li class="<?= ($currentPage=='admindashboard.php') ? 'active' : '' ?>">
        <a href="admindashboard.php"><i class="fas fa-house"></i><span>Home</span></a>
      </li>
      <li class="<?= ($currentPage=='viewcomplaints.php') ? 'active' : '' ?>">
        <a href="viewcomplaints.php"><i class="fas fa-eye"></i><span>View</span></a>
      </li>
      <li class="<?= ($currentPage=='managecomplaints.php') ? 'active' : '' ?>">
        <a href="managecomplaints.php"><i class="fas fa-folder-open"></i><span>Manage</span></a>
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

  <main class="dashboard">

    <!-- PAGE HEADER -->
    <header class="page-header">
      <h2 class="page-title">STUDENT COMPLAINT HISTORY</h2>
      <div class="header-right">
        <!-- Category count pills (SELECT COUNT GROUP BY) -->
        <span class="cat-pill academic">
          <i class="fas fa-graduation-cap"></i> Academic: <?= $cat_counts['Academic'] ?>
        </span>
        <span class="cat-pill nonacademic">
          <i class="fas fa-building"></i> Non-Academic: <?= $cat_counts['Non-Academic'] ?>
        </span>
      </div>
    </header>

    <!-- SEARCH BAR (triggers SELECT with LIKE) -->
    <div class="search-row">
      <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="Search by student number, name, or email…"
               value="<?= htmlspecialchars($search) ?>"
               onkeydown="if(event.key==='Enter') applySearch()">
        <button onclick="applySearch()" class="search-btn">Search</button>
      </div>
    </div>

    <!-- STUDENT TABLE -->
    <div class="students-table">
      <table>
        <thead>
          <tr>
            <th>Student No.</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Total</th>
            <th>Status Breakdown</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($students)): ?>
            <tr><td colspan="6" class="empty-row">No students found.</td></tr>
          <?php else: ?>
            <?php foreach ($students as $s): ?>
              <tr>
                <td><?= htmlspecialchars($s['student_number']) ?></td>
                <td><strong><?= htmlspecialchars($s['full_name']) ?></strong></td>
                <td><?= htmlspecialchars($s['email']) ?></td>
                <td><strong><?= $s['total_complaints'] ?></strong></td>
                <td>
                  <?php if ($s['submitted_count'] > 0): ?>
                    <span class="status-badge badge-submitted"><?= $s['submitted_count'] ?> Submitted</span>
                  <?php endif; ?>
                  <?php if ($s['pending_count'] > 0): ?>
                    <span class="status-badge badge-pending"><?= $s['pending_count'] ?> Pending</span>
                  <?php endif; ?>
                  <?php if ($s['resolved_count'] > 0): ?>
                    <span class="status-badge badge-resolved"><?= $s['resolved_count'] ?> Resolved</span>
                  <?php endif; ?>
                  <?php if ($s['declined_count'] > 0): ?>
                    <span class="status-badge badge-declined"><?= $s['declined_count'] ?> Declined</span>
                  <?php endif; ?>
                  <?php if ($s['total_complaints'] == 0): ?>
                    <span style="color:#ccc;font-size:0.78em;">No complaints</span>
                  <?php endif; ?>
                </td>
                <td>
                  <button class="view-btn"
                    onclick="window.location.href='viewcomplaints.php?student_number=<?= urlencode($s['student_number']) ?>'">
                    <i class="fas fa-eye"></i> View
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <!-- STUDENT COMPLAINT DETAIL MODAL -->
  <?php if ($selected_student): ?>
  <div id="studentModal" class="modal" style="display:block;">
    <div class="modal-content wide">
      <button class="close" onclick="window.location.href='viewcomplaints.php<?= $search ? '?search='.urlencode($search) : '' ?>'">
        <i class="fas fa-times"></i>
      </button>

      <div class="student-header">
        <div class="student-avatar"><?= strtoupper(substr($selected_student['full_name'], 0, 2)) ?></div>
        <div>
          <h2><?= htmlspecialchars($selected_student['full_name']) ?></h2>
          <p><?= htmlspecialchars($selected_student['student_number']) ?> &middot; <?= htmlspecialchars($selected_student['email']) ?></p>
          <p><?= count($student_complaints) ?> complaint(s) shown</p>
        </div>
      </div>

      <!-- Filters inside modal (SELECT with WHERE) -->
      <div class="modal-filters">
        <div class="search-wrap small">
          <i class="fas fa-filter"></i>
          <select id="modalStatus" onchange="applyModalFilters()">
            <option value="All"       <?= $status_filter==='All'       ? 'selected':'' ?>>All Statuses</option>
            <option value="Submitted" <?= $status_filter==='Submitted' ? 'selected':'' ?>>Submitted</option>
            <option value="Pending"   <?= $status_filter==='Pending'   ? 'selected':'' ?>>Pending</option>
            <option value="Resolved"  <?= $status_filter==='Resolved'  ? 'selected':'' ?>>Resolved</option>
            <option value="Declined"  <?= $status_filter==='Declined'  ? 'selected':'' ?>>Declined</option>
          </select>
          <select id="modalCategory" onchange="applyModalFilters()">
            <option value="All"          <?= $category_filter==='All'          ? 'selected':'' ?>>All Categories</option>
            <option value="Academic"     <?= $category_filter==='Academic'     ? 'selected':'' ?>>Academic</option>
            <option value="Non-Academic" <?= $category_filter==='Non-Academic' ? 'selected':'' ?>>Non-Academic</option>
          </select>
        </div>
      </div>

      <!-- Complaint list (SELECT result) -->
      <?php if (empty($student_complaints)): ?>
        <div class="no-complaints">No complaints match the selected filters.</div>
      <?php else: ?>
        <?php foreach ($student_complaints as $c): ?>
          <?php $sc = strtolower(trim($c['status'])); ?>
          <div class="complaint-item">
            <div class="complaint-item-top">
              <h4><?= htmlspecialchars($c['subject']) ?></h4>
              <span class="status-badge badge-<?= $sc ?>"><?= strtoupper($c['status']) ?></span>
            </div>
            <div class="complaint-meta">
              <span>ID #<?= $c['complaint_id'] ?></span>
              <span><?= htmlspecialchars($c['category']) ?></span>
              <span><?= date('M d, Y', strtotime($c['created_at'])) ?></span>
            </div>
            <p class="complaint-desc"><?= htmlspecialchars($c['description']) ?></p>
            <?php if (!empty($c['student_suggestion'])): ?>
              <div class="complaint-block suggestion">
                <strong>Student's Suggestion:</strong> <?= htmlspecialchars($c['student_suggestion']) ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($c['solution'])): ?>
              <div class="complaint-block solution">
                <strong>Admin Response:</strong> <?= htmlspecialchars($c['solution']) ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($c['proof_image'])): ?>
              <div class="complaint-block proof">
                <strong>Resolution Proof:</strong>
                <a href="<?= htmlspecialchars($c['proof_image']) ?>" target="_blank" class="evidence-link">
                  <i class="fas fa-image"></i> View Proof
                </a>
                <?php if (!empty($c['proof_remarks'])): ?>
                  <span style="color:#666;font-size:0.78em;margin-left:8px;"><?= htmlspecialchars($c['proof_remarks']) ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($c['evidence_file'])): ?>
              <div class="complaint-block evidence">
                <strong>Student Evidence:</strong>
                <a href="<?= htmlspecialchars($c['evidence_file']) ?>" target="_blank" class="evidence-link">
                  <i class="fas fa-paperclip"></i> View File
                </a>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <script>
  function applySearch() {
    const s = document.getElementById('searchInput').value;
    window.location.href = 'viewcomplaints.php?search=' + encodeURIComponent(s);
  }

  function applyModalFilters() {
    const snum     = new URLSearchParams(window.location.search).get('student_number');
    const status   = document.getElementById('modalStatus').value;
    const category = document.getElementById('modalCategory').value;
    window.location.href = `viewcomplaints.php?student_number=${encodeURIComponent(snum)}&status=${status}&category=${category}`;
  }
  </script>
  <script src="logout.js"></script>
</body>
</html>
<?php $conn->close(); ?>