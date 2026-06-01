<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['consultation_id'])) {
    exit();
}

$userId = $_SESSION['user_id'];
$consultationId = $_GET['consultation_id'];

$messagesStmt = $pdo->prepare("
    SELECT m.*, u.name as sender_name, u.avatar, u.role
    FROM ConsultationMessages m
    JOIN Users u ON m.sender_id = u.id
    WHERE m.consultation_id = ?
    ORDER BY m.sent_at ASC
");
$messagesStmt->execute([$consultationId]);
$messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($messages as $message) {
    echo '<div class="message ' . ($message['role'] == 'user' ? 'message-user' : 'message-advisor') . '">';
    echo '<div class="message-content">';
    echo '<div class="message-header">';
    echo '<span class="message-sender">' . htmlspecialchars($message['sender_name']) . '</span>';
    echo '<span class="message-time">' . date('H:i - Y/m/d', strtotime($message['sent_at'])) . '</span>';
    echo '</div>';
    echo '<div class="message-text">' . nl2br(htmlspecialchars($message['message'])) . '</div>';
    echo '</div></div>';
}
?>