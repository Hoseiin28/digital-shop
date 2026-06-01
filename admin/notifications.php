<?php
session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit();
}

require_once 'config.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

try {
    $pdo->query("UPDATE Notifications SET is_read = TRUE WHERE is_read = FALSE");
} catch (PDOException $e) {
    $error = "خطا در بروزرسانی اعلان‌ها: " . $e->getMessage();
}

$notifications = [];
try {
    $stmt = $pdo->query("SELECT * FROM Notifications ORDER BY created_at DESC");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطا در دریافت اعلان‌ها: " . $e->getMessage();
}

if (isset($_POST['delete_all'])) {
    try {
        $pdo->query("DELETE FROM Notifications");
        $_SESSION['message'] = "تمام اعلان‌ها با موفقیت حذف شدند.";
        $_SESSION['message_type'] = 'success';
        header("Location: notifications.php");
        exit();
    } catch (PDOException $e) {
        $error = "خطا در حذف اعلان‌ها: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت اعلان‌ها</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-notifications.css">
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
            font-family: <?= $settings['font_family'] ?? 'IRANSans, sans-serif' ?>;
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
                    <i class="fas fa-bell me-2"></i>
                    مدیریت اعلان‌ها
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
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message_type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                <i class="fas <?= $_SESSION['message_type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                <?= $_SESSION['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">لیست اعلان‌ها</h5>
                    <form method="post" class="d-inline">
                        <button type="submit" name="delete_all" class="btn btn-danger btn-sm" onclick="return confirm('آیا از حذف تمام اعلان‌ها مطمئن هستید؟');">
                            <i class="fas fa-trash-alt me-1"></i>حذف همه
                        </button>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (count($notifications) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>">
                                <?php
                                switch($notification['type']) {
                                    case 'new_user':
                                        $iconClass = 'bg-primary bg-opacity-10 text-primary';
                                        $iconName = 'fas fa-user-plus';
                                        break;
                                    case 'new_order':
                                        $iconClass = 'bg-success bg-opacity-10 text-success';
                                        $iconName = 'fas fa-shopping-cart';
                                        break;
                                    case 'new_message':
                                        $iconClass = 'bg-info bg-opacity-10 text-info';
                                        $iconName = 'fas fa-envelope';
                                        break;
                                    case 'new_review':
                                        $iconClass = 'bg-warning bg-opacity-10 text-warning';
                                        $iconName = 'fas fa-comment';
                                        break;
                                    case 'new_consultation':
                                        $iconClass = 'bg-secondary bg-opacity-10 text-secondary';
                                        $iconName = 'fas fa-headset';
                                        break;
                                    default:
                                        $iconClass = 'bg-light text-dark';
                                        $iconName = 'fas fa-bell';
                                }
                                ?>
                                
                                <div class="notification-icon <?= $iconClass ?>">
                                    <i class="<?= $iconName ?>"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title"><?= htmlspecialchars($notification['title']) ?></div>
                                    <div class="notification-message"><?= htmlspecialchars($notification['message']) ?></div>
                                    <div class="notification-time">
                                        <i class="fas fa-clock me-1"></i>
                                        <?= date('Y-m-d H:i', strtotime($notification['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <p>هیچ اعلانی وجود ندارد</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.documentElement.style.setProperty('--primary-color', '<?= $settings['button_color'] ?? '#4e73df' ?>');
    </script>
</body>
</html>