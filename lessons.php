<?php
   session_start();
   require_once 'config/config.php'; // Database Connection

   // Resolve current user id from session (supporting either user_id or sUser=username)
   $userID = null;
   if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
   $userID = (int)$_SESSION['user_id'];
   } elseif (!empty($_SESSION['sUser'])) {
   $u = $connection->prepare("SELECT user_id FROM tbl_user WHERE username = ? LIMIT 1");
   $u->bind_param("s", $_SESSION['sUser']);
   $u->execute();
   $res = $u->get_result();
   if ($res && ($row = $res->fetch_assoc())) $userID = (int)$row['user_id'];
   $u->close();
   }

   $role = $_SESSION['sRole'] ?? 'Gakusei';

   // ✅ redirect if still not logged in
   if ($userID === null) {
      header("Location: login.php");
      exit;
   }

   // Successful account creation message for confirmation (CAN REMOVE IF NOT APPEALING)
   if (isset($_SESSION['success_message'])) {
    echo "<script>alert('" . addslashes($_SESSION['success_message']) . "');</script>";
    unset($_SESSION['success_message']);
   }

   // Mobile detection function
   function isMobileDevice() {
       $userAgent = $_SERVER['HTTP_USER_AGENT'];
       $mobileKeywords = [
           'mobile', 'android', 'silk', 'kindle', 'blackberry', 'iphone', 'ipod',
           'ipad', 'webos', 'symbian', 'windows phone', 'phone'
       ];
       
       foreach ($mobileKeywords as $keyword) {
           if (stripos($userAgent, $keyword) !== false) {
               return true;
           }
       }
       return false;
   }

   $isMobile = isMobileDevice();

   // MOBILE or DESKTOP includes
   if ($isMobile) {
       $pageCSS = 'CSS/mobile/lessonsStyle.css';
       $pageJS = 'JS/mobile/lessonsScript.js';
   } else {
       $pageCSS = 'CSS/desktop/lessonsStyle.css';
       $pageJS = 'JS/desktop/lessonsScript.js';
   }

   $pageTitle = 'GAKUMON — Lessons';

   include 'include/header.php';
  
   // Get user role and username - USE EXISTING $userID
   $userRole = null;
   $username = $_SESSION['sUser'] ?? '';

   if ($userID && !empty($_SESSION['sUser'])) {
      $username = $_SESSION['sUser'];
      // Just get the role, we already have userID
      $stmt = $connection->prepare("SELECT role FROM tbl_user WHERE user_id = ?");
      $stmt->bind_param("i", $userID);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($row = $result->fetch_assoc()) {
         $userRole = $row['role'];
         $_SESSION['sUserRole'] = $userRole;
      } else {
         echo "User not found.";
         exit;
      }
   } else {
      echo "User not logged in.";
      exit;
   }

   // Build ONLY the logged-in user's enrolled lessons
   $lessons = [];

   $sql = "
   SELECT l.lesson_id, l.title, l.short_desc, l.long_desc, l.duration, l.topic_id, l.difficulty_level
   FROM tbl_user_enrollments ue
   INNER JOIN tbl_lesson l ON l.lesson_id = ue.lesson_id
   WHERE ue.user_id = ?
   ORDER BY ue.enrolled_at DESC
   ";
   $stmt = $connection->prepare($sql);
   $stmt->bind_param("i", $userID);
   $stmt->execute();
   $result = $stmt->get_result();

   if ($result && $result->num_rows > 0) {
   while ($row = $result->fetch_assoc()) {

      // (Keep your existing TOPIC lookup exactly as you had it)
      $topic = ['topic_name' => '', 'topic_icon' => ''];
      $topicSql = "SELECT topic_name, topic_icon FROM tbl_topic WHERE topic_id = " . (int)$row['topic_id'] . " LIMIT 1";
      if ($topicRes = $connection->query($topicSql)) {
         $topic = $topicRes->fetch_assoc() ?: $topic;
      }

      // (Keep your existing FILES lookup exactly as you had it)
      $files = [];
      $filesSql = "SELECT file_id, lesson_id, file_type, file_url
                  FROM tbl_lesson_files
                  WHERE lesson_id = " . (int)$row['lesson_id'];
      if ($filesRes = $connection->query($filesSql)) {
         while ($f = $filesRes->fetch_assoc()) {
         $files[] = [
            'file_id'   => (int)$f['file_id'],
            'file_type' => $f['file_type'],
            'file_url'  => $f['file_url'],
         ];
         }
      }

      // Build the lesson object exactly like before so your JS keeps working
      $lessons[] = [
         'id'          => (int)$row['lesson_id'],
         'title'       => $row['title'],
         'short_desc'  => $row['short_desc'],
         'long_desc'   => $row['long_desc'],
         'duration'    => $row['duration'],
         'topic'       => $topic['topic_name'],
         'icon'        => $topic['topic_icon'],
         'difficulty'  => $row['difficulty_level'],
         'files'       => $files,
      ];
   }
   }
   $stmt->close();
   
   // Fetch pet data for the current user
   $petData = null;
   $petSql = "SELECT 
               p.pet_name,
               p.image_url,
               up.custom_name,
               up.created_at as pet_created_at,
               DATEDIFF(NOW(), up.created_at) as days_old,
               up.energy_level
            FROM tbl_user_pet up
            INNER JOIN tbl_pet p ON up.pet_id = p.pet_id
            WHERE up.user_id = $userID
            LIMIT 1";

   $petResult = $connection->query($petSql);

   if ($petResult && $petResult->num_rows > 0) {
      $petData = $petResult->fetch_assoc();
   }


    // 1) FIXED: build $lessonsAll - Show ENROLLED lessons (both GakuLessons and user-enrolled)
    $lessonsAll = [];
    $sqlAll = "
        SELECT l.lesson_id, l.title, l.short_desc, l.long_desc, l.duration, l.topic_id, l.difficulty_level,
                COALESCE(u.username, 'GakuLesson') AS author_name
        FROM tbl_lesson l
        LEFT JOIN tbl_user u ON u.user_id = l.author_id
        INNER JOIN tbl_user_enrollments ue ON ue.lesson_id = l.lesson_id AND ue.user_id = ?
        ORDER BY ue.enrolled_at DESC
    ";
    $stmtAll = $connection->prepare($sqlAll);
    $stmtAll->bind_param("i", $userID);
    $stmtAll->execute();
    $result = $stmtAll->get_result();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // ✅ ADD TOPIC LOOKUP
            $topic = ['topic_name' => '', 'topic_icon' => ''];
            $topicSql = "SELECT topic_name, topic_icon FROM tbl_topic WHERE topic_id = " . (int)$row['topic_id'] . " LIMIT 1";
            if ($topicRes = $connection->query($topicSql)) {
                $topic = $topicRes->fetch_assoc() ?: $topic;
            }

            // ✅ ADD FILES LOOKUP
            $files = [];
            $filesSql = "SELECT file_id, lesson_id, file_type, file_url
                        FROM tbl_lesson_files
                        WHERE lesson_id = " . (int)$row['lesson_id'];
            if ($filesRes = $connection->query($filesSql)) {
                while ($f = $filesRes->fetch_assoc()) {
                    $files[] = [
                        'file_id'   => (int)$f['file_id'],
                        'file_type' => $f['file_type'],
                        'file_url'  => $f['file_url'],
                    ];
                }
            }

            $lessonsAll[] = [
                'id' => (int)$row['lesson_id'],
                'title' => $row['title'],
                'short_desc' => $row['short_desc'],
                'long_desc' => $row['long_desc'],
                'duration' => $row['duration'],
                'topic' => $topic['topic_name'],
                'icon' => $topic['topic_icon'],
                'difficulty' => $row['difficulty_level'],
                'author_name' => $row['author_name'],
                'files' => $files,
            ];
        }
    }
    $stmtAll->close();

    // 2) Lessons created by the logged-in user
    $lessonsMy = [];
    $sqlMy = "
        SELECT l.lesson_id, l.title, l.short_desc, l.long_desc, l.duration, l.topic_id, l.difficulty_level,
                COALESCE(u.username, 'GakuLesson') AS author_name
        FROM tbl_lesson l
        LEFT JOIN tbl_user u ON u.user_id = l.author_id
        WHERE l.author_id = ? AND l.is_private = 1
        ORDER BY l.created_at DESC
    ";
    $myStmt = $connection->prepare($sqlMy);
    $myStmt->bind_param("i", $userID);
    $myStmt->execute();
    $myRes = $myStmt->get_result();
    if ($myRes && $myRes->num_rows > 0) {
        while ($row = $myRes->fetch_assoc()) {
            // ✅ ADD TOPIC LOOKUP
            $topic = ['topic_name' => '', 'topic_icon' => ''];
            $topicSql = "SELECT topic_name, topic_icon FROM tbl_topic WHERE topic_id = " . (int)$row['topic_id'] . " LIMIT 1";
            if ($topicRes = $connection->query($topicSql)) {
                $topic = $topicRes->fetch_assoc() ?: $topic;
            }

            // ✅ ADD FILES LOOKUP
            $files = [];
            $filesSql = "SELECT file_id, lesson_id, file_type, file_url
                        FROM tbl_lesson_files
                        WHERE lesson_id = " . (int)$row['lesson_id'];
            if ($filesRes = $connection->query($filesSql)) {
                while ($f = $filesRes->fetch_assoc()) {
                    $files[] = [
                        'file_id'   => (int)$f['file_id'],
                        'file_type' => $f['file_type'],
                        'file_url'  => $f['file_url'],
                    ];
                }
            }

            $lessonsMy[] = [
                'id'         => (int)$row['lesson_id'],
                'title'      => $row['title'],
                'short_desc' => $row['short_desc'],
                'long_desc'  => $row['long_desc'],
                'duration'   => $row['duration'],
                'topic'      => $topic['topic_name'],
                'icon'       => $topic['topic_icon'],
                'difficulty' => $row['difficulty_level'],
                'author_name'=> $row['author_name'],
                'files'      => $files,
            ];
        }
    }
    $myStmt->close();

    // 3) Published Gaku Lessons (all public lessons from database)
    $lessonsPublished = [];
    $sqlPublished = "
        SELECT l.lesson_id, l.title, l.short_desc, l.long_desc, l.duration, l.topic_id, l.difficulty_level,
                COALESCE(u.username, 'GakuLesson') AS author_name
        FROM tbl_lesson l
        LEFT JOIN tbl_user u ON u.user_id = l.author_id
        WHERE l.author_id = ? AND l.is_private = 0
        ORDER BY l.created_at DESC
    ";
    $publishedStmt = $connection->prepare($sqlPublished);
    $publishedStmt->bind_param("i", $userID);
    $publishedStmt->execute();
    $publishedRes = $publishedStmt->get_result();
    if ($publishedRes && $publishedRes->num_rows > 0) {
        while ($row = $publishedRes->fetch_assoc()) {
            // ✅ ADD TOPIC LOOKUP
            $topic = ['topic_name' => '', 'topic_icon' => ''];
            $topicSql = "SELECT topic_name, topic_icon FROM tbl_topic WHERE topic_id = " . (int)$row['topic_id'] . " LIMIT 1";
            if ($topicRes = $connection->query($topicSql)) {
                $topic = $topicRes->fetch_assoc() ?: $topic;
            }

            // ✅ ADD FILES LOOKUP
            $files = [];
            $filesSql = "SELECT file_id, lesson_id, file_type, file_url
                        FROM tbl_lesson_files
                        WHERE lesson_id = " . (int)$row['lesson_id'];
            if ($filesRes = $connection->query($filesSql)) {
                while ($f = $filesRes->fetch_assoc()) {
                    $files[] = [
                        'file_id'   => (int)$f['file_id'],
                        'file_type' => $f['file_type'],
                        'file_url'  => $f['file_url'],
                    ];
                }
            }

            $lessonsPublished[] = [
                'id'         => (int)$row['lesson_id'],
                'title'      => $row['title'],
                'short_desc' => $row['short_desc'],
                'long_desc'  => $row['long_desc'],
                'duration'   => $row['duration'],
                'topic'      => $topic['topic_name'],
                'icon'       => $topic['topic_icon'],
                'difficulty' => $row['difficulty_level'],
                'author_name'=> $row['author_name'],
                'files'      => $files,
            ];
        }
    }
    $publishedStmt->close();

   // MOBILE or DESKTOP includes
   if ($isMobile) {
       include 'include/mobileNav.php';
   } else {
       include 'include/desktopNav.php';
   }
