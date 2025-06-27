<?php
session_start();
require_once 'config/database.php';
require_once 'includes/premium-functions.php';
require_once 'includes/comments.php';

$database = new Database();
$db = $database->getConnection();
$comments_handler = new Comments($database);

$paste_id = $_GET['id'] ?? 0;
$password_attempt = $_POST['password'] ?? '';

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment']) && isset($_SESSION['user_id'])) {
    $comment_content = $_POST['comment_content'] ?? '';
    $result = $comments_handler->addComment($_SESSION['user_id'], $comment_content, $paste_id);
    
    if ($result['success']) {
        header("Location: view.php?id=$paste_id#comments");
        exit;
    } else {
        $comment_error = $result['error'];
    }
}

// Handle comment deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment']) && isset($_SESSION['user_id'])) {
    $comment_id = $_POST['comment_id'] ?? 0;
    $result = $comments_handler->deleteComment($comment_id, $_SESSION['user_id'], $_SESSION['role'] ?? 'user');
    
    if ($result['success']) {
        header("Location: view.php?id=$paste_id#comments");
        exit;
    }
}

// Get paste details
$query = "SELECT p.*, u.username, u.role, u.upgrade_tier, u.custom_color, u.avatar FROM pastes p 
          LEFT JOIN users u ON p.user_id = u.id 
          WHERE p.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$paste_id]);
$paste = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paste) {
    header('Location: index.php');
    exit;
}

// Check if user can view private paste
if ($paste['is_private'] && (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $paste['user_id'])) {
    header('Location: index.php');
    exit;
}

// Handle password protection
$password_required = false;
$password_error = false;

if ($paste['password_hash']) {
    $password_required = true;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($password_attempt)) {
        if (password_verify($password_attempt, $paste['password_hash'])) {
            $_SESSION['paste_password_' . $paste_id] = true;
            $password_required = false;
        } else {
            $password_error = true;
        }
    } elseif (isset($_SESSION['paste_password_' . $paste_id])) {
        $password_required = false;
    }
    
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $paste['user_id']) {
        $password_required = false;
    }
}

if ($password_required) {
    // Show password form (same as before)
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
        <title>Password Required - NebulaBin</title>
        <link rel="stylesheet" href="assets/css/doxbin-style.css">
    </head>
    <body>
        <header class="header">
            <nav class="nav">
                <a href="index.php" class="logo">NebulaBin</a>
                <ul class="nav-menu">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="add-paste.php">Add Paste</a></li>
                    <li><a href="users.php">Users</a></li>
                    <li><a href="upgrades.php">Upgrades</a></li>
                    <li><a href="hall-of-autism.php">Hall of Autism</a></li>
                    <li><a href="support.php">Support</a></li>
                </ul>
                <div class="nav-right">
                    <span class="user-count"><?php echo number_format($user_count); ?></span>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="profile.php">Profile</a>
                        <a href="logout.php">Logout</a>
                    <?php else: ?>
                        <a href="login.php">Login</a>
                        <a href="register.php">Register</a>
                    <?php endif; ?>
                </div>
            </nav>
        </header>

        <div class="container">
            <div class="form-container">
                <h2 style="text-align: center; margin-bottom: 20px; color: #fff;">?? Password Protected</h2>
                <p style="text-align: center; color: #888; margin-bottom: 20px;">
                    This paste is password protected. Enter the password to view it.
                </p>
                
                <?php if ($password_error): ?>
                    <div class="message message-error">Incorrect password. Please try again.</div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input" required autofocus>
                    </div>
                    <button type="submit" class="form-btn">View Paste</button>
                </form>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="index.php" style="color: #4a9eff;">? Back to Home</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Increment view count
$update_query = "UPDATE pastes SET views = views + 1 WHERE id = ?";
$update_stmt = $db->prepare($update_query);
$update_stmt->execute([$paste_id]);

// Handle pinning (only for staff members)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_pin'])) {
    if (isset($_SESSION['user_id']) && isStaff($_SESSION['role'] ?? 'user')) {
        $new_pin_status = $paste['is_pinned'] ? 0 : 1;
        $pin_query = "UPDATE pastes SET is_pinned = ? WHERE id = ?";
        $pin_stmt = $db->prepare($pin_query);
        $pin_stmt->execute([$new_pin_status, $paste_id]);
        header("Location: view.php?id=$paste_id");
        exit;
    }
}

