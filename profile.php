<?php
session_start();
require_once 'config/database.php';
require_once 'includes/premium-functions.php';
require_once 'includes/comments.php';

$database = new Database();
$db = $database->getConnection();
$comments_handler = new Comments($database);

// Get user ID from URL parameter or session
$profile_user_id = $_GET['id'] ?? $_SESSION['user_id'] ?? null;

if (!$profile_user_id) {
    header('Location: login.php');
    exit;
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment']) && isset($_SESSION['user_id'])) {
    $comment_content = $_POST['comment_content'] ?? '';
    $result = $comments_handler->addComment($_SESSION['user_id'], $comment_content, null, $profile_user_id);
    
    if ($result['success']) {
        header("Location: profile.php?id=$profile_user_id#comments");
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
        header("Location: profile.php?id=$profile_user_id#comments");
        exit;
    }
}

// Get user details
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute([$profile_user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: home.php');
    exit;
}

// Get user's pastes count
$paste_count_query = "SELECT COUNT(*) as count FROM pastes WHERE user_id = ?";
$paste_count_stmt = $db->prepare($paste_count_query);
$paste_count_stmt->execute([$profile_user_id]);
$paste_count = $paste_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get user's total views
$views_query = "SELECT SUM(views) as total_views FROM pastes WHERE user_id = ?";
$views_stmt = $db->prepare($views_query);
$views_stmt->execute([$profile_user_id]);
$total_views = $views_stmt->fetch(PDO::FETCH_ASSOC)['total_views'] ?? 0;

// Get user's pastes (public ones, or all if viewing own profile)
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_user_id) {
    $pastes_query = "SELECT * FROM pastes WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
} else {
    $pastes_query = "SELECT * FROM pastes WHERE user_id = ? AND is_private = 0 ORDER BY created_at DESC LIMIT 20";
}
$pastes_stmt = $db->prepare($pastes_query);
$pastes_stmt->execute([$profile_user_id]);
$user_pastes = $pastes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get profile comments
$profile_comments = $comments_handler->getProfileComments($profile_user_id);

