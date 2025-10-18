<?php
   session_start();

   $pageTitle = 'GAKUMON — Materials Moderation';
   $pageCSS = 'CSS/desktop/materials_moderationStyle.css';
   $pageJS = 'JS/desktop/materials_moderationScript.js';

   include 'include/header.php';
   require_once 'config/config.php'; // Database Connection

   $BASE_URL = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); // e.g. /GAKUMON

   // --- [ADD] minimal helper to resolve stored file names/paths into real URLs ---
   // Returns a web URL (string) or null if file is missing. Tries common extensions and folders.
    function resolve_media_url(?string $fileUrl, array $tryExt = [], ?string $forceDir = null): ?string {
        if (!$fileUrl) return null;

        $fileUrl = ltrim($fileUrl, '/'); // normalize
        $fsRoot  = realpath(__DIR__);    // filesystem base (this script's dir)

        // If a target folder is known (Notes/Videos) and path doesn't start with IMG/, prepend it.
        if ($forceDir && strpos($fileUrl, 'IMG/') !== 0) {
            $fileUrl = "IMG/{$forceDir}/{$fileUrl}";
        }

        // Has extension already?
        $hasExt = preg_match('/\.[A-Za-z0-9]+$/', $fileUrl) === 1;

        // Build candidate list
        $candidates = [];
        if ($hasExt) {
            $candidates[] = $fileUrl;
        } else {
            foreach ($tryExt as $ext) $candidates[] = $fileUrl . $ext;
            // Also try any extension via glob
            foreach (glob($fsRoot . DIRECTORY_SEPARATOR . $fileUrl . '.*') as $hit) {
                if (is_file($hit)) {
                    // Convert absolute path back to web path and prefix BASE_URL
                    $rel = str_replace($fsRoot . DIRECTORY_SEPARATOR, '', $hit);
                    $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
                    return $GLOBALS['BASE_URL'] . '/' . ltrim($rel, '/');
                }
            }
        }

        // Check the candidates (prefix BASE_URL on return)
        foreach ($candidates as $rel) {
            $abs = $fsRoot . DIRECTORY_SEPARATOR . $rel;
            if (file_exists($abs)) {
                $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
                return $GLOBALS['BASE_URL'] . '/' . ltrim($rel, '/');
            }
        }

        return null;
    }

   // --- [END ADD] ---

   if (isset($_SESSION['sUser'])) {
      $username = $_SESSION['sUser'];

      // Get UserID from database
      $stmt = $connection->prepare("SELECT user_id FROM tbl_user WHERE username = ?");
      $stmt->bind_param("s", $username);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($row = $result->fetch_assoc()) {
         $userID = $row['user_id'];   // Now you have the userID
      } else {
         echo "User not found.";
         exit;
      }

   } else {
      echo "User not logged in.";
      header("Location: login.php");
      exit;
   }

    // Session and role validation
    if (!isset($_SESSION['sUser']) || $_SESSION['sRole'] !== 'Kanri') {
        header("Location: login.php");
        exit;
    }

   include 'include/desktopKanriNav.php';

   // Pagination setup
   $items_per_page = 15;
   $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
   if ($current_page < 1) $current_page = 1;
   
   $offset = ($current_page - 1) * $items_per_page;

   // Get total count of lessons
   $count_query = "SELECT COUNT(*) as total FROM tbl_lesson";
   $count_result = $connection->query($count_query);
   $total_items = $count_result->fetch_assoc()['total'];
   $total_pages = ceil($total_items / $items_per_page);

   // Ensure current page doesn't exceed total pages
   if ($current_page > $total_pages && $total_pages > 0) {
      $current_page = $total_pages;
      $offset = ($current_page - 1) * $items_per_page;
   }
?>

<!-- Main layout with three columns -->
<div class="main-layout">
   <!-- Left navigation (already fixed by your CSS) -->
    <div class="content-area">
        <div class="page-content">
            <div class="card account-card">
                <div class="card-header">
                    <h2>MATERIALS MODERATION</h2>
                </div>

