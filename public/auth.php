<?php

function isAdmin() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT role FROM Users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    return ($user && $user['role'] === 'admin');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function logout() {
    session_unset();
    session_destroy();
    header("Location: forbidden.php");
    exit();
}