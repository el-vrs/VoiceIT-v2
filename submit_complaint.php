<?php
session_start();

// Debug log (optional)
file_put_contents("debug.txt", "Form reached\n", FILE_APPEND);

// Database connection — role-based user (voiceit_student_user) with root fallback
$conn = new mysqli("voiceit-mysql-alc-verse0.e.aivencloud.com", "avnadmin", "AVNS_5DUZvHNyRl6Ou_Tb5Bf", "voiceit", 10458);
$conn->ssl_set(NULL, NULL, __DIR__ . "/ca.pem", NULL, NULL);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
    if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
}

// Ensure user is logged in
if (!isset($_SESSION['student_number'])) {
    echo "❌ You must be logged in to submit a complaint.";
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_number = $_SESSION['student_number'];
    $category = trim($_POST['category']);
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $student_suggestion = trim($_POST['solution']);
    $status = "Submitted"; // ✅ User submits with "Submitted" status
    $created_at = date("Y-m-d H:i:s");
    $evidence_file = null;

    // Validate required fields
    if (empty($category) || empty($subject) || empty($description) || empty($student_suggestion)) {
        echo "❌ All required fields must be filled out.";
        exit;
    }

    // Handle optional file upload
    if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = $_FILES['evidence']['name'];
        $file_tmp = $_FILES['evidence']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'doc'];

        if (in_array($file_ext, $allowed_extensions)) {
            $new_filename = $student_number . '_' . time() . '.' . $file_ext;
            $file_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $file_path)) {
                $evidence_file = $file_path;
            } else {
                echo "❌ Error uploading file. Check folder permissions.";
                exit;
            }
        } else {
            echo "❌ Invalid file type. Only JPG, PNG, PDF, DOCX, and DOC files are allowed.";
            exit;
        }
    }

    // Insert complaint into database
    $sql = "INSERT INTO complaints (
                student_number, category, subject, description,
                status, student_suggestion, evidence_file, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $student_number, $category, $subject, $description, $status, $student_suggestion, $evidence_file, $created_at);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header("Location: complaint.php?success=1");
        exit;
    } else {
        echo "❌ Error submitting complaint: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>