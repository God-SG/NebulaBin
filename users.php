<?php
session_start();
require_once 'config/database.php';
require_once 'includes/premium-functions.php';

$database = new Database();
$db = $database->getConnection();

// Get total user count
$user_count_query = "SELECT COUNT(*) as count FROM users";
$user_count_stmt = $db->prepare($user_count_query);
$user_count_stmt->execute();
$total_users = $user_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Handle search
$search_term = $_GET['search'] ?? '';
$where_clause = '';
$params = [];

if (!empty($search_term)) {
    $where_clause = "WHERE u.username LIKE ?";
    $params[] = "%$search_term%";
}

// Get users by role with exact Doxbin ordering (including dev)
$roles = ['admin', 'dev', 'manager', 'council', 'mod', 'helper', 'clique'];
$users_by_role = [];

foreach ($roles as $role) {
    $query = "SELECT u.*, 
              (SELECT COUNT(*) FROM pastes p WHERE p.user_id = u.id) as paste_count,
              (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id) as comment_count
              FROM users u 
              WHERE u.role = ? " . ($search_term ? "AND u.username LIKE ?" : "") . "
              ORDER BY u.id ASC";
    
    $stmt = $db->prepare($query);
    if ($search_term) {
        $stmt->execute([$role, "%$search_term%"]);
    } else {
        $stmt->execute([$role]);
    }
    $users_by_role[$role] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get regular users (not in special roles)
$regular_users_query = "SELECT u.*, 
                        (SELECT COUNT(*) FROM pastes p WHERE p.user_id = u.id) as paste_count,
                        (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id) as comment_count
                        FROM users u 
                        WHERE u.role = 'user' " . ($search_term ? "AND u.username LIKE ?" : "") . "
                        ORDER BY u.id ASC 
                        LIMIT 50";

$regular_stmt = $db->prepare($regular_users_query);
if ($search_term) {
    $regular_stmt->execute(["%$search_term%"]);
} else {
    $regular_stmt->execute();
}
$regular_users = $regular_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate displayed users count
$displayed_count = 0;
foreach ($users_by_role as $users) {
    $displayed_count += count($users);
}
$displayed_count += count($regular_users);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - NebulaBin</title>
    <link rel="stylesheet" href="assets/css/doxbin-style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="home.php" class="logo">NebulaBin</a>
            <ul class="nav-menu">
                <li><a href="home.php">Home</a></li>
                <li><a href="add-paste.php">Add Paste</a></li>
                <li><a href="users.php" class="active">Users</a></li>
                <li><a href="upgrades.php">Upgrades</a></li>
                <li><a href="hall-of-autism.php">Hall of Autism</a></li>
                <li><a href="support.php">Support</a></li>
            </ul>
            <div class="nav-right">
                <span class="user-count"><?php echo number_format($total_users); ?></span>
                <?php if (isset($_SESSION['user_id'])): ?>
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
        <h1 class="users-title" style="text-align: center; font-size: 32px; margin: 40px 0; color: #fff;">Users</h1>
        
        <div class="search-section">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Username..." id="userSearch" 
                       value="<?php echo htmlspecialchars($search_term); ?>">
                <button class="search-btn" onclick="searchUsers()">Search</button>
            </div>
            <div class="results-info">
                Showing <?php echo $displayed_count; ?> (of <?php echo number_format($total_users); ?>) users
                <?php if ($search_term): ?>
                    for "<?php echo htmlspecialchars($search_term); ?>"
                    <a href="users.php" style="color: #4a9eff; margin-left: 10px;">[Clear Search]</a>
                <?php endif; ?>
            </div>
        </div>

        <?php 
        $role_titles = [
            'admin' => 'Admin',
            'manager' => 'Manager', 
            'council' => 'Council',
            'mod' => 'Mod',
            'helper' => 'Helper',
            'clique' => 'Clique',
            'dev' => 'Dev :3'
        ];
        
        foreach ($roles as $role): 
            if (!empty($users_by_role[$role])): 
        ?>
            <div class="section-title"><?php echo $role_titles[$role]; ?></div>
            <table class="paste-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Pastes</th>
                        <th>Comments</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users_by_role[$role] as $user): ?>
                    <tr class="<?php echo getRowClass($user['role']); ?>">
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo getUsernameWithRole($user['username'], $user['role'], $user['upgrade_tier'], $user['custom_color'], $user['id'], $user['avatar']); ?></td>
                        <td><?php echo $user['paste_count']; ?></td>
                        <td><?php echo $user['comment_count']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php 
            endif;
        endforeach; 
        ?>

        <?php if (!empty($regular_users)): ?>
            <div class="section-title">Users</div>
            <table class="paste-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Pastes</th>
                        <th>Comments</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($regular_users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo getUsernameWithRole($user['username'], $user['role'], $user['upgrade_tier'], $user['custom_color'], $user['id'], $user['avatar']); ?></td>
                        <td><?php echo $user['paste_count']; ?></td>
                        <td><?php echo $user['comment_count']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($displayed_count == 0): ?>
            <div style="text-align: center; color: #888; padding: 40px;">
                <p>No users found<?php echo $search_term ? " matching \"" . htmlspecialchars($search_term) . "\"" : ""; ?>.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Chat Button -->
    <button class="chat-button">Chat</button>

    <script>
        function searchUsers() {
            const searchTerm = document.getElementById('userSearch').value;
            if (searchTerm.trim()) {
                window.location.href = `users.php?search=${encodeURIComponent(searchTerm)}`;
            } else {
                window.location.href = 'users.php';
            }
        }

        document.getElementById('userSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchUsers();
            }
        });
    </script>
</body>
</html>
