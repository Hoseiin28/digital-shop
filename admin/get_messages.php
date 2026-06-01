<?php
require_once 'config.php';

if (!isset($_GET['consultation_id'])) {
    die("Consultation ID is required");
}

session_start();

if (!isset($_SESSION['user_id'])) {
    die("User is not logged in.");
}

try {
    $stmt = $pdo->prepare("
        SELECT cm.*, u.name as sender_name 
        FROM ConsultationMessages cm
        JOIN Users u ON cm.sender_id = u.id
        WHERE cm.consultation_id = ?
        ORDER BY cm.sent_at ASC
    ");
    $stmt->execute([$_GET['consultation_id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($messages)) {
        echo "No messages found.";
        exit;
    }

    foreach ($messages as $message) {
        $isCurrentUser = isset($message['sender_id']) && ($message['sender_id'] == $_SESSION['user_id']);
        $messageClass = $isCurrentUser ? 'user-message' : 'advisor-message';
        echo '<div class="message ' . $messageClass . '">';
        echo '<strong>' . htmlspecialchars($message['sender_name']) . '</strong><br>';
        echo '<small class="text-muted">' . date('M j, Y H:i', strtotime($message['sent_at'])) . '</small><br>';
        echo nl2br(htmlspecialchars($message['message']));
        echo '</div>';
    }
} catch (PDOException $e) {
    die("Error fetching messages: " . $e->getMessage());
}
?>