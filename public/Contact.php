<?php
session_start();
require_once 'config.php';

$settings = [];
$shopSettings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $shopSettings = $settings;
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);

    if (empty($name) || empty($email) || empty($message)) {
        $error = 'لطفاً تمامی فیلدها را پر کنید.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'ایمیل وارد شده معتبر نیست.';
    } else {
        try {
            $query = "INSERT INTO ContactMessages (name, email, message) VALUES (:name, :email, :message)";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':message', $message);

            if ($stmt->execute()) {
                $success = 'پیام شما با موفقیت ارسال شد.';
            } else {
                $error = 'خطا در ارسال پیام. لطفاً دوباره امتحان کنید.';
            }
        } catch (PDOException $e) {
            $error = 'خطا در سیستم. لطفاً بعداً تلاش کنید.';
            error_log("Error saving contact message: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تماس با ما | <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-contact.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?= $settings['button_color'] ?? '#4e73df' ?>;
            --primary-hover: <?= $settings['button_color'] ?? '#4e73df' ?>cc;
            --font-family: <?= $settings['font_family'] ?? 'Tahoma, Arial, sans-serif' ?>;
        }

        body {
            font-family: var(--font-family);
            background-color: #f8f9fa;
            color: #333;
        }

        .contact-hero {
            background: linear-gradient(135deg, #f5f7fa 0%, <?= substr($settings['button_color'] ?? '4e73df', 1) ?>20 100%);
            padding: 80px 0;
            text-align: center;
            margin-bottom: 50px;
            border-bottom: 1px solid #eee;
        }
        </style>
</head>

<body>
    <?php include 'header-index.php'; ?>

    <section class="contact-hero">
        <div class="container">
            <h1>تماس با <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?></h1>
            <p>ما اینجا هستیم تا به سوالات و پیشنهادات شما پاسخ دهیم. فرم زیر را پر کنید یا از طریق اطلاعات تماس با ما در ارتباط باشید.</p>
        </div>
    </section>

    <div class="container">
        <div class="contact-container">
            <div class="contact-section">
                <div class="contact-card">
                    <div class="card-header">
                        <i class="fas fa-paper-plane"></i>
                        ارسال پیام سریع
                    </div>

                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <?= htmlspecialchars($success) ?>
                            </div>
                            <script>
                                setTimeout(function() {
                                    window.location.href = "../index.php";
                                }, 3000);
                            </script>
                        <?php endif; ?>

                        <form action="contact.php" method="POST">
                            <div class="form-group">
                                <label for="name" class="form-label">نام کامل</label>
                                <input type="text" class="form-control" id="name" name="name" placeholder="نام و نام خانوادگی خود را وارد کنید" required value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                            </div>

                            <div class="form-group">
                                <label for="email" class="form-label">آدرس ایمیل</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="ایمیل معتبر خود را وارد کنید" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                            </div>

                            <div class="form-group">
                                <label for="message" class="form-label">متن پیام</label>
                                <textarea class="form-control" id="message" name="message" rows="5" placeholder="پیام خود را با جزئیات بنویسید..." required><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
                            </div>

                            <div class="btn-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> ارسال پیام
                                </button>
                                <a href="../index.php" class="btn btn-outline">
                                    <i class="fas fa-arrow-right"></i> بازگشت به فروشگاه
                                </a>
                            </div>
                        </form>
                    </div>

                    <div class="card-footer">
                        <p>
                            برای پیگیری پاسخ پیام‌های خود به
                            <a href="/digital-shop/public/profile.php#messages">پنل کاربری</a>
                            مراجعه کنید.
                        </p>
                    </div>
                </div>
            </div>

            <div class="contact-section">
                <div class="contact-card">
                    <div class="card-header">
                        <i class="fas fa-address-card"></i>
                        راه‌های ارتباطی
                    </div>

                    <div class="card-body">
                        <div class="contact-info-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div class="contact-info-text">
                                <h4>آدرس فروشگاه</h4>
                                <p><?= !empty($settings['address']) ? nl2br(htmlspecialchars($settings['address'])) : 'آدرس وارد نشده است' ?></p>
                            </div>
                        </div>

                        <div class="contact-info-item">
                            <i class="fas fa-phone-alt"></i>
                            <div class="contact-info-text">
                                <h4>تلفن‌های تماس</h4>
                                <p><?= !empty($settings['phone']) ? htmlspecialchars($settings['phone']) : 'شماره تماس وارد نشده است' ?></p>
                                <?php if (!empty($settings['whatsapp'])): ?>
                                    <p><a href="<?= htmlspecialchars($settings['whatsapp']) ?>" target="_blank"><i class="fab fa-whatsapp"></i> گفتگو در واتساپ</a></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="contact-info-item">
                            <i class="fas fa-envelope"></i>
                            <div class="contact-info-text">
                                <h4>پست الکترونیک</h4>
                                <p><a href="mailto:<?= !empty($settings['email']) ? htmlspecialchars($settings['email']) : '#' ?>"><?= !empty($settings['email']) ? htmlspecialchars($settings['email']) : 'ایمیل وارد نشده است' ?></a></p>
                            </div>
                        </div>

                        <div class="contact-info-item">
                            <i class="fas fa-clock"></i>
                            <div class="contact-info-text">
                                <h4>ساعات کاری</h4>
                                <p><?= !empty($settings['working_hours']) ? htmlspecialchars($settings['working_hours']) : 'ساعات کاری وارد نشده است' ?></p>
                            </div>
                        </div>

                        <div class="social-linkss">
                            <?php if (!empty($settings['instagram'])): ?>
                                <a href="<?= htmlspecialchars($settings['instagram']) ?>" title="اینستاگرام" target="_blank"><i class="fab fa-instagram"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($settings['telegram'])): ?>
                                <a href="<?= htmlspecialchars($settings['telegram']) ?>" title="تلگرام" target="_blank"><i class="fab fa-telegram-plane"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($settings['whatsapp'])): ?>
                                <a href="<?= htmlspecialchars($settings['whatsapp']) ?>" title="واتساپ" target="_blank"><i class="fab fa-whatsapp"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($settings['youtube'])): ?>
                                <a href="<?= htmlspecialchars($settings['youtube']) ?>" title="یوتیوب" target="_blank"><i class="fab fa-youtube"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="contact-card">
                    <div class="card-header">
                        <i class="fas fa-map-marked-alt"></i>
                        موقعیت فروشگاه
                    </div>

                    <div class="card-body" style="padding: 0;">
                        <div class="map-container">
                            <?php if (!empty($settings['location'])): ?>
                                <iframe
                                    src="<?= htmlspecialchars($settings['location']) ?>"
                                    allowfullscreen=""
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade">
                                </iframe>
                            <?php else: ?>
                                <div class="no-map">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <p>موقعیت فروشگاه تنظیم نشده است</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ارسال...';
        });

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>

</html>