?>

<?php if ($isMobile): ?>
    <!-- MOBILE LAYOUT -->
    <div class="main-layout">
        <!-- Middle content area -->
        <div class="content-area">
            <!-- Search Bar at the top -->
            <div class="search-container">
                <form class="search-form" id="lessonSearchForm" action="searchResults.php" method="GET">
                    <div class="input-group">
                        <input type="text" class="form-control search-input" placeholder="Search GakuLessons" id="lessonSearchInput" name="query" aria-label="Search">
                        <button class="searchbtn btn btn-search" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>

                <!-- Modified Tabs Section -->
                <div class="tabs-scroll">
                    <div class="tab active" data-category="gakulessons">Gakulessons</div>
                    <div class="tab" data-category="mylessons">My Lessons</div>
                    <?php if (isset($_SESSION['sRole']) && strtolower($_SESSION['sRole']) === 'gakusensei'): ?>
                        <div class="tab" data-category="published">Published</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Page content below the search bar -->
            <div class="container-fluid page-content">
                <!-- Add Lesson Button -->
                <div class="add-lesson-container">
                    <button class="btn btn-primary addlLessonBtn">
                        <i class="fas fa-plus"></i> &nbsp; Add Lesson
                    </button>
                </div>

                <div class="tabs-container">
                    <div class="cards-container">
                        <div class="cards-grid">
                            <!-- Cards will be dynamically loaded here -->
                            <div class="no-lessons-container" style="display: none; text-align:center; padding:20px;">
                                <p class="no-lessons-message">No lessons found.</p>
                            </div>
                        </div>

                        <!-- NO LESSONS -->
                        <div class="no-lessons-container">
                            <div class="no-lessons-icon">
                                <i class="fas fa-book-open"></i>
                            </div>
                            <div class="no-lessons-title">No Lessons Yet</div>
                            <p class="no-lessons-message">It looks like you haven't enrolled in any lessons yet. Browse our collection of GakuLessons and start your learning journey today!</p>
                        </div>
                    </div>

                    <div class="pagination">
                        <div class="page-item">
                            <div class="page-link prev"><i class="fas fa-chevron-left"></i></div>
                        </div>
                        <div class="page-item">
                            <div class="page-link active">1</div>
                        </div>
                        <div class="page-item">
                            <div class="page-link">2</div>
                        </div>
                        <div class="page-item">
                            <div class="page-link">3</div>
                        </div>
                        <div class="page-item">
                            <div class="page-link next"><i class="fas fa-chevron-right"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- DESKTOP LAYOUT -->
    <div class="main-layout">
        <!-- Middle content area -->
        <div class="content-area">
            <!-- Search Bar at the top -->
            <div class="search-container">
                <form class="search-form" id="lessonSearchForm" action="searchResults.php" method="GET">
                    <div class="input-group">
                        <input type="text" class="form-control search-input" placeholder="Search GakuLessons" id="lessonSearchInput" name="query" aria-label="Search">
                        <button class="searchbtn btn btn-search" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>

                <!-- Modified Tabs Section -->
                <div class="tabs-scroll">
                    <div class="tab active" data-category="gakulessons">Gakulessons</div>
                    <div class="tab" data-category="mylessons">My Lessons</div>
                    <?php if (isset($_SESSION['sRole']) && strtolower($_SESSION['sRole']) === 'gakusensei'): ?>
                        <div class="tab" data-category="published">Published GakuLessons</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Page content below the search bar -->
            <div class="container-fluid page-content">
                <!-- Add Lesson Button -->
                <div class="add-lesson-container">
                    <button class="btn btn-primary addlLessonBtn">
                        <i class="fas fa-plus"></i> &nbsp; Add Lesson
                    </button>
                </div>

                <div class="tabs-container">
                    <div class="cards-container">
                        <div class="cards-grid">
                            <!-- Cards will be dynamically loaded here -->
                            <div class="no-lessons-container" style="display: none; text-align:center; padding:20px;">
                                <p class="no-lessons-message">No lessons found.</p>
                            </div>
                        </div>

                        <!-- NO LESSONS -->
                        <div class="no-lessons-container">
                            <div class="no-lessons-icon">
                                <i class="fas fa-book-open"></i>
                            </div>
                            <div class="no-lessons-title">No Lessons Yet</div>
                            <p class="no-lessons-message">It looks like you haven't enrolled in any lessons yet. Browse our collection of GakuLessons and start your learning journey today!</p>
                        </div>
                    </div>

                    <div class="pagination">
                        <div class="page-item">
                            <div class="page-link prev"><i class="fas fa-chevron-left"></i></div>
                        </div>
                        <div class="page-item">
                            <div class="page-link active">1</div>
                        </div>
                        <div class="page-item">
                            <div class="page-link">2</div>
                        </div>
                        <div class="page-item">
                            <div class="page-link">3</div>
                        </div>
                        <div class="page-item">
                            <div class="page-link next"><i class="fas fa-chevron-right"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Desktop Pet Panel -->
        <?php include 'include/petPanel.php'; ?>
    </div>
