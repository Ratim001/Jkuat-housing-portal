<?php
/**
 * includes/validation.php
 * Purpose: Centralize input validation rules for use across the app and tests.
 * Author: repo automation / commit: tests: add validation helpers
 */

function validate_name(string $name): bool {
    return mb_strlen(trim($name)) >= 2;
}

function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_phone(string $phone): bool {
    $p = trim($phone);
    return preg_match('/^[0-9+()\s\-]{6,25}$/', $p) === 1;
}

function validate_username(string $username): bool {
    return preg_match('/^[a-zA-Z0-9_.-]{3,}$/', $username) === 1;
}

function validate_password(string $password): bool {
    return strlen($password) >= 8;
}
