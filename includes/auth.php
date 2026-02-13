<?php
/**
 * includes/auth.php
 * Simple auth helpers for admin checks.
 */

function is_admin(): bool {
    // Prefer an explicit flag if set
    if (isset($_SESSION['is_admin'])) {
        return (bool) $_SESSION['is_admin'];
    }
    // Fallback to role string check
    $role = $_SESSION['role'] ?? '';
    return stripos($role, 'admin') !== false;
}
