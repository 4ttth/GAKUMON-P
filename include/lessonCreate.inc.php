<?php
// include/lessonCreate.inc.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

// 1) SECURITY: require login
if (!isset($_SESSION['user_id']) && empty($_SESSION['sUser'])) {

  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'NOT_AUTHENTICATED']);
  exit;
}

// 2) DB CONNECT
require_once __DIR__ . '/../config/config.php';
$connection->set_charset('utf8mb4');

// 🔒 Hard-require the helper so functions exist
$__lim = __DIR__ . DIRECTORY_SEPARATOR . 'creationLimits.inc.php';
if (!is_file($__lim)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'SERVER_MISCONFIG: creationLimits.inc.php not found']);
    exit;
}
require_once $__lim;

// now safe to call:
$authorId = cm_resolve_user_id($connection);
$gate     = cm_check_creation_limit($connection, $authorId, 'lesson', 2);
if (!$gate['allowed']) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $gate['reason']]);
    exit;
}

// 3) Resolve author_id from session (handles your two-session-field pattern)
$authorId = null;
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
  $authorId = (int) $_SESSION['user_id'];
} elseif (!empty($_SESSION['sUser'])) {
  $u = $connection->prepare("SELECT user_id FROM tbl_user WHERE username = ? LIMIT 1");
  $u->bind_param("s", $_SESSION['sUser']);
  $u->execute();
  $res = $u->get_result();
  if ($res && ($row = $res->fetch_assoc())) $authorId = (int) $row['user_id'];
  $u->close();
}
if ($authorId === null) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'NO_AUTHOR']);
  exit;
}

// 4) Read form data (FormData post)
$title          = trim($_POST['title'] ?? '');
$short_desc     = trim($_POST['short_desc'] ?? '');
$long_desc      = trim($_POST['long_desc'] ?? '');
$duration       = trim($_POST['duration'] ?? '00:10:00'); // "HH:MM:SS"
$difficulty     = trim($_POST['difficulty'] ?? 'Beginner'); // must match enum
$is_private     = isset($_POST['is_private']) ? (int)!!$_POST['is_private'] : 0;

// Topic: either topic_id or custom_topic; prefer numeric topic_id
// topic resolution
$topic_id    = isset($_POST['topic_id']) && ctype_digit($_POST['topic_id']) ? (int)$_POST['topic_id'] : null;
$custom_topic= trim($_POST['custom_topic'] ?? '');

// Objectives: posted as JSON array of strings
$objectivesJson = $_POST['objectives'] ?? '[]';
$objectives     = json_decode($objectivesJson, true);
if (!is_array($objectives)) $objectives = [];

// basic validation
if ($title === '' || $short_desc === '' || $long_desc === '' || $duration === '' || $difficulty === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'MISSING_FIELDS']);
  exit;
}

// 5) If custom topic provided, create or reuse existing one and return its id
if (!$topic_id) {
  if ($custom_topic === '') { http_response_code(400); echo json_encode(['ok'=>false,'err'=>'Missing topic']); exit; }
  // upsert/find by name
  $stmt = $connection->prepare("SELECT topic_id FROM tbl_topic WHERE topic_name=? LIMIT 1");
  $stmt->bind_param("s", $custom_topic);
  $stmt->execute();
  $stmt->bind_result($found_id);
  if ($stmt->fetch()) { $topic_id = (int)$found_id; }
  $stmt->close();

  if (!$topic_id) {
    // 🔥 Added: insert with icon
    $icon = '<i class="bi bi-journals"></i>';
    $stmt = $connection->prepare("INSERT INTO tbl_topic(topic_name, topic_icon) VALUES (?, ?)");
    $stmt->bind_param("ss", $custom_topic, $icon);
    $stmt->execute();
    $topic_id = $stmt->insert_id;
    $stmt->close();
  }
}

