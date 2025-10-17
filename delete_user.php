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

if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID is required.']);
    exit;
}

$user_id = intval($_POST['user_id']);

try {
    // Delete the user
    $stmt = $connection->prepare("DELETE FROM tbl_user WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        // ✅ Log the Kanri delete action
        if (isset($_SESSION['sRole']) && $_SESSION['sRole'] === 'Kanri' && isset($_SESSION['sUserID'])) {
            logAdminAction(
                $connection,
                $_SESSION['sUserID'],                              // Kanri ID
                "Deleted an account (User ID: {$user_id})",    // Action description
                'user',                                        // Target type
                $user_id                                       // Target ID
            );
        }
        echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user.']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$connection->close();
?>