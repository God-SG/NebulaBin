<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

$errors = [];

// Check rate limiting
if (!Security::checkRateLimit('login', $_SERVER['REMOTE_ADDR'], 5, 900)) {
    $errors[] = "Too many login attempts. Please try again in 15 minutes.";
}

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();

// Generate captcha after 3 failed attempts
$show_captcha = isset($_SESSION['failed_login_attempts']) && $_SESSION['failed_login_attempts'] >= 3;
$captcha_question = '';
if ($show_captcha) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || !isset($_SESSION['captcha_question'])) {
        $captcha_question = Captcha::generateMathCaptcha();
    } else {
        $captcha_question = $_SESSION['captcha_question'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    // Verify CSRF token
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    }
    
    // Verify captcha if required
    if ($show_captcha) {
        if (!Captcha::verifyCaptcha($_POST['captcha_answer'] ?? '')) {
            $errors[] = "Incorrect captcha answer.";
            // Generate new captcha after failed attempt
            $captcha_question = Captcha::generateMathCaptcha();
        } else {
            // Clear captcha on success
            Captcha::clearCaptcha();
        }
    }
    
    if (empty($errors)) {
        $login = Security::sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($login) || empty($password)) {
            $errors[] = "Please enter both username/email and password.";
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT id, username, password_hash, role, upgrade_tier FROM users WHERE username = ? OR email = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && Security::verifyPassword($password, $user['password_hash'])) {
                // Reset failed attempts and clear captcha
                unset($_SESSION['failed_login_attempts']);
                Captcha::clearCaptcha();
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['upgrade_tier'] = $user['upgrade_tier'];
                
                header('Location: home.php');
                exit;
            } else {
                // Increment failed attempts
                $_SESSION['failed_login_attempts'] = ($_SESSION['failed_login_attempts'] ?? 0) + 1;
                $errors[] = "Invalid username/email or password.";
                
                // Show captcha after 3 failed attempts
                if ($_SESSION['failed_login_attempts'] >= 3) {
                    $show_captcha = true;
                    $captcha_question = Captcha::generateMathCaptcha();
                }
            }
        }
    }
}

// Get user count for nav
$database = new Database();
$db = $database->getConnection();
$user_count_query = "SELECT COUNT(*) as count FROM users";
$user_count_stmt = $db->prepare($user_count_query);
$user_count_stmt->execute();
$user_count = $user_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NebulaBin</title>
    <link rel="stylesheet" href="assets/css/doxbin-style.css">
    <style>
        .captcha-container {
            background: #222;
            padding: 15px;
            border: 1px solid #333;
            border-radius: 5px;
            margin: 15px 0;
        }
        .captcha-question {
            font-size: 18px;
            font-weight: bold;
            color: #4a9eff;
            margin-bottom: 10px;
        }
        .security-notice {
            background: rgba(255, 170, 68, 0.1);
            border: 1px solid #ffaa44;
            color: #ffaa44;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="home.php" class="logo">NebulaBin</a>
            <ul class="nav-menu">
                <li><a href="home.php">Home</a></li>
                <li><a href="add-paste.php">Add Paste</a></li>
                <li><a href="users.php">Users</a></li>
                <li><a href="upgrades.php">Upgrades</a></li>
                <li><a href="hall-of-autism.php">Hall of Autism</a></li>
                <li><a href="support.php">Support</a></li>
            </ul>
            <div class="nav-right">
                <span class="user-count"><?php echo number_format($user_count); ?></span>
                <a href="register.php">Register</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="form-container">
            <h2 style="text-align: center; margin-bottom: 20px; color: #fff;">Login to NebulaBin</h2>
            
            <?php if (isset($_SESSION['failed_login_attempts']) && $_SESSION['failed_login_attempts'] >= 2): ?>
                <div class="security-notice">
                    ⚠️ Multiple failed login attempts detected. Additional security measures are now active.
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="message message-error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label class="form-label">Username or Email</label>
                    <input type="text" name="username" class="form-input" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" required>
                </div>

                <?php if ($show_captcha): ?>
                <div class="captcha-container">
                    <label class="form-label">Security Check</label>
                    <div class="captcha-question"><?php echo htmlspecialchars($captcha_question); ?></div>
                    <input type="number" name="captcha_answer" class="form-input" required 
                           placeholder="Enter the answer" style="width: 150px;">
                    <div style="color: #888; font-size: 12px; margin-top: 5px;">
                        Multiple failed attempts detected. Please solve this math problem.
                    </div>
                </div>
                <?php endif; ?>

                <button type="submit" class="form-btn">Login</button>
            </form>

            <div style="text-align: center; margin-top: 20px;">
                <p style="color: #888; font-size: 13px;">
                    Don't have an account? <a href="register.php" style="color: #4a9eff;">Register here</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