<?php endif; ?>

<!-- Rest of your modals remain exactly the same -->
<!-- Custom Lesson Detail Modal -->
<div class="custom-modal" id="lessonModal">
    <div class="custom-modal-backdrop"></div>
    <div class="custom-modal-dialog">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
               <div class="modalCard-img" id="lessonModalIcon">
                  <i class="fas ${lesson.icon}"></i>
               </div>
            </div>
            <div class="custom-modal-body">
                <div class="modal-lesson-content">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
            <div class="submitButton custom-modal-footer">
               <a id="take-quiz-link-2" class="btnSubmit btn btn-primary">Take Quiz</a>
               <button type="button" class="exitButton btn btn-secondary custom-modal-close-btn">Close</button>
            </div> 
        </div>
    </div>
</div>

<!-- Secondary Modal for Lecture Materials -->
<div class="custom-modal" id="materialsModal">
    <div class="custom-modal-backdrop" id="materialsBackdrop"></div>
    <div class="custom-modal-dialog materials-dialog">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <div class="modalCard-img" id="lessonModalIcon">  
                    <i id="materialsIcon"></i>
                </div>
            </div>
            <div class="custom-modal-body">
                <div class="modal-lesson-content">
                    <div class="modal-lesson-header">
                        <div class="cardLesson-title" id="materialsTitle"></div>
                        <div class="labels">
                            <div class="cardLabel cardLabel-gaku">GakuLesson</div>
                            <div class="cardLabel cardLabel-topic" id="materialsTopic"></div>
                        </div>
                    
                        <div class="modal-meta">
                            <span><i class="fas fa-clock"></i> <span id="materialsDuration"></span></span>
                            <span><i class="fas fa-signal"></i> <span id="materialsDifficulty"></span></span>
                        </div>
                    </div>
                    
                    <div class="materials-header">
                        <div class="cardObjectives" id="materialsTypeHeader"></div>
                        <button class="exitButton btn-back" id="backToLessonModal">
                            <i class="fas fa-arrow-left"></i> &nbsp; Back to Lesson
                        </button>
                    </div>
                    
                    <div class="materials-container" id="materialsList">
                        <!-- Files will be populated here -->
                    </div>
                </div>
            </div>

            <div class="submitButton custom-modal-footer">
               <a id="take-quiz-link" class="btnSubmit btn btn-primary">Take Quiz</a>
            </div>
        </div>
    </div>
