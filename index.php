<?php
    session_start();

    $pageTitle = 'GAKUMON';
    $pageCSS = '../CSS/desktop/indexStyle.css';

    include 'include/header.php';
    require_once 'config/config.php';

    if(isset($_SESSION['sUser'])) {
        header("Location: homepage.php");
        exit;
    }
?>


<div class="transition-overlay"></div>
<div class="container page-contents">

    <div class="login-container">
        <a href="login.php" class="btn-login">
            <i class="bi bi-person-circle me-2"></i>Login
        </a>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="logo-container d-flex justify-content-center mb-4">
                    <img src="IMG/Logos/logo_text_portrait_red.png" alt="Logo" class="logo img-fluid">
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div class="d-grid gap-2">
                            <a href="signup.php" class="btn btn-lg start">Get Started <span class="arrow"></span></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>