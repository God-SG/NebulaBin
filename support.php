<?php
session_start();
require_once 'config/database.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - CodeBin</title>
    <link rel="stylesheet" href="assets/css/doxbin-style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="home.php" class="logo">CodeBin</a>
            <ul class="nav-menu">
                <li><a href="home.php">Home</a></li>
                <li><a href="add-paste.php">Add Paste</a></li>
                <li><a href="users.php">Users</a></li>
                <li><a href="upgrades.php">Upgrades</a></li>
                <li><a href="support.php" class="active">Support</a></li>
            </ul>
            <div class="nav-right">
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
        <h1 style="text-align: center; font-size: 32px; margin: 40px 0;">Support</h1>
        
        <div style="max-width: 800px; margin: 0 auto;">
            <div class="form-container">
                <h2>Contact Support</h2>
                <p style="color: #cccccc; margin-bottom: 20px;">
                    Need help with CodeBin? Send us a message and we'll get back to you as soon as possible.
                </p>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Message</label>
                        <textarea name="message" class="form-input" rows="6" required></textarea>
                    </div>
                    
                    <button type="submit" class="form-btn">Send Message</button>
                </form>
            </div>
            
            <div style="margin-top: 40px; text-align: center;">
                <h3>Other Ways to Reach Us</h3>
                <p style="color: #cccccc; margin: 20px 0;">
                    <strong>Telegram:</strong> <a href="https://t.me/CodeBinSupport" style="color: #00aaff;">@CodeBinSupport</a><br>
                    <strong>Discord:</strong> <a href="https://discord.gg/codebin" style="color: #00aaff;">CodeBin Community</a><br>
                    <strong>Email:</strong> <a href="mailto:support@codebin.com" style="color: #00aaff;">support@codebin.com</a>
                </p>
            </div>
            
            <div style="margin-top: 40px;">
                <h3>Frequently Asked Questions</h3>
                <div style="background: #2a2a2a; border: 1px solid #3a3a3a; border-radius: 5px; padding: 20px; margin: 20px 0;">
                    <h4 style="color: #ffffff; margin-bottom: 10px;">How do I create a private paste?</h4>
                    <p style="color: #cccccc;">Private pastes are available for premium users. Upgrade to Criminal or Rich tier to unlock this feature.</p>
                </div>
                
                <div style="background: #2a2a2a; border: 1px solid #3a3a3a; border-radius: 5px; padding: 20px; margin: 20px 0;">
                    <h4 style="color: #ffffff; margin-bottom: 10px;">Can I edit my pastes after posting?</h4>
                    <p style="color: #cccccc;">Yes! VIP and higher tier users can edit their pastes instantly. Regular users can contact support for edits.</p>
                </div>
                
                <div style="background: #2a2a2a; border: 1px solid #3a3a3a; border-radius: 5px; padding: 20px; margin: 20px 0;">
                    <h4 style="color: #ffffff; margin-bottom: 10px;">How do I change my username color?</h4>
                    <p style="color: #cccccc;">Purchase the "Change Color" upgrade for $10 to unlock 20 different color options for your username.</p>
                </div>
                
                <div style="background: #2a2a2a; border: 1px solid #3a3a3a; border-radius: 5px; padding: 20px; margin: 20px 0;">
                    <h4 style="color: #ffffff; margin-bottom: 10px;">Can I post without an account?</h4>
                    <p style="color: #cccccc;">Yes! You can create pastes without registering, but you won't be able to edit or manage them later. Creating an account gives you more features and control over your pastes.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
