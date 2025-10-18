<?php
   session_start();
    require_once 'config/config.php'; // Database Connection
   // Comment to test out Githut!

   // Successful account creation message for confirmation (CAN REMOVE IF NOT APPEALING)
   if (isset($_SESSION['success_message'])) {
    echo "<script>alert('" . addslashes($_SESSION['success_message']) . "');</script>";
    unset($_SESSION['success_message']);
   }

   // For GAKUSENSEI Bank Details
   // after session + config
    $bankInfo = null;
    if (isset($_SESSION['sUser'])) {
    $stmt = $connection->prepare("
        SELECT account_first_name, account_last_name, bank_code, other_bank_name,
            account_number, account_type, mobile_number, qr_code_url
        FROM tbl_gakusensei_bank_info
        JOIN tbl_user USING(user_id)
        WHERE tbl_user.username = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $_SESSION['sUser']);
    $stmt->execute();
    $bankInfo = $stmt->get_result()->fetch_assoc();

    // After: $bankInfo = $stmt->get_result()->fetch_assoc();
    $requiresOtherBankName = !empty($bankInfo['bank_code']) && strtoupper($bankInfo['bank_code']) === 'OTHER';

    // Define what "complete" means (adjust field list if needed)
    $hasCoreFields =
        !empty($bankInfo['account_first_name']) &&
        !empty($bankInfo['account_last_name']) &&
        !empty($bankInfo['account_number']) &&
        !empty($bankInfo['account_type']) &&
        !empty($bankInfo['mobile_number']) &&
        // bank must be chosen: either a code or an "other" name
        (
        (!empty($bankInfo['bank_code']) && strtoupper($bankInfo['bank_code']) !== 'OTHER') ||
        ($requiresOtherBankName && !empty($bankInfo['other_bank_name']))
        );

    // Show modal if there is NO row or the row is INCOMPLETE
    $needsBankInfo = !$bankInfo || !$hasCoreFields;

    $stmt->close();
    }


   $pageTitle = 'GAKUMON';

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

    // MOBILRE or DESKTOP includes
    if ($isMobile) {
        $pageCSS = 'CSS/mobile/homepageStyle.css';
        $pageJS = 'JS/mobile/homepageScript.js';
    } else {
        $pageCSS = 'CSS/desktop/homepageStyle.css';
        $pageJS = 'JS/desktop/homepageScript.js';
    }



   include 'include/header.php';
   require_once 'config/config.php'; // Database Connection

    if (isset($_SESSION['sUser'])) {
        $username = $_SESSION['sUser'];

        // Get UserID AND role from database
        $stmt = $connection->prepare("SELECT user_id, role FROM tbl_user WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $userID = $row['user_id'];   // Now you have the userID
            $_SESSION['sUserRole'] = $row['role']; // Store role in session
        } else {
            echo "User not found.";
            exit;
        }

        $is_premium = false;
        if (isset($_SESSION['sUser'])) {
            $stmt = $connection->prepare("SELECT subscription_type FROM tbl_user WHERE username = ?");
            $stmt->bind_param("s", $_SESSION['sUser']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $is_premium = ($row['subscription_type'] === 'Premium');
            }
            $stmt->close();
        }

    } else {
        echo "User not logged in.";
        header("Location: login.php");
        exit;
    }

    // Check if user just logged in (you might need to set this flag after successful login)
    if (!isset($_SESSION['login_shown'])) {
        $_SESSION['login_shown'] = true;
        // This ensures the modal only shows once after login
    }

   // Fetch lesson contents from database
    $lessons = [];
    $sql = "SELECT lesson_id, title, short_desc, long_desc, duration, author_id, topic_id, difficulty_level, is_private 
            FROM tbl_lesson 
            WHERE is_private = 0"; // Only show public lessons
    $result = $connection->query($sql);

    if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // 🔹 Fetch author name (added)
        $authorSql = "SELECT username FROM tbl_user WHERE user_id = " . (int)$row['author_id'] . " LIMIT 1";
        $authorResult = $connection->query($authorSql);
        $author = $authorResult && $authorResult->num_rows > 0
            ? $authorResult->fetch_assoc()['username']
            : 'GakuLesson';

        // Fetch topic name
        $topicSql = "SELECT topic_name, topic_icon FROM tbl_topic WHERE topic_id = " . $row['topic_id'];
        $topicResult = $connection->query($topicSql);
        $topic = $topicResult->fetch_assoc();

        // Fetch files for this lesson
        $filesSql = "SELECT file_id, lesson_id, file_type, file_url FROM tbl_lesson_files WHERE lesson_id = " . $row['lesson_id'];
        $filesResult = $connection->query($filesSql);
        $files = [];

        // TRY
        if ($filesResult && $filesResult->num_rows > 0) {
            while($fileRow = $filesResult->fetch_assoc()) {
                $files[] = [
                'file_id' => $fileRow['file_id'],
                'file_type' => $fileRow['file_type'],
                'file_url' => $fileRow['file_url']
                ];
            }
        }

        $lessons[] = [
            'id' => $row['lesson_id'],
            'title' => $row['title'],
            'short_desc' => $row['short_desc'],
            'long_desc' => $row['long_desc'],
            'duration' => $row['duration'],
            'author_id' => $row['author_id'],
            'author_name' => $author,  // ✅ added here
            'topic' => $topic['topic_name'],
            'icon' => $topic['topic_icon'],
            'difficulty' => $row['difficulty_level'],
            'is_private' => $row['is_private'],
            'files' => $files
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
   

    // MOBILRE or DESKTOP includes
    if ($isMobile) {
        include 'include/mobileNav.php';
    } else {
        include 'include/desktopNav.php';
    }

?>

<?php if ($isMobile): ?>
    <!-- MOBILE LAYOUT -->
    <div class="main-layout">
    <!-- Left navigation (already fixed by your CSS) -->
    
    <!-- Middle content area -->
    <div class="content-area">
        <!-- Search Bar at the top -->
        <div class="search-container">
            <form class="search-form" id="lessonSearchForm" action="searchResults.php" method="GET">
                <div class="input-group">
                <input type="text" class="form-control search-input" placeholder="Search GakuLessons" id="lessonSearchInput" name="query" aria-label="Search">

                <!-- Search Button -->
                <button class="searchbtn btn btn-search" type="submit">
                    <i class="fas fa-search"></i>
                </button>
                </div>
            </form>

            <div class="tabs-scroll">
                <div class="tab active" data-category="all">All Lessons</div>
                <div class="tab" data-category="inputdevices">Input Devices</div>
                <div class="tab" data-category="webprogramming">Web Programming</div>
                <div class="tab" data-category="javascript">JavaScript</div>
                <div class="tab" data-category="introtocomputing">Intro to Computing</div>
                <div class="tab" data-category="computerhistory">Computer History</div>
                <div class="tab" data-category="database">Databases</div>
                <div class="tab" data-category="networking">Networking</div>
            </div>
        </div>

        <!-- GAKUSENSEI DASHBOARD - MOBILE ONLY -->
        <?php if (isset($_SESSION['sUserRole']) && $_SESSION['sUserRole'] === 'Gakusensei'): 
            
            // Fetch real data from database but maintain the same structure
            // 1. Total Students
            $studentStmt = $connection->prepare("
                SELECT COUNT(DISTINCT ue.user_id) as total_students 
                FROM tbl_user_enrollments ue 
                INNER JOIN tbl_lesson l ON ue.lesson_id = l.lesson_id 
                WHERE l.author_id = ?
            ");
            $studentStmt->bind_param("i", $userID);
            $studentStmt->execute();
            $studentResult = $studentStmt->get_result();
            $totalStudents = $studentResult->fetch_assoc()['total_students'] ?? 0;
            $studentStmt->close();
            
            // 2. Lessons Created
            $lessonStmt = $connection->prepare("
                SELECT COUNT(*) as lessons_created 
                FROM tbl_lesson 
                WHERE author_id = ?
            ");
            $lessonStmt->bind_param("i", $userID);
            $lessonStmt->execute();
            $lessonResult = $lessonStmt->get_result();
            $lessonsCreated = $lessonResult->fetch_assoc()['lessons_created'] ?? 0;
            $lessonStmt->close();
            
            // 3. Total Daily Enrollments
            $enrollmentStmt = $connection->prepare("
                SELECT COUNT(*) as total_enrollments
                FROM tbl_user_enrollments ue
                INNER JOIN tbl_lesson l ON ue.lesson_id = l.lesson_id
                WHERE l.author_id = ?
                AND ue.enrolled_at >= NOW() - INTERVAL 1 DAY
            ");
            $enrollmentStmt->bind_param("i", $userID);
            $enrollmentStmt->execute();
            $enrollmentResult = $enrollmentStmt->get_result();
            $totalEnrollments = $enrollmentResult->fetch_assoc()['total_enrollments'] ?? 0;
            $enrollmentStmt->close();
            
            // 4. Gakucoins Earned
            $coinsStmt = $connection->prepare("
                SELECT gakucoins as gakucoins_earned 
                FROM tbl_user 
                WHERE user_id = ?
            ");
            $coinsStmt->bind_param("i", $userID);
            $coinsStmt->execute();
            $coinsResult = $coinsStmt->get_result();
            $gakucoinsEarned = $coinsResult->fetch_assoc()['gakucoins_earned'] ?? 0;
            $coinsStmt->close();
            
            // 5. Completion Rate
            $completionStmt = $connection->prepare("
                SELECT 
                    COALESCE(
                        AVG(
                            CASE 
                                WHEN uqa.score >= (SELECT COUNT(*) FROM tbl_questions q2 WHERE q2.quiz_id = q.quiz_id) * 0.7 
                                THEN 100 
                                ELSE (uqa.score * 100.0 / GREATEST((SELECT COUNT(*) FROM tbl_questions q2 WHERE q2.quiz_id = q.quiz_id), 1))
                            END
                        ), 0
                    ) as avg_completion_rate
                FROM tbl_user_quiz_attempts uqa 
                INNER JOIN tbl_quizzes q ON uqa.quiz_id = q.quiz_id 
                INNER JOIN tbl_lesson l ON q.lesson_id = l.lesson_id 
                WHERE l.author_id = ?
            ");
            $completionStmt->bind_param("i", $userID);
            $completionStmt->execute();
            $completionResult = $completionStmt->get_result();
            $completionRate = round($completionResult->fetch_assoc()['avg_completion_rate'] ?? 78);
            $completionStmt->close();
            
            // 6. Revenue Earned
            $revenue = 0;

            // ₱1.50 per enrollment in Gakusensei's lessons
            $enrollRevenueStmt = $connection->prepare("
                SELECT COUNT(DISTINCT ue.user_id, ue.lesson_id) AS enroll_count
                FROM tbl_user_enrollments ue
                INNER JOIN tbl_lesson l ON ue.lesson_id = l.lesson_id
                WHERE l.author_id = ?
            ");
            $enrollRevenueStmt->bind_param("i", $userID);
            $enrollRevenueStmt->execute();
            $enrollResult = $enrollRevenueStmt->get_result();
            $enrollCount = $enrollResult->fetch_assoc()['enroll_count'] ?? 0;
            $enrollRevenueStmt->close();

            $revenue += $enrollCount * 1;

            // ₱3.00 per student who got 100% once
            $quizRevenueStmt = $connection->prepare("
                SELECT COUNT(DISTINCT uqa.user_id, q.quiz_id) AS perfect_quizzes
                FROM tbl_user_quiz_attempts uqa
                INNER JOIN tbl_quizzes q ON uqa.quiz_id = q.quiz_id
                INNER JOIN tbl_lesson l ON q.lesson_id = l.lesson_id
                WHERE l.author_id = ?
                AND uqa.score = (SELECT COUNT(*) FROM tbl_questions qq WHERE qq.quiz_id = q.quiz_id)
            ");
            $quizRevenueStmt->bind_param("i", $userID);
            $quizRevenueStmt->execute();
            $quizResult = $quizRevenueStmt->get_result();
            $perfectCount = $quizResult->fetch_assoc()['perfect_quizzes'] ?? 0;
            $quizRevenueStmt->close();

            $revenue += $perfectCount * 3.00;

            // No rounding — use raw float value
            $revenueEarned = $revenue;

            // 7. Monthly Earnings (past 30 days)
            $monthlyRevenue = 0;

            // ₱1.50 per enrollment in last 30 days
            $monthlyEnrollStmt = $connection->prepare("
                SELECT COUNT(DISTINCT ue.user_id, ue.lesson_id) AS enroll_count
                FROM tbl_user_enrollments ue
                INNER JOIN tbl_lesson l ON ue.lesson_id = l.lesson_id
                WHERE l.author_id = ?
                AND ue.enrolled_at >= NOW() - INTERVAL 30 DAY
            ");
            $monthlyEnrollStmt->bind_param("i", $userID);
            $monthlyEnrollStmt->execute();
            $monthlyEnrollResult = $monthlyEnrollStmt->get_result();
            $monthlyEnrollCount = $monthlyEnrollResult->fetch_assoc()['enroll_count'] ?? 0;
            $monthlyEnrollStmt->close();

            $monthlyRevenue += $monthlyEnrollCount * 1;

            // ₱3.00 per perfect quiz (past 30 days, first time only)
            $monthlyQuizStmt = $connection->prepare("
                SELECT COUNT(DISTINCT uqa.user_id, q.quiz_id) AS perfect_quizzes
                FROM tbl_user_quiz_attempts uqa
                INNER JOIN tbl_quizzes q ON uqa.quiz_id = q.quiz_id
                INNER JOIN tbl_lesson l ON q.lesson_id = l.lesson_id
                WHERE l.author_id = ?
                AND uqa.score = (SELECT COUNT(*) FROM tbl_questions qq WHERE qq.quiz_id = q.quiz_id)
                AND uqa.attempted_at >= NOW() - INTERVAL 30 DAY
            ");
            $monthlyQuizStmt->bind_param("i", $userID);
            $monthlyQuizStmt->execute();
            $monthlyQuizResult = $monthlyQuizStmt->get_result();
            $monthlyPerfectCount = $monthlyQuizResult->fetch_assoc()['perfect_quizzes'] ?? 0;
            $monthlyQuizStmt->close();

            $monthlyRevenue += $monthlyPerfectCount * 3.00;

            // Keep exact float value (no rounding)
            $monthlyEarnings = $monthlyRevenue;

            // Next payout date
            $nextPayout = date('M j, Y', strtotime('first day of next month +14 days'));
        ?>
            <div class="gakusensei-dashboard">
                <h4 class="dashboard-title">Gakusensei Dashboard</h4>
                
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <div class="stat-content">
                            <h3><?php echo number_format($totalStudents); ?></h3>
                            <p>Total Students</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <h3><?php echo number_format($lessonsCreated); ?></h3>
                            <p>Lessons Created</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <h3><?php echo number_format($totalEnrollments); ?></h3>
                            <p>Total Daily Enrollments</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <h3 id="revenueEarned"><?php echo number_format($revenueEarned); ?></h3>
                            <p>Revenue Earned</p>
                        </div>
                    </div>
                </div>
                
                <div class="engagement-metrics">
                    <h5>Engagement Metrics</h5>
                    <div class="metric-bar">
                        <span class="metric-label">Completion Rate</span>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?php echo $completionRate; ?>%"><?php echo $completionRate; ?>%</div>
                        </div>
                    </div>
                </div>
                
                <div class="revenue-section">
                    <h5>Revenue Overview</h5>
                    <div class="revenue-stats">
                        <div class="revenue-item">
                            <span>This Month:</span>
                            <span class="revenue-amount"><?php echo number_format($monthlyEarnings); ?> Php</span>
                        </div>
                        <div class="revenue-item">
                            <span>All Time:</span>
                            <span class="revenue-amount"><?php echo number_format($revenueEarned); ?> Php</span>
                        </div>
                        <div class="revenue-item">
                            <span>Next Payout:</span>
                            <span class="revenue-amount"><?php echo $nextPayout; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Page content below the search bar -->
        <div class="container-fluid page-content">
            <div class="tabs-container">

                <div class="cards-container"> <!-- Added container for scrollable cards -->
                    <div class="cards-grid">
                        <!-- Cards will be dynamically loaded here -->
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
    
    <?php 
            // MOBILRE or DESKTOP includes
            if (!$isMobile) {
                include 'include/petPanel.php';
            } 
    ?>
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

                <div class="tabs-scroll">
                    <div class="tab active" data-category="all">All Lessons</div>
                    <div class="tab" data-category="inputdevices">Input Devices</div>
                    <div class="tab" data-category="webprogramming">Web Programming</div>
                    <div class="tab" data-category="javascript">JavaScript</div>
                    <div class="tab" data-category="introtocomputing">Intro to Computing</div>
                    <div class="tab" data-category="computerhistory">Computer History</div>
                    <div class="tab" data-category="database">Databases</div>
                    <div class="tab" data-category="networking">Networking</div>
                </div>
            </div>

            <!-- Page content below the search bar -->
            <div class="container-fluid page-content">
                <div class="tabs-container">
                    <div class="cards-container">
                        <div class="cards-grid">
                            <!-- Cards will be dynamically loaded here -->
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
               <!-- <button type="button" class="custom-modal-close" aria-label="Close">
                  <i class="fas fa-times"></i>
               </button> -->
            </div>
            <div class="custom-modal-body">
                <div class="modal-lesson-content">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
            <div class="submitButton custom-modal-footer">
               <button type="button" class="btnSubmit btn btn-primary start-lesson-btn">Enroll</button>
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

<!-- Enrollment Prompt Modal -->
<div class="custom-modal" id="enrollmentModal">
    <div class="custom-modal-backdrop"></div>
    <div class="custom-modal-dialog">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <div class="modalCard-img">
                    <!-- <i class="fas fa-graduation-cap"></i> -->
                </div>
            </div>
            <div class="custom-modal-body">
                <div class="modal-lesson-content">
                    <div class="enrollment-prompt">
                        <div class="alertTitle text-center">Enroll First!</div>
                        <p class="alertCaption text-center mb-4">You need to enroll in this lesson before accessing the lecture materials. <br> Would you like to enroll now?</p>
                    </div>
                </div>
            </div>
            <div class="submitButton custom-modal-footer">
                <button type="button" class="btnSubmit btn btn-primary enroll-confirm-btn">Enroll</button>
                <button type="button" class="exitButton btn btn-secondary enroll-cancel-btn">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Enrollment Success Modal -->
<div class="custom-modal" id="enrollmentSuccessModal">
    <div class="custom-modal-backdrop"></div>
    <div class="custom-modal-dialog">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <div class="modalCard-img">
                    <!-- <i class="fas fa-check-circle"></i> -->
                </div>
            </div>
            <div class="custom-modal-body">
                <div class="modal-lesson-content">
                     <div class="enrollment-prompt">
                        <div class="alertTitle text-center">Enrollment Successful!</div>
                        <p class="alertCaption text-center mb-4">You have successfully enrolled in this lesson. <br> You can now access the lecture materials.</p>
                    </div>
                </div>
            </div>
            <div class="submitButton custom-modal-footer">
                <button type="button" class="btnSubmit btn btn-primary success-ok-btn">OK</button>
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
                    <h6> Number of Lesson Creation Limit Already Reached! Subscribe to Gakumon to create more lessons! </h6>
            <div class="submitButton custom-modal-footer-input">
               <a href="subscription.php"><button type="button" class="btnSubmit btn btn-primary">Subscribe Now!</button></a>
               <button type="button" class="exitButton btn btn-secondary" onclick="closeSubscribeModal(this)">Cancel</button>

            </div>
        </div>
    </div>
</div>

<!-- GAKUSENSEI PART ONLY!!! -->
<?php

if (($_SESSION['sUserRole'] ?? null) === 'Gakusensei' && ($needsBankInfo ?? false)):

?>

<!-- Gakusensei Bank Information Modal -->
<div class="gakusensei-modal" id="gakusenseiBankModal">
    <div class="gakusensei-modal-content">
        <div class="custom-modal-header">
            <div class="modalCard-img">
            </div>
            <!-- <button type="button" class="custom-modal-close" aria-label="Close">
                <i class="fas fa-times"></i>
            </button> -->
        </div>

        <div class="gakusensei-modal-body">
            <!-- Success Message
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i> 
                <strong>Congratulations!</strong> You've been approved as a Gakusensei. Set up your payment information to start earning.
            </div> -->
            <h3 class="cardLesson-title">Welcome, Sensei!</h3>
            <p class="cardLesson-description">Set up your payment information to receive earnings from your lessons</p>
            
            <form method="post" action="include/saveBankInfo.inc.php" name="gakusenseiBankForm" enctype="multipart/form-data">
                <div class="form-group row mt-3">
                    <!-- First Name -->
                    <div class="col-md-6 mb-3">
                        <label for="firstName" class="form-label">First Name</label>
                        <input type="text" class="form-control" name="firstName" id="firstName"
                            value="<?php echo htmlspecialchars($bankInfo['account_first_name'] ?? ''); ?>" 
                            placeholder="Enter your first name" required>
                    </div>
                    
                    <!-- Last Name -->
                    <div class="col-md-6 mb-3">
                        <label for="lastName" class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="lastName" id="lastName"
                            value="<?php echo htmlspecialchars($bankInfo['account_last_name'] ?? ''); ?>"
                            placeholder="Enter your last name" required>
                    </div>
                </div>

                <div class="form-group row">
                    <!-- Bank Selection -->
                    <div class="col-md-6 mb-3">
                        <label for="bankName" class="form-label">Bank Name</label>
                        <select class="form-control" name="bankName" id="bankName" required>
                            <option value="">Select your bank</option>
                            <option value="bpi">BPI (Bank of the Philippine Islands)</option>
                            <option value="bdo">BDO (Banco de Oro)</option>
                            <option value="metrobank">Metrobank</option>
                            <option value="landbank">Land Bank of the Philippines</option>
                            <option value="pnb">PNB (Philippine National Bank)</option>
                            <option value="security_bank">Security Bank</option>
                            <option value="unionbank">UnionBank</option>
                            <option value="china_bank">China Bank</option>
                            <option value="rcbc">RCBC</option>
                            <option value="other">Other Bank</option>
                        </select>
                    </div>
                    
                    <!-- Account Number -->
                    <div class="col-md-6 mb-3">
                        <label for="accountNumber" class="form-label">Account Number</label>
                        <input type="text" class="form-control" name="accountNumber" id="accountNumber"
                            placeholder="Enter your account number" required>
                    </div>
                </div>

                <div class="form-group row">
                    <!-- Account Type -->
                    <div class="col-md-6 mb-3">
                        <label for="accountType" class="form-label">Account Type</label>
                        <select class="form-control" name="accountType" id="accountType" required>
                            <option value="">Select account type</option>
                            <option value="savings">Savings Account</option>
                            <option value="checking">Checking Account</option>
                            <option value="current">Current Account</option>
                        </select>
                    </div>
                    
                    <!-- Mobile Number for Verification -->
                    <div class="col-md-6 mb-3">
                        <label for="mobileNumber" class="form-label">Mobile Number</label>
                        <input type="tel" class="form-control" name="mobileNumber" id="mobileNumber"
                            placeholder="09XX-XXX-XXXX" required>
                    </div>
                </div>

                <div class="form-group row">
                    <!-- QR Code Upload -->
                    <div class="col-12 mb-3">
                        <label for="qrCode" class="form-label">Bank QR Code (Optional)</label>
                        <input type="file" class="form-control" name="qrCode" id="qrCode"
                            accept=".jpg,.jpeg,.png,.gif">
                        <small class="form-text text-muted">Upload a QR code for your bank account if available (Max 2MB)</small>
                    </div>
                </div>

                <div class="form-group row">
                    <!-- Terms Agreement -->
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input terms" type="checkbox" name="termsAgreement" id="termsAgreement" required>
                            <label class="form-check-label cardLesson-description" for="termsAgreement">
                                I agree to the <a class="terms" href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> and confirm that the provided bank details are accurate
                            </label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="submitButton gakusensei-modal-footer">
            <button type="button" class="btnSubmit btn btn-primary" id="save-bank-info-btn">Save</button>
            <button type="button" class="exitButton btn btn-outline-secondary" id="remind-later-btn">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
   // Pass PHP lessons array to JS
   const lessons = {
      all: <?php echo json_encode($lessons); ?>,
      // You can filter by topic in JS if needed
   };
    const currentUserRole = '<?php echo $_SESSION['sUserRole'] ?? ''; ?>';
   const isUserPremium = <?php echo $is_premium ? 'true' : 'false'; ?>;
</script>

<?php include 'include/footer.php'; ?>
<script src="JS/desktop/petEnergy.js"></script>