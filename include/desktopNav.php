<?php
    // Get the current page name to determine active nav item
    $currentPage = basename($_SERVER['PHP_SELF']);
    $activeHome = '';
    $activeLessons = '';
    $activeQuizzes = '';
    $activeSensei = '';

    // Set active class based on current page
    switch($currentPage) {
        case 'homepage.php':
            $activeHome = 'active';
            break;
        case 'lessons.php':
            $activeLessons = 'active';
            break;
        case 'quizzes.php':
            $activeQuizzes = 'active';
            break;
        case 'sensei.php': // Add this for the Sensei page
            $activeSensei = 'active';
            break;
    }


    // Determine which logo to show based on user role and subscription
    $logoPath = "IMG/Logos/logo_text_landscape_white.png"; // Default logo
   
    if (isset($_SESSION['sUserRole'])) {
        if ($_SESSION['sUserRole'] === 'Gakusensei') {
            $logoPath = "IMG/Logos/sensei.png"; // Sensei logo
        } else if ($_SESSION['sUserRole'] === 'Gakusei') {
            // Check subscription type for Gakusei
            if (isset($_SESSION['sSubscription']) && $_SESSION['sSubscription'] === 'Premium') {
                $logoPath = "IMG/Logos/premium.png"; // Premium student logo
            } else {
                $logoPath = "IMG/Logos/logo_text_landscape_white.png"; // Free student logo
            }
        }
    }
?>


<nav class="side-navbar">
    <!-- Logo - Dynamic based on user role and subscription -->
    <div class="nav-logo">
        <img src="<?php echo $logoPath; ?>" alt="Gakumon Logo">
    </div>
    
    <!-- Navigation Items -->
    <div class="nav-items">
        <a href="homepage.php" class="nav-item <?php echo $activeHome; ?>">
            <span class="nav-text">Home</span>
            <i class="bi bi-house-door-fill"></i>
        </a>
        <a href="lessons.php" class="nav-item <?php echo $activeLessons; ?>">
            <span class="nav-text">Lessons</span>
            <i class="bi bi-book-half"></i>
        </a>
        <a href="quizzes.php" class="nav-item <?php echo $activeQuizzes; ?>">
            <span class="nav-text">Quizzes</span>
            <i class="bi bi-lightbulb-fill"></i>
        </a>
        
        <!-- Sensei Nav Item - Only show for Gakusensei users -->
        <?php if (isset($_SESSION['sUserRole']) && $_SESSION['sUserRole'] === 'Gakusensei'): ?>
        <a href="sensei.php" class="nav-item <?php echo $activeSensei; ?>">
            <span class="nav-text">Sensei</span>
            <i class="bi bi-person-badge-fill"></i>
        </a>
        <?php endif; ?>
    </div>

     <!-- Account Section with Dropdown -->
    <div class="account-section">
        <button class="account-btn" id="accountDropdownBtn">
            <i class="bi bi-person-circle account"></i>
        </button>
        
        <!-- Dropdown Menu -->
        <div class="account-dropdown" id="accountDropdown">
            <div class="dropdown-user-info">
                <!-- <div class="dropdown-email"><?php echo $_SESSION['sEmail'] ?? 'user@example.com'; ?></div> -->
                <div class="dropdown-username"><?php echo $_SESSION['sUser'] ?? 'slwyntlr'; ?></div>
            </div>
            
            <div class="dropdown-divider"></div>
            
            <!-- <a href="#" class="dropdown-item">
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
            
            <div class="dropdown-divider"></div>
            
            <!-- <a href="#" class="dropdown-item">
                <i class="bi bi-file-text"></i>
                <span>Legal Agreement</span>
            </a> -->
            
            <div class="dropdown-divider"></div>

            <?php if (!isset($_SESSION['sUserRole']) || $_SESSION['sUserRole'] !== 'Gakusensei'): ?>
                <a href="gakusensei.php" class="dropdown-item">
                    <i class="bi bi-file-text"></i>
                    <span>Become one of us</span>
                </a>
            <?php endif; ?>

            <!-- Another div tab for Subscription -->
            <?php if (!isset($_SESSION['sSubscription']) || $_SESSION['sSubscription'] !== 'Premium'): ?>
                <a href="subscription.php" class="dropdown-item">
                    <i class="bi bi-star-fill"></i>
                    <span>Subscribe Now</span>
                </a>
            <?php else: ?>
                <a href="subscription.php" class="dropdown-item">
                    <i class="bi bi-star-fill text-warning"></i>
                    <span>Premium Member</span>
                </a>
            <?php endif; ?>
            
            <div class="dropdown-divider"></div>
            
            <a href="config/logoutAccount.php" class="dropdown-logout">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</nav>