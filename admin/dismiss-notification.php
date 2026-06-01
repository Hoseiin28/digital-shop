<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'شناسه اعلان نامعتبر است']);
    exit();
}


$id = $conn->real_escape_string($data['id']);
$conn->query("UPDATE Notifications SET is_dismissed = TRUE WHERE id = '$id'");

$conn->close();

echo json_encode(['success' => true]);
?>