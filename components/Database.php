<?php
/**
 * Database Connection and Utility Functions
 * Reusable database functions for the Sewamandu project
 */

// Connection settings. Use 127.0.0.1 (TCP) rather than "localhost",
// which forces a unix socket. The standalone PHP server's default
// socket (/tmp/mysql.sock) does not match XAMPP's socket location,
// so "localhost" fails with "No such file or directory".
if (!defined('DB_HOST'))  define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT'))  define('DB_PORT', 3306);
if (!defined('DB_USER'))  define('DB_USER', 'root');
if (!defined('DB_PASS'))  define('DB_PASS', '');

function getDBConnection() {
    $database = "sewamandu";
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, $database, DB_PORT);
        return $conn;
    } catch (mysqli_sql_exception $e) {
        // Fallback to old name if 'sewamandu' doesn't exist
        $database = "gharsewa";
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, $database, DB_PORT);
            return $conn;
        } catch (mysqli_sql_exception $e2) {
            die("Connection failed. Please ensure the database 'sewamandu' or 'gharsewa' exists. Error: " . $e2->getMessage());
        }
    }
}

/**
 * Resilient connection for public pages (e.g. landing page).
 * Returns a mysqli connection on success, or null on failure
 * instead of killing the request, so public pages can fall back
 * to static content if the database is unavailable.
 */
function getDBConnectionSafe() {
    $databases = ["sewamandu", "gharsewa"];
    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }
    foreach ($databases as $database) {
        try {
            return new mysqli(DB_HOST, DB_USER, DB_PASS, $database, DB_PORT);
        } catch (Throwable $e) {
            continue;
        }
    }
    return null;
}

function closeDBConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

/**
 * Safe database query execution with error handling
 */
function executeQuery($conn, $sql, $params = [], $types = '') {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['error' => 'Query preparation failed: ' . $conn->error];
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            $types = str_repeat('s', count($params)); // Default to string type
        }
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        return ['error' => 'Query execution failed: ' . $stmt->error];
    }
    
    $result = $stmt->get_result();
    $stmt->close();
    
    return ['success' => true, 'result' => $result];
}

/**
 * Fetch single row from database
 */
function fetchRow($conn, $sql, $params = [], $types = '') {
    $query_result = executeQuery($conn, $sql, $params, $types);
    if (isset($query_result['error'])) {
        return null;
    }
    return $query_result['result']->fetch_assoc();
}

/**
 * Fetch all rows from database
 */
function fetchAll($conn, $sql, $params = [], $types = '') {
    $query_result = executeQuery($conn, $sql, $params, $types);
    if (isset($query_result['error'])) {
        return [];
    }
    $rows = [];
    while ($row = $query_result['result']->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}
?> 