// Get user count for nav
$user_count_query = "SELECT COUNT(*) as count FROM users";
$user_count_stmt = $db->prepare($user_count_query);
$user_count_stmt->execute();
$user_count = $user_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$is_own_profile = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_user_id;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?> - NebulaBin</title>
    <link rel="stylesheet" href="assets/css/doxbin-style.css">
    <style>
        .profile-comments {
            background: #111;
            border: 1px solid #333;
            border-radius: 5px;
            margin-top: 30px;
        }
        
        .profile-comments-header {
            background: #222;
            padding: 20px;
            border-bottom: 1px solid #333;
        }
        
        .profile-comments-header h3 {
            color: #fff;
            margin: 0;
            font-size: 18px;
        }
        
        .profile-comment-form {
            padding: 20px;
            border-bottom: 1px solid #333;
        }
        
        .profile-comment-form textarea {
            width: 100%;
            background: #222;
            border: 1px solid #444;
            color: #fff;
            padding: 12px;
            border-radius: 5px;
            resize: vertical;
            min-height: 100px;
            font-size: 14px;
        }
        
        .profile-comment-form button {
            background: #333;
            border: 1px solid #555;
            color: #fff;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .profile-comment-form button:hover {
            background: #444;
        }
        
        .profile-comment {
            padding: 20px;
            border-bottom: 1px solid #222;
        }
        
        .profile-comment:last-child {
            border-bottom: none;
        }
        
        .profile-comment-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .profile-comment-author {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .profile-comment-author img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .profile-comment-meta {
            font-size: 12px;
            color: #666;
        }
        
        .profile-comment-content {
            color: #ccc;
            font-size: 14px;
            line-height: 1.5;
            word-wrap: break-word;
        }
        
        .profile-comment-actions {
            margin-top: 10px;
        }
        
        .profile-comment-delete {
            background: none;
            border: none;
            color: #ff4444;
            cursor: pointer;
            font-size: 12px;
            padding: 5px 10px;
        }
        
        .profile-comment-delete:hover {
            text-decoration: underline;
        }
        
        .no-profile-comments {
            padding: 40px;
            text-align: center;
            color: #666;
            font-style: italic;
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
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile.php?id=<?php echo $_SESSION['user_id']; ?>" <?php echo $is_own_profile ? 'class="active"' : ''; ?>>Profile</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="profile-header">
            <?php if ($user['avatar']): ?>
                <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" class="avatar-large">
            <?php endif; ?>
            
            <h1 style="color: #fff; margin-bottom: 5px;">
                <?php echo getUsernameWithRole($user['username'], $user['role'], $user['upgrade_tier'], $user['custom_color']); ?>
            </h1>
            
            <div style="color: #888; margin-bottom: 5px;">
                User ID: #<?php echo $user['id']; ?>
            </div>
            
            <?php if ($user['bio']): ?>
                <div style="color: #ccc; margin-bottom: 20px; max-width: 500px; margin-left: auto; margin-right: auto;">
                    <?php echo htmlspecialchars($user['bio']); ?>
                </div>
            <?php endif; ?>
            
            <div class="profile-stats">
                <div class="profile-stat">
                    <span class="profile-stat-number"><?php echo number_format($paste_count); ?></span>
                    <span class="profile-stat-label">Pastes</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-number"><?php echo number_format($total_views); ?></span>
                    <span class="profile-stat-label">Total Views</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-number"><?php echo ucfirst($user['role']); ?></span>
                    <span class="profile-stat-label">Role</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-number"><?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                    <span class="profile-stat-label">Joined</span>
                </div>
            </div>
            
            <?php if ($is_own_profile): ?>
                <div style="margin-top: 20px;">
                    <a href="edit-profile.php" class="form-btn" style="display: inline-block; padding: 8px 16px; text-decoration: none;">Edit Profile</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="section-title">
            <?php echo $is_own_profile ? 'My Pastes' : htmlspecialchars($user['username']) . "'s Pastes"; ?>
            (<?php echo count($user_pastes); ?>)
        </div>
        
        <?php if (!empty($user_pastes)): ?>
        <table class="paste-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Views</th>
                    <th>Comments</th>
                    <?php if ($is_own_profile): ?>
                        <th>Private</th>
                        <th>Pinned</th>
                    <?php endif; ?>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($user_pastes as $paste): ?>
                <tr>
                    <td><a href="view.php?id=<?php echo $paste['id']; ?>"><?php echo htmlspecialchars($paste['title']); ?></a></td>
                    <td><?php echo number_format($paste['views']); ?></td>
                    <td><?php echo number_format($paste['comment_count'] ?? 0); ?></td>
                    <?php if ($is_own_profile): ?>
                        <td><?php echo $paste['is_private'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $paste['is_pinned'] ? 'Yes' : 'No'; ?></td>
                    <?php endif; ?>
                    <td><?php echo date('M j, Y', strtotime($paste['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align: center; color: #888; padding: 40px;">
            <p><?php echo $is_own_profile ? "You haven't created any pastes yet." : "This user hasn't created any public pastes yet."; ?></p>
            <?php if ($is_own_profile): ?>
                <a href="add-paste.php" class="form-btn" style="display: inline-block; margin-top: 15px; width: auto; padding: 10px 20px;">Create Your First Paste</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Profile Comments Section -->
        <div class="profile-comments" id="comments">
            <div class="profile-comments-header">
                <h3>💬 Profile Comments (<?php echo count($profile_comments); ?>)</h3>
            </div>

            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $profile_user_id): ?>
                <div class="profile-comment-form">
                    <?php if (isset($comment_error)): ?>
                        <div style="color: #ff4444; font-size: 14px; margin-bottom: 10px;">
                            <?php echo htmlspecialchars($comment_error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <textarea name="comment_content" placeholder="Leave a comment on <?php echo htmlspecialchars($user['username']); ?>'s profile..." maxlength="1000" required></textarea>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                            <button type="submit" name="add_comment">Post Comment</button>
                            <span style="font-size: 12px; color: #666;">Max 1000 characters</span>
                        </div>
                    </form>
                </div>
            <?php elseif (!isset($_SESSION['user_id'])): ?>
                <div style="padding: 20px; border-bottom: 1px solid #333; text-align: center;">
                    <a href="login.php" style="color: #4a9eff;">Login to leave a comment</a>
                </div>
            <?php elseif ($is_own_profile): ?>
                <div style="padding: 20px; border-bottom: 1px solid #333; text-align: center; color: #666;">
                    Comments from other users will appear here
                </div>
            <?php endif; ?>

            <div class="profile-comments-list">
                <?php if (empty($profile_comments)): ?>
                    <div class="no-profile-comments">
                        <?php if ($is_own_profile): ?>
                            No one has commented on your profile yet.
                        <?php else: ?>
                            No comments on this profile yet. Be the first to comment!
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($profile_comments as $comment): ?>
                        <div class="profile-comment">
                            <div class="profile-comment-header">
                                <div class="profile-comment-author">
                                    <?php if ($comment['avatar']): ?>
                                        <img src="<?php echo htmlspecialchars($comment['avatar']); ?>" alt="Avatar">
                                    <?php endif; ?>
                                    <div>
                                        <div>
                                            <?php echo getUsernameWithRole($comment['username'], $comment['role'], $comment['upgrade_tier'], $comment['custom_color']); ?>
                                        </div>
                                        <div class="profile-comment-meta">
                                            <?php echo date('M j, Y \a\t g:i A', strtotime($comment['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="profile-comment-content">
                                <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                            </div>
                            
                            <?php if (isset($_SESSION['user_id']) && 
                                     ($comment['user_id'] == $_SESSION['user_id'] || 
                                      $is_own_profile ||
                                      in_array($_SESSION['role'] ?? 'user', ['admin', 'manager', 'mod']))): ?>
                                <div class="profile-comment-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                        <button type="submit" name="delete_comment" class="profile-comment-delete" 
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

    <!-- Chat Button -->
    <button class="chat-button">Chat</button>
</body>
</html>
