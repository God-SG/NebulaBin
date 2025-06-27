<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/premium-functions.php';

$errors = [];
$is_logged_in = isset($_SESSION['user_id']);

// Rate limiting - use IP for anonymous users, user ID for logged in users
$rate_limit_identifier = $is_logged_in ? $_SESSION['user_id'] : $_SERVER['REMOTE_ADDR'];

// More lenient rate limiting for anonymous users - 5 pastes per hour
$max_attempts = $is_logged_in ? 20 : 5;
if (!Security::checkRateLimit('create_paste', $rate_limit_identifier, $max_attempts, 3600)) {
    $errors[] = $is_logged_in ? 
        "You've created too many pastes recently. Please wait before creating more." :
        "Too many pastes from this IP address. Please wait before creating more.";
}

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();

// Generate captcha for non-premium users (always show for anonymous users)
$user_role = $_SESSION['role'] ?? 'user';
$user_tier = $_SESSION['upgrade_tier'] ?? 'none';
$show_captcha = !$is_logged_in || (!isStaff($user_role) && $user_tier === 'none');

if ($show_captcha) {
    $captcha_question = Captcha::generateMathCaptcha();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    // Verify CSRF token
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    }
    
    // Verify captcha for non-premium users
    if ($show_captcha && !Captcha::verifyCaptcha($_POST['captcha_answer'] ?? '')) {
        $errors[] = "Incorrect captcha answer.";
        $captcha_question = Captcha::generateMathCaptcha();
    }
    
    if (empty($errors)) {
        $title = Security::sanitizeInput($_POST['title'] ?? '');
        $content = $_POST['content'] ?? ''; // Don't over-sanitize code content
        $is_private = isset($_POST['is_private']) ? 1 : 0;
        $is_unlisted = isset($_POST['is_unlisted']) ? 1 : 0;
        $password = Security::sanitizeInput($_POST['password'] ?? '');
        
        // Validate input
        if (empty($title) || strlen($title) > 255) {
            $errors[] = "Title is required and must be less than 255 characters.";
        }
        
        if (empty($content) || strlen($content) > 1000000) {
            $errors[] = "Content is required and must be less than 1MB.";
        }
        
        // Check premium features (only for logged in users)
        if ($is_logged_in) {
            if ($is_private && !canUseFeature('private', $user_tier, $user_role)) {
                $errors[] = "Private pastes require Criminal or Rich upgrade.";
            }
            
            if ($is_unlisted && !canUseFeature('unlisted', $user_tier, $user_role)) {
                $errors[] = "Unlisted pastes require Criminal or Rich upgrade.";
            }
            
            if (!empty($password) && !canUseFeature('password', $user_tier, $user_role)) {
                $errors[] = "Password protection requires Rich upgrade.";
            }
        } else {
            // Anonymous users can't use premium features
            if ($is_private) {
                $errors[] = "Private pastes require an account with Criminal or Rich upgrade.";
            }
            
            if ($is_unlisted) {
                $errors[] = "Unlisted pastes require an account with Criminal or Rich upgrade.";
            }
            
            if (!empty($password)) {
                $errors[] = "Password protection requires an account with Rich upgrade.";
            }
        }
        
        if (empty($errors)) {
            $database = new Database();
            $db = $database->getConnection();
            
            // Hash password if provided
            $password_hash = !empty($password) ? Security::hashPassword($password) : null;
            
            // Use NULL for user_id if not logged in
            $user_id = $is_logged_in ? $_SESSION['user_id'] : null;
            
            $query = "INSERT INTO pastes (user_id, title, content, is_private, is_unlisted, password_hash, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([
                $user_id,
                $title,
                $content,
                $is_private,
                $is_unlisted,
                $password_hash
            ])) {
                $paste_id = $db->lastInsertId();
                header("Location: view.php?id=$paste_id");
                exit;
            } else {
                $errors[] = "Failed to create paste. Please try again.";
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
    <title>Add Paste - NebulaBin</title>
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
        .anonymous-notice {
            background: #2d4a2d;
            border: 1px solid #4a7c59;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            color: #b8e6b8;
        }
        .anonymous-notice a {
            color: #4a9eff;
            text-decoration: none;
        }
        .anonymous-notice a:hover {
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
                <li><a href="add-paste.php" class="active">Add Paste</a></li>
                <li><a href="users.php">Users</a></li>
                <li><a href="upgrades.php">Upgrades</a></li>
                <li><a href="hall-of-autism.php">Hall of Autism</a></li>
                <li><a href="support.php">Support</a></li>
            </ul>
            <div class="nav-right">
                <span class="user-count"><?php echo number_format($user_count); ?></span>
                <?php if ($is_logged_in): ?>
                    <a href="profile.php?id=<?php echo $_SESSION['user_id']; ?>">Profile</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <div class="container">
        <h1 style="text-align: center; font-size: 32px; margin: 40px 0;">Add New Paste</h1>
        
        <?php if (!$is_logged_in): ?>
            <div class="anonymous-notice">
                <strong>📝 Posting as Anonymous</strong><br>
                You're creating a paste without an account. You won't be able to edit or delete it later.
                <a href="register.php">Create an account</a> or <a href="login.php">login</a> to manage your pastes and unlock premium features!
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
            
            <div class="editor-container">
                <div class="editor-header">
                    <div class="form-group">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-input" style="width: 300px;" 
                               placeholder="Enter paste title..." required maxlength="255"
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="code-textarea" placeholder="Paste your code here..." 
                              required rows="20"><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                </div>
                
                <?php if ($is_logged_in): ?>
                <div class="premium-options">
                    <h3 style="color: #fff; margin: 20px 0 15px 0;">Privacy Options</h3>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" name="is_private" 
                                   <?php echo isset($_POST['is_private']) ? 'checked' : ''; ?>
                                   <?php echo !canUseFeature('private', $user_tier, $user_role) ? 'disabled' : ''; ?>>
                            Private Paste
                            <?php if (!canUseFeature('private', $user_tier, $user_role)): ?>
                                <span class="premium-required">Requires Criminal or Rich upgrade</span>
                            <?php endif; ?>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" name="is_unlisted" 
                                   <?php echo isset($_POST['is_unlisted']) ? 'checked' : ''; ?>
                                   <?php echo !canUseFeature('unlisted', $user_tier, $user_role) ? 'disabled' : ''; ?>>
                            Unlisted Paste (hidden from public listings)
                            <?php if (!canUseFeature('unlisted', $user_tier, $user_role)): ?>
                                <span class="premium-required">Requires Criminal or Rich upgrade</span>
                            <?php endif; ?>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Password Protection (optional)
                            <?php if (!canUseFeature('password', $user_tier, $user_role)): ?>
                                <span class="premium-required">Requires Rich upgrade</span>
                            <?php endif; ?>
                        </label>
                        <input type="password" name="password" class="form-input" style="width: 200px;"
                               placeholder="Enter password..."
                               <?php echo !canUseFeature('password', $user_tier, $user_role) ? 'disabled' : ''; ?>
                               value="<?php echo htmlspecialchars($_POST['password'] ?? ''); ?>">
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($show_captcha): ?>
                <div class="captcha-container">
                    <label class="form-label">Security Check</label>
                    <div class="captcha-question"><?php echo htmlspecialchars($captcha_question); ?></div>
                    <input type="number" name="captcha_answer" class="form-input" required 
                           placeholder="Enter the answer" style="width: 150px;">
                    <div style="color: #888; font-size: 12px; margin-top: 5px;">
                        <?php echo $is_logged_in ? 
                            "Free users must complete this security check to prevent spam." :
                            "Anonymous users must complete this security check to prevent spam."; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; margin: 20px 0;">
                <button type="submit" class="form-btn" style="width: 200px;">Create Paste</button>
            </div>
        </form>
    </div>
</body>
</html>
