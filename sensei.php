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
    $stmt->close();
    }


   $pageTitle = 'GAKUMON';
   $pageCSS = 'CSS/desktop/homepageStyle.css';
   $pageJS = 'JS/desktop/senseiScript.js';

   include 'include/header.php';
   require_once 'config/config.php'; // Database Connection

//    if (isset($_SESSION['sUser'])) {
//       $username = $_SESSION['sUser'];

//       // Get UserID from database
//       $stmt = $connection->prepare("SELECT user_id FROM tbl_user WHERE username = ?");
//       $stmt->bind_param("s", $username);
//       $stmt->execute();
//       $result = $stmt->get_result();

//       if ($row = $result->fetch_assoc()) {
//          $userID = $row['user_id'];   // Now you have the userID
//       } else {
//          echo "User not found.";
//          exit;
//       }

//    } else {
//       echo "User not logged in.";
//       header("Location: login.php");
//       exit;
//    }

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

         $lessons[] = [
               'id' => $row['lesson_id'],
               'title' => $row['title'],
               'short_desc' => $row['short_desc'],
               'long_desc' => $row['long_desc'],
               'duration' => $row['duration'],
               'author_id' => $row['author_id'],
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
   

   include 'include/desktopNav.php';
?>

<!-- Main layout with three columns -->
<div class="main-layout">
   <!-- Left navigation (already fixed by your CSS) -->
   
   <!-- Middle content area -->
   <div class="content-area">

      <!-- Page content below the search bar -->
      <div class="container-fluid page-content">
         <div class="tabs-container">

            <!-- GAKUSENSEI DASHBOARD -->
<!-- GAKUSENSEI DASHBOARD -->
<!-- GAKUSENSEI DASHBOARD -->
<?php if (isset($_SESSION['sUserRole']) && $_SESSION['sUserRole'] === 'Gakusensei'): 
    
    // Fetch real data from database
    // 1. Total Students (unique students enrolled in creator's lessons)
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
    
    // 3. Total Daily Enrollments (enrollments in the last 24 hours)
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
    
    // 4. Revenue Earned - Get from tbl_creator_earnings (sum of earned_amount for this user)
    $revenueStmt = $connection->prepare("
        SELECT COALESCE(SUM(earned_amount), 0) as total_revenue_earned
        FROM tbl_creator_earnings 
        WHERE user_id = ?
    ");
    $revenueStmt->bind_param("i", $userID);
    $revenueStmt->execute();
    $revenueResult = $revenueStmt->get_result();
    $revenueEarned = $revenueResult->fetch_assoc()['total_revenue_earned'] ?? 0;
    $revenueStmt->close();
    
    // 5. Monthly Earnings (earnings from the current month)
    $monthlyStmt = $connection->prepare("
        SELECT COALESCE(SUM(earned_amount), 0) as monthly_earnings 
        FROM tbl_creator_earnings 
        WHERE user_id = ? 
        AND MONTH(recorded_at) = MONTH(CURRENT_DATE())
        AND YEAR(recorded_at) = YEAR(CURRENT_DATE())
    ");
    $monthlyStmt->bind_param("i", $userID);
    $monthlyStmt->execute();
    $monthlyResult = $monthlyStmt->get_result();
    $monthlyEarnings = $monthlyResult->fetch_assoc()['monthly_earnings'] ?? 0;
    $monthlyStmt->close();
    
    // 6. Completion Rate (average quiz completion rate for creator's lessons)
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
    
    // Next payout date (15th of next month)
    $nextPayout = date('M j, Y', strtotime('first day of next month +14 days'));
?>
    <div class="gakusensei-dashboard">
        <h4 class="dashboard-title"><i class="fas fa-chalkboard-teacher"></i> &nbsp; Gakusensei Dashboard</h4>
        
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
                    <p>Daily Enrollments</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <h3>₱<?php echo number_format($revenueEarned, 2); ?></h3>
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
                    <span class="revenue-amount">₱<?php echo number_format($monthlyEarnings, 2); ?></span>
                </div>
                <div class="revenue-item">
                    <span>All Time:</span>
                    <span class="revenue-amount">₱<?php echo number_format($revenueEarned, 2); ?></span>
                </div>
                <div class="revenue-item">
                    <span>Next Payout:</span>
                    <span class="revenue-amount"><?php echo $nextPayout; ?></span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
         </div>
      </div>
   </div>
   
   <?php include 'include/petPanel.php'; ?>
</div>


<!-- GAKUSENSEI PART ONLY!!! -->
<?php 
// Only show bank modal if user is Gakusensei AND hasn't submitted bank info yet
if (isset($_SESSION['sUserRole']) && $_SESSION['sUserRole'] === 'Gakusensei' && empty($bankInfo)): 
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
</script>

<?php include 'include/footer.php'; ?>
<script src="JS/desktop/petEnergy.js"></script>