</div>

<!-- Add Lesson Modal -->
<div class="custom-modal" id="addLessonModal">
    <div class="custom-modal-backdrop"></div>
    <div class="custom-modal-dialog">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
               <div class="modalCard-img">
               </div>
            </div>
            <div class="custom-modal-body">
                <div class="modal-lesson-content">
                    <form id="addLessonForm">
                        <div class="modal-lesson-header">
                            <input type="text" class="cardLesson-title-input" id="lessonTitle" placeholder="Enter lesson title" required>
                            <div class="labels">
                                <div class="cardLabel cardLabel-gaku" id="usernameLabel"><?php echo htmlspecialchars($username); ?></div>
                                <div class="cardLabel cardLabel-topic" id="topicLabel">
                                    <select id="topicSelect" class="topic-select">
                                        <option value="">Select topic</option>
                                        <?php
                                        // Fetch topics from database
                                        $topicsSql = "SELECT topic_id, topic_name FROM tbl_topic";
                                        $topicsResult = $connection->query($topicsSql);
                                        if ($topicsResult && $topicsResult->num_rows > 0) {
                                            while($topicRow = $topicsResult->fetch_assoc()) {
                                                echo '<option value="' . (int)$topicRow['topic_id'] . '">' . htmlspecialchars($topicRow['topic_name']) . '</option>';
                                            }
                                        }
                                        ?>
                                        <option value="custom">+ Add custom topic</option>
                                    </select>
                                    <input type="text" id="customTopicInput" class="custom-topic-input" 
                                           placeholder="Enter custom topic" style="display: none;">
                                </div>
                            </div>
                        
                            <div class="modal-meta">
                                <span>
                                    <i class="fas fa-clock"></i> 
                                    <input type="text" class="duration-input ti" id="lessonDuration" 
                                           placeholder="e.g., 45 min" required>
                                </span>
                                <span>
                                    <i class="fas fa-signal"></i> 
                                    <select id="lessonDifficulty" class="difficulty-select ti" required>
                                        <option value="">Select difficulty</option>
                                        <option value="Beginner">Beginner</option>
                                        <option value="Intermediate">Intermediate</option>
                                        <option value="Advanced">Advanced</option>
                                    </select>
                                </span>
                                <?php if ($userRole === 'Gakusensei'): ?>
                                    <span>
                                        <i class="fas fa-lock"></i> 
                                        <select id="lessonPrivacy" class="privacy-select" required>
                                            <option value="0">Public</option>
                                            <option value="1">Private</option>
                                        </select>
                                    </span>
                                <?php else: ?>
                                    <input type="hidden" id="lessonPrivacy" value="1">
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="modal-lesson-description">
                            <textarea class="cardLesson-description-input" id="lessonDescription" 
                                      placeholder="Enter detailed description of your lesson..." 
                                      rows="4" required></textarea>
                        </div>
                        
                        <div class="modal-lesson-objectives">
                            <div class="cardObjectives">Learning Objectives</div>
                              <div id="objectivesContainer">
                                 <div class="objective-input-group">
                                    <input type="text" class="objective-input" placeholder="Enter learning objective">
                                    <button type="button" class="btn-remove-objective" onclick="removeObjective(this)">×</button>
                                 </div>
                              </div>
                            <button type="button" class="btn-add-objective" onclick="addObjective()">
                                <i class="fas fa-plus"></i> &nbsp; Add Objective
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="submitButton custom-modal-footer-input">
               <button type="button" class="btnSubmit btn btn-primary" onclick="openAddMaterialsModal()">Add Materials</button>
               <button type="button" class="exitButton btn btn-secondary" onclick="closeAddLessonModal()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Materials Modal -->
