<?php
require_once 'security.php';
require_once 'premium-functions.php';

class Comments {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
    }
    
    /**
     * Add a comment to a paste or profile
     */
    public function addComment($user_id, $content, $paste_id = null, $profile_user_id = null) {
        // Validate input
        $content = Security::sanitizeInput($content);
        if (empty($content) || strlen($content) > 1000) {
            return ['success' => false, 'error' => 'Comment must be between 1-1000 characters'];
        }
        
        // Check rate limiting
        if (!Security::checkRateLimit('comment', $user_id, 5, 300)) {
            return ['success' => false, 'error' => 'Too many comments. Please wait before commenting again.'];
        }
        
        // Validate that either paste_id or profile_user_id is provided, but not both
        if (($paste_id && $profile_user_id) || (!$paste_id && !$profile_user_id)) {
            return ['success' => false, 'error' => 'Invalid comment target'];
        }
        
        // Convert empty values to NULL
        $paste_id = $paste_id ?: null;
        $profile_user_id = $profile_user_id ?: null;
        
        // If commenting on a paste, check if it exists and is accessible
        if ($paste_id) {
            $paste_query = "SELECT id, is_private, user_id FROM pastes WHERE id = ?";
            $paste_stmt = $this->db->prepare($paste_query);
            $paste_stmt->execute([$paste_id]);
            $paste = $paste_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$paste) {
                return ['success' => false, 'error' => 'Paste not found'];
            }
            
            // Check if user can access private paste
            if ($paste['is_private'] && $paste['user_id'] != $user_id) {
                return ['success' => false, 'error' => 'Cannot comment on private paste'];
            }
        }
        
        // If commenting on a profile, check if user exists
        if ($profile_user_id) {
            $user_query = "SELECT id FROM users WHERE id = ?";
            $user_stmt = $this->db->prepare($user_query);
            $user_stmt->execute([$profile_user_id]);
            if (!$user_stmt->fetch()) {
                return ['success' => false, 'error' => 'User not found'];
            }
            
            // Prevent users from commenting on their own profile
            if ($profile_user_id == $user_id) {
                return ['success' => false, 'error' => 'You cannot comment on your own profile'];
            }
        }
        
        try {
            $this->db->beginTransaction();
            
            // Insert comment with explicit NULL handling
            $query = "INSERT INTO comments (user_id, content, paste_id, profile_user_id, created_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $user_id, 
                $content, 
                $paste_id, 
                $profile_user_id
            ]);
            
            if (!$result) {
                throw new Exception('Failed to insert comment');
            }
            
            $comment_id = $this->db->lastInsertId();
            
            // Update comment count for pastes only
            if ($paste_id) {
                $update_query = "UPDATE pastes SET comment_count = COALESCE(comment_count, 0) + 1 WHERE id = ?";
                $update_stmt = $this->db->prepare($update_query);
                $update_stmt->execute([$paste_id]);
            }
            
            $this->db->commit();
            
            return ['success' => true, 'comment_id' => $comment_id];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => 'Failed to add comment: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get comments for a paste
     */
    public function getPasteComments($paste_id, $limit = 50) {
        $limit = (int)$limit;
        
        $query = "SELECT c.*, u.username, u.role, u.upgrade_tier, u.custom_color, u.avatar 
                  FROM comments c 
                  LEFT JOIN users u ON c.user_id = u.id 
                  WHERE c.paste_id = ? AND c.profile_user_id IS NULL
                  ORDER BY c.created_at ASC 
                  LIMIT $limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$paste_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get comments for a profile
     */
    public function getProfileComments($profile_user_id, $limit = 20) {
        $limit = (int)$limit;
        
        $query = "SELECT c.*, u.username, u.role, u.upgrade_tier, u.custom_color, u.avatar 
                  FROM comments c 
                  LEFT JOIN users u ON c.user_id = u.id 
                  WHERE c.profile_user_id = ? AND c.paste_id IS NULL
                  ORDER BY c.created_at DESC 
                  LIMIT $limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$profile_user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Delete a comment (only by author, profile owner, or admin)
     */
    public function deleteComment($comment_id, $user_id, $user_role) {
        // Get comment details
        $query = "SELECT user_id, paste_id, profile_user_id FROM comments WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$comment_id]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$comment) {
            return ['success' => false, 'error' => 'Comment not found'];
        }
        
        // Check permissions: comment author, profile owner, or staff
        $can_delete = false;
        
        // Comment author can delete
        if ($comment['user_id'] == $user_id) {
            $can_delete = true;
        }
        
        // Profile owner can delete comments on their profile
        if ($comment['profile_user_id'] == $user_id) {
            $can_delete = true;
        }
        
        // Staff can delete any comment
        if (in_array($user_role, ['admin', 'manager', 'mod'])) {
            $can_delete = true;
        }
        
        if (!$can_delete) {
            return ['success' => false, 'error' => 'Permission denied'];
        }
        
        try {
            $this->db->beginTransaction();
            
            // Delete comment
            $delete_query = "DELETE FROM comments WHERE id = ?";
            $delete_stmt = $this->db->prepare($delete_query);
            $delete_stmt->execute([$comment_id]);
            
            // Update comment count for pastes only
            if ($comment['paste_id']) {
                $update_query = "UPDATE pastes SET comment_count = GREATEST(COALESCE(comment_count, 1) - 1, 0) WHERE id = ?";
                $update_stmt = $this->db->prepare($update_query);
                $update_stmt->execute([$comment['paste_id']]);
            }
            
            $this->db->commit();
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => 'Failed to delete comment: ' . $e->getMessage()];
        }
    }
}
?>
