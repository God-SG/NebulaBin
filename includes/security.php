<?php

class Security {
    
    /**
     * Sanitize input to prevent XSS attacks
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        // Remove null bytes
        $input = str_replace(chr(0), '', $input);
        
        // Convert special characters to HTML entities
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return trim($input);
    }
    
    /**
     * Validate and sanitize URL parameters
     */
    public static function validateId($id) {
        // Only allow positive integers
        if (!is_numeric($id) || $id <= 0 || $id != (int)$id) {
            return false;
        }
        return (int)$id;
    }
    
    /**
     * Prevent URL manipulation and redirects
     */
    public static function validateUrl($url) {
        // Only allow relative URLs or same domain
        if (empty($url)) return false;
        
        // Block external redirects
        if (preg_match('/^https?:\/\//', $url)) {
            $allowed_domains = [$_SERVER['HTTP_HOST']];
            $parsed = parse_url($url);
            if (!in_array($parsed['host'], $allowed_domains)) {
                return false;
            }
        }
        
        // Block dangerous protocols
        if (preg_match('/^(javascript|data|vbscript):/i', $url)) {
            return false;
        }
        
        return filter_var($url, FILTER_SANITIZE_URL);
    }
    
    /**
     * Validate and sanitize username
     */
    public static function validateUsername($username) {
        $username = self::sanitizeInput($username);
        
        // Check length
        if (strlen($username) < 3 || strlen($username) > 30) {
            return false;
        }
        
        // Only allow alphanumeric, underscore, and hyphen
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            return false;
        }
        
        return $username;
    }
    
    /**
     * Validate email address
     */
    public static function validateEmail($email) {
        $email = self::sanitizeInput($email);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        return $email;
    }
    
    /**
     * Secure session management
     */
    public static function startSecureSession() {
        // Prevent session fixation
        if (session_status() == PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } else if (time() - $_SESSION['created'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit($action, $identifier, $max_attempts = 5, $time_window = 300) {
        // Dev role bypasses all rate limits
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'dev') {
            return true;
        }
        
        $cache_key = "rate_limit_{$action}_{$identifier}";
        
        if (!isset($_SESSION[$cache_key])) {
            $_SESSION[$cache_key] = ['count' => 0, 'first_attempt' => time()];
        }
        
        $data = $_SESSION[$cache_key];
        
        // Reset if time window has passed
        if (time() - $data['first_attempt'] > $time_window) {
            $_SESSION[$cache_key] = ['count' => 1, 'first_attempt' => time()];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $max_attempts) {
            return false;
        }
        
        // Increment counter
        $_SESSION[$cache_key]['count']++;
        return true;
    }
    
    /**
     * Reset rate limit for a specific action and identifier
     */
    public static function resetRateLimit($action, $identifier) {
        $cache_key = "rate_limit_{$action}_{$identifier}";
        unset($_SESSION[$cache_key]);
    }
    
    /**
     * Clear all rate limits (useful for development)
     */
    public static function clearAllRateLimits() {
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'rate_limit_') === 0) {
                unset($_SESSION[$key]);
            }
        }
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Hash password securely
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}

class Captcha {
    
    /**
     * Generate simple math captcha
     */
    public static function generateMathCaptcha() {
        // Only generate new captcha if one doesn't exist or has expired
        if (isset($_SESSION['captcha_answer']) && isset($_SESSION['captcha_time']) && 
            isset($_SESSION['captcha_question']) && (time() - $_SESSION['captcha_time']) < 300) {
            return $_SESSION['captcha_question'];
        }
        
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $operations = ['+', '-'];
        $operation = $operations[array_rand($operations)];
        
        switch ($operation) {
            case '+':
                $answer = $num1 + $num2;
                break;
            case '-':
                // Ensure positive result
                if ($num1 < $num2) {
                    $temp = $num1;
                    $num1 = $num2;
                    $num2 = $temp;
                }
                $answer = $num1 - $num2;
                break;
        }
        
        $question = "$num1 $operation $num2 = ?";
        
        $_SESSION['captcha_answer'] = $answer;
        $_SESSION['captcha_time'] = time();
        $_SESSION['captcha_question'] = $question;
        
        return $question;
    }
    
    /**
     * Verify captcha answer
     */
    public static function verifyCaptcha($user_answer) {
        if (!isset($_SESSION['captcha_answer']) || !isset($_SESSION['captcha_time'])) {
            return false;
        }
        
        // Captcha expires after 5 minutes
        if (time() - $_SESSION['captcha_time'] > 300) {
            unset($_SESSION['captcha_answer'], $_SESSION['captcha_time'], $_SESSION['captcha_question']);
            return false;
        }
        
        // Convert both to integers and compare
        $user_answer = (int)trim($user_answer);
        $correct_answer = (int)$_SESSION['captcha_answer'];
        
        $correct = $user_answer === $correct_answer;
        
        // Clear captcha after verification attempt (whether correct or not)
        unset($_SESSION['captcha_answer'], $_SESSION['captcha_time'], $_SESSION['captcha_question']);
        
        return $correct;
    }
    
    /**
     * Clear captcha from session
     */
    public static function clearCaptcha() {
        unset($_SESSION['captcha_answer'], $_SESSION['captcha_time'], $_SESSION['captcha_question']);
    }
}
?>