// Get comments
$comments = $comments_handler->getPasteComments($paste_id);

// Get user count for nav
$user_count_query = "SELECT COUNT(*) as count FROM users";
$user_count_stmt = $db->prepare($user_count_query);
$user_count_stmt->execute();
$user_count = $user_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$can_edit = isset($_SESSION['user_id']) && canInstantEdit($_SESSION['user_id'], $paste['user_id'], $_SESSION['upgrade_tier'] ?? 'none', $_SESSION['role'] ?? 'user');

// Debug: Check if current user is staff
$is_current_user_staff = isset($_SESSION['role']) ? isStaff($_SESSION['role']) : false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($paste['title']); ?> - NebulaBin</title>
    <link rel="stylesheet" href="assets/css/doxbin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <style>
        .paste-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
            margin: 20px 0;
        }
        
        .paste-content {
            background: #1a1a1a;
            border: 1px solid #333;
        }
        
        .comments-sidebar {
            background: #111;
            border: 1px solid #333;
            border-radius: 5px;
            height: fit-content;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .comments-header {
            background: #222;
            padding: 15px;
            border-bottom: 1px solid #333;
            font-weight: bold;
            color: #fff;
        }
        
        .comment-form {
            padding: 15px;
            border-bottom: 1px solid #333;
        }
        
        .comment-form textarea {
            width: 100%;
            background: #222;
            border: 1px solid #444;
            color: #fff;
            padding: 10px;
            border-radius: 3px;
            resize: vertical;
            min-height: 80px;
            font-size: 13px;
        }
        
        .comment-form button {
            background: #333;
            border: 1px solid #555;
            color: #fff;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
            margin-top: 10px;
            font-size: 13px;
        }
        
        .comment-form button:hover {
            background: #444;
        }
        
        .comment {
            padding: 15px;
            border-bottom: 1px solid #222;
        }
        
        .comment:last-child {
            border-bottom: none;
        }
        
        .comment-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .comment-author {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .comment-author img {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .comment-meta {
            font-size: 11px;
            color: #666;
        }
        
        .comment-content {
            color: #ccc;
            font-size: 13px;
            line-height: 1.4;
            word-wrap: break-word;
        }
        
        .comment-actions {
            margin-top: 8px;
        }
        
        .comment-delete {
            background: none;
            border: none;
            color: #ff4444;
            cursor: pointer;
            font-size: 11px;
            padding: 2px 5px;
        }
        
        .comment-delete:hover {
            text-decoration: underline;
        }
        
        .no-comments {
            padding: 20px;
            text-align: center;
            color: #666;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .paste-layout {
                grid-template-columns: 1fr;
            }
            
            .comments-sidebar {
                order: 2;
                max-height: 400px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">NebulaBin</a>
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="add-paste.php">Add Paste</a></li>
                <li><a href="users.php">Users</a></li>
                <li><a href="upgrades.php">Upgrades</a></li>
                <li><a href="hall-of-autism.php">Hall of Autism</a></li>
                <li><a href="support.php">Support</a></li>
            </ul>
            <div class="nav-right">
                <span class="user-count"><?php echo number_format($user_count); ?></span>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile.php">Profile</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="paste-layout">
            <!-- Main Paste Content -->
            <div class="paste-content">
                <div style="background: #2a2a2a; padding: 15px; border-bottom: 1px solid #333;">
                    <h1 style="color: #fff; font-size: 18px; margin: 0; font-weight: normal;">
                        <?php if ($paste['is_pinned']): ?>
                            <span style="color: #ffdd00;">&#128204;</span>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($paste['title']); ?>
                        <?php if ($paste['is_private']): ?>
                            <span style="color: #ff4444;">??</span>
                        <?php endif; ?>
                        <?php if ($paste['is_unlisted']): ?>
                            <span style="color: #ffaa44;">???????</span>
                        <?php endif; ?>
                        <?php if ($paste['password_hash']): ?>
                            <span style="color: #aa44ff;">??</span>
                        <?php endif; ?>
                    </h1>
                    
                    <div style="color: #888; font-size: 13px; margin-top: 5px;">
                        By <?php echo getUsernameWithRole($paste['username'] ?? 'Anonymous', $paste['role'] ?? 'user', $paste['upgrade_tier'] ?? 'none', $paste['custom_color']); ?>
                        &#8226; <?php echo date('M j, Y \a\t g:i A', strtotime($paste['created_at'])); ?>
                        &#8226; <?php echo number_format($paste['views']); ?> views
                        &#8226; <?php echo number_format($paste['comment_count'] ?? 0); ?> comments
                    </div>
                    
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div style="margin-top: 10px;">
                            <?php if ($is_current_user_staff): ?>
                                <form method="POST" style="display: inline;">
                                    <button type="submit" name="toggle_pin" class="form-btn" style="width: auto; padding: 5px 10px; font-size: 12px;">
                                        <?php echo $paste['is_pinned'] ? 'Unpin' : 'Pin'; ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($can_edit && $_SESSION['user_id'] == $paste['user_id']): ?>
                                <a href="edit-paste.php?id=<?php echo $paste_id; ?>" class="form-btn" style="width: auto; padding: 5px 10px; font-size: 12px; margin-left: 5px; text-decoration: none;">
                                    ? Edit
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div style="padding: 0;">
                    <pre style="background: #0a0a0a; color: #fff; padding: 20px; margin: 0; overflow-x: auto; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.4;"><code><?php echo htmlspecialchars($paste['content']); ?></code></pre>
                </div>
                
                <div style="background: #2a2a2a; padding: 15px; border-top: 1px solid #333;">
                    <button onclick="copyToClipboard()" class="form-btn" style="width: auto; padding: 8px 15px; margin-right: 10px;">
                        Copy Code
                    </button>
                    <a href="add-paste.php" class="form-btn" style="display: inline-block; padding: 8px 15px; text-decoration: none; margin-right: 10px;">Create New</a>
                    <a href="index.php" class="form-btn" style="display: inline-block; padding: 8px 15px; text-decoration: none;">Back to Home</a>
                </div>
            </div>

            <!-- Comments Sidebar -->
            <div class="comments-sidebar" id="comments">
                <div class="comments-header">
                   &#128172; Comments (<?php echo count($comments); ?>)
                </div>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="comment-form">
                        <?php if (isset($comment_error)): ?>
                            <div style="color: #ff4444; font-size: 12px; margin-bottom: 10px;">
                                <?php echo htmlspecialchars($comment_error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <textarea name="comment_content" placeholder="Add a comment..." maxlength="1000" required></textarea>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                                <button type="submit" name="add_comment">Post Comment</button>
                                <span style="font-size: 11px; color: #666;">Max 1000 characters</span>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="padding: 15px; border-bottom: 1px solid #333; text-align: center;">
                        <a href="login.php" style="color: #4a9eff;">Login to comment</a>
                    </div>
                <?php endif; ?>

                <div class="comments-list">
                    <?php if (empty($comments)): ?>
                        <div class="no-comments">
                            No comments yet. Be the first to comment!
                        </div>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment">
                                <div class="comment-header">
                                    <div class="comment-author">
                                        <?php if ($comment['avatar']): ?>
                                            <img src="<?php echo htmlspecialchars($comment['avatar']); ?>" alt="Avatar">
                                        <?php endif; ?>
                                        <span>
                                            <?php echo getUsernameWithRole($comment['username'], $comment['role'], $comment['upgrade_tier'], $comment['custom_color']); ?>
                                        </span>
                                    </div>
                                    <div class="comment-meta">
                                        <?php echo date('M j, g:i A', strtotime($comment['created_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="comment-content">
                                    <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                </div>
                                
                                <?php if (isset($_SESSION['user_id']) && 
                                         ($comment['user_id'] == $_SESSION['user_id'] || 
                                          in_array($_SESSION['role'] ?? 'user', ['admin', 'manager', 'mod']))): ?>
                                    <div class="comment-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                            <button type="submit" name="delete_comment" class="comment-delete" 
                                                    onclick="return confirm('Delete this comment?')">Delete</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
        hljs.highlightAll();
        
        function copyToClipboard() {
            const code = document.querySelector('code').textContent;
            navigator.clipboard.writeText(code).then(() => {
                alert('Code copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }
    </script>
</body>
</html>