<div class="custom-modal" id="addMaterialsModal">
    <div class="custom-modal-backdrop"></div>
    <div class="custom-modal-dialog materials-dialog">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <div class="modalCard-img">
                    <i class="fas fa-file-upload"></i>
                </div>
            </div>
            <div class="custom-modal-body">
                <div class="modal-lesson-content">
                    <div class="modal-lesson-header">
                        <div class="cardLesson-title" id="materialsLessonTitle"></div>
                        <div class="labels">
                            <div class="cardLabel cardLabel-gaku"><?php echo htmlspecialchars($username); ?></div>
                            <div class="cardLabel cardLabel-topic" id="materialsLessonTopic"></div>
                        </div>
                    
                        <div class="modal-meta">
                            <span><i class="fas fa-clock"></i> <span id="materialsLessonDuration"></span></span>
                            <span><i class="fas fa-signal"></i> <span id="materialsLessonDifficulty"></span></span>
                        </div>
                    </div>
                    
                    <div class="materials-header">
                        <div class="cardObjectives">Upload Lecture Materials</div>
                        <button class="exitButton btn-back" onclick="backToAddLessonModal()">
                            <i class="fas fa-arrow-left"></i> &nbsp; Back to Lesson
                        </button>
                    </div>
                    
                    <div class="materials-container">
                        <div class="upload-section">
                            <div class="upload-zone" id="uploadZone">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Drag & drop files here or click to browse</p>
                                <input type="file" id="fileUpload" name="attachments[]" multiple style="display: none;" 
                                       onchange="handleFileUpload(this.files)">
                            </div>
                            <div class="uploaded-files" id="uploadedFiles">
                                <!-- Uploaded files will appear here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Number of Questions -->
                <div class="form-row question" style="margin-bottom:12px;">
                    <label for="autoGenQuestionCount" class="form-label">Number of Questions <small>(5–15)</small></label>
                    <input id="autoGenQuestionCount"
                            name="numberOfQuestion"
                            type="number"
                            min="5"
                            max="15"
                            placeholder="e.g., 10"
                            class="form-input"
                            required />
                    <small id="autoGenCountError" class="form-error" style="display:none;">Please choose 5–15.</small>
                </div>

                <!-- Type of Quiz -->
                <div class="form-row" style="margin-bottom:16px;">
                    <label for="autoGenType" class="form-label">Quiz Type</label>
                    <select id="autoGenType" name="selectedOption" class="form-select" required>
                        <option value="" selected disabled>Select type…</option>
                        <option value="Multiple Choice">Multiple Choice</option>
                        <option value="Identification">Identification</option>
                        <option value="True or False">True or False</option>
                        <option value="Fill in the Blanks">Fill in the Blanks</option>
                    </select>
                    <small id="autoGenTypeError" class="form-error" style="display:none;">Please select a type.</small>
                </div>

            </div>

            <div class="submitButton custom-modal-footer-input">
               <button type="button" id="autoGenQuiz" class="btnSubmit btn btn-primary" onclick="saveLesson('auto')">Auto-generate Quiz</button>
               <button type="button" id="createQuizBtn" class="btnSubmit btn btn-primary" onclick="saveLesson()">Create my own quiz</button>
               <button type="button" class="exitButton btn btn-secondary" onclick="closeAddMaterialsModal()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Subscribe Modal -->
