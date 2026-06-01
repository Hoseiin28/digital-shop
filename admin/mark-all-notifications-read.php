<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit();
}


$conn->query("UPDATE Notifications SET is_read = TRUE WHERE is_read = FALSE");

$conn->close();

echo json_encode(['success' => true]);
?>