<?php
/**
 * Shared strong-password rules for registration and password reset.
 */

function passwordPolicyRules(): array
{
    return [
        'min_length' => 8,
        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#^()_\-+=\[\]{}:;,.<>\/\\\\|~`"\'])\S{8,}$/',
        'message' => 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character (no spaces).',
        'hint' => 'Use 8+ characters with uppercase, lowercase, a number, and a special character.',
    ];
}

/**
 * Validate a password against the project policy.
 * Returns null when valid, or an error message string when invalid.
 */
function validatePasswordStrength(string $password): ?string
{
    $rules = passwordPolicyRules();

    if ($password === '') {
        return 'Password is required.';
    }

    if (preg_match('/\s/', $password)) {
        return 'Password must not contain spaces.';
    }

    if (strlen($password) < (int) $rules['min_length']) {
        return 'Password must be at least ' . $rules['min_length'] . ' characters long.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must include at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must include at least one lowercase letter.';
    }

    if (!preg_match('/\d/', $password)) {
        return 'Password must include at least one number.';
    }

    if (!preg_match('/[@$!%*?&#^()_\-+=\[\]{}:;,.<>\/\\\\|~`"\']/', $password)) {
        return 'Password must include at least one special character.';
    }

    return null;
}
