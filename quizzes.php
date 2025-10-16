<?php
   session_start();

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
       $pageCSS = 'CSS/mobile/quizzesStyle.css';
       $pageJS = 'JS/mobile/quizzesScript.js';
   } else {
       $pageCSS = 'CSS/desktop/quizzesStyle.css';
       $pageJS = 'JS/desktop/quizzesScript.js';
   }

   $pageTitle = 'GAKUMON — Quizzes';

   include 'include/header.php';
   require_once 'config/config.php'; // Database Connection

   // Resolve user id from session
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
   // ✅ redirect if still not logged in
   if ($userID === null) {
      header("Location: login.php");
      exit;
   }

   // Fetch lesson contents from database
   $lessonsAll = [];
   $sql = "
      SELECT 
         l.lesson_id, l.title, l.short_desc, l.long_desc, l.duration,
         l.topic_id, l.difficulty_level,
         COALESCE(u.username, 'GakuLesson') AS author_name
      FROM tbl_lesson l
      LEFT JOIN tbl_user u ON u.user_id = l.author_id
   ";
   $result = $connection->query($sql);

   if ($result && $result->num_rows > 0) {
      while($row = $result->fetch_assoc()) {
         // Fetch topic name
         $topicSql = "SELECT topic_name, topic_icon FROM tbl_topic WHERE topic_id = " . $row['topic_id'];
         $topicResult = $connection->query($topicSql);
         $topic = $topicResult->fetch_assoc();

         // Fetch files for this lesson
         $filesSql = "SELECT file_id, lesson_id, file_type, file_url FROM tbl_lesson_files WHERE lesson_id = " . $row['lesson_id'];
         $filesResult = $connection->query($filesSql);
         $files = [];

         // TRY
         // Inside your while loop where you fetch files
         if ($filesResult && $filesResult->num_rows > 0) {
            while($fileRow = $filesResult->fetch_assoc()) {
               $files[] = [
                     'file_id' => $fileRow['file_id'],
                     'file_type' => $fileRow['file_type'],
                     'file_url' => $fileRow['file_url']  // This should be IMG/Notes/filename.ext
               ];
            }
         }

         $authorName = isset($row['author_name']) ? $row['author_name'] : 'GakuLesson';
         $lessonsAll[] = [
         'id'         => (int)$row['lesson_id'],
         'title'      => $row['title'],
         'short_desc' => $row['short_desc'],
         'long_desc'  => $row['long_desc'],
         'duration'   => $row['duration'],
         'topic'      => $topic['topic_name'],
         'icon'       => $topic['topic_icon'],
         'difficulty' => $row['difficulty_level'],
         'author_name'=> $authorName,
         'files'      => $files,
         'quiz_id' => isset($row['quiz_id']) ? (int)$row['quiz_id'] : null,
         ];
      }
   }
   
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

   // === Enrolled lessons + progress (best quiz %) ===
   // progress_pct = MAX(score/total*100) across this user's attempts for that lesson
   $sql = "
      SELECT 
         l.lesson_id,
         l.title,
         l.short_desc,
         l.long_desc,
         l.duration,
         l.topic_id,
         l.difficulty_level,
         COALESCE(u.username, 'GakuLesson') AS author_name,
         COALESCE(
            MAX(
            ROUND(
               qa.score * 100.0 / NULLIF(tq.total_questions, 0)
            )
            ), 0
         ) AS progress_pct
      FROM tbl_user_enrollments ue
      INNER JOIN tbl_lesson l ON l.lesson_id = ue.lesson_id
      LEFT JOIN tbl_user u ON u.user_id = l.author_id
      LEFT JOIN tbl_quizzes q ON q.lesson_id = l.lesson_id
      LEFT JOIN (
         SELECT quiz_id, COUNT(*) AS total_questions
         FROM tbl_questions
         GROUP BY quiz_id
      ) AS tq ON tq.quiz_id = q.quiz_id
      LEFT JOIN tbl_user_quiz_attempts qa
         ON qa.quiz_id = q.quiz_id
         AND qa.user_id = ue.user_id
      WHERE ue.user_id = ?
      GROUP BY 
         l.lesson_id, l.title, l.short_desc, l.long_desc, l.duration, l.topic_id, l.difficulty_level
      ORDER BY ue.enrolled_at DESC
      ";
   $stmt = $connection->prepare($sql);
   $stmt->bind_param("i", $userID);
   $stmt->execute();
   $result = $stmt->get_result();

   $lessonsEnrolled = [];
   if ($result && $result->num_rows > 0) {
   while ($row = $result->fetch_assoc()) {

      // topic (unchanged pattern)
      $topic = ['topic_name' => '', 'topic_icon' => ''];
      $topicSql = "SELECT topic_name, topic_icon FROM tbl_topic WHERE topic_id=".(int)$row['topic_id']." LIMIT 1";
      if ($topicRes = $connection->query($topicSql)) {
         $topic = $topicRes->fetch_assoc() ?: $topic;
      }

      // files (unchanged pattern)
      $files = [];
      $filesSql = "SELECT file_id, lesson_id, file_type, file_url FROM tbl_lesson_files WHERE lesson_id=".(int)$row['lesson_id'];
      if ($filesRes = $connection->query($filesSql)) {
         while ($f = $filesRes->fetch_assoc()) {
         $files[] = [
            'file_id'   => (int)$f['file_id'],
            'file_type' => $f['file_type'],
            'file_url'  => $f['file_url'],
         ];
         }
      }

      $lessonsEnrolled[] = [
         'id'         => (int)$row['lesson_id'],
         'title'      => $row['title'],
         'short_desc' => $row['short_desc'],
         'long_desc'  => $row['long_desc'],
         'duration'   => $row['duration'],
         'author_name'=> isset($row['author_name']) ? $row['author_name'] : 'GakuLesson',
         'topic'      => $topic['topic_name'],
         'icon'       => $topic['topic_icon'],
         'difficulty' => $row['difficulty_level'],
         'files'      => $files,
         'progress'   => (int)$row['progress_pct'], // ✅ add progress here
         'quiz_id' => isset($row['quiz_id']) ? (int)$row['quiz_id'] : null,
      ];
   }
   }
   $stmt->close();

   // Fetch lessons created by the logged-in user (AUTHORED), with per-user progress
   $lessonsMy = [];

   // Progress = best attempt % for THIS user on any quiz under the lesson
   $sqlMy = "
   SELECT 
      l.lesson_id,
      l.title,
      l.short_desc,
      l.long_desc,
      l.duration,
      l.topic_id,
      l.difficulty_level,
      COALESCE(u.username, 'GakuLesson') AS author_name,
      COALESCE(
         MAX(
         ROUND(
            qa.score * 100.0 / NULLIF(tq.total_questions, 0)
         )
         ),
         0
      ) AS progress_pct
   FROM tbl_lesson l
   LEFT JOIN tbl_user u ON u.user_id = l.author_id
   LEFT JOIN tbl_quizzes q ON q.lesson_id = l.lesson_id
   LEFT JOIN (
      SELECT quiz_id, COUNT(*) AS total_questions
      FROM tbl_questions
      GROUP BY quiz_id
   ) AS tq ON tq.quiz_id = q.quiz_id
   LEFT JOIN tbl_user_quiz_attempts qa
      ON qa.quiz_id = q.quiz_id
      AND qa.user_id = ?          -- progress for the current user
   WHERE l.author_id = ?         -- lessons the current user authored
   GROUP BY 
      l.lesson_id, l.title, l.short_desc, l.long_desc, l.duration, l.topic_id, l.difficulty_level, u.username
   ORDER BY l.created_at DESC
   ";

   $myStmt = $connection->prepare($sqlMy);
   $myStmt->bind_param("ii", $userID, $userID);
   $myStmt->execute();
   $myRes = $myStmt->get_result();

   if ($myRes && $myRes->num_rows > 0) {
   while ($row = $myRes->fetch_assoc()) {
      // topic (same lookup you use elsewhere so cards stay consistent)
      $topic = ['topic_name' => '', 'topic_icon' => ''];
      $topicSql = "SELECT topic_name, topic_icon FROM tbl_topic WHERE topic_id = " . (int)$row['topic_id'] . " LIMIT 1";
      if ($topicRes = $connection->query($topicSql)) {
         $topic = $topicRes->fetch_assoc() ?: $topic;
      }

      // files (same lookup)
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

      // Build lesson object – include author_name and progress
      $authorName = isset($row['author_name']) ? $row['author_name'] : 'GakuLesson';
      $lessonsMy[] = [
         'id'          => (int)$row['lesson_id'],
         'title'       => $row['title'],
         'short_desc'  => $row['short_desc'],
         'long_desc'   => $row['long_desc'],
         'duration'    => $row['duration'],
         'topic'       => $topic['topic_name'],
         'icon'        => $topic['topic_icon'],
         'difficulty'  => $row['difficulty_level'],
         'author_name'=> isset($row['author_name']) ? $row['author_name'] : 'GakuLesson',
         'files'       => $files,
         'progress'    => (int)$row['progress_pct'],
         'quiz_id' => isset($row['quiz_id']) ? (int)$row['quiz_id'] : null,
      ];
   }
   }
   $myStmt->close();

   // ✅ Fetch the username of the currently logged-in user
   $userName = $_SESSION['sUser'] ?? null;
   if (!$userName && isset($userID)) {
      $qUser = $connection->query("SELECT username FROM tbl_user WHERE user_id = $userID LIMIT 1");
      if ($qUser && $u = $qUser->fetch_assoc()) $userName = $u['username'];
   }

   // ✅ Fetch orphan quizzes (those without referenced lesson_id)
   $orphanQuizzes = [];
   $sqlOrphan = "
      SELECT 
         q.quiz_id,
         q.title,
         q.is_ai_generated,
         q.created_at,
         q.author_id
      FROM tbl_quizzes q
      WHERE q.lesson_id IS NULL AND q.author_id = $userID
      ORDER BY q.created_at DESC
   ";

   if ($resOrphan = $connection->query($sqlOrphan)) {
      while ($row = $resOrphan->fetch_assoc()) {
         $quizId     = (int)$row['quiz_id'];
         $isAi       = (int)$row['is_ai_generated'];
         $createdAt  = $row['created_at'];
         $quizTitle  = trim($row['title']) !== '' ? $row['title'] : "Quiz #{$quizId}";

         // ✅ Compute user's best attempt percentage for this standalone quiz
         $progressPct = 0;
         $progressSql = "
            SELECT 
               MAX(ROUND(qa.score * 100.0 / NULLIF(tq.total_questions, 0))) AS pct
            FROM tbl_user_quiz_attempts qa
            LEFT JOIN (
               SELECT quiz_id, COUNT(*) AS total_questions
               FROM tbl_questions
               GROUP BY quiz_id
            ) AS tq ON tq.quiz_id = qa.quiz_id
            WHERE qa.quiz_id = $quizId AND qa.user_id = $userID
         ";
         if ($progRes = $connection->query($progressSql)) {
            if ($p = $progRes->fetch_assoc()) {
               $progressPct = (int)($p['pct'] ?? 0);
            }
         }

         $orphanQuizzes[] = [
            'id'          => $quizId,
            'title'       => $quizTitle,   // ✅ use actual stored title if available
            'short_desc'  => 'Standalone quiz',
            'long_desc'   => '',
            'duration'    => '',
            'topic'       => '',
            'icon'        => '<i class=\"bi bi-question-circle\"></i>',
            'difficulty'  => '',
            'author_name' => $userName ?? 'You',   // personalize for the owner
            'files'       => [],
            'progress'    => $progressPct,  // ✅ show correct completion %
            'is_orphan'   => true,
            'created_at'  => $createdAt,
            'is_ai'       => $isAi,
            'quiz_id'     => $quizId
         ];
      }
   }

   // ✅ Merge orphan quizzes into "My Quizzes" category
   if (!empty($orphanQuizzes)) {
       $lessonsMy = array_merge($lessonsMy, $orphanQuizzes);
   }

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
                        <input type="text" class="form-control search-input" placeholder="Search GakuQuizzes" id="lessonSearchInput" name="query" aria-label="Search">
                        <button class="searchbtn btn btn-search" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>

                <!-- Modified Tabs Section -->
                <div class="tabs-scroll">
                    <div class="tab active" data-category="gakulessons">Gakuquizzes</div>
                    <div class="tab" data-category="mylessons">My Quizzes</div>
                </div>
            </div>

            <!-- Page content below the search bar -->
            <div class="container-fluid page-content">
                <!-- Add Button -->
                <div class="add-lesson-container">
                    <button class="btn btn-primary addlLessonBtn" id="addQuizBtn">
                        <i class="fas fa-plus"></i> &nbsp; Add Quiz
                    </button>
                </div>

                <div class="tabs-container">
                    <div class="cards-container">
                        <div class="cards-grid">
                            <!-- Cards will be dynamically loaded here -->
                            <div class="no-lessons-container" style="display: none; text-align:center; padding:20px;">
                                <p class="no-lessons-message">No quizzes found.</p>
                            </div>
                        </div>

                        <!-- NO QUIZZES -->
                        <div class="no-lessons-container">
                            <div class="no-lessons-icon">
                                <i class="fas fa-book-open"></i>
                            </div>
                            <div class="no-lessons-title">No Quizzes Yet</div>
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
                        <input type="text" class="form-control search-input" placeholder="Search GakuQuizzes" id="lessonSearchInput" name="query" aria-label="Search">
                        <button class="searchbtn btn btn-search" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>

                <!-- Modified Tabs Section -->
                <div class="tabs-scroll">
                    <div class="tab active" data-category="gakulessons">Gakuquizzes</div>
                    <div class="tab" data-category="mylessons">My Quizzes</div>
                </div>
            </div>

            <!-- Page content below the search bar -->
            <div class="container-fluid page-content">
                <!-- Add Button -->
                <div class="add-lesson-container">
                    <button class="btn btn-primary addlLessonBtn" id="addQuizBtn">
                        <i class="fas fa-plus"></i> &nbsp; Add Quiz
                    </button>
                </div>

                <div class="tabs-container">
                    <div class="cards-container">
                        <div class="cards-grid">
                            <!-- Cards will be dynamically loaded here -->
                            <div class="no-lessons-container" style="display: none; text-align:center; padding:20px;">
                                <p class="no-lessons-message">No quizzes found.</p>
                            </div>
                        </div>

                        <!-- NO QUIZZES -->
                        <div class="no-lessons-container">
                            <div class="no-lessons-icon">
                                <i class="fas fa-book-open"></i>
                            </div>
                            <div class="no-lessons-title">No Quizzes Yet</div>
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