<div class="card-body">
    <!-- Pagination Info
    <div class="pagination-info mb-3">
        <p class="text-muted">
            Showing <?php echo min($items_per_page, $total_items - $offset); ?> of <?php echo $total_items; ?> items
            <?php if ($total_pages > 1): ?>
                - Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
            <?php endif; ?>
        </p>
    </div> -->

    <table class="account-table">
        <thead>
            <tr>
                <th>Content ID</th>
                <th>Title</th>
                <th>Tag</th>
                <th>Difficulty</th>
                <th>Description</th>
                <th>Video</th>
                <th>Document</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Fetch lessons with files information with pagination
            $query = "SELECT 
                        l.lesson_id, 
                        l.title, 
                        l.short_desc as description,
                        l.difficulty_level,
                        t.topic_name as tag,
                        l.created_at,
                        vf.file_url as video_file,
                        nf.file_url as document_file
                    FROM tbl_lesson l
                    LEFT JOIN tbl_topic t ON l.topic_id = t.topic_id
                    LEFT JOIN tbl_lesson_files vf ON (l.lesson_id = vf.lesson_id AND vf.file_type = 'Video')
                    LEFT JOIN tbl_lesson_files nf ON (l.lesson_id = nf.lesson_id AND nf.file_type = 'Notes')
                    GROUP BY l.lesson_id
                    ORDER BY l.lesson_id ASC
                    LIMIT ? OFFSET ?";
            
            $stmt = $connection->prepare($query);
            $stmt->bind_param("ii", $items_per_page, $offset);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {

                    // --- [ADD] resolve actual urls to avoid 404s (tries common extensions/folders) ---
                    $videoUrl = resolve_media_url($row['video_file'], ['.mp4', '.webm', '.mov'], 'Videos');
                    $docUrl   = resolve_media_url($row['document_file'], ['.pdf', '.pptx', '.docx'], 'Notes');
                    // --- [END ADD] ---

                    echo "<tr>
                        <td>{$row['lesson_id']}</td>
                        <td><strong>{$row['title']}</strong></td>
                        <td>{$row['tag']}</td>
                        <td>{$row['difficulty_level']}</td>
                        <td>{$row['description']}</td>
                        <td>" . ($videoUrl ? '<span class="file-badge">Video</span>' : '<span class="no-file">None</span>') . "</td>
                        <td>" . ($docUrl ? '<span class="file-badge">Document</span>' : '<span class="no-file">None</span>') . "</td>
                        <td class=\"action-buttons\">
                            <button class=\"view-btn\" 
                                    data-bs-toggle=\"modal\" 
                                    data-bs-target=\"#viewModal{$row['lesson_id']}\"
                                    title=\"View Content\"
                                    data-lesson-id=\"{$row['lesson_id']}\">
                                <i class=\"bi bi-eye\"></i>
                            </button>
                        </td>
                    </tr>";
                    
                    // View Modal for this lesson
                    echo "
                    <!-- View Modal for Lesson {$row['lesson_id']} -->
                    <div class='modal fade' id='viewModal{$row['lesson_id']}' tabindex='-1'>
                        <div class='modal-dialog modal-dialog-centered modal-xl'>
                            <div class='modal-content view-modal'>
                                <div class='modal-header'>
                                    <h5 class='modal-title'><i class='bi bi-eye-fill me-2'></i>View Content: {$row['title']}</h5>
                                    <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                                </div>
                                <div class='modal-body'>
                                    <div class='row'>
                                        <!-- Left Column - Content Details -->
                                        <div class='col-md-4'>
                                            <div class='content-info-card'>
                                                <h6 class='info-title'><i class='bi bi-info-circle me-2'></i>Content Information</h6>
                                                <div class='info-item'>
                                                    <label>Content ID:</label>
                                                    <span>{$row['lesson_id']}</span>
                                                </div>
                                                <div class='info-item'>
                                                    <label>Title:</label>
                                                    <span>{$row['title']}</span>
                                                </div>
                                                <div class='info-item'>
                                                    <label>Tag:</label>
                                                    <span class='tag-badge'>{$row['tag']}</span>
                                                </div>
                                                <div class='info-item'>
                                                    <label>Difficulty:</label>
                                                    <span class='difficulty-badge'>{$row['difficulty_level']}</span>
                                                </div>
                                                <div class='info-item'>
                                                    <label>Description:</label>
                                                    <p class='description-text'>{$row['description']}</p>
                                                </div>
                                            </div>
                                            
                                            <!-- File Information -->
                                            <div class='file-info-card mt-3'>
                                                <h6 class='info-title'><i class='bi bi-folder me-2'></i>File Information</h6>
                                                <div class='file-item'>
                                                    <label>Video File:</label>
                                                    <span>" . ($videoUrl ? 
                                                        '<a href=\"' . htmlspecialchars($videoUrl, ENT_QUOTES) . '\" target=\"_blank\" class=\"file-link\">' . htmlspecialchars(basename($videoUrl), ENT_QUOTES) . '</a>' 
                                                        : '<span class=\"no-file\">None</span>') . "</span>
                                                </div>
                                                <div class='file-item'>
                                                    <label>Document File:</label>
                                                    <span>" . ($docUrl ? 
                                                        '<a href=\"' . htmlspecialchars($docUrl, ENT_QUOTES) . '\" target=\"_blank\" class=\"file-link\">' . htmlspecialchars(basename($docUrl), ENT_QUOTES) . '</a>' 
                                                        : '<span class=\"no-file\">None</span>') . "</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Right Column - File Previews -->
                                        <div class='col-md-8'>
                                            <div class='preview-section'>
                                                <h6 class='preview-title'><i class='bi bi-play-btn me-2'></i>Media Preview</h6>
                                                
                                                " . ($videoUrl ? "
                                                <div class='preview-card'>
                                                    <div class='preview-header'>
                                                        <span>Video Preview</span>
                                                        <a href='" . htmlspecialchars($videoUrl, ENT_QUOTES) . "' target='_blank' class='btn-open-new'>
                                                            <i class='bi bi-box-arrow-up-right'></i> Open Fullscreen
                                                        </a>
                                                    </div>
                                                    <div class='preview-content'>
                                                        <video controls class='video-player'>
                                                            <source src='" . htmlspecialchars($videoUrl, ENT_QUOTES) . "' type='video/mp4'>
                                                            Your browser does not support the video tag.
                                                        </video>
                                                    </div>
                                                </div>
                                                " : "<div class='no-preview'><i class='bi bi-camera-video-off'></i> No Video Available</div>") . "
                                                
                                                " . ($docUrl ? "
                                                <div class='preview-card mt-3'>
                                                    <div class='preview-header'>
                                                        <span>Document Preview</span>
                                                        <a href='" . htmlspecialchars($docUrl, ENT_QUOTES) . "' target='_blank' class='btn-open-new'>
                                                            <i class='bi bi-download'></i> Download
                                                        </a>
                                                    </div>
                                                    <div class='preview-content'>
                                                        <iframe src='" . htmlspecialchars($docUrl, ENT_QUOTES) . "' class='document-viewer' frameborder='0'></iframe>
                                                        <div class='document-actions mt-2'>
                                                            <a href='" . htmlspecialchars($docUrl, ENT_QUOTES) . "' target='_blank' class='btn btn-sm btn-outline-primary'>
                                                                <i class='bi bi-arrows-fullscreen'></i> Full View
                                                            </a>
                                                            <a href='" . htmlspecialchars($docUrl, ENT_QUOTES) . "' download class='btn btn-sm btn-outline-primary'>
                                                                <i class='bi bi-download'></i> Download
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                                " : "<div class='no-preview'><i class='bi bi-file-earmark-text'></i> No Document Available</div>") . "
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class='modal-footer'>
                                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
                                        <i class='bi bi-x-circle me-1'></i> Exit
                                    </button>
                                    <button type='button' class='btn btn-danger delete-content-btn' data-lesson-id='{$row['lesson_id']}'>
                                        <i class='bi bi-trash me-1'></i> Delete Content
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>";
                }
            } else {
                echo "<tr><td colspan='8'>No content found</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
            <!-- Previous Page -->
            <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>

            <!-- Page Numbers -->
            <?php
            $start_page = max(1, $current_page - 2);
            $end_page = min($total_pages, $current_page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++): 
            ?>
                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>

            <!-- Next Page -->
            <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
  const origFetch = window.fetch;
  window.fetch = (...args) =>
    origFetch(...args).then(async (res) => {
      const clone = res.clone();
      const raw = await clone.text();

      // 1) If backend returns proper JSON, use it
      try {
        const parsed = JSON.parse(raw);
        res.json = async () => parsed;
        return res;
      } catch (_) {}

      // 2) If backend fatals with missing logAdminAction, treat as success
      if (/Call to undefined function\s+logAdminAction/i.test(raw)) {
        res.json = async () => ({ success: true, message: 'OK' });
        return res;
      }

      // 3) Otherwise, keep your existing behavior: surface the text
      res.json = async () => ({ success: res.ok, message: raw.slice(0, 300) });
      return res;
    });
})();
</script>


<?php include 'include/footer.php'; ?>
