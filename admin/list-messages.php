<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit();
}


$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch();
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$messagesPerPage = 6;
$offset = ($page - 1) * $messagesPerPage;

$query = "SELECT * FROM ContactMessages WHERE name LIKE :search OR email LIKE :search ORDER BY created_at DESC LIMIT :offset, :messagesPerPage";
$stmt = $pdo->prepare($query);
$stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':messagesPerPage', $messagesPerPage, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalMessagesQuery = "SELECT COUNT(*) as total FROM ContactMessages WHERE name LIKE :search OR email LIKE :search";
$totalMessagesStmt = $pdo->prepare($totalMessagesQuery);
$totalMessagesStmt->execute(['search' => "%$search%"]);
$totalMessages = $totalMessagesStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalMessages / $messagesPerPage);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت پیام‌ها</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-list-messages.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($settings['button_color'] ?? '#4e73df') ?>;
            --secondary-color: #f8f9fc;
            --dark-color: #5a5c69;
            --light-color: #f8f9fa;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
        }

        body {
            font-family: <?= htmlspecialchars($settings['font_family'] ?? 'Vazir, sans-serif') ?>;
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
                    <i class="fas fa-envelope me-2"></i>
                    مدیریت پیام‌ها
                </h1>
                <div>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-store me-1"></i>
                        <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <a href="admin-panel.php" class="back-to-panel" title="بازگشت به پنل مدیریت">
        <i class="fas fa-arrow-left"></i>
    </a>

    <div class="container">
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="search-bar">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control search-input"
                            placeholder="جستجو بر اساس نام یا ایمیل" value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary btn-search">
                            <i class="fas fa-search me-2"></i> جستجو
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>لیست پیام‌ها</h5>
                    <span class="badge bg-light text-dark">
                        تعداد کل: <?= $totalMessages ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (count($messages) > 0): ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="col-md-6">
                                <div class="message-card">
                                    <h5>
                                        <i class="fas fa-user me-2"></i> <?= htmlspecialchars($message['name']) ?>
                                    </h5>
                                    <p class="message-preview">
                                        <i class="fas fa-comment me-2"></i> <?= htmlspecialchars(substr($message['message'], 0, 100)) ?>...
                                    </p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-2"></i> <?= htmlspecialchars($message['created_at']) ?>
                                    </small>
                                    <div class="btn-actions">
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#messageModal<?= $message['id'] ?>">
                                            <i class="fas fa-eye me-2"></i> مشاهده
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteMessage(<?= $message['id'] ?>)">
                                            <i class="fas fa-trash me-2"></i> حذف
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="messageModal<?= $message['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header d-flex align-items-center">
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            <h5 class="modal-title ms-2"><i class="fas fa-info-circle me-2"></i> جزئیات پیام</h5>
                                        </div>
                                        <div class="modal-body">
                                            <p><strong><i class="fas fa-user me-2"></i> نام:</strong> <?= htmlspecialchars($message['name']) ?></p>
                                            <p><strong><i class="fas fa-envelope me-2"></i> ایمیل:</strong> <?= htmlspecialchars($message['email']) ?></p>
                                            <p><strong><i class="fas fa-comment me-2"></i> پیام:</strong></p>
                                            <div class="bg-light p-3 rounded">
                                                <?= nl2br(htmlspecialchars($message['message'])) ?>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                <i class="fas fa-times me-2"></i> بستن
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i> هیچ پیامی یافت نشد.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?search=<?= htmlspecialchars($search) ?>&page=<?= $page - 1 ?>" aria-label="قبلی">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?search=<?= htmlspecialchars($search) ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?search=<?= htmlspecialchars($search) ?>&page=<?= $page + 1 ?>" aria-label="بعدی">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function deleteMessage(id) {
            if (confirm('آیا مطمئن هستید که می‌خواهید این پیام را حذف کنید؟')) {
                window.location.href = `delete-message.php?id=${id}`;
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>