<div class="custom-modal" id="subscribeModal" aria-hidden="true">
  <div class="custom-modal-backdrop" onclick="closeSubscribeModal()"></div>

  <div class="custom-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="subscribeTitle">
    <div class="custom-modal-content">
      <!-- Header (kept consistent with other modals) -->
      <div class="custom-modal-header">
        <!-- <h5 class="custom-modal-title" id="subscribeTitle">Upgrade to Premium</h5>
        <button type="button" class="custom-modal-close" aria-label="Close" onclick="closeSubscribeModal()">&times;</button> -->
      </div>

      <!-- Body -->
      <div class="custom-modal-body">
        <div class="modal-lesson-content">
          <h6>You must be a Premium Member to enroll this lesson!</h6>
        </div>
      </div>

      <!-- Footer (same placement as your other modals) -->
      <div class="custom-modal-footer">
        <button type="button" class="exitButton btn btn-secondary" onclick="closeSubscribeModal()">Cancel</button>
        <a href="subscription.php" class="btn btn-primary btnSubmit btnSubscribe">Subscribe Now!</a>
      </div>
    </div>
  </div>
</div>

<script>
    window.userRole = <?= json_encode($role) ?>;
  // PHP → JS scalars
  const currentUsername = "<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>";
  const userId = <?php echo (int)$userID; ?>;

  (function () {
    // Build one canonical payload with safe fallbacks so undefined PHP vars don't break the page
    const payload = {
      // If $lessonsAll isn't set yet, fall back to the older $lessons (your original array)
      all: <?php echo json_encode(isset($lessonsAll) ? $lessonsAll : (isset($lessons) ? $lessons : []), JSON_UNESCAPED_UNICODE); ?>,
      enrolled: <?php echo json_encode(isset($lessonsEnrolled) ? $lessonsEnrolled : [], JSON_UNESCAPED_UNICODE); ?>,
      my: <?php echo json_encode(isset($lessonsMy) ? $lessonsMy : [], JSON_UNESCAPED_UNICODE); ?>,
      published: <?php echo json_encode(isset($lessonsPublished) ? $lessonsPublished : [], JSON_UNESCAPED_UNICODE); ?>
    };

    // Primary (new)
    window.lessons = payload;

    // Back-compat for any legacy code that expects globals or the bare "lessons" var
    window.lessonsAll = payload.all;
    window.lessonsEnrolled = payload.enrolled;
    window.lessonsMy = payload.my;
    window.lessonsPublished = payload.published;
    // Provide the legacy global variable too
    var lessons = window.lessons;

    // (Optional) quick sanity log — remove later
    console.log('[LESSONS] all:%d enrolled:%d my:%d published:%d',
      (payload.all || []).length,
      (payload.enrolled || []).length,
      (payload.my || []).length,
      (payload.published || []).length
    );
  })();
</script>

<?php include 'include/footer.php'; ?>

<?php if ($isMobile): ?>
    <script src="JS/mobile/petEnergy.js"></script>
<?php else: ?>
    <script src="JS/desktop/petEnergy.js"></script>
<?php endif; ?>