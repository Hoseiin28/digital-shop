<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit();
}

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

$adminId = $_SESSION['user_id'];

$consultationsStmt = $pdo->prepare("
    SELECT c.*, u.name as user_name 
    FROM Consultations c
    JOIN Users u ON c.user_id = u.id
    WHERE c.advisor_id = ?
    ORDER BY c.consultation_date DESC
");
$consultationsStmt->execute([$adminId]);
$consultations = $consultationsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $consultationId = $_POST['consultation_id'];
        $newStatus = $_POST['status'];

        $updateStatusStmt = $pdo->prepare("
            UPDATE Consultations
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateStatusStmt->execute([$newStatus, $consultationId]);

        header("Location: list-consultations.php?consultation_id=$consultationId");
        exit();
    }

    if (isset($_POST['delete_consultation'])) {
        $consultationId = $_POST['consultation_id'];

        $deleteMessagesStmt = $pdo->prepare("DELETE FROM ConsultationMessages WHERE consultation_id = ?");
        $deleteMessagesStmt->execute([$consultationId]);

        $deleteConsultationStmt = $pdo->prepare("DELETE FROM Consultations WHERE id = ?");
        $deleteConsultationStmt->execute([$consultationId]);

        header("Location: list-consultations.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $consultationId = $_POST['consultation_id'];
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        $insertStmt = $pdo->prepare("
            INSERT INTO ConsultationMessages (consultation_id, sender_id, message)
            VALUES (?, ?, ?)
        ");
        $insertStmt->execute([$consultationId, $adminId, $message]);
        
        $updateStmt = $pdo->prepare("
            UPDATE Consultations 
            SET status = 'confirmed', updated_at = NOW() 
            WHERE id = ? AND status = 'pending'
        ");
        $updateStmt->execute([$consultationId]);
        
        header("Location: list-consultations.php?consultation_id=$consultationId");
        exit();
    }
}

$currentConsultation = null;
$messages = [];
if (isset($_GET['consultation_id'])) {
    $consultationId = $_GET['consultation_id'];
    
    $consultationStmt = $pdo->prepare("
        SELECT c.*, u.name as user_name, u.avatar as user_avatar
        FROM Consultations c
        JOIN Users u ON c.user_id = u.id
        WHERE c.id = ? AND c.advisor_id = ?
    ");
    $consultationStmt->execute([$consultationId, $adminId]);
    $currentConsultation = $consultationStmt->fetch();
    
    if ($currentConsultation) {
        $messagesStmt = $pdo->prepare("
            SELECT m.*, u.name as sender_name, u.avatar, u.role
            FROM ConsultationMessages m
            JOIN Users u ON m.sender_id = u.id
            WHERE m.consultation_id = ?
            ORDER BY m.sent_at ASC
        ");
        $messagesStmt->execute([$consultationId]);
        $messages = $messagesStmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت جلسات مشاوره | پنل مدیریت</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-list-consultations.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?= $settings['button_color'] ?? '#4e73df' ?>;
            --secondary-color: #f8f9fc;
            --dark-color: #5a5c69;
            --light-color: #f8f9fa;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
        }
        
        body {
            font-family: <?= $settings['font_family'] ?? 'Vazir, sans-serif' ?>;
            background-color: #f8f9fc;
            color: #333;
        }
        </style>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <i class="fas fa-headset me-2"></i>
                    مدیریت جلسات مشاوره
                </h1>
                <div>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-store me-1"></i>
                        <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه من') ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <a href="admin-panel.php" class="back-to-panel" title="بازگشت به پنل مدیریت">
        <i class="fas fa-arrow-left"></i>
    </a>

    <div class="container">
        <div class="consultation-container">
            
            <div class="sidebar">
                <div class="sidebar-header">
                    <i class="fas fa-calendar-alt me-2"></i> جلسات مشاوره
                </div>
                
                <ul class="consultation-list">
                    <?php foreach ($consultations as $consultation): ?>
                        <li class="consultation-item <?= ($currentConsultation && $currentConsultation['id'] == $consultation['id']) ? 'active' : '' ?>"
                            onclick="window.location.href='list-consultations.php?consultation_id=<?= $consultation['id'] ?>'">
                            <h5 class="bb"><?= htmlspecialchars($consultation['topic']) ?></h5>
                            <p class="aa"><i class="fas fa-user me-1"></i> <?= htmlspecialchars($consultation['user_name']) ?></p>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <small><i class="far fa-calendar me-1"></i> <?= date('Y/m/d', strtotime($consultation['consultation_date'])) ?></small>
                                <span class="consultation-status status-<?= $consultation['status'] ?>">
                                    <?php 
                                        switch($consultation['status']) {
                                            case 'pending': echo 'در انتظار'; break;
                                            case 'confirmed': echo 'تأیید شده'; break;
                                            case 'completed': echo 'تکمیل شده'; break;
                                            case 'cancelled': echo 'لغو شده'; break;
                                        }
                                    ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    
                    <?php if (empty($consultations)): ?>
                        <div class="no-consultation">
                            <i class="fas fa-comment-slash"></i>
                            <p>هیچ جلسه مشاوره‌ای ندارید</p>
                        </div>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="main-content">
                <?php if ($currentConsultation): ?>
                    <div class="chat-header">
                        <img src="<?= $currentConsultation['user_avatar'] ?>" alt="کاربر" class="advisor-avatar">
                        <div>
                            <h5 class="mb-0"><?= htmlspecialchars($currentConsultation['user_name']) ?></h5>
                            <small><i class="fas fa-user me-1"></i> کاربر</small>
                        </div>
                        <div class="chat-status">
                            <i class="fas fa-circle <?= $currentConsultation['status'] == 'completed' ? 'offline' : 'online' ?>"></i>
                            <?= $currentConsultation['status'] == 'completed' ? 'پایان یافته' : 'آنلاین' ?>
                        </div>
                    </div>

                    <div class="chat-messages" id="chatMessages">
                        <?php foreach ($messages as $message): ?>
                            <div class="message <?= $message['role'] == 'admin' ? 'message-advisor' : 'message-user' ?>">
                                <div class="message-header">
                                    <span><?= htmlspecialchars($message['sender_name']) ?></span>
                                    <small><?= date('H:i - Y/m/d', strtotime($message['sent_at'])) ?></small>
                                </div>
                                <div class="message-text">
                                    <?= nl2br(htmlspecialchars($message['message'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($messages)): ?>
                            <div class="no-messages">
                                <i class="fas fa-comment-dots"></i>
                                <p>هنوز هیچ پیامی رد و بدل نشده است</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($currentConsultation['status'] != 'completed' && $currentConsultation['status'] != 'cancelled'): ?>
                        <div class="chat-input">
                            <form method="POST" id="chatForm">
                                <input type="hidden" name="consultation_id" value="<?= $currentConsultation['id'] ?>">
                                <textarea name="message" placeholder="پیام خود را بنویسید..." required id="messageInput"></textarea>
                                <button type="submit" name="send_message" class="send-btn">
                                    <i class="fas fa-paper-plane"></i> 
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="consultation-ended">
                            <i class="fas fa-calendar-times"></i>
                            <p>این جلسه مشاوره به پایان رسیده است</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-consultation">
                        <i class="fas fa-comments"></i>
                        <h5>جلسه مشاوره انتخاب نشده است</h5>
                        <p>لطفاً از لیست سمت راست یک جلسه را انتخاب کنید.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
            
        <?php if ($currentConsultation): ?>
            <div class="manage-chat">
                <form method="POST" class="d-flex align-items-center gap-3">
                    <input type="hidden" name="consultation_id" value="<?= $currentConsultation['id'] ?>">
                    <div class="form-group mb-0">
                        <label for="status" class="me-2">وضعیت چت:</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="pending" <?= $currentConsultation['status'] === 'pending' ? 'selected' : '' ?>>در انتظار</option>
                            <option value="confirmed" <?= $currentConsultation['status'] === 'confirmed' ? 'selected' : '' ?>>تأیید شده</option>
                            <option value="completed" <?= $currentConsultation['status'] === 'completed' ? 'selected' : '' ?>>تکمیل شده</option>
                            <option value="cancelled" <?= $currentConsultation['status'] === 'cancelled' ? 'selected' : '' ?>>لغو شده</option>
                        </select>
                    </div>
                    <button type="submit" name="update_status" class="btn btn-primary">بروزرسانی وضعیت</button>
                </form>
                <form method="POST" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این چت را حذف کنید؟')">
                    <input type="hidden" name="consultation_id" value="<?= $currentConsultation['id'] ?>">
                    <button type="submit" name="delete_consultation" class="btn btn-danger">حذف چت</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function loadMessages() {
        const consultationId = <?= isset($currentConsultation['id']) ? $currentConsultation['id'] : 'null' ?>;
        if (consultationId) {
            fetch(`load_messages.php?consultation_id=${consultationId}`)
                .then(response => response.text())
                .then(data => {
                    if (data) {
                        chatMessages.innerHTML = data;
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }
                })
                .catch(error => console.error('Error loading messages:', error));
        }
    }

    setInterval(loadMessages, 3000);

    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    
    if (chatForm && messageInput) {
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.submit();
            }
        });
    }

    function showChatPopup(consultationId) {
        window.open(`chat-popup.php?consultation_id=${consultationId}`, 'chatWindow', 'width=400,height=600');
    }
    </script>
</body>
</html>