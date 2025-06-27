<?php
session_start();
require_once 'config/database.php';
require_once 'includes/premium-functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get user details
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: home.php');
    exit;
}

// Get remaining username changes
$username_changes_used_query = "SELECT COUNT(*) as used FROM username_changes WHERE user_id = ?";
$changes_stmt = $db->prepare($username_changes_used_query);
$changes_stmt->execute([$_SESSION['user_id']]);
$changes_used = $changes_stmt->fetch(PDO::FETCH_ASSOC)['used'];

$max_changes = canChangeUsername($user['upgrade_tier'], $user['role']);
$remaining_changes = $max_changes === PHP_INT_MAX ? PHP_INT_MAX : max(0, $max_changes - $changes_used);

// Available colors for color change
$available_colors = [
    '#ff0000' => 'Red',
    '#ff8800' => 'Orange', 
    '#ffff00' => 'Yellow',
    '#88ff00' => 'Lime',
    '#00ff00' => 'Green',
    '#00ff88' => 'Spring Green',
    '#00ffff' => 'Cyan',
    '#0088ff' => 'Sky Blue',
    '#0000ff' => 'Blue',
    '#8800ff' => 'Purple',
    '#ff00ff' => 'Magenta',
    '#ff0088' => 'Pink',
    '#ffffff' => 'White',
    '#cccccc' => 'Light Gray',
    '#888888' => 'Gray',
    '#444444' => 'Dark Gray',
    '#000000' => 'Black',
    '#8b4513' => 'Brown',
    '#ffc0cb' => 'Light Pink',
    '#800080' => 'Dark Purple'
];

// Handle form submission
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username']);
    $new_bio = trim($_POST['bio']);
    $new_avatar = trim($_POST['avatar']);
    $new_color = $_POST['custom_color'] ?? '';
    $remove_color = isset($_POST['remove_color']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate current password for any changes
    if (!password_verify($current_password, $user['password_hash'])) {
        $errors[] = "Current password is incorrect";
    }
    
    // Username validation and change
    $username_changed = false;
    if ($new_username !== $user['username']) {
        if ($remaining_changes <= 0 && $remaining_changes !== PHP_INT_MAX) {
            $errors[] = "You have no remaining username changes";
        } else {
            // Validate new username
            if (!preg_match('/^[a-zA-Z0-9_-]{3,30}$/', $new_username)) {
                $errors[] = "Username can only contain letters, numbers, underscores, and hyphens (3-30 characters)";
            } else {
                // Check if username is taken
                $check_username = "SELECT id FROM users WHERE username = ? AND id != ?";
                $check_stmt = $db->prepare($check_username);
                $check_stmt->execute([$new_username, $_SESSION['user_id']]);
                
                if ($check_stmt->fetch()) {
                    $errors[] = "Username is already taken";
                } else {
                    $username_changed = true;
                }
            }
        }
    }
    
    // Color change validation
    $color_changed = false;
    if ($remove_color) {
        $new_color = null;
        $color_changed = true;
    } elseif (!empty($new_color) && $new_color !== $user['custom_color']) {
        if (!canUseFeature('color_change', $user['upgrade_tier'], $user['role'], $user['can_change_color'])) {
            $errors[] = "Color change requires purchasing the Color Change upgrade";
        } elseif (!array_key_exists($new_color, $available_colors)) {
            $errors[] = "Invalid color selection";
        } else {
            $color_changed = true;
        }
    }
    
    // Password validation
    $password_changed = false;
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters long";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        } else {
            $password_changed = true;
        }
    }
    
    // Avatar validation
    if (!empty($new_avatar) && !filter_var($new_avatar, FILTER_VALIDATE_URL)) {
        $errors[] = "Avatar must be a valid URL";
    }
    
    // Bio validation and XSS protection
    if (strlen($new_bio) > 500) {
        $errors[] = "Bio cannot exceed 500 characters";
    }
    // Strip HTML tags for security
    $new_bio = strip_tags($new_bio);
    
    // Update profile if no errors
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Update user profile
            $update_fields = [];
            $update_params = [];
            
            if ($username_changed) {
                $update_fields[] = "username = ?";
                $update_params[] = $new_username;
            }
            
            $update_fields[] = "bio = ?";
            $update_params[] = $new_bio;
            
            $update_fields[] = "avatar = ?";
            $update_params[] = $new_avatar ?: null;
            
            if ($color_changed) {
                $update_fields[] = "custom_color = ?";
                $update_params[] = $new_color;
            }
            
            if ($password_changed) {
                $update_fields[] = "password_hash = ?";
                $update_params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            $update_params[] = $_SESSION['user_id'];
            
            $update_query = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute($update_params);
            
            // Log username change (only for non-staff)
            if ($username_changed && !isStaff($user['role'])) {
                $log_change = "INSERT INTO username_changes (user_id, old_username, new_username) VALUES (?, ?, ?)";
                $log_stmt = $db->prepare($log_change);
                $log_stmt->execute([$_SESSION['user_id'], $user['username'], $new_username]);
            }
            
            $db->commit();
            
            // Update session
            if ($username_changed) {
                $_SESSION['username'] = $new_username;
            }
            
            $success = "Profile updated successfully!";
            
            // Refresh user data
            $user_stmt->execute([$_SESSION['user_id']]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Recalculate remaining changes
            if ($username_changed && !isStaff($user['role'])) {
                $remaining_changes = $remaining_changes === PHP_INT_MAX ? PHP_INT_MAX : $remaining_changes - 1;
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Failed to update profile. Please try again.";
        }
    }
}

