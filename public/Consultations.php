<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch();
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

$userStmt = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

$advisorsStmt = $pdo->query("SELECT * FROM Users WHERE role = 'admin'");
$advisors = $advisorsStmt->fetchAll();

$consultationsStmt = $pdo->prepare("
    SELECT c.*, a.name as advisor_name 
    FROM Consultations c
    JOIN Users a ON c.advisor_id = a.id
    WHERE c.user_id = ?
    ORDER BY c.consultation_date DESC
");
$consultationsStmt->execute([$userId]);
$consultations = $consultationsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $consultationId = $_POST['consultation_id'];
    $message = trim($_POST['message']);

    if (!empty($message)) {
        $insertStmt = $pdo->prepare("
            INSERT INTO ConsultationMessages (consultation_id, sender_id, message)
            VALUES (?, ?, ?)
        ");
        $insertStmt->execute([$consultationId, $userId, $message]);

        $updateStmt = $pdo->prepare("
            UPDATE Consultations 
            SET status = 'confirmed', updated_at = NOW() 
            WHERE id = ? AND status = 'pending'
        ");
        $updateStmt->execute([$consultationId]);

        header("Location: consultations.php?consultation_id=$consultationId");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_consultation'])) {
    $advisorId = $_POST['advisor_id'];
    $topic = trim($_POST['topic']);
    $message = trim($_POST['initial_message']);
    $consultationDate = $_POST['consultation_date'];

    if (empty($advisorId) || empty($topic) || empty($message) || empty($consultationDate)) {
        $_SESSION['error'] = "لطفاً تمام فیلدهای فرم را پر کنید.";
        header("Location: consultations.php");
        exit();
    }

    $stmt = $pdo->prepare("
        INSERT INTO Consultations (user_id, advisor_id, topic, message, consultation_date)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $advisorId, $topic, $message, $consultationDate]);

    $consultationId = $pdo->lastInsertId();
    header("Location: consultations.php?consultation_id=$consultationId");
    exit();
}

$currentConsultation = null;
$messages = [];
if (isset($_GET['consultation_id'])) {
    $consultationId = $_GET['consultation_id'];

    $consultationStmt = $pdo->prepare("
        SELECT c.*, a.name as advisor_name
        FROM Consultations c
        JOIN Users a ON c.advisor_id = a.id
        WHERE c.id = ? AND c.user_id = ?
    ");
    $consultationStmt->execute([$consultationId, $userId]);
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
    <title>مشاوره آنلاین | <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style-consultations.css">
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
            --chat-bg: #f5f7fb;
            --user-message-bg: #e3f2fd;
            --advisor-message-bg: #ffffff;
        }

        body {
            font-family: <?= $settings['font_family'] ?? 'Vazir, sans-serif' ?>;
            background-color: #f8f9fc;
            color: #333;
        }
    </style>
</head>

<body>
    <?php include 'header-index.php'; ?><br><br>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show text-center" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="container animate__animated animate__fadeIn">
        <div class="container animate__animated animate__fadeIn">

            <div class="alert alert-info mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle fa-2x me-3"></i>
                    <div>
                        <h4 class="alert-heading mb-2">راهنمای سامانه مشاوره</h4>
                        <p class="mb-1">این سامانه برای پاسخگویی به سوالات و حل مشکلات شما طراحی شده است.</p>
                        <p class="mb-0">می‌توانید با مشاوران ما گفتگو کنید و راهنمایی‌های تخصصی دریافت نمایید.</p>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-headset me-2"></i>
                    سامانه مشاوره آنلاین
                </h1>
                <button id="newConsultationBtn" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> جلسه جدید
                </button>
            </div>

            <div class="consultation-container">
                <div class="sidebar">
                    <div class="sidebar-header">
                        <h2 class="h5 mb-0"><i class="fas fa-calendar-alt me-2"></i> جلسات مشاوره</h2>
                    </div>

                    <ul class="consultation-list">
                        <?php foreach ($consultations as $consultation): ?>
                            <li class="consultation-item <?= ($currentConsultation && $currentConsultation['id'] == $consultation['id']) ? 'active' : '' ?>"
                                onclick="window.location.href='consultations.php?consultation_id=<?= $consultation['id'] ?>'">
                                <h3 class="h6 mb-1"><?= htmlspecialchars($consultation['topic']) ?></h3>
                                <p class="small mb-1"><i class="fas fa-user-tie me-1"></i> <?= htmlspecialchars($consultation['advisor_name']) ?></p>
                                <div class="d-flex justify-content-between small">
                                    <span><i class="far fa-calendar me-1"></i> <?= date('Y/m/d', strtotime($consultation['consultation_date'])) ?></span>
                                    <span class="consultation-status status-<?= $consultation['status'] ?>">
                                        <?php
                                        switch ($consultation['status']) {
                                            case 'pending':
                                                echo 'در انتظار';
                                                break;
                                            case 'confirmed':
                                                echo 'تأیید شده';
                                                break;
                                            case 'completed':
                                                echo 'تکمیل شده';
                                                break;
                                            case 'cancelled':
                                                echo 'لغو شده';
                                                break;
                                        }
                                        ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>

                        <?php if (empty($consultations)): ?>
                            <div class="no-consultation py-5">
                                <i class="fas fa-comment-slash mb-3"></i>
                                <p class="mb-0">هیچ جلسه مشاوره‌ای ندارید</p>
                            </div>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="main-content">
                    <?php if ($currentConsultation): ?>
                        <div class="chat-header">
                            <img src="<?= $currentConsultation['avatar'] ?? '../image/default-avatar.jpg' ?>" alt="مشاور" class="advisor-avatar">
                            <div class="flex-grow-1">
                                <h2 class="h5 mb-0"><?= htmlspecialchars($currentConsultation['advisor_name']) ?></h2>
                                <p class="small mb-0"><i class="fas fa-graduation-cap me-1"></i> ادمین</p>
                            </div>
                            <div class="chat-status">
                                <i class="fas fa-circle <?= $currentConsultation['status'] == 'completed' ? 'offline' : 'online' ?> me-1"></i>
                                <?= $currentConsultation['status'] == 'completed' ? 'پایان یافته' : 'آنلاین' ?>
                            </div>
                        </div>

                        <div class="chat-messages" id="chatMessages">
                            <?php foreach ($messages as $message): ?>
                                <div class="message <?= $message['role'] == 'user' ? 'message-user' : 'message-advisor' ?>">
                                    <div class="message-content">
                                        <div class="message-header">
                                            <span class="message-sender"><?= htmlspecialchars($message['sender_name']) ?></span>
                                            <span class="message-time"><?= date('H:i - Y/m/d', strtotime($message['sent_at'])) ?></span>
                                        </div>
                                        <div class="message-text">
                                            <?= nl2br(htmlspecialchars($message['message'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (empty($messages)): ?>
                                <div class="no-consultation py-5">
                                    <i class="fas fa-comment-dots mb-3"></i>
                                    <p class="mb-0">هنوز هیچ پیامی رد و بدل نشده است</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($currentConsultation['status'] != 'completed' && $currentConsultation['status'] != 'cancelled'): ?>
                            <div class="chat-input position-relative">
                                <form method="POST" class="d-flex">
                                    <input type="hidden" name="consultation_id" value="<?= $currentConsultation['id'] ?>">
                                    <textarea name="message" class="form-control me-2" placeholder="پیام خود را بنویسید..." rows="1" required></textarea>
                                    <button type="submit" name="send_message" class="btn btn-primary send-btn">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="consultation-ended">
                                <i class="fas fa-calendar-times me-2"></i>
                                <span>این جلسه مشاوره به پایان رسیده است</span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-consultation py-5">
                            <i class="fas fa-comments mb-3"></i>
                            <h2 class="h5 mb-2">جلسه مشاوره انتخاب نشده است</h2>
                            <p class="mb-0">لطفاً از لیست سمت راست یک جلسه را انتخاب کنید یا جلسه جدیدی ایجاد نمایید.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="newConsultationModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="h5 mb-0"><i class="fas fa-calendar-plus me-2"></i> ایجاد جلسه مشاوره جدید</h2>
                    <button class="close-btn">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="advisor_id" class="form-label"><i class="fas fa-user-tie me-2"></i> مشاور</label>
                            <select class="form-select" id="advisor_id" name="advisor_id" required>
                                <option value="">-- انتخاب مشاور --</option>
                                <?php foreach ($advisors as $advisor): ?>
                                    <option value="<?= $advisor['id'] ?>">
                                        <?= htmlspecialchars($advisor['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="topic" class="form-label"><i class="fas fa-tag me-2"></i> موضوع مشاوره</label>
                            <input type="text" class="form-control" id="topic" name="topic" placeholder="موضوع جلسه مشاوره" required>
                        </div>

                        <div class="mb-3">
                            <label for="consultation_date" class="form-label"><i class="far fa-calendar-alt me-2"></i> تاریخ و زمان جلسه</label>
                            <input type="datetime-local" class="form-control" id="consultation_date" name="consultation_date" required>
                        </div>

                        <div class="mb-4">
                            <label for="initial_message" class="form-label"><i class="fas fa-comment-dots me-2"></i> پیام اولیه</label>
                            <textarea class="form-control" id="initial_message" name="initial_message" rows="3" placeholder="توضیحاتی درباره درخواست مشاوره خود بنویسید..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer p-3 bg-light">
                        <button type="submit" name="create_consultation" class="btn btn-primary">ایجاد جلسه</button>
                        <button type="button" class="btn btn-outline-secondary me-2 close-btn">انصراف</button>
                    </div>
                </form>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            const modal = document.getElementById('newConsultationModal');
            const openBtns = document.querySelectorAll('#newConsultationBtn, #floatingChatBtn');
            const closeBtns = document.querySelectorAll('.close-btn');

            openBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                });
            });

            closeBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                });
            });

            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });

            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('consultation_date').min = tomorrow.toISOString().slice(0, 16);

            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            });

            function loadMessages() {
                const consultationId = <?= $currentConsultation['id'] ?? 'null' ?>;
                const chatMessages = document.getElementById('chatMessages');

                if (consultationId && chatMessages) {
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

            setInterval(loadMessages, 5000);

            const floatingChatBtn = document.getElementById('floatingChatBtn');
            const consultationContainer = document.querySelector('.consultation-container');

            floatingChatBtn.addEventListener('click', function() {
                if (consultationContainer.style.display === 'none') {
                    consultationContainer.style.display = 'flex';
                    this.innerHTML = '<i class="fas fa-comment-dots fa-lg"></i>';
                } else {
                    consultationContainer.style.display = 'none';
                    this.innerHTML = '<i class="fas fa-times fa-lg"></i>';
                }
            });
        </script>
</body>

</html>