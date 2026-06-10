<?php
/**
 * Web-accessible cleanup script for expired reset tokens
 * Access via: http://localhost/sewamandu/cleanup_web.php
 */

// Simple authentication (you can modify this)
$admin_password = 'admin'; // Change this to a secure password

if (isset($_POST['password']) && $_POST['password'] === $admin_password) {
    // Run the cleanup
    ob_start();
    require_once 'cleanup_expired_tokens.php';
    $output = ob_get_clean();
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Cleanup Expired Tokens - Sewamandu</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .output { background: #f5f5f5; padding: 20px; border-radius: 5px; white-space: pre-wrap; }
            .success { color: green; }
            .error { color: red; }
            .form { margin-bottom: 20px; }
            input[type='password'] { padding: 8px; margin-right: 10px; }
            button { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        </style>
    </head>
    <body>
        <h1>Cleanup Expired Reset Tokens</h1>
        <div class='output'>$output</div>
        <p><a href='cleanup_web.php'>Run Again</a></p>
    </body>
    </html>";
    
} else {
    // Show login form
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Cleanup Expired Tokens - Sewamandu</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .form { margin: 20px 0; }
            input[type='password'] { padding: 8px; margin-right: 10px; }
            button { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        </style>
    </head>
    <body>
        <h1>Cleanup Expired Reset Tokens</h1>
        <p>Enter the admin password to run the cleanup:</p>
        <form method='POST' class='form'>
            <input type='password' name='password' placeholder='Admin Password' required>
            <button type='submit'>Run Cleanup</button>
        </form>
        <p><small>Note: Change the password in the script file for security.</small></p>
    </body>
    </html>";
}
?> 