<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get total user count for nav
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
    <title>Upgrades - NebulaBin</title>
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
                <li><a href="upgrades.php" class="active">Upgrades</a></li>
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
        <h1 style="text-align: center; font-size: 32px; margin: 40px 0; color: #fff;">Upgrades</h1>
        
        <div class="upgrades-container">
            <!-- VIP Package -->
            <div class="upgrade-card">
                <div class="upgrade-header">
                    <div class="upgrade-title role-vip">VIP</div>
                    <div class="upgrade-price">$30</div>
                </div>
                <div class="upgrade-features">
                    <div class="feature-item">Preview <span class="role-vip">Anonymous [VIP]</span></div>
                    <div class="feature-item">Color <span class="role-vip">Light purple</span></div>
                    <div class="feature-item">Paste highlight <span class="role-vip">Light purple background</span></div>
                    <div class="feature-item">.GIF profile picture <span class="feature-check">✓</span></div>
                    <div class="feature-item">Instant paste edits <span class="feature-check">✓</span></div>
                    <div class="feature-item">Unlist your own pastes <span class="feature-cross">✗</span></div>
                    <div class="feature-item">Private your own pastes <span class="feature-cross">✗</span></div>
                    <div class="feature-item">Password protected pastes <span class="feature-cross">✗</span></div>
                    <div class="feature-item">Username changes <span class="feature-count">1</span></div>
                </div>
                <a target="_blank" href="https://t.me/StopbegsT"><button class="upgrade-btn">Purchase VIP</button></a>
            </div>

            <!-- Criminal Package -->
            <div class="upgrade-card">
                <div class="upgrade-header">
                    <div class="upgrade-title role-criminal">Criminal</div>
                    <div class="upgrade-price">$50</div>
                </div>
                <div class="upgrade-features">
                    <div class="feature-item">Preview <span class="role-criminal">Anonymous [Criminal]</span></div>
                    <div class="feature-item">Color <span class="role-criminal">Dark Purple</span></div>
                    <div class="feature-item">Paste highlight <span class="role-criminal">Dark Purple background</span></div>
                    <div class="feature-item">.GIF profile picture <span class="feature-check">✓</span></div>
                    <div class="feature-item">Instant paste edits <span class="feature-check">✓</span></div>
                    <div class="feature-item">Unlist your own pastes <span class="feature-check">✓</span></div>
                    <div class="feature-item">Private your own pastes <span class="feature-check">✓</span></div>
                    <div class="feature-item">Password protected pastes <span class="feature-cross">✗</span></div>
                    <div class="feature-item">Username changes <span class="feature-count">2</span></div>
                </div>
                <a target="_blank" href="https://t.me/StopbegsT"><button class="upgrade-btn">Purchase Criminal</button></a>
            </div>

            <!-- Rich Package -->
            <div class="upgrade-card">
                <div class="upgrade-header">
                    <div class="upgrade-title role-rich">Rich</div>
                    <div class="upgrade-price">$100</div>
                </div>
                <div class="upgrade-features">
                    <div class="feature-item">Preview <span class="role-rich">Anonymous [Rich]</span></div>
                    <div class="feature-item">Color <span class="role-rich">Sparkling Gold</span></div>
                    <div class="feature-item">Paste highlight <span class="role-rich">Gold background</span></div>
                    <div class="feature-item">.GIF profile picture <span class="feature-check">✓</span></div>
                    <div class="feature-item">Instant paste edits <span class="feature-check">✓</span></div>
                    <div class="feature-item">Unlist your own pastes <span class="feature-check">✓</span></div>
                    <div class="feature-item">Private your own pastes <span class="feature-check">✓</span></div>
                    <div class="feature-item">Password protected pastes <span class="feature-check">✓</span></div>
                    <div class="feature-item">Username changes <span class="feature-count">3</span></div>
                </div>
                <a target="_blank" href="https://t.me/StopbegsT"><button class="upgrade-btn">Purchase Rich</button></a>
            </div>

            <!-- Change Color Package -->
            <div class="upgrade-card">
                <div class="upgrade-header">
                    <div class="upgrade-title">Change Color</div>
                    <div class="upgrade-price">$10</div>
                </div>
                <div class="upgrade-features">
                    <div class="feature-item">Ability to change username color <span class="feature-check">✓</span></div>
                    <div class="feature-item">Amount of colors <span class="feature-count">20</span></div>
                    <div class="feature-item">Separate from tier upgrades <span class="feature-check">✓</span></div>
                    <div class="feature-item">Works with any tier <span class="feature-check">✓</span></div>
                    <div class="feature-item">Staff get this for free <span class="feature-check">✓</span></div>
                    
                    <div class="color-grid">
                        <div class="color-option" style="background: #ff0000;" title="Red"></div>
                        <div class="color-option" style="background: #ff8800;" title="Orange"></div>
                        <div class="color-option" style="background: #ffff00;" title="Yellow"></div>
                        <div class="color-option" style="background: #88ff00;" title="Lime"></div>
                        <div class="color-option" style="background: #00ff00;" title="Green"></div>
                        <div class="color-option" style="background: #00ff88;" title="Spring Green"></div>
                        <div class="color-option" style="background: #00ffff;" title="Cyan"></div>
                        <div class="color-option" style="background: #0088ff;" title="Sky Blue"></div>
                        <div class="color-option" style="background: #0000ff;" title="Blue"></div>
                        <div class="color-option" style="background: #8800ff;" title="Purple"></div>
                        <div class="color-option" style="background: #ff00ff;" title="Magenta"></div>
                        <div class="color-option" style="background: #ff0088;" title="Pink"></div>
                        <div class="color-option" style="background: #ffffff;" title="White"></div>
                        <div class="color-option" style="background: #cccccc;" title="Light Gray"></div>
                        <div class="color-option" style="background: #888888;" title="Gray"></div>
                        <div class="color-option" style="background: #444444;" title="Dark Gray"></div>
                        <div class="color-option" style="background: #000000; border: 1px solid #666;" title="Black"></div>
                        <div class="color-option" style="background: #8b4513;" title="Brown"></div>
                        <div class="color-option" style="background: #ffc0cb;" title="Light Pink"></div>
                        <div class="color-option" style="background: #800080;" title="Dark Purple"></div>
                    </div>
                </div>
                <a target="_blank" href="https://t.me/StopbegsT"><button class="upgrade-btn">Purchase Color Change</button></a>
            </div>
        </div>

        <div style="text-align: center; margin-top: 40px; color: #888; font-size: 14px;">
            <p>All purchases are processed securely. Contact support for any questions.</p>
            <p style="margin-top: 10px;">Upgrades are permanent and tied to your account.</p>
            <p style="margin-top: 15px; color: #4a9eff; font-weight: bold;">
                🛡️ Staff members (Admin, Manager, Mod, Helper) get all premium features for free!
            </p>
        </div>
    </div>

    <!-- Chat Button -->
    <button class="chat-button">Chat</button>
</body>
</html>