// 6) Insert lesson
// ✅ compute first, then bind variables (no inline casts/trim in bind_param)
$title       = (string)$title;
$short_desc  = (string)$short_desc;
$long_desc   = (string)$long_desc;
$durationVal = (string)$duration;       // e.g. "00:10:00"
$authorIdVal = (int)$authorId;
$topicIdVal  = (int)$topic_id;
$difficultyV = (string)$difficulty;
$isPrivateV  = (int)$is_private;

$ins = $connection->prepare("
  INSERT INTO tbl_lesson (title, short_desc, long_desc, duration, author_id, topic_id, difficulty_level, is_private)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
//             s      s           s          s        i          i        s               i
$ins->bind_param("ssssiisi",
  $title, $short_desc, $long_desc, $durationVal, $authorIdVal, $topicIdVal, $difficultyV, $isPrivateV
);

if (!$ins->execute()) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'LESSON_INSERT_FAILED',
    'mysqli_error' => $ins->error
  ]);
  exit;
}
$lesson_id = (int)$ins->insert_id;
$ins->close();


// 7) Insert objectives (if any)
if (!empty($objectives)) {
  $obj = $connection->prepare("
    INSERT INTO tbl_lesson_objectives (lesson_id, objective_text, objective_order)
    VALUES (?, ?, ?)
  ");
  $order = 1;

  foreach ($objectives as $text) {
    $txt = trim((string)$text);
    if ($txt === '') { $order++; continue; }  // keep order consistent, but skip empty

    $lessonIdVal = (int)$lesson_id; // variable by value
    $txtVal      = (string)$txt;
    $orderVal    = (int)$order;     // ✅ use a temp variable instead of $order++ in bind_param

    //            i   s    i
    $obj->bind_param("isi", $lessonIdVal, $txtVal, $orderVal);

    if (!$obj->execute()) {
      // You can choose to abort here, or continue
      // For debugging:
      // error_log("Objective insert failed: " . $obj->error);
    }

    $order++; // increment AFTER binding/executing
  }
  $obj->close();
}


// 8) (Optional) Files: if you post real uploads, move them and record in tbl_lesson_files
// foreach (['Notes' => 'notes_files', 'Video' => 'video_files'] as $type => $field) {
//   if (!empty($_FILES[$field]['name'][0])) {
//     $dir = __DIR__ . "/../uploads/$type";
//     if (!is_dir($dir)) @mkdir($dir, 0777, true);

//     for ($i = 0; $i < count($_FILES[$field]['name']); $i++) {
//       if ($_FILES[$field]['error'][$i] !== UPLOAD_ERR_OK) continue;

//       $base = basename($_FILES[$field]['name'][$i]);
//       $safe = preg_replace('/[^\w.-]/', '_', $base);
//       $dest = $dir . '/' . uniqid() . '_' . $safe;

//       if (move_uploaded_file($_FILES[$field]['tmp_name'][$i], $dest)) {
//         $relPath = "uploads/$type/" . basename($dest);  // ✅ variable, not expression in bind_param

//         $f = $connection->prepare("INSERT INTO tbl_lesson_files (lesson_id, file_type, file_url) VALUES (?, ?, ?)");
//         $lessonIdVal = (int)$lesson_id;
//         $fileTypeVal = (string)$type;
//         $fileUrlVal  = (string)$relPath;

//         $f->bind_param("iss", $lessonIdVal, $fileTypeVal, $fileUrlVal);
//         $f->execute();
//         $f->close();
//       }
//     }
//   }
// }

// ───────────────────────────────────────────────
// Lesson files: sort to IMG/Notes or IMG/Videos
// file_type must be 'Notes' or 'Video' (per enum)
// file_url must be 'IMG/Notes/...' or 'IMG/Videos/...'
// ───────────────────────────────────────────────

$IMG_EXT   = ['jpg','jpeg','png','gif','webp','bmp','tif','tiff','svg'];
$VIDEO_EXT = ['mp4','mov','mkv','avi','webm','m4v'];
$DOC_EXT   = ['pdf','doc','docx','ppt','pptx','xls','xlsx','txt','rtf','odt','ods','odp'];

function normalize_filename(string $name): string {
  $name = preg_replace('/[^\w.\- ]+/', '_', $name);
  $name = preg_replace('/\s+/', ' ', $name);
  return trim($name);
}

function ensure_dir(string $abs): void {
  if (!is_dir($abs)) @mkdir($abs, 0777, true);
}

function move_and_record_upload(
  array $file, int $i, int $lesson_id, mysqli $connection,
  array $IMG_EXT, array $VIDEO_EXT, array $DOC_EXT
): void {
  if (($file['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return;

  $orig = $file['name'][$i] ?? '';
  $tmp  = $file['tmp_name'][$i] ?? '';
  if ($orig === '' || $tmp === '' || !is_uploaded_file($tmp)) return;

  $base = normalize_filename(basename($orig));
  $ext  = strtolower(pathinfo($base, PATHINFO_EXTENSION));

  // Decide final folder + enum value
  if (in_array($ext, $VIDEO_EXT, true)) {
    $subFolder = 'Videos';   // plural (matches DB samples)
    $fileType  = 'Video';    // enum value
  } else {
    // docs + images both stored as Notes in DB (schema has only Notes/Video)
    $subFolder = 'Notes';
    $fileType  = 'Notes';
  }

  // Project root (…/GAKUMON), then IMG/<subFolder>
  $root   = realpath(__DIR__ . '/');   // C:\xampp\htdocs\GAKUMON
  $imgDir = $root . DIRECTORY_SEPARATOR . 'IMG';
  ensure_dir($imgDir);

  $absDir = $imgDir . DIRECTORY_SEPARATOR . $subFolder; // IMG/Notes or IMG/Videos
  ensure_dir($absDir);

  // Avoid overwrites
  $targetName = $base;
  $nameNoExt  = pathinfo($base, PATHINFO_FILENAME);
  $n = 1;
  while (file_exists($absDir . DIRECTORY_SEPARATOR . $targetName)) {
    $targetName = $nameNoExt . '-' . $n . ($ext ? "." . $ext : '');
    $n++;
  }

  $absPath = $absDir . DIRECTORY_SEPARATOR . $targetName;
  $relPath = 'IMG/' . $subFolder . '/' . $targetName;   // ← what we store in DB

  if (move_uploaded_file($tmp, $absPath)) {
    // Insert into tbl_lesson_files (lesson_id, file_type, file_url)
    $lessonIdVal = (int)$lesson_id;
    $fileTypeVal = (string)$fileType;    // 'Notes' or 'Video'
    $fileUrlVal  = (string)$relPath;     // 'IMG/Notes/...' or 'IMG/Videos/...'

    $insF = $connection->prepare(
      "INSERT INTO tbl_lesson_files (lesson_id, file_type, file_url) VALUES (?, ?, ?)"
    );
    $insF->bind_param("iss", $lessonIdVal, $fileTypeVal, $fileUrlVal);
    $insF->execute();
    $insF->close();
  }
}

// Accept these input names; add yours here if different
$fields = ['attachments', 'img_files', 'video_files', 'notes_files'];
foreach ($fields as $field) {
  if (!empty($_FILES[$field]['name']) && is_array($_FILES[$field]['name'])) {
    for ($i = 0; $i < count($_FILES[$field]['name']); $i++) {
      move_and_record_upload($_FILES[$field], $i, $lesson_id, $connection, $IMG_EXT, $VIDEO_EXT, $DOC_EXT);
    }
  }
}


// 9) Return the newly created lesson (minimal)
echo json_encode([
  'ok' => true,
  'lesson' => [
    'lesson_id'  => $lesson_id,
    'title'      => $title,
    'short_desc' => $short_desc,
    'long_desc'  => $long_desc,
    'duration'   => $duration,
    'difficulty' => $difficulty,
    'topic_id'   => $topic_id,
    'author_id'  => $authorId
  ]
]);
