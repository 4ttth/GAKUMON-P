<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
// Start buffering immediately so any accidental output can be cleaned
ob_start();
session_start();

// Use an absolute path so includes never fail silently
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../include/helpers/admin_audit.php';  // <-- make it available

// Always return JSON; block PHP warnings from leaking into the response
header('Content-Type: application/json; charset=utf-8');

// Convert *any* PHP warning/notice into an exception we can catch and JSONify
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// 1) Try to load a dedicated helper if you have one
$helper = __DIR__ . '/../include/helper/log_admin_action.php';

// 2) Otherwise, (carefully) load from admin_ajax.php without emitting its output
if (file_exists($helper)) {
    require_once $helper;
} else {
    $maybeAdminAjax = __DIR__ . '/../admin_ajax.php';
    if (file_exists($maybeAdminAjax)) {
        ob_start();
        require_once $maybeAdminAjax;
        ob_end_clean();
    }
}

// 3) Final safety: define a compatible logger if still missing (prevents fatal)
if (!function_exists('logAdminAction')) {
    function logAdminAction($connection, $adminId, $action, $targetTypeOrDetails = '', $targetId = null) {
        try {
            // If your table has columns: admin_id, action, details, created_at
            $details = is_null($targetId) ? (string)$targetTypeOrDetails : ($targetTypeOrDetails . ' #' . $targetId);
            if ($connection && method_exists($connection, 'prepare')) {
                $stmt = $connection->prepare(
                    "INSERT INTO tbl_admin_audit_logs (admin_id, action, details, created_at)
                    VALUES (?, ?, ?, NOW())"
                );
                if ($stmt) {
                    $stmt->bind_param('iss', $adminId, $action, $details);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } catch (Throwable $e) {
            // swallow/log as needed
        }
        return true;
    }
}

try {
    // Basic request validation
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, 'Invalid request method');
    }

    $action = $_POST['action'] ?? '';
    if ($action !== 'delete_content') {
        respond(false, 'Invalid action');
    }

    // Validate ID
    $lesson_id = isset($_POST['lesson_id']) ? (int) $_POST['lesson_id'] : 0;
if ($lesson_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid lesson ID']);
    exit;
}

$connection->begin_transaction();

try {
    // 1) Quiz-related data (deepest children first)
    // 1a) Question options -> questions
    if ($stmt = $connection->prepare("
        DELETE FROM tbl_question_options
        WHERE question_id IN (
            SELECT q.question_id
            FROM tbl_questions q
            WHERE q.quiz_id IN (
                SELECT z.quiz_id FROM tbl_quizzes z WHERE z.lesson_id = ?
            )
        )
    ")) {
        $stmt->bind_param("i", $lesson_id);
        $stmt->execute();
        $stmt->close();
    }

    // 1b) User quiz attempts
    if ($stmt = $connection->prepare("
        DELETE FROM tbl_user_quiz_attempts
        WHERE quiz_id IN (SELECT z.quiz_id FROM tbl_quizzes z WHERE z.lesson_id = ?)
    ")) {
        $stmt->bind_param("i", $lesson_id);
        $stmt->execute();
        $stmt->close();
    }

    // 1c) Questions
    if ($stmt = $connection->prepare("
        DELETE FROM tbl_questions
        WHERE quiz_id IN (SELECT z.quiz_id FROM tbl_quizzes z WHERE z.lesson_id = ?)
    ")) {
        $stmt->bind_param("i", $lesson_id);
        $stmt->execute();
        $stmt->close();
    }

    // 1d) Quizzes (FK -> tbl_lesson.lesson_id)
    if ($stmt = $connection->prepare("DELETE FROM tbl_quizzes WHERE lesson_id = ?")) {
        $stmt->bind_param("i", $lesson_id);
        $stmt->execute();
        $stmt->close();
    }

    // 2) Other lesson dependents
    // 2a) Creator earnings (FK -> tbl_lesson.lesson_id)
    if ($stmt = $connection->prepare("DELETE FROM tbl_creator_earnings WHERE lesson_id = ?")) {
        $stmt->bind_param("i", $lesson_id);
        $stmt->execute();
        $stmt->close();
    }

    // 2b) Enrollments (FK -> tbl_lesson.lesson_id)
    if ($stmt = $connection->prepare("DELETE FROM tbl_user_enrollments WHERE lesson_id = ?")) {
        $stmt->bind_param("i", $lesson_id);
        $stmt->execute();
        $stmt->close();
    }

    // 2c) Lesson files (FK -> tbl_lesson.lesson_id)
    if ($stmt = $connection->prepare("DELETE FROM tbl_lesson_files WHERE lesson_id = ?")) {
        $stmt->bind_param("i", $lesson_id);
        $stmt->execute();
        $stmt->close();
    }

    // 3) Finally, delete the lesson (parent)
    $stmt = $connection->prepare("DELETE FROM tbl_lesson WHERE lesson_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare parent delete');
    }
    $stmt->bind_param("i", $lesson_id);
    $stmt->execute();
    $stmt->close();

    // Commit the cascade
    $connection->commit();

    // 4) Audit log (use target_type = 'lesson' per your enum)
    if (isset($_SESSION['sRole'], $_SESSION['sUserID']) && $_SESSION['sRole'] === 'Kanri') {
        logAdminAction(
            $connection,
            (int) $_SESSION['sUserID'],                   // Kanri admin user_id
            "Deleted a lesson (Lesson ID: {$lesson_id})",
            'lesson',                                 // <-- correct enum value
            $lesson_id
        );
    }

    echo json_encode(['success' => true, 'message' => 'Material deleted successfully']);
    exit;

} catch (Throwable $e) {
    $connection->rollback();
    echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
    exit;
}


} catch (Throwable $e) {
    // Any PHP warning/notice/exception ends up here as a clean JSON error
    respond(false, 'Server error: ' . $e->getMessage());
} finally {
    restore_error_handler();
}

/**
 * Flush *only* JSON; purge any prior output so fetch().json() never breaks.
 */
function respond(bool $success, string $message, array $extra = []): void {
    $payload = json_encode(['success' => $success, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        $payload = '{"success":false,"message":"JSON encode failure"}';
    }
    if (ob_get_length()) { ob_clean(); }          // remove stray output
    header('Cache-Control: no-store');
    header('Content-Length: ' . strlen($payload));
    echo $payload;
    exit;
}
