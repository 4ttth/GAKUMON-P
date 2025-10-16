<?php
    // Get the current page name to determine active nav item
    $currentPage = basename($_SERVER['PHP_SELF']);
    
    // Initialize all active variables
    $activeDashboard = '';
    // $activeLessonManagement = '';
    $activeAccountManagement = '';
    // $activeLessonReviewer = '';
    $activeMaterialsModeration = '';
    $activeQuizAnalytics = '';
    $activePetCustomization = '';
    $activeActivityLogs = '';
    $activeGakusenseiVerification = '';
    $activeGakusenseiPayout = '';

    // Set active class based on current page
    switch($currentPage) {
        case 'kanriDashboard.php':
        case 'admin_dashboard.php':
            $activeDashboard = 'active';
            break;
        // case 'kanriLessonManagement.php':
        //     $activeLessonManagement = 'active';
        //     break;
        case 'account_management.php':
            $activeAccountManagement = 'active';
            break;
        // case 'kanriLessonReviewer.php':
        //     $activeLessonReviewer = 'active';
        //     break;
        case 'materials_moderation.php':
            $activeMaterialsModeration = 'active';
            break;
        // case 'kanriQuizAnalytics.php':
        //     $activeQuizAnalytics = 'active';
        //     break;
        case 'pet_customization.php':
            $activePetCustomization = 'active';
            break;
        case 'activity_logs.php':
            $activeActivityLogs = 'active';
            break;
        case 'gakusensei_verification.php':
            $activeGakusenseiVerification = 'active';
            break;
        case 'gakusenseiPayout.php':
            $activeGakusenseiPayout = 'active';
            break;
    }
?>

<nav class="side-navbar">
    <!-- Logo -->
    <div class="nav-logo">
        <img src="IMG/Logos/logo_text_landscape_white.png" alt="Gakumon Logo">
    </div>
    
    <!-- Navigation Items -->
    <div class="nav-items">
        <a href="admin_dashboard.php" class="nav-item <?php echo $activeDashboard; ?>">
            <span class="nav-text">Dashboard</span>
        </a>
        <!-- <a href="lesson_management.php" class="nav-item <?php echo $activeLessonManagement; ?>">
            <span class="nav-text">Lesson Management</span>
        </a> -->
        <a href="account_management.php" class="nav-item <?php echo $activeAccountManagement; ?>">
            <span class="nav-text">Account Management</span>
        </a>
        <!-- <a href="lesson_reviewer.php" class="nav-item <?php echo $activeLessonReviewer; ?>">
            <span class="nav-text">Lesson & Reviewer</span>
        </a> -->
        <a href="materials_moderation.php" class="nav-item <?php echo $activeMaterialsModeration; ?>">
            <span class="nav-text">Materials Moderation</span>
        </a>
        <!-- <a href="quiz_analytics.php" class="nav-item <?php echo $activeQuizAnalytics; ?>">
            <span class="nav-text">Quiz Analytics</span>
        </a> -->
        <a href="pet_customization.php" class="nav-item <?php echo $activePetCustomization; ?>">
            <span class="nav-text">Pet Customization</span>
        </a>
        <a href="activity_logs.php" class="nav-item <?php echo $activeActivityLogs; ?>">
            <span class="nav-text">Activity Logs</span>
        </a>
        <a href="gakusensei_verification.php" class="nav-item <?php echo $activeGakusenseiVerification; ?>">
            <span class="nav-text">Gakusensei Verification</span>
        </a>
        <a href="gakusenseiPayout.php" class="nav-item <?php echo $activeGakusenseiPayout; ?>">
            <span class="nav-text">Gakusensei Payout</span>
        </a>
    </div>

     <!-- Account Section with Dropdown -->
    <div class="account-section">
        <button class="account-btn" id="accountDropdownBtn">
            <i class="bi bi-person-circle account"></i>
        </button>
        
        <!-- Dropdown Menu -->
        <div class="account-dropdown" id="accountDropdown">
            <div class="dropdown-user-info">
                <div class="dropdown-email"><?php echo $_SESSION['sEmail'] ?? 'admin@example.com'; ?></div>
                <div class="dropdown-username"><?php echo $_SESSION['sUser'] ?? 'Administrator'; ?></div>
            </div>
            
            <!-- <div class="dropdown-divider"></div> -->
            
            <!-- <a href="system_settings.php" class="dropdown-item">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a> -->
            
            <!-- <div class="dropdown-divider"></div> -->
            
            <!-- <a href="faqs.php" class="dropdown-item">
                <i class="bi bi-question-circle"></i>
                <span>FAQs</span>
            </a> -->
            
            <!-- <div class="dropdown-divider"></div> -->
            
            <!-- <a href="contactUs.php" class="dropdown-item">
                <i class="bi bi-envelope"></i>
                <span>Contact Us</span>
            </a> -->
            
            <!-- <div class="dropdown-divider"></div> -->
            
            <!-- <a href="#" class="dropdown-item">
                <i class="bi bi-file-text"></i>
                <span>Legal Agreement</span>
            </a> -->
            
            <div class="dropdown-divider"></div>
            
            <a href="config/logoutAccount.php" class="dropdown-logout">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</nav>