// Get user count for nav
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
    <title>Edit Profile - NebulaBin</title>
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
                <a href="profile.php?id=<?php echo $_SESSION['user_id']; ?>">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="form-container" style="max-width: 700px;">
            <h2 style="text-align: center; margin-bottom: 20px; color: #fff;">Edit Profile</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="message message-error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="message message-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <!-- Current Avatar Preview -->
                <?php if ($user['avatar']): ?>
                    <div style="text-align: center; margin-bottom: 20px;">
                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Current Avatar" 
                             style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #333;">
                        <div style="color: #888; font-size: 12px; margin-top: 5px;">Current Avatar</div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">
                        Username 
                        <?php if ($max_changes > 0): ?>
                            <span style="color: #888; font-size: 12px;">
                                (<?php echo $remaining_changes === PHP_INT_MAX ? 'Unlimited' : $remaining_changes . ' changes remaining'; ?>)
                            </span>
                        <?php else: ?>
                            <span style="color: #ff4444; font-size: 12px;">(No changes allowed - upgrade to change username)</span>
                        <?php endif; ?>
                    </label>
                    <input type="text" name="username" class="form-input" 
                           value="<?php echo htmlspecialchars($user['username']); ?>"
                           pattern="[a-zA-Z0-9_-]{3,30}"
                           title="Username can only contain letters, numbers, underscores, and hyphens (3-30 characters)"
                           <?php echo ($max_changes <= 0 && $remaining_changes <= 0) ? 'readonly' : ''; ?>>
                </div>

                <div class="form-group">
                    <label class="form-label">Avatar URL (optional)</label>
                    <input type="url" name="avatar" class="form-input" 
                           placeholder="https://example.com/avatar.jpg"
                           value="<?php echo htmlspecialchars($user['avatar'] ?? ''); ?>">
                    <div style="color: #888; font-size: 12px; margin-top: 5px;">
                        Leave empty to use default avatar. Recommended size: 150x150px. GIF support for <?php echo isStaff($user['role']) ? 'staff' : 'VIP+ users'; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Bio (optional, max 500 characters)</label>
                    <textarea name="bio" class="form-input" rows="4" maxlength="500" 
                              placeholder="Tell others about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    <div style="color: #888; font-size: 12px; margin-top: 5px;">
                        <span id="bioCount"><?php echo strlen($user['bio'] ?? ''); ?></span>/500 characters
                    </div>
                </div>

                <!-- Color Change Section -->
                <div class="form-group">
                    <label class="form-label">
                        Username Color
                        <?php if (!canUseFeature('color_change', $user['upgrade_tier'], $user['role'], $user['can_change_color'])): ?>
                            <span class="premium-required">Requires Color Change upgrade ($10) or staff status</span>
                        <?php endif; ?>
                    </label>
                    
                    <?php if ($user['custom_color']): ?>
                        <div style="margin-bottom: 10px;">
                            <span style="color: <?php echo htmlspecialchars($user['custom_color']); ?>; font-weight: bold;">
                                Current: <?php echo htmlspecialchars($user['username']); ?>
                            </span>
                            <label style="margin-left: 15px; color: #888;">
                                <input type="checkbox" name="remove_color"> Remove custom color
                            </label>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (canUseFeature('color_change', $user['upgrade_tier'], $user['role'], $user['can_change_color'])): ?>
                        <div class="color-selection">
                            <div class="color-grid">
                                <?php foreach ($available_colors as $color => $name): ?>
                                    <label class="color-option-label" title="<?php echo $name; ?>">
                                        <input type="radio" name="custom_color" value="<?php echo $color; ?>" 
                                               <?php echo ($user['custom_color'] === $color) ? 'checked' : ''; ?>
                                               style="display: none;">
                                        <div class="color-option" style="background: <?php echo $color; ?>; <?php echo $color === '#000000' ? 'border: 2px solid #666;' : ''; ?>"></div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div style="color: #888; font-size: 12px; margin-top: 10px;">
                                Click a color to select it. Your username will appear in this color.
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="color: #888; font-style: italic;">
                            Purchase the Color Change upgrade for $10 to unlock 20 different color options.
                            <a href="upgrades.php" style="color: #4a9eff; margin-left: 10px;">View Upgrades</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Current Password (required for any changes)</label>
                    <input type="password" name="current_password" class="form-input" required>
                </div>

                <hr style="border: 1px solid #333; margin: 30px 0;">
                <h3 style="color: #fff; margin-bottom: 15px;">Change Password (optional)</h3>

                <div class="form-group">
                    <label class="form-label">New Password (leave empty to keep current)</label>
                    <input type="password" name="new_password" class="form-input" minlength="6">
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-input" minlength="6">
                </div>

                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" class="form-btn" style="flex: 1;">Update Profile</button>
                    <a href="profile.php?id=<?php echo $_SESSION['user_id']; ?>" class="form-btn" 
                       style="flex: 1; text-align: center; text-decoration: none; background: #555;">Cancel</a>
                </div>
            </form>

            <!-- Username Change History -->
            <?php if ($max_changes > 0): ?>
                <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #333;">
                    <h3 style="color: #fff; margin-bottom: 15px;">Username Change History</h3>
                    <?php
                    $history_query = "SELECT * FROM username_changes WHERE user_id = ? ORDER BY changed_at DESC LIMIT 10";
                    $history_stmt = $db->prepare($history_query);
                    $history_stmt->execute([$_SESSION['user_id']]);
                    $history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (!empty($history)): ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 1px solid #333;">
                                    <th style="text-align: left; padding: 10px; color: #ccc;">From</th>
                                    <th style="text-align: left; padding: 10px; color: #ccc;">To</th>
                                    <th style="text-align: left; padding: 10px; color: #ccc;">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $change): ?>
                                <tr style="border-bottom: 1px solid #222;">
                                    <td style="padding: 8px; color: #888;"><?php echo htmlspecialchars($change['old_username']); ?></td>
                                    <td style="padding: 8px; color: #4a9eff;"><?php echo htmlspecialchars($change['new_username']); ?></td>
                                    <td style="padding: 8px; color: #888;"><?php echo date('M j, Y', strtotime($change['changed_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #888; font-style: italic;">No username changes yet.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Bio character counter
        const bioTextarea = document.querySelector('textarea[name="bio"]');
        const bioCounter = document.getElementById('bioCount');
        
        if (bioTextarea && bioCounter) {
            bioTextarea.addEventListener('input', function() {
                bioCounter.textContent = this.value.length;
            });
        }

        // Password confirmation validation
        const newPassword = document.querySelector('input[name="new_password"]');
        const confirmPassword = document.querySelector('input[name="confirm_password"]');
        
        if (newPassword && confirmPassword) {
            function validatePasswords() {
                if (newPassword.value && confirmPassword.value) {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            newPassword.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);
        }

        // Color selection
        document.querySelectorAll('.color-option-label').forEach(label => {
            label.addEventListener('click', function() {
                // Remove selected class from all options
                document.querySelectorAll('.color-option').forEach(option => {
                    option.classList.remove('selected');
                });
                
                // Add selected class to clicked option
                this.querySelector('.color-option').classList.add('selected');
            });
        });
    </script>
</body>
</html>
