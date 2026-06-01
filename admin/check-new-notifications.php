<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit();
}

$lastCheck = $_SESSION['last_notification_check'] ?? date('Y-m-d H:i:s', strtotime('-5 minutes'));

$countQuery = $conn->query("SELECT COUNT(*) AS count FROM Notifications 
                           WHERE created_at > '$lastCheck' AND is_dismissed = FALSE AND is_read = FALSE");
$newCount = $countQuery ? $countQuery->fetch_assoc()['count'] : 0;

$lastNotification = [];
if ($newCount > 0) {
    $lastQuery = $conn->query("SELECT * FROM Notifications 
                              WHERE created_at > '$lastCheck' AND is_dismissed = FALSE AND is_read = FALSE
                              ORDER BY created_at DESC LIMIT 1");
    if ($lastQuery) {
        $lastNotification = $lastQuery->fetch_assoc();
    }
}

$_SESSION['last_notification_check'] = date('Y-m-d H:i:s');

$conn->close();

echo json_encode([
    'success' => true,
    'newNotifications' => $newCount,
    'lastNotification' => $lastNotification
]);
?>