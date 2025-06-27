<?php
require_once 'includes/security.php';
Security::startSecureSession();
require_once 'config/database.php';
require_once 'includes/premium-functions.php';

$database = new Database();
$db = $database->getConnection();

// Pagination settings
$pastes_per_page = 20;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $pastes_per_page;

// Get total user count
$user_count_query = "SELECT COUNT(*) as count FROM users";
$user_count_stmt = $db->prepare($user_count_query);
$user_count_stmt->execute();
$user_count = $user_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get total paste count (excluding private and pinned)
$paste_count_query = "SELECT COUNT(*) as count FROM pastes WHERE is_private = 0 AND is_pinned = 0 AND is_unlisted = 0";
$paste_count_stmt = $db->prepare($paste_count_query);
$paste_count_stmt->execute();
$total_pastes = $paste_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Calculate total pages
$total_pages = max(1, ceil($total_pastes / $pastes_per_page));

// Ensure current page doesn't exceed total pages
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $pastes_per_page;

// Get pinned pastes (always show these)
$pinned_query = "SELECT p.*, u.username, u.role, u.upgrade_tier, u.custom_color, u.avatar, u.id as user_id,
                 COALESCE(p.comment_count, 0) as comment_count
                 FROM pastes p 
                 LEFT JOIN users u ON p.user_id = u.id 
                 WHERE p.is_pinned = 1 AND p.is_private = 0 AND p.is_unlisted = 0
                 ORDER BY p.created_at DESC";
$pinned_stmt = $db->prepare($pinned_query);
$pinned_stmt->execute();
$pinned_pastes = $pinned_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent pastes (paginated, excluding pinned) - Fix the LIMIT/OFFSET issue
$recent_query = "SELECT p.*, u.username, u.role, u.upgrade_tier, u.custom_color, u.avatar, u.id as user_id,
                 COALESCE(p.comment_count, 0) as comment_count
                 FROM pastes p 
                 LEFT JOIN users u ON p.user_id = u.id 
                 WHERE p.is_private = 0 AND p.is_pinned = 0 AND p.is_unlisted = 0
                 ORDER BY p.created_at DESC 
                 LIMIT $pastes_per_page OFFSET $offset";
$recent_stmt = $db->prepare($recent_query);
$recent_stmt->execute();
$recent_pastes = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate pagination links
function generatePaginationLinks($current_page, $total_pages) {
    $links = [];
    
    // Previous button
    if ($current_page > 1) {
        $links[] = '<a href="?page=' . ($current_page - 1) . '">&lt;</a>';
    } else {
        $links[] = '<span class="disabled">&lt;</span>';
    }
    
    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    // Show first page if not in range
    if ($start > 1) {
        $links[] = '<a href="?page=1">1</a>';
        if ($start > 2) {
            $links[] = '<span>...</span>';
        }
    }
    
    // Show page numbers in range
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current_page) {
            $links[] = '<span class="current">' . $i . '</span>';
        } else {
            $links[] = '<a href="?page=' . $i . '">' . $i . '</a>';
        }
    }
    
    // Show last page if not in range
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $links[] = '<span>...</span>';
        }
        $links[] = '<a href="?page=' . $total_pages . '">' . $total_pages . '</a>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $links[] = '<a href="?page=' . ($current_page + 1) . '">&gt;</a>';
    } else {
        $links[] = '<span class="disabled">&gt;</span>';
    }
    
    return $links;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NebulaBin</title>
    <link rel="stylesheet" href="assets/css/doxbin-style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="home.php" class="logo">NebulaBin</a>
            <ul class="nav-menu">
                <li><a href="home.php" class="active">Home</a></li>
                <li><a href="add-paste.php">Add Paste</a></li>
                <li><a href="users.php">Users</a></li>
                <li><a href="upgrades.php">Upgrades</a></li>
                <li><a href="hall-of-autism.php">Hall of Autism</a></li>
                <li><a href="support.php">Support</a></li>
            </ul>
            <div class="nav-right">
                <span class="user-count"><?php echo $user_count; ?></span>
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
        <div class="logo-section">
            <img style="width:200px; height:200px" src="logo.gif">
            <a target="_blank" href="https://t.me/nebulabincoms" class="telegram-link">JOIN NEBULABIN TELEGRAM</a>
            <div class="mirrors">Mirrors: nebulabin.com | nebulabin.net</div>
        </div>

        <div class="search-section">
            <label class="search-label">Search for a paste</label>
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search for..." id="searchInput">
                <button class="search-btn" onclick="searchPastes()">Search</button>
            </div>
            <div class="results-info">
                Showing <?php echo count($recent_pastes); ?> (of <?php echo number_format($total_pastes); ?> total) pastes
                <?php if ($total_pages > 1): ?>
                    - Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php echo implode('', generatePaginationLinks($current_page, $total_pages)); ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($pinned_pastes)): ?>
        <div class="section-title">Pinned Pastes</div>
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
                <?php foreach ($pinned_pastes as $paste): ?>
                <tr class="<?php echo getRowClass($paste['role'] ?? 'user'); ?>">
                    <td>
                        <span class="pin-icon">&#128204;</span>
                        <a href="view.php?id=<?php echo $paste['id']; ?>"><?php echo htmlspecialchars($paste['title']); ?></a>
                    </td>
                    <td><?php echo number_format($paste['comment_count']); ?></td>
                    <td><?php echo number_format($paste['views']); ?></td>
                    <td><?php echo getUsernameWithRole($paste['username'] ?? 'Anonymous', $paste['role'] ?? 'user', $paste['upgrade_tier'] ?? 'none', $paste['custom_color'], $paste['user_id'], $paste['avatar']); ?></td>
                    <td><?php echo date('M j, Y', strtotime($paste['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if (!empty($recent_pastes)): ?>
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
                <?php foreach ($recent_pastes as $paste): ?>
                <tr class="<?php echo getRowClass($paste['role'] ?? 'user'); ?>">
                    <td><a href="view.php?id=<?php echo $paste['id']; ?>"><?php echo htmlspecialchars($paste['title']); ?></a></td>
                    <td><?php echo number_format($paste['comment_count']); ?></td>
                    <td><?php echo number_format($paste['views']); ?></td>
                    <td><?php echo getUsernameWithRole($paste['username'] ?? 'Anonymous', $paste['role'] ?? 'user', $paste['upgrade_tier'] ?? 'none', $paste['custom_color'], $paste['user_id'], $paste['avatar']); ?></td>
                    <td><?php echo date('M j, Y', strtotime($paste['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align: center; color: #888; padding: 40px;">
            <p>No pastes found.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Chat Button -->
    <button class="chat-button">Chat</button>

    <script>
        function searchPastes() {
            const searchTerm = document.getElementById('searchInput').value;
            if (searchTerm.trim()) {
                window.location.href = `search.php?q=${encodeURIComponent(searchTerm)}`;
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

