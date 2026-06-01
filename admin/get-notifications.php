<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_role'])) {
    header("Location: ../public/login.php");
    exit();
}

$unreadCount = 0;
$notifications = [];

$query = $conn->query("SELECT * FROM Notifications WHERE is_read = FALSE AND is_dismissed = FALSE ORDER BY created_at DESC LIMIT 10");

if ($query) {
    while ($row = $query->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'is_read' => (bool)$row['is_read'],
            'related_id' => $row['related_id'],
            'created_at' => $row['created_at']
        ];
    }
    $unreadCount = count($notifications);
}

$conn->close();

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unreadCount' => $unreadCount
]);
?>