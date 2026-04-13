<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = new mysqli("voiceit-mysql-alc-verse0.e.aivencloud.com", "avnadmin", "AVNS_5DUZvHNyRl6Ou_Tb5Bf", "defaultdb", 10458);
$conn->ssl_set(NULL, NULL, __DIR__ . '/ca.pem', NULL, NULL);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed']);
    exit();
}

$action       = $_POST['action']       ?? '';
$complaint_id = intval($_POST['complaint_id'] ?? 0);

if (!$complaint_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']);
    exit();
}

if ($action === 'delete_complaint') {
    // DELETE FROM complaints — cascades to resolution_proof automatically (FK CASCADE)
    $stmt = $conn->prepare("DELETE FROM complaints WHERE complaint_id = ?");
    $stmt->bind_param("i", $complaint_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Complaint deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $stmt->error]);
    }
    $stmt->close();

} elseif ($action === 'delete_proof') {
    // DELETE resolution proof only — complaint record stays intact
    $stmt = $conn->prepare("DELETE FROM resolution_proof WHERE complaint_id = ?");
    $stmt->bind_param("i", $complaint_id);
    if ($stmt->execute()) {
        // Revert status to Pending and clear solution so admin can re-resolve
        $stmt2 = $conn->prepare("UPDATE complaints SET solution = NULL, status = 'Pending' WHERE complaint_id = ?");
        $stmt2->bind_param("i", $complaint_id);
        $stmt2->execute();
        $stmt2->close();
        echo json_encode(['success' => true, 'message' => 'Resolution proof removed. Complaint reverted to Pending.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Delete proof failed: ' . $stmt->error]);
    }
    $stmt->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

$conn->close();
?>
