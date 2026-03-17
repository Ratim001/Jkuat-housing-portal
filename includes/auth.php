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

function current_role(): string {
    return (string) ($_SESSION['role'] ?? '');
}

function is_ict_admin(): bool {
    return current_role() === 'ICT Admin';
}

/**
 * CS-admin capabilities are shared by both CS Admin and HR Admin.
 */
function is_cs_admin(): bool {
    $role = current_role();
    return $role === 'CS Admin' || $role === 'HR Admin';
}
