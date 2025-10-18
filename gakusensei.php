<?php
   session_start();
   require_once 'config/config.php'; // Database Connection

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
        $pageCSS = 'CSS/mobile/gakusenseiStyle.css';
        $pageJS = 'JS/mobile/gakusenseiScript.js';
    } else {
        $pageCSS = 'CSS/desktop/gakusenseiStyle.css';
        $pageJS = 'JS/desktop/gakusenseiScript.js';
    }

   include 'include/header.php';

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

    // Check if user just logged in (you might need to set this flag after successful login)
    if (!isset($_SESSION['login_shown'])) {
        $_SESSION['login_shown'] = true;
        // This ensures the modal only shows once after login
    }

    // For GAKUSENSEI Bank Details
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

    // MOBILE or DESKTOP includes
    if ($isMobile) {
        include 'include/mobileNav.php';
    } else {
        include 'include/desktopNav.php';
    }
?>

<?php if ($isMobile): ?>
    <!-- MOBILE LAYOUT FOR GAKUSENSEI PAGE -->
    <div class="main-layout">
        <div class="content-area">
            <div class="container-fluid page-content">
                <!-- Title Card Section -->
                <div class="hero-section">
                    <div class="hero-image">
                        <img src="IMG/Logos/sensei_logo.png" alt="Gakusensei Educator">
                    </div>
                    <div class="hero-content">
                        <h1 class="hero-title">Share Your Expertise. Profit With Ease.</h1>
                        <p class="hero-subtitle">Turn your knowledge into impact and income. Join our exclusive community of verified educators on Gakumon.</p>
                    </div>
                </div>

                <!-- Stats Section -->
                <div class="stats-container">
                    <div class="stat-item">
                        <div class="stat-number">500+</div>
                        <div class="stat-label">Active Gakusensei</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱25K+</div>
                        <div class="stat-label">Paid to Creators</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">10K+</div>
                        <div class="stat-label">Lessons Created</div>
                    </div>
                    <div class="stat-item last">
                        <div class="stat-number">95%</div>
                        <div class="stat-label">Satisfaction Rate</div>
                    </div>
                </div>

                <!-- Introduction Section -->
                <div class="section">
                    <h2 class="section-title">What is the Gakusensei Program?</h2>
                    <div class="intro-text">
                        <p>Welcome to <strong>Gakusensei</strong> (がく先生), the premier content creator program for passionate experts and educators.</p>
                        <p>Whether you're a programming wizard, history buff, language maestro, or science enthusiast, your expertise has a home here. Our program empowers you to create high-quality lessons and quizzes, share them with our community, and earn real revenue.</p>
                        <p>Think of it as your own digital classroom, with our platform handling the technology, audience, and payments.</p>
                    </div>
                </div>

                <!-- Benefits Section -->
                <div class="section">
                    <h2 class="section-title">Why Become a Gakusensei?</h2>
                    <div class="benefits-grid">
                        <div class="benefit-card">
                            <div class="benefit-icon"><i class="bi bi-cash-coin"></i></div>
                            <div class="benefit-content">
                                <h3>Monetize Your Passion</h3>
                                <p class="benefit-text">Earn real money as a rewarding side hustle.</p>
                            </div>
                        </div>
                        <div class="benefit-card">
                            <div class="benefit-icon"><i class="bi bi-trophy-fill"></i></div>
                            <div class="benefit-content">
                                <h3>Build Credibility</h3>
                                <p class="benefit-text">Become a verified expert on a respected platform.</p>
                            </div>
                        </div>
                        <div class="benefit-card">
                            <div class="benefit-icon"><i class="bi bi-person-hearts"></i></div>
                            <div class="benefit-content">
                                <h3>Reach Audience</h3>
                                <p class="benefit-text">Teach motivated learners without building from scratch.</p>
                            </div>
                        </div>
                        <div class="benefit-card">
                            <div class="benefit-icon"><i class="bi bi-lightbulb-fill"></i></div>
                            <div class="benefit-content">
                                <h3>Make Impact</h3>
                                <p class="benefit-text">Shape minds and become a trusted knowledge source.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- How It Works Section -->
                <div class="section">
                    <h2 class="section-title">How It Works: The Gakusensei Journey</h2>
                    <div class="how-it-works">
                        <div class="step-card">
                            <div class="step-number">1</div>
                            <h3 class="step-title">Apply & Get Verified</h3>
                            <p>Submit your application showcasing your expertise and credentials.</p>
                        </div>
                        <div class="step-card">
                            <div class="step-number">2</div>
                            <h3 class="step-title">Create & Publish</h3>
                            <p>Use our tools to develop lessons, lectures, and quizzes.</p>
                        </div>
                        <div class="step-card">
                            <div class="step-number">3</div>
                            <h3 class="step-title">Engage & Grow</h3>
                            <p>Interact with students and promote your classes.</p>
                        </div>
                        <div class="step-card">
                            <div class="step-number">4</div>
                            <h3 class="step-title">Earn Revenue</h3>
                            <p>Get paid based on enrollments and engagement metrics.</p>
                        </div>
                    </div>
                </div>

                <!-- Payment Section -->
                <div class="section">
                    <h2 class="section-title">How Do You Get Paid?</h2>
                    <div class="benefits-grid">
                        <div class="benefit-card">
                            <div class="benefit-icon"><i class="bi bi-graph-up-arrow"></i></div>
                            <div class="benefit-content">
                                <h3>Revenue Share</h3>
                                <p class="benefit-text">Earn a share of revenue from your content's performance.</p>
                            </div>
                        </div>
                        <div class="benefit-card">
                            <div class="benefit-icon"><i class="bi bi-calendar-check"></i></div>
                            <div class="benefit-content">
                                <h3>Monthly Payouts</h3>
                                <p class="benefit-text">Earnings are tallied monthly and sent to your preferred method.</p>
                            </div>
                        </div>
                        <div class="benefit-card">
                            <div class="benefit-icon"><i class="bi bi-shield-lock"></i></div>
                            <div class="benefit-content">
                                <h3>Secure Transfers</h3>
                                <p class="benefit-text">We use secure payment gateways for reliable transfers.</p>
                            </div>
                        </div>
                        <div class="benefit-card">
                            <div class="benefit-icon"><i class="bi bi-bar-chart"></i></div>
                            <div class="benefit-content">
                                <h3>Dashboard Analytics</h3>
                                <p class="benefit-text">Track performance and earnings in real-time.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ Section -->
                <div class="section">
                    <h2 class="section-title">Frequently Asked Questions</h2>
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="eligibilityHeading">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#eligibilityCollapse" aria-expanded="false" aria-controls="eligibilityCollapse">
                                    Who is eligible to become a Gakusensei?
                                </button>
                            </h2>
                            <div id="eligibilityCollapse" class="accordion-collapse collapse" aria-labelledby="eligibilityHeading" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    To maintain quality, we require Gakusensei to be subject matter experts with proven knowledge, verified users in good standing, effective communicators who can explain complex topics, and committed to creating accurate, engaging content.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="earningsHeading">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#earningsCollapse" aria-expanded="false" aria-controls="earningsCollapse">
                                    How much can I earn as a Gakusensei?
                                </button>
                            </h2>
                            <div id="earningsCollapse" class="accordion-collapse collapse" aria-labelledby="earningsHeading" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Earnings vary based on content quality and engagement. Top creators earn $500-$2000 monthly, with some exceeding $5000 during peak seasons.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="paymentsHeading">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#paymentsCollapse" aria-expanded="false" aria-controls="paymentsCollapse">
                                    How often are payments made?
                                </button>
                            </h2>
                            <div id="paymentsCollapse" class="accordion-collapse collapse" aria-labelledby="paymentsHeading" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Payments are processed monthly, around the 15th of each month for the previous month's earnings.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="topicsHeading">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#topicsCollapse" aria-expanded="false" aria-controls="topicsCollapse">
                                    What topics are most popular?
                                </button>
                            </h2>
                            <div id="topicsCollapse" class="accordion-collapse collapse" aria-labelledby="topicsHeading" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Programming, language learning, test preparation, and professional skills are consistently popular, but we welcome all quality educational content.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CTA Section -->
                <div class="cta-section">
                    <h2 class="cta-title">Ready to Inspire the Next Generation?</h2>
                    <button type="button" class="cta-button btn btn-secondary">Apply to Become a Gakusensei</button>
                    <p style="margin-top: 20px; opacity: 0.9;" class="intro-text">Have questions? Contact our creator support team at gakusensei-support@gakumon.com</p>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- DESKTOP LAYOUT FOR GAKUSENSEI PAGE -->
    <div class="main-layout">
        <!-- Middle content area -->
        <div class="content-area">
            <div class="container-fluid page-content">
                <!-- Title Card Section -->
                <div class="hero-section">
                    <div class="hero-image">
                        <img src="IMG/Logos/sensei_logo.png" alt="Gakusensei Educator">
                    </div>
                    <div class="hero-content">
                        <h1 class="hero-title">Share Your Expertise. <br> Profit With Ease.</h1>
                        <p class="hero-subtitle">Turn your knowledge into impact and income. <br> Join our exclusive community of verified educators on Gakumon.</p>
                    </div>
                </div>

                <!-- Stats Section -->
                <div class="stats-container">
                    <div class="stat-item">
                        <div class="stat-number">500+</div>
                        <div class="stat-label">Active Gakusensei</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">$50K+</div>
                        <div class="stat-label">Paid to Creators</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">10K+</div>
                        <div class="stat-label">Lessons Created</div>
                    </div>
                    <div class="stat-item last">
                        <div class="stat-number">95%</div>
                        <div class="stat-label">Satisfaction Rate</div>
                    </div>
                </div>

                <!-- Two Column Layout: Intro + Benefits -->
                <div class="two-column-section">
                    <!-- Introduction Section -->
                    <div class="section">
                        <h2 class="section-title">What is the Gakusensei Program?</h2>
                        <div class="intro-text">
                            <p>Welcome to <strong>Gakusensei</strong> (がく先生), the premier content creator program for passionate experts and educators.</p>
                            <p>Whether you're a programming wizard, history buff, language maestro, or science enthusiast, your expertise has a home here. Our program empowers you to create high-quality lessons and quizzes, share them with our community, and earn real revenue.</p>
                            <p>Think of it as your own digital classroom, with our platform handling the technology, audience, and payments.</p>
                        </div>
                    </div>

                    <!-- Benefits Section -->
                    <div class="section">
                        <h2 class="section-title">Why Become a Gakusensei?</h2>
                        <div class="benefits-grid">
                            <div class="benefit-card">
                                <div class="benefit-icon"><i class="bi bi-cash-coin"></i></div>
                                <div class="benefit-content">
                                    <h3>Monetize Your Passion</h3>
                                    <p class="benefit-text">Earn real money as a rewarding side hustle.</p>
                                </div>
                            </div>
                            <div class="benefit-card">
                                <div class="benefit-icon"><i class="bi bi-trophy-fill"></i></div>
                                <div class="benefit-content">
                                    <h3>Build Credibility</h3>
                                    <p class="benefit-text">Become a verified expert on a respected platform.</p>
                                </div>
                            </div>
                            <div class="benefit-card">
                                <div class="benefit-icon"><i class="bi bi-person-hearts"></i></div>
                                <div class="benefit-content">
                                    <h3>Reach Audience</h3>
                                    <p class="benefit-text">Teach motivated learners without building from scratch.</p>
                                </div>
                            </div>
                            <div class="benefit-card">
                                <div class="benefit-icon"><i class="bi bi-lightbulb-fill"></i></div>
                                <div class="benefit-content">
                                    <h3>Make Impact</h3>
                                    <p class="benefit-text">Shape minds and become a trusted knowledge source.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- How It Works Section -->
                <div class="section">
                    <h2 class="section-title">How It Works: The Gakusensei Journey</h2>
                    <div class="how-it-works">
                        <div class="step-card">
                            <div class="step-number">1</div>
                            <h3 class="step-title">Apply & Get Verified</h3>
                            <p>Submit your application showcasing your expertise and credentials.</p>
                        </div>
                        <div class="step-card">
                            <div class="step-number">2</div>
                            <h3 class="step-title">Create & Publish</h3>
                            <p>Use our tools to develop lessons, lectures, <br> and quizzes.</p>
                        </div>
                        <div class="step-card">
                            <div class="step-number">3</div>
                            <h3 class="step-title">Engage & Grow</h3>
                            <p>Interact with students and promote your classes.</p>
                        </div>
                        <div class="step-card">
                            <div class="step-number">4</div>
                            <h3 class="step-title">Earn Revenue</h3>
                            <p>Get paid based on enrollments and engagement metrics.</p>
                        </div>
                    </div>
                </div>

                <!-- Two Column Layout: Payment + Eligibility -->
                <div class="two-column-section">
                    <!-- Payment Section -->
                    <div class="section">
                        <h2 class="section-title">How Do You Get Paid?</h2>
                        <div class="benefits-grid">
                            <div class="benefit-card">
                                <div class="benefit-icon"><i class="bi bi-graph-up-arrow"></i></div>
                                <div class="benefit-content">
                                    <h3>Revenue Share</h3>
                                    <p class="benefit-text">Earn a share of revenue from your content's performance.</p>
                                </div>
                            </div>
                            <div class="benefit-card">
                                <div class="benefit-icon"><i class="bi bi-calendar-check"></i></div>
                                <div class="benefit-content">
                                    <h3>Monthly Payouts</h3>
                                    <p class="benefit-text">Earnings are tallied monthly and sent to your preferred method.</p>
                                </div>
                            </div>
                            <div class="benefit-card">
                                <div class="benefit-icon"><i class="bi bi-shield-lock"></i></div>
                                <div class="benefit-content">
                                    <h3>Secure Transfers</h3>
                                    <p class="benefit-text">We use secure payment gateways for reliable transfers.</p>
                                </div>
                            </div>
                            <div class="benefit-card">
                                <div class="benefit-icon"><i class="bi bi-bar-chart"></i></div>
                                <div class="benefit-content">
                                    <h3>Dashboard Analytics</h3>
                                    <p class="benefit-text">Track performance and earnings in real-time.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FAQ Section -->
                    <div class="section">
                        <h2 class="section-title">Frequently Asked Questions</h2>
                        <div class="accordion" id="faqAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="eligibilityHeading">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#eligibilityCollapse" aria-expanded="false" aria-controls="eligibilityCollapse">
                                        Who is eligible to become a Gakusensei?
                                    </button>
                                </h2>
                                <div id="eligibilityCollapse" class="accordion-collapse collapse" aria-labelledby="eligibilityHeading" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        To maintain quality, we require Gakusensei to be subject matter experts with proven knowledge, verified users in good standing, effective communicators who can explain complex topics, and committed to creating accurate, engaging content.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="earningsHeading">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#earningsCollapse" aria-expanded="false" aria-controls="earningsCollapse">
                                        How much can I earn as a Gakusensei?
                                    </button>
                                </h2>
                                <div id="earningsCollapse" class="accordion-collapse collapse" aria-labelledby="earningsHeading" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Earnings vary based on content quality and engagement. Top creators earn $500-$2000 monthly, with some exceeding $5000 during peak seasons.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="paymentsHeading">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#paymentsCollapse" aria-expanded="false" aria-controls="paymentsCollapse">
                                        How often are payments made?
                                    </button>
                                </h2>
                                <div id="paymentsCollapse" class="accordion-collapse collapse" aria-labelledby="paymentsHeading" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Payments are processed monthly, around the 15th of each month for the previous month's earnings.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="topicsHeading">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#topicsCollapse" aria-expanded="false" aria-controls="topicsCollapse">
                                        What topics are most popular?
                                    </button>
                                </h2>
                                <div id="topicsCollapse" class="accordion-collapse collapse" aria-labelledby="topicsHeading" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Programming, language learning, test preparation, and professional skills are consistently popular, but we welcome all quality educational content.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CTA Section -->
                <div class="cta-section">
                    <h2 class="cta-title">Ready to Inspire the Next Generation?</h2>
                    <button type="button" class="cta-button btn btn-secondary">Apply to Become a Gakusensei</button>
                    <p style="margin-top: 20px; opacity: 0.9;" class="intro-text">Have questions? Contact our creator support team at gakusensei-support@gakumon.com</p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Gakusensei Application Modal -->
<div class="custom-modal" id="gakusenseiModal">
    <div class="custom-modal-backdrop"></div>
    <div class="custom-modal-dialog">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
               <div class="modalCard-img">
               </div>
            </div>
            <div class="custom-modal-body">
                <div class="modal-lesson-content">
                    <h3 class="cardLesson-title">Apply to Become a Gakusensei</h3>
                    <p class="cardLesson-description">Complete the form below to join our community of educators</p>
                    
                    <form method="post" action="include/gakusenseiApplication.inc.php" name="gakusenseiApplication" enctype="multipart/form-data">
                        <div class="form-group row mt-4">
                            <!-- Education Attainment -->
                            <div class="col-12">
                                <label for="education" class="form-label">Education Attainment</label>
                                <select class="form-control" name="education" id="education" required>
                                    <option value="">Select your highest education level</option>
                                    <option value="high_school">High School Diploma</option>
                                    <option value="associate">Associate Degree</option>
                                    <option value="bachelor">Bachelor's Degree</option>
                                    <option value="master">Master's Degree</option>
                                    <option value="doctorate">Doctorate/PhD</option>
                                    <option value="professional">Professional Certification</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group row mt-3">
                            <!-- School/Institution -->
                            <div class="col-12">
                                <label for="school" class="form-label">School/Institution</label>
                                <input type="text" class="form-control" name="school" id="school"
                                    placeholder="Enter your school or institution" required>
                            </div>
                        </div>

                        <div class="form-group row mt-3">
                            <!-- Field of Expertise -->
                            <div class="col-12">
                                <label for="expertise" class="form-label">Field of Expertise</label>
                                <select class="form-control" name="expertise" id="expertise" required>
                                    <option value="">Select your field of expertise</option>
                                    <option value="programming">Programming & Software Development</option>
                                    <option value="web_development">Web Development</option>
                                    <option value="data_science">Data Science & Analytics</option>
                                    <option value="ai_ml">Artificial Intelligence & Machine Learning</option>
                                    <option value="cybersecurity">Cybersecurity</option>
                                    <option value="networking">Networking & Infrastructure</option>
                                    <option value="database">Database Management</option>
                                    <option value="graphic_design">Graphic Design & Multimedia</option>
                                    <option value="it_support">IT Support & Administration</option>
                                    <option value="other">Other Computer Science Field</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group row mt-3">
                            <!-- Proof of Expertise -->
                            <div class="col-12">
                                <label for="proof" class="form-label">Proof of Expertise (PDF, DOC, Image)</label>
                                <input type="file" class="form-control" name="proof" id="proof"
                                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                                <small class="form-text text-muted">Upload your diploma, certificate, or portfolio (Max 5MB)</small>
                            </div>
                        </div>

                        <div class="submitButton custom-modal-footer">
                            <button type="submit" name="submitApplicationBtn" class="btnSubmit btn btn-primary start-lesson-btn" id="submitApplicationBtn">Let's Go!</button>
                            <button type="button" class="exitButton btn btn-secondary custom-modal-close-btn">x</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="applicationToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="fas fa-check-circle text-success me-2"></i>
            <strong class="me-auto">Gakumon</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            Your application has been submitted successfully! We'll review it and get back to you soon.
        </div>
    </div>
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
        </div>

        <div class="gakusensei-modal-body">
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

<?php include 'include/footer.php'; ?>