<!-- Custom Lesson Detail Modal -->
<div class="custom-modal" id="lessonModal">
    <div class="custom-modal-backdrop"></div>
    <div class="custom-modal-dialog">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
               <div class="modalCard-img">
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
                <div class="modalCard-img">
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

<!-- Subscribe Modal -->
<div class="custom-modal" id="subscribeModal">
    <div class="custom-modal-backdrop"></div>
    <div class="custom-modal-dialog">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
            </div>
            <div class="custom-modal-body">
                <div class="modal-lesson-content">
                    <h6> Number of Quiz Creation Limit Already Reached! Subscribe to Gakumon to create more lessons! </h6>
            <div class="submitButton custom-modal-footer-input">
               <a href="subscription.php"><button type="button" class="btnSubmit btn btn-primary">Subscribe Now!</button></a>
               <button type="button" class="exitButton btn btn-secondary" onclick="closeSubscribeModal(this)">Cancel</button>

            </div>
        </div>
    </div>
</div>

<script>
  // Set this if your include path differs on this page
  window.LIMITS_ENDPOINT = '/include/creationLimits.inc.php';
</script>

<script>
   window.loggedInUsername = <?php echo json_encode($_SESSION['sUser'] ?? 'GakuLesson'); ?>;
   
   // For addQuizBtn
   document.getElementById('addQuizBtn').addEventListener('click', () => {
      // Remove any saved draft so the create page starts empty
      localStorage.removeItem('gakumon_draft_quiz');
      // Pass an explicit "fresh" flag so createQuiz can also skip loading
      window.location.href = 'createQuiz.php?fresh=1';
    });

   // For Progress Bar
   // Build a quick lookup: { [lessonId]: progressPct }
   window.progressByLesson = <?php
      // $lessonsEnrolled rows already include `id` and `progress`
      // If the variable name differs, adjust accordingly.
      $map = [];
      if (!empty($lessonsEnrolled)) {
         foreach ($lessonsEnrolled as $row) {
         $map[(string)$row['id']] = (int)($row['progress'] ?? 0);
         }
      }
      echo json_encode($map, JSON_UNESCAPED_UNICODE);
   ?>;

   // TRY
   // Build a quick lookup: { [lessonId]: progressPct } for enrolled + authored
   window.progressByLesson = (function () {
      const m = {};
      <?php
         // enrolled lessons carry 'id' and 'progress'
         if (!empty($lessonsEnrolled)) {
         foreach ($lessonsEnrolled as $row) {
            echo 'm["', $row['id'], '"] = ', (int)($row['progress'] ?? 0), ';';
         }
         }
         // authored lessons carry 'id' and 'progress' (from step A1)
         if (!empty($lessonsMy)) {
         foreach ($lessonsMy as $row) {
            echo 'm["', $row['id'], '"] = ', (int)($row['progress'] ?? 0), ';';
         }
         }
      ?>
      return m;
   })();

   // Pass PHP lessons array to JS
  (function () {
    const payload = {
      all: <?php echo json_encode(
        array_merge(
          isset($lessonsAll) ? $lessonsAll : (isset($lessons) ? $lessons : []),
          isset($orphanQuizzes) ? $orphanQuizzes : []
        ),
        JSON_UNESCAPED_UNICODE
      ); ?>,

      enrolled: <?php echo json_encode(isset($lessonsEnrolled) ? $lessonsEnrolled : [], JSON_UNESCAPED_UNICODE); ?>,

      // include MY orphan quizzes here
      my: <?php echo json_encode(
        array_merge(
          isset($lessonsMy) ? $lessonsMy : [],
          isset($myOrphanQuizzes) ? $myOrphanQuizzes : []
        ),
        JSON_UNESCAPED_UNICODE
      ); ?>
    };
    window.lessons = payload;
  })();
</script>

<?php include 'include/footer.php'; ?>

<?php if ($isMobile): ?>
    <script src="JS/mobile/petEnergy.js"></script>
<?php else: ?>
    <script src="JS/desktop/petEnergy.js"></script>
<?php endif; ?>