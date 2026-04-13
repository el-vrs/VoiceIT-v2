<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: registerlogin.php");
    exit();
}

$conn = new mysqli("voiceit-mysql-alc-verse0.e.aivencloud.com", "avnadmin", "AVNS_5DUZvHNyRl6Ou_Tb5Bf", "voiceit", 10458);
$conn->ssl_set(NULL, NULL, __DIR__ . '/ca.pem', NULL, NULL);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$complaint_id = intval($_POST['complaint_id'] ?? 0);
$status       = trim($_POST['status']       ?? '');
$solution     = trim($_POST['solution']     ?? '');
$admin_id     = $_SESSION['admin_id'];

if (!$complaint_id || !$status) {
    header("Location: managecomplaints.php?error=invalid");
    exit();
}

$allowed = ['Submitted', 'Pending', 'Resolved', 'Decline'];
if (!in_array($status, $allowed)) {
    header("Location: managecomplaints.php?error=invalid_status");
    exit();
}

// UPDATE complaints SET status, solution, updated_at
$stmt = $conn->prepare("UPDATE complaints SET status = ?, solution = ?, updated_at = NOW() WHERE complaint_id = ?");
$stmt->bind_param("ssi", $status, $solution, $complaint_id);
$stmt->execute();
$stmt->close();

// If Resolved, handle proof image upload and INSERT into resolution_proof
if ($status === 'Resolved' && isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/proof/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $ext      = pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION);
    $filename = 'proof_' . $complaint_id . '_' . time() . '.' . $ext;
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $filepath)) {
        $remarks = trim($_POST['remarks'] ?? '');

        // Check if proof already exists for this complaint (UNIQUE constraint)
        $check = $conn->prepare("SELECT proof_id FROM resolution_proof WHERE complaint_id = ?");
        $check->bind_param("i", $complaint_id);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            // UPDATE existing proof record
            $stmt2 = $conn->prepare("UPDATE resolution_proof SET proof_image = ?, remarks = ?, uploaded_at = NOW() WHERE complaint_id = ?");
            $stmt2->bind_param("ssi", $filepath, $remarks, $complaint_id);
            $stmt2->execute();
            $stmt2->close();
        } else {
            // INSERT new proof record
            $stmt2 = $conn->prepare("INSERT INTO resolution_proof (complaint_id, admin_id, proof_image, remarks) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("iiss", $complaint_id, $admin_id, $filepath, $remarks);
            $stmt2->execute();
            $stmt2->close();
        }
    }
}

$conn->close();
header("Location: managecomplaints.php?updated=1");
exit();
?>