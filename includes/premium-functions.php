<?php
// Premium feature checking functions

function isStaff($role) {
    return in_array($role, ['admin', 'manager', 'mod', 'helper', 'dev', 'council']);
}

function canUseFeature($feature, $upgrade_tier, $role, $can_change_color = false) {
    // Staff members and dev can use all features
    if (isStaff($role)) {
        return true;
    }
    
    $features = [
        'private' => ['criminal', 'rich'],
        'unlisted' => ['criminal', 'rich'],
        'password' => ['rich'],
        'instant_edit' => ['vip', 'criminal', 'rich'],
        'color_change' => [] // Special handling below
    ];
    
    // Special handling for color change
    if ($feature === 'color_change') {
        return $can_change_color || isStaff($role);
    }
    
    return isset($features[$feature]) && in_array($upgrade_tier, $features[$feature]);
}

function canChangeUsername($upgrade_tier, $role) {
    // Staff members and dev have unlimited username changes
    if (isStaff($role)) {
        return PHP_INT_MAX; // Unlimited
    }
    
    // Check upgrade tier permissions
    $permissions = [
        'vip' => 1,
        'criminal' => 2,
        'rich' => 3
    ];
    
    return isset($permissions[$upgrade_tier]) ? $permissions[$upgrade_tier] : 0;
}

function canInstantEdit($user_id, $paste_user_id, $upgrade_tier, $role) {
    if ($user_id != $paste_user_id) return false;
    return canUseFeature('instant_edit', $upgrade_tier, $role);
}

function getUsernameWithRole($username, $role, $upgrade_tier, $custom_color = null, $user_id = null, $avatar = null) {
    if ($custom_color) {
        $color_style = "style='color: $custom_color'";
    } else {
        $class = '';
        switch ($role) {
            case 'admin':
                $class = 'role-admin';
                break;
            case 'manager':
                $class = 'role-manager';
                break;
            case 'mod':
                $class = 'role-mod';
                break;
            case 'helper':
                $class = 'role-helper';
                break;
            case 'clique':
                $class = 'role-clique';
                break;
            case 'council':
                $class = 'role-council';
                break;
            case 'dev':
                $class = 'role-dev';
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
                    default:
                        $class = '';
                }
        }
        $color_style = $class ? "class='$class'" : '';
    }
    
    $badge = '';
    switch ($role) {
        case 'mod':
            $badge = '<span class="role-badge">[Mod]</span>';
            break;
        case 'council':
            $badge = '<span class="role-badge">[Council]</span>';
            break;
        case 'admin':
            $badge = '<span class="role-badge">[Admin]</span>';
            break;
        case 'manager':
            $badge = '<span class="role-badge">[Manager]</span>';
            break;
        case 'helper':
            $badge = '<span class="role-badge">[Helper]</span>';
            break;
        case 'dev':
            $badge = '<span class="role-badge badge-dev">[Dev] :3</span>';
            break;
        default:
            switch ($upgrade_tier) {
                case 'vip':
                    $badge = '<span class="role-badge badge-vip">[VIP]</span>';
                    break;
                case 'criminal':
                    $badge = '<span class="role-badge badge-criminal">[Criminal]</span>';
                    break;
                case 'rich':
                    $badge = '<span class="role-badge badge-rich">[Rich]</span>';
                    break;
            }
    }
    
    $avatar_img = '';
    if ($avatar) {
        $avatar_img = "<img src='" . htmlspecialchars($avatar) . "' alt='Avatar' class='avatar'>";
    }
    
    $profile_link = $user_id ? "profile.php?id=$user_id" : "#";
    
    return $avatar_img . "<a href='$profile_link' class='username-link'><span $color_style>$username</span></a><span class='user-id'>#$user_id</span>$badge";
}

function getRowClass($role, $upgrade_tier = 'none') {
    // Staff roles take priority
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
        case 'dev':
            return 'dev-row';
        default:
            // Premium user highlighting
            switch ($upgrade_tier) {
                case 'vip':
                    return 'vip-row';
                case 'criminal':
                    return 'criminal-row';
                case 'rich':
                    return 'rich-row';
                default:
                    return '';
            }
    }
}

// Debug function to check if user is staff (for testing)
function debugStaffStatus($role) {
    return "Role: $role, Is Staff: " . (isStaff($role) ? 'YES' : 'NO');
}
?>
