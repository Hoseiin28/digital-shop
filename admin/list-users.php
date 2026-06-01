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
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

$message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $user_id = intval($_POST['user_id']);
    $new_role = $_POST['new_role'];

    if (in_array($new_role, ['user', 'admin'])) {
        try {
            $stmt = $pdo->prepare("UPDATE Users SET role = ? WHERE id = ?");
            $stmt->execute([$new_role, $user_id]);

            $_SESSION['message'] = "نقش کاربر با موفقیت تغییر کرد.";
            $_SESSION['message_type'] = "success";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $message = "خطا در تغییر نقش کاربر: " . $e->getMessage();
            $alert_type = "danger";
        }
    } else {
        $message = "نقش انتخاب شده معتبر نیست.";
        $alert_type = "danger";
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);

    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['message'] = "شما نمی‌توانید حساب خودتان را حذف کنید.";
        $_SESSION['message_type'] = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM Users WHERE id = ?");
            $stmt->execute([$user_id]);

            $_SESSION['message'] = "کاربر با موفقیت حذف شد.";
            $_SESSION['message_type'] = "success";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $message = "خطا در حذف کاربر: " . $e->getMessage();
            $alert_type = "danger";
        }
    }
}

$filter = $_GET['filter'] ?? 'all';

$sql = "SELECT id, name, email, phone, avatar, role, created_at FROM Users ";
if ($filter === 'admins') {
    $sql .= "WHERE role = 'admin' ";
} elseif ($filter === 'users') {
    $sql .= "WHERE role = 'user' ";
}
$sql .= "ORDER BY created_at ASC";

try {
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "خطا در دریافت لیست کاربران: " . $e->getMessage();
    $alert_type = "danger";
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-list-user.css">
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
                    <i class="fas fa-users me-2"></i>
                    مدیریت کاربران
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

        <?php if ($message): ?>
            <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
                <i class="fas <?= $alert_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="filter-buttons mb-4 text-center">
            <a href="?filter=all" class="btn btn-outline-primary <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-users me-1"></i> همه کاربران
            </a>
            <a href="?filter=admins" class="btn btn-outline-danger <?= $filter === 'admins' ? 'active' : '' ?>">
                <i class="fas fa-user-shield me-1"></i> مدیران
            </a>
            <a href="?filter=users" class="btn btn-outline-success <?= $filter === 'users' ? 'active' : '' ?>">
                <i class="fas fa-user me-1"></i> کاربران عادی
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    لیست کاربران
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th width="70">تصویر</th>
                                <th>نام</th>
                                <th>ایمیل</th>
                                <th width="120">نقش</th>
                                <th width="150">تاریخ ثبت‌نام</th>
                                <th width="180">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $index => $user): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <img src="<?= htmlspecialchars($user['avatar'] ?: '../image/avatars/default-avatar.jpg') ?>"
                                                alt="تصویر پروفایل"
                                                class="avatar-img">
                                        </td>
                                        <td><?= htmlspecialchars($user['name']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <span class="badge rounded-pill <?= $user['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                                                <?= $user['role'] === 'admin' ? 'مدیر' : 'کاربر' ?>
                                            </span>
                                        </td>
                                        <td><?= date('Y/m/d', strtotime($user['created_at'])) ?></td>
                                        <td class="user-actions">
                                            <button class="btn btn-sm btn-info"
                                                data-bs-toggle="modal"
                                                data-bs-target="#userDetailsModal"
                                                data-user-id="<?= $user['id'] ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="new_role" value="<?= $user['role'] === 'admin' ? 'user' : 'admin' ?>">
                                                <button type="submit" name="change_role" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </button>
                                            </form>

                                            <a href="?action=delete&id=<?= $user['id'] ?>"
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('آیا از حذف این کاربر مطمئن هستید؟')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">هیچ کاربری یافت نشد</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="userDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <h5 class="modal-title ms-2">جزئیات کاربر</h5>
                </div>
                <div class="modal-body" id="userDetailsContent">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/script-list-user.js"></script>
</body>

</html>