<?php

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0'); // log errors instead of printing to output

session_start();
require_once 'config/config.php';


// Check if user is logged in
if (!isset($_SESSION['sUser'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

set_error_handler(function($sev, $msg, $file, $line){
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => $msg, 'file' => $file, 'line' => $line]);
  exit;
});

// Get and validate form data
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : null;
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : null;
$username = isset($_POST['username']) ? trim($_POST['username']) : null;
$email = isset($_POST['email_address']) ? trim($_POST['email_address']) : null;
$role = isset($_POST['role']) ? trim($_POST['role']) : null;

// Validate required fields
if (!$user_id || !$first_name || !$last_name || !$username || !$email || !$role) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

// Validate role
$allowed_roles = ['Gakusei', 'Gakusensei', 'Kanri'];
if (!in_array($role, $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
    exit;
}

try {
    // Check if username already exists for another user
    $check_stmt = $connection->prepare("SELECT user_id FROM tbl_user WHERE username = ? AND user_id != ?");
    $check_stmt->bind_param("si", $username, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists.']);
        exit;
    }
    $check_stmt->close();
    
    // Check if email already exists for another user
    $check_stmt = $connection->prepare("SELECT user_id FROM tbl_user WHERE email_address = ? AND user_id != ?");
    $check_stmt->bind_param("si", $email, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists.']);
        exit;
    }
    $check_stmt->close();
    
    // Update user information
    $update_stmt = $connection->prepare("UPDATE tbl_user SET first_name = ?, last_name = ?, username = ?, email_address = ?, role = ? WHERE user_id = ?");
    $update_stmt->bind_param("sssssi", $first_name, $last_name, $username, $email, $role, $user_id);
    
    if ($update_stmt->execute()) {
        // ✅ Log the Kanri edit action
        if (isset($_SESSION['sRole']) && $_SESSION['sRole'] === 'Kanri' && isset($_SESSION['sUserID'])) {
            logAdminAction(
                $connection,
                $_SESSION['sUserID'],                              // Kanri ID
                "Edited an account (User ID: {$user_id})",     // Action description
                'user',                                        // Target type
                $user_id                                       // Target ID
            );
        }
        echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user.']);
    }
    
    $update_stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$connection->close();
?>