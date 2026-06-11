<?php
/**
 * Database Connection and Utility Functions
 * Reusable database functions for the Sewamandu project
 */

function getDBConnection() {
    $database = "sewamandu";
    try {
        $conn = new mysqli("127.0.0.1", "root", "", $database);
        return $conn;
    } catch (mysqli_sql_exception $e) {
        // Fallback to old name if 'sewamandu' doesn't exist
        $database = "gharsewa";
        try {
            $conn = new mysqli("127.0.0.1", "root", "", $database);
            return $conn;
        } catch (mysqli_sql_exception $e2) {
            die("Connection failed. Please ensure the database 'sewamandu' or 'gharsewa' exists. Error: " . $e2->getMessage());
        }
    }
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