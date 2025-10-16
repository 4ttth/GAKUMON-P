<?php
    ob_start();
    session_start();
    require_once 'config/config.php';    // Database Connection
    require_once 'include/sendEmail.inc.php';

    // Session Check
    // if(isset($_SESSION['sUser'])) {
    //     header("Location: homepage.php");
    //     exit;
    // }

    if(isset($_POST['signup'])) {
        // Variables for User Input
        $firstName = $connection->real_escape_string($_POST['firstName']);
        $lastName = $connection->real_escape_string($_POST['lastName']);
        $username = $connection->real_escape_string($_POST['username']);
        $emailAddress = $connection->real_escape_string($_POST['email']);
        $pass = $connection->real_escape_string($_POST['password']);
        $confirmPassword = $connection->real_escape_string($_POST['confirmPassword']);
        $otp = generateUniqueOTP($connection);

        // Check if email exists in tbl_user
        $stmt = $connection->prepare("SELECT * FROM tbl_user WHERE email_address = ?");
        $stmt->bind_param("s", $emailAddress);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows > 0) {
            $errors[] = "Email address already registered.";
        }
        $stmt->close();

        // Check if email exists in tbl_pending_verif
        $stmt = $connection->prepare("SELECT * FROM tbl_pending_verif WHERE email_address = ?");
        $stmt->bind_param("s", $emailAddress);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows > 0) {
            $errors[] = "Email address already in verification process.";
        }
        $stmt->close();

        // Username Validation
        if(!preg_match('/^[a-zA-Z0-9]{4,20}$/', $username)) {
            $errors[] = "Username must be 4-20 alphanumeric characters.";
        } else {
            // Check if username exists in tbl_user
            $stmt = $connection->prepare("SELECT 1 FROM tbl_user WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if($result->num_rows > 0) {
                $errors[] = "Username already exists.";
            }
            $stmt->close();

            // Check if username exists in tbl_pending_verif
            $stmt = $connection->prepare("SELECT 1 FROM tbl_pending_verif WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if($result->num_rows > 0) {
                $errors[] = "Username is already in verification process.";
            }
            $stmt->close();
        }


        // Password Validations
        // Length and Case Check
        if (strlen($pass) < 8) {
            $errors[] = "Password must be at least 8 characters.";
        } elseif (!preg_match('/[A-Z]/', $pass) || !preg_match('/[a-z]/', $pass)) {
            $errors[] = "Password must contain both uppercase and lowercase letters.";
        }

        // Confirm Password Check
        if ($pass !== $confirmPassword) {
            $errors[] = "Passwords do not match.";
        }

        // If no errors, proceed with registration
        if(empty($errors)) {
            // Hash the password
            $hashedPass = password_hash($pass, PASSWORD_DEFAULT);

            // Prepare Statement for pending verification
            $stmt = $connection->prepare("INSERT INTO tbl_pending_verif (first_name, last_name, username, email_address, pass, verif_code) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $firstName, $lastName, $username, $emailAddress, $hashedPass, $otp);

            if($stmt->execute()) {
                $stmt->close();
                
                // Prepare email content
                $subject = "GAKUMON - Email Verification";
                $body = "
                    <h2>Welcome to GAKUMON!</h2>
                    <p>Hello $firstName,</p>
                    <p>Your verification code is: <strong>$otp</strong></p>
                    <p>Please enter this code to verify your email address.</p>
                    <p>If you didn't create an account, please ignore this email.</p>
                ";

                // Set session email for verification
                $_SESSION['verification_email'] = $emailAddress;
                
                // Try to send email but don't let it block the redirect
                try {
                    @sendEmail($connection, $emailAddress, $subject, $body);
                } catch (Exception $e) {
                    // Log error but continue with redirect
                    error_log("Email sending failed: " . $e->getMessage());
                }
                
                ob_end_clean();
                header("Location: verifyEmail.php");
                exit();
            } else {
                $errors[] = "Registration failed. Please try again.";
                $stmt->close();
            }
        }
    }
    function generateUniqueOTP($connection, $seed = null, $tableName = 'tbl_pending_verif', $columnName = 'verif_code') {
    // Set the seed for randomization if provided
    if ($seed !== null) {
        if (is_numeric($seed)) {
            mt_srand((int)$seed);
        } else {
            mt_srand(crc32($seed));
        }
    }
   
    $maxAttempts = 100; // Prevent infinite loops
    $attempts = 0;
    $otp = '';
   
    while ($attempts < $maxAttempts) {
        // Generate a 6-digit code
        $otp = sprintf('%06d', mt_rand(0, 999999));
       
        // Check if this OTP already exists in the database
        $stmt = $connection->prepare("SELECT COUNT(*) as count FROM $tableName WHERE $columnName = ?");
        if (!$stmt) {
            throw new Exception("Database prepare statement failed: " . $connection->error);
        }
       
        $stmt->bind_param("s", $otp);
        $stmt->execute();
        $result = $stmt->get_result();
       
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row['count'] == 0) {
                // OTP is unique, return it
                $stmt->close();
               
                // Reset random seed if we changed it
                if ($seed !== null) {
                    mt_srand(); // Return to automatic seeding
                }
               
                return $otp;
            }
        }
       
        $stmt->close();
        $attempts++;
    }
   
    // Reset random seed if we changed it
    if ($seed !== null) {
        mt_srand();
    }
   
    // If we've reached maximum attempts without finding a unique OTP
    throw new Exception("Failed to generate a unique OTP after $maxAttempts attempts");
}

    $pageTitle = 'GAKUMON — Sign up';
    $pageCSS = 'CSS/desktop/signupStyle.css';
    $pageJS = 'JS/desktop/signupScript.js';
    include 'include/header.php';
?>

<!-- <div class="transition-overlay"></div> -->
<div class="container page-contents">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-12">
            <div class="logo-container d-flex justify-content-center mb-4">
                <img src="IMG/Logos/logo_text_landscape_red.png" alt="Logo" class="logo img-fluid">
            </div>
            <div class="card">
                <div class="card-body">
                    <?php if(!empty($errors)): ?>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                // Show all errors in a custom modal
                                const errorMessages = <?php echo json_encode($errors); ?>;
                                const errorList = errorMessages.map(error => `• ${error}`).join('<br>');
                               
                                // Use our custom alert system
                                setTimeout(() => {
                                    customAlerts.showAlert(`
                                        <div style="text-align: left;">
                                            <h4 style="color: #dc3545; margin-bottom: 15px;">Registration Failed</h4>
                                            <div style="color: #6c757d;">${errorList}</div>
                                        </div>
                                    `, 'error');
                                }, 500);
                            });
                        </script>
                    <?php endif; ?>
                   
                    <form method="post" action="signup.php" name="signup">
                        <div class="form-group row mt-2">
                            <!-- First Name -->
                            <div class="col-sm-6">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" name="firstName" id="firstName"
                                    placeholder="Enter first name" required
                                    title="Enter your first name">
                            </div>

                            <!-- Last Name -->
                            <div class="col-sm-6">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="lastName" id="lastName"
                                    placeholder="Enter last name" required
                                    title="Enter your last name">
                            </div>
                        </div>

                        <div class="form-group row mt-3">
                            <!-- Username -->
                            <div class="col-12">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" id="username"
                                    placeholder="Enter username" required
                                    title="4-20 alphanumeric characters"
                                    pattern="[a-zA-Z0-9]{4,20}">
                                <small class="form-text text-muted">4-20 alphanumeric characters</small>
                            </div>
                        </div>

                        <div class="form-group row mt-3">
                            <!-- Email Address -->
                            <div class="col-12">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" id="email"
                                    placeholder="Enter email address" required
                                    title="Enter a valid email address">
                            </div>
                        </div>

                        <div class="form-group row mt-3">
                            <!-- Password -->
                            <div class="col-12">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="password"
                                        placeholder="Enter password" required
                                        title="Minimum 8 characters with both uppercase and lowercase letters"
                                        onkeyup="updatePasswordRequirements()">

                                        <!-- Eye Button -->
                                        <button class="eyebtn btn btn-outline-secondary" type="button" onclick="togglePassword('password', 'toggleIcon')">
                                            <i id="toggleIcon" class="fas fa-eye"></i>
                                        </button>
                                </div>
                                <small class="form-text requirement">
                                    <span id="lengthReq" class="text-danger">✓ At least 8 characters</span>
                                    <span id="caseReq" class="text-danger">✓ Both uppercase and lowercase letters</span>
                                </small>
                            </div>
                        </div>

                        <div class="form-group row mt-3">
                            <!-- Confirm Password -->
                            <div class="col-12">
                                <label for="confirmPassword" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="confirmPassword" id="confirmPassword"
                                        placeholder="Confirm your password" required
                                        title="Please confirm your password"
                                        onkeyup="checkPasswordMatch()">

                                        <!-- Eye Button -->
                                        <button class="eyebtn btn btn-outline-secondary" type="button" onclick="togglePassword('confirmPassword', 'toggleConfirmIcon')">
                                            <i id="toggleConfirmIcon" class="fas fa-eye"></i>
                                        </button>
                                </div>
                                <small id="passwordMatch" class="form-text"></small>
                            </div>
                        </div>

                        <div class="form-group row mt-4">
                            <div class="col-12 submitButton">
                                <button type="submit" name="signup" class="btnSubmit btn btn-lg btn-primary w-100">Let's Go!</button>
                            </div>
                            <div class="loginNote col-12 text-center mt-3">
                                Already have an account? <a href="login.php" class="text-link">Login</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Alert Modal -->
<div class="custom-modal" id="customAlertModal">
    <div class="custom-modal-backdrop"></div>
    <div class="custom-modal-dialog">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <div class="modalCard-img">
                    <i class="fas fa-info-circle" id="alertIcon"></i>
                </div>
            </div>
            <div class="custom-modal-body">
                <div class="modal-lesson-content">
                    <div class="alert-message" id="alertMessage"></div>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" id="alertCancelBtn" style="display: none;">Cancel</button>
                <button type="button" class="btn btn-primary" id="alertOkBtn">OK</button>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>

