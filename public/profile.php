<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$default_tab = 'profile';
$allowed_tabs = ['profile', 'orders', 'favorites', 'consultations', 'messages'];
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], $allowed_tabs) ? $_GET['tab'] : $default_tab;

$user_id = $_SESSION['user_id'];

$settings = [];
$settings_query = $conn->query("SELECT * FROM ShopSettings LIMIT 1");
if ($settings_query && $settings_query->num_rows > 0) {
    $settings = $settings_query->fetch_assoc();
}
$primary_color = $settings['primary_color'] ?? '#4e73df';
$secondary_color = $settings['secondary_color'] ?? '#2c3e50';

$user_stmt = $conn->prepare("SELECT * FROM Users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

$favorites_stmt = $conn->prepare("
    SELECT p.id, p.name, p.price, p.image_url, 
           (SELECT AVG(rating) FROM Reviews WHERE product_id = p.id) as avg_rating,
           (SELECT COUNT(*) FROM Reviews WHERE product_id = p.id) as review_count
    FROM Favorites f
    JOIN Products p ON f.product_id = p.id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");
$favorites_stmt->bind_param("i", $user_id);
$favorites_stmt->execute();
$favorites = $favorites_stmt->get_result();

$orders_stmt = $conn->prepare("SELECT * FROM Orders WHERE user_id = ? ORDER BY created_at DESC");
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders = $orders_stmt->get_result();

$messages_stmt = $conn->prepare("SELECT * FROM ContactMessages WHERE email = ? ORDER BY created_at DESC");
$messages_stmt->bind_param("s", $user['email']);
$messages_stmt->execute();
$messages = $messages_stmt->get_result();

$consultations_stmt = $conn->prepare("
    SELECT Consultations.*, Users.name AS advisor_name 
    FROM Consultations
    JOIN Users ON Consultations.advisor_id = Users.id
    WHERE Consultations.user_id = ?
    ORDER BY Consultations.created_at DESC
");
$consultations_stmt->bind_param("i", $user_id);
$consultations_stmt->execute();
$consultations = $consultations_stmt->get_result();

$orderStatusTranslations = [
    'pending' => 'در انتظار بررسی',
    'processing' => 'در حال پردازش',
    'shipped' => 'ارسال شده',
    'completed' => 'تکمیل شده',
    'cancelled' => 'لغو شده'
];

$consultStatusTranslations = [
    'pending' => 'در انتظار بررسی',
    'confirmed' => 'تایید شده',
    'completed' => 'برگزار شده',
    'cancelled' => 'لغو شده'
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پروفایل کاربری | <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style-profile.css">

    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>

    <style>
        :root {
            --primary-color: <?= $primary_color ?>;
            --secondary-color: <?= $secondary_color ?>;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
    </style>
</head>

<body>
    <div class="container py-4">
        <div class="profile-header p-4 mb-4 animate__animated animate__fadeIn">
            <div class="row align-items-center">
                <div class="col-md-2 text-center text-md-start">
                    <img src="<?= htmlspecialchars($user['avatar'] ?? 'static/img/default-avatar.jpg') ?>"
                        alt="آواتار کاربر"
                        class="profile-avatar shadow">
                </div>
                <div class="col-md-7 text-center text-md-start">
                    <h3 class="mb-2 fw-bold"><?= htmlspecialchars($user['name']) ?></h3>
                    <p class="mb-2"><i class="bi bi-envelope-fill me-2"></i> <?= htmlspecialchars($user['email']) ?></p>
                    <p class="mb-0"><i class="bi bi-calendar-event me-2"></i> عضو شده در <?= date('Y/m/d', strtotime($user['created_at'])) ?></p>
                </div>
                <div class="col-md-3 d-flex flex-column flex-md-row align-items-center justify-content-md-end">
                    <a href="edit-profile.php" class="btn btn-outline-light btn-sm mb-2 mb-md-0 me-md-2">
                        <i class="bi bi-pencil-square me-1"></i> ویرایش پروفایل
                    </a>
                    <a href="logout.php" class="btn btn-light btn-sm">
                        <i class="bi bi-box-arrow-left me-1"></i> خروج
                    </a>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-3">
                <div class="profile-nav mb-4 animate__animated animate__fadeInLeft">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#profile" data-bs-toggle="tab">
                                <i class="bi bi-person"></i> پروفایل من
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#orders" data-bs-toggle="tab">
                                <i class="bi bi-cart3"></i> سفارش‌های من
                                <span class="badge bg-primary rounded-pill float-start"><?= $orders->num_rows ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#consultations" data-bs-toggle="tab">
                                <i class="bi bi-headset"></i> جلسات مشاوره
                                <span class="badge bg-primary rounded-pill float-start"><?= $consultations->num_rows ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#messages" data-bs-toggle="tab">
                                <i class="bi bi-chat-square-text"></i> پیام‌های من
                                <span class="badge bg-primary rounded-pill float-start"><?= $messages->num_rows ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a id="favorites-tab" class="nav-link" href="#favorites" data-bs-toggle="tab">
                                <i class="bi bi-heart-fill"></i> علاقه‌مندی‌ها
                                <span class="badge bg-primary rounded-pill float-start" id="favorites-count"><?= $favorites->num_rows ?></span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="profile-card animate__animated animate__fadeInLeft animate__delay-1s">
                    <div class="card-header">
                        <i class="bi bi-shield-lock"></i> امنیت حساب
                    </div>
                    <div class="card-body">
                        <a href="change-password.php" class="btn btn-outline-primary w-100 mb-3">
                            <i class="bi bi-key me-1"></i> تغییر رمز عبور
                        </a>
                        <div class="d-flex align-items-center text-muted">
                            <i class="bi bi-info-circle me-2"></i>
                            <small>آخرین ورود: <?= date('Y/m/d H:i', strtotime($user['last_login'] ?? 'now')) ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="profile">
                        <div class="profile-card animate__animated animate__fadeIn">
                            <div class="card-header">
                                <i class="bi bi-info-circle"></i> اطلاعات شخصی
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <div class="info-item d-flex align-items-center">
                                            <div class="icon-box bg-light-primary rounded-circle p-3 me-3">
                                                <i class="bi bi-telephone text-primary fs-4"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 fw-bold">تلفن همراه</h6>
                                                <p class="text-muted mb-0"><?= htmlspecialchars($user['phone'] ?? 'ثبت نشده') ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <div class="info-item d-flex align-items-center">
                                            <div class="icon-box bg-light-primary rounded-circle p-3 me-3">
                                                <i class="bi bi-geo-alt text-primary fs-4"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 fw-bold">آدرس</h6>
                                                <p class="text-muted mb-0"><?= htmlspecialchars($user['address'] ?? 'ثبت نشده') ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="profile-card animate__animated animate__fadeIn animate__delay-1s">
                            <div class="card-header">
                                <i class="bi bi-activity"></i> فعالیت‌های اخیر
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="stat-box">
                                            <div class="icon-box bg-light-primary rounded-circle p-3 mx-auto mb-3">
                                                <i class="bi bi-cart3 text-primary fs-4"></i>
                                            </div>
                                            <h3 class="text-primary fw-bold"><?= $orders->num_rows ?></h3>
                                            <p class="text-muted mb-0">سفارش‌ها</p>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-box">
                                            <div class="icon-box bg-light-primary rounded-circle p-3 mx-auto mb-3">
                                                <i class="bi bi-headset text-primary fs-4"></i>
                                            </div>
                                            <h3 class="text-primary fw-bold"><?= $consultations->num_rows ?></h3>
                                            <p class="text-muted mb-0">مشاوره‌ها</p>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-box">
                                            <div class="icon-box bg-light-primary rounded-circle p-3 mx-auto mb-3">
                                                <i class="bi bi-chat-square-text text-primary fs-4"></i>
                                            </div>
                                            <h3 class="text-primary fw-bold"><?= $messages->num_rows ?></h3>
                                            <p class="text-muted mb-0">پیام‌ها</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="favorites">
                        <div class="profile-card animate__animated animate__fadeIn">
                            <div class="card-header">
                                <i class="bi bi-heart-fill"></i> محصولات مورد علاقه
                            </div>
                            <div class="card-body">
                                <?php if ($favorites->num_rows > 0): ?>
                                    <div class="row">
                                        <?php while ($fav = $favorites->fetch_assoc()): ?>
                                            <div class="col-md-4 mb-4 animate__animated animate__fadeIn">
                                                <div class="favorite-product-card h-100">
                                                    <div class="product-image-container">
                                                        <img src="<?= htmlspecialchars($fav['image_url'] ?? '../image/default-product.jpg') ?>"
                                                            alt="<?= htmlspecialchars($fav['name']) ?>"
                                                            class="product-image">
                                                        <button class="remove-favorite-btn"
                                                            data-product-id="<?= $fav['id'] ?>"
                                                            onclick="removeFavorite(this, <?= $fav['id'] ?>)">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    </div>
                                                    <div class="product-info p-3">
                                                        <h5 class="fw-bold mb-2"><?= htmlspecialchars($fav['name']) ?></h5>
                                                        <div class="product-price text-primary fw-bold mb-2">
                                                            <?= number_format($fav['price']) ?> تومان
                                                        </div>
                                                        <div class="product-rating mb-3">
                                                            <?php
                                                            $avg_rating = !empty($fav['avg_rating']) ? floatval($fav['avg_rating']) : 0;
                                                            $full_stars = floor($avg_rating);
                                                            $half_star = ($avg_rating - $full_stars) >= 0.5 ? 1 : 0;
                                                            $empty_stars = 5 - $full_stars - $half_star;

                                                            for ($i = 0; $i < $full_stars; $i++) {
                                                                echo '<i class="bi bi-star-fill text-warning"></i>';
                                                            }
                                                            if ($half_star) {
                                                                echo '<i class="bi bi-star-half text-warning"></i>';
                                                            }
                                                            for ($i = 0; $i < $empty_stars; $i++) {
                                                                echo '<i class="bi bi-star text-warning"></i>';
                                                            }
                                                            ?>
                                                            <small class="text-muted">(<?= $fav['review_count'] ?? 0 ?>)</small>
                                                        </div>
                                                        <div class="product-actions d-flex gap-2">
                                                            <a href="product-details.php?id=<?= $fav['id'] ?>"
                                                                class="btn btn-outline-primary btn-sm flex-grow-1">
                                                                مشاهده محصول
                                                            </a>
                                                            <button class="btn btn-primary btn-sm flex-grow-1" onclick="addToCart(<?= $fav['id'] ?>)">
                                                                <i class="bi bi-cart-plus"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state text-center py-5">
                                        <div class="icon-box bg-light-primary rounded-circle p-4 mx-auto mb-4">
                                            <i class="bi bi-heart text-primary fs-1"></i>
                                        </div>
                                        <h5 class="mt-3 fw-bold">لیست علاقه‌مندی‌های شما خالی است</h5>
                                        <p class="mb-4 text-muted">می‌توانید محصولات مورد علاقه خود را از فروشگاه به این لیست اضافه کنید.</p>
                                        <a href="/digital-shop/index.php" class="btn btn-primary px-4">
                                            <i class="bi bi-arrow-left me-1"></i> بازگشت به فروشگاه
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="orders">
                        <div class="profile-card animate__animated animate__fadeIn">
                            <div class="card-header">
                                <i class="bi bi-cart3"></i> تاریخچه سفارش‌ها
                            </div>
                            <div class="card-body">
                                <?php if ($orders->num_rows > 0): ?>
                                    <?php while ($order = $orders->fetch_assoc()): ?>
                                        <div class="order-item mb-4 p-3 border-bottom animate__animated animate__fadeIn">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="icon-box bg-light-primary rounded-circle p-2 me-3">
                                                        <i class="bi bi-receipt text-primary fs-5"></i>
                                                    </div>
                                                    <h5 class="mb-0 fw-bold">سفارش #<?= $order['id'] ?></h5>
                                                </div>
                                                <span class="status-badge status-<?= $order['status'] ?>">
                                                    <?= $orderStatusTranslations[$order['status'] ?? 'نامشخص'] ?>
                                                </span>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-4 mb-2 mb-md-0">
                                                    <div class="d-flex align-items-center text-muted">
                                                        <i class="bi bi-calendar me-2"></i>
                                                        <span><?= date('Y/m/d', strtotime($order['created_at'])) ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 mb-2 mb-md-0">
                                                    <div class="d-flex align-items-center text-muted">
                                                        <i class="bi bi-currency-exchange me-2"></i>
                                                        <span><?= number_format($order['total_price']) ?> تومان</span>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 text-md-end">
                                                    <a href="order-details-for-users.php?id=<?= $order['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                        جزئیات سفارش <i class="bi bi-arrow-left me-1"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="empty-state text-center py-5">
                                        <div class="icon-box bg-light-primary rounded-circle p-4 mx-auto mb-4">
                                            <i class="bi bi-cart-x text-primary fs-1"></i>
                                        </div>
                                        <h5 class="mt-3 fw-bold">هنوز سفارشی ثبت نکرده‌اید</h5>
                                        <p class="mb-4 text-muted">می‌توانید از فروشگاه ما محصولات مورد نیاز خود را خریداری کنید.</p>
                                        <a href="/digital-shop/index.php" class="btn btn-primary px-4">
                                            <i class="bi bi-arrow-left me-1"></i> بازگشت به فروشگاه
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="consultations">
                        <div class="profile-card animate__animated animate__fadeIn">
                            <div class="card-header">
                                <i class="bi bi-headset"></i> جلسات مشاوره
                            </div>
                            <div class="card-body">
                                <?php if ($consultations->num_rows > 0): ?>
                                    <?php while ($consult = $consultations->fetch_assoc()): ?>
                                        <div class="consultation-item mb-4 p-3 border-bottom animate__animated animate__fadeIn">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="icon-box bg-light-primary rounded-circle p-2 me-3">
                                                        <i class="bi bi-person text-primary fs-5"></i>
                                                    </div>
                                                    <div>
                                                        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($consult['advisor_name']) ?></h5>
                                                        <p class="mb-0 text-muted"><?= htmlspecialchars($consult['topic']) ?></p>
                                                    </div>
                                                </div>
                                                <span class="status-badge status-<?= $consult['status'] ?>">
                                                    <?= $consultStatusTranslations[$consult['status'] ?? 'نامشخص'] ?>
                                                </span>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-2 mb-md-0">
                                                    <div class="d-flex align-items-center text-muted">
                                                        <i class="bi bi-clock me-2"></i>
                                                        <span><?= date('Y/m/d H:i', strtotime($consult['consultation_date'])) ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 text-md-end">
                                                    <a href="consultations.php?id=<?= $consult['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                        جزئیات جلسه <i class="bi bi-arrow-left me-1"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="empty-state text-center py-5">
                                        <div class="icon-box bg-light-primary rounded-circle p-4 mx-auto mb-4">
                                            <i class="bi bi-calendar-x text-primary fs-1"></i>
                                        </div>
                                        <h5 class="mt-3 fw-bold">هنوز جلسه مشاوره‌ای رزرو نکرده‌اید</h5>
                                        <p class="mb-4 text-muted">می‌توانید از طریق سیستم مشاوره ما، با متخصصان ما جلسه رزرو کنید.</p>
                                        <a href="consultations.php" class="btn btn-primary px-4">
                                            <i class="bi bi-plus-circle me-1"></i> رزرو مشاوره
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="messages">
                        <div class="profile-card animate__animated animate__fadeIn">
                            <div class="card-header">
                                <i class="bi bi-chat-square-text"></i> پیام‌های شما
                            </div>
                            <div class="card-body">
                                <?php if ($messages->num_rows > 0): ?>
                                    <?php while ($message = $messages->fetch_assoc()): ?>
                                        <div class="message-item mb-4 p-3 border-bottom animate__animated animate__fadeIn">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="icon-box bg-light-primary rounded-circle p-2 me-3">
                                                        <i class="bi bi-person-circle text-primary fs-5"></i>
                                                    </div>
                                                    <h5 class="mb-0 fw-bold"><?= htmlspecialchars($message['name']) ?></h5>
                                                </div>
                                                <small class="text-muted"><?= date('Y/m/d', strtotime($message['created_at'])) ?></small>
                                            </div>
                                            <p class="mb-0"><?= htmlspecialchars($message['message']) ?></p>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="empty-state text-center py-5">
                                        <div class="icon-box bg-light-primary rounded-circle p-4 mx-auto mb-4">
                                            <i class="bi bi-envelope-open text-primary fs-1"></i>
                                        </div>
                                        <h5 class="mt-3 fw-bold">پیامی یافت نشد</h5>
                                        <p class="mb-4 text-muted">شما هنوز هیچ پیامی ارسال نکرده‌اید.</p>
                                        <a href="contact.php" class="btn btn-primary px-4">
                                            <i class="bi bi-send me-1"></i> ارسال پیام جدید
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <a href="/digital-shop/index.php" class="floating-action-btn animate__animated animate__fadeInUp animate__delay-1s" title="بازگشت به فروشگاه">
        <i class="bi bi-house-door fs-5"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/script-profile.js"></script>
</body>

</html>