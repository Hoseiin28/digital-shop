<?php
session_start();
require_once 'config.php';

$message = '';
$error = '';

$settings = [];
$sliders = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT * FROM ShopSliders ORDER BY display_order");
    $sliders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $shop_name = $_POST['shop_name'];
        $shop_description = $_POST['shop_description'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $location = $_POST['location'];
        $working_hours = $_POST['working_hours'];
        $button_color = $_POST['button_color'];
        $font_family = $_POST['font_family'];
        
        $social_platforms = ['instagram', 'telegram', 'whatsapp', 'youtube'];
        $social_data = [];
        
        foreach ($social_platforms as $platform) {
            $base_url = $_POST["{$platform}_base"];
            $username = $_POST[$platform];
            
            if (!empty($username)) {
                if (!empty($base_url)) {
                    $social_data[$platform] = rtrim($base_url, '/') . '/' . ltrim($username, '/');
                } else {
                    $default_urls = [
                        'instagram' => 'https://instagram.com/',
                        'telegram' => 'https://t.me/',
                        'whatsapp' => 'https://wa.me/',
                        'youtube' => 'https://youtube.com/'
                    ];
                    $social_data[$platform] = $default_urls[$platform] . ltrim($username, '/');
                }
            } else {
                $social_data[$platform] = '';
            }
        }
        
        $logo_url = $settings['logo_url'] ?? '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../image/logo-shop/';
            $file_name = uniqid('logo_') . '.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                $logo_url = $upload_path;
                if (!empty($settings['logo_url']) && file_exists($settings['logo_url'])) {
                    unlink($settings['logo_url']);
                }
            }
        }
        
        if ($settings) {
            $sql = "UPDATE ShopSettings SET 
                    shop_name = ?, shop_description = ?, logo_url = ?, email = ?, phone = ?, 
                    instagram = ?, telegram = ?, whatsapp = ?, youtube = ?, address = ?, 
                    location = ?, working_hours = ?, button_color = ?, font_family = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $shop_name, $shop_description, $logo_url, $email, $phone,
                $social_data['instagram'], $social_data['telegram'], $social_data['whatsapp'], $social_data['youtube'],
                $address, $location, $working_hours, $button_color, $font_family, $settings['id']
            ]);
        } else {
            $sql = "INSERT INTO ShopSettings (
                    shop_name, shop_description, logo_url, email, phone, 
                    instagram, telegram, whatsapp, youtube, address, 
                    location, working_hours, button_color, font_family
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $shop_name, $shop_description, $logo_url, $email, $phone,
                $social_data['instagram'], $social_data['telegram'], $social_data['whatsapp'], $social_data['youtube'],
                $address, $location, $working_hours, $button_color, $font_family
            ]);
        }
        
        if (isset($_POST['slider_caption'])) {
            $pdo->exec("DELETE FROM ShopSliders");
            
            foreach ($_POST['slider_caption'] as $index => $caption) {
                $image_url = '';
                
                if (isset($_FILES['slider_image']['name'][$index]) && $_FILES['slider_image']['error'][$index] === UPLOAD_ERR_OK) {
                    $upload_dir = '../image/sliders/';
                    $file_name = uniqid('slider_') . '.' . pathinfo($_FILES['slider_image']['name'][$index], PATHINFO_EXTENSION);
                    $upload_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['slider_image']['tmp_name'][$index], $upload_path)) {
                        $image_url = $upload_path;
                    }
                } elseif (isset($_POST['existing_slider_image'][$index])) {
                    $image_url = $_POST['existing_slider_image'][$index];
                }
                
                if (!empty($image_url)) {
                    $button_text = $_POST['slider_button_text'][$index] ?? '';
                    $button_link = $_POST['slider_button_link'][$index] ?? '';
                    $display_order = $index + 1;
                    
                    $sql = "INSERT INTO ShopSliders (image_url, caption, button_text, button_link, display_order) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$image_url, $caption, $button_text, $button_link, $display_order]);
                }
            }
        }
        
        $pdo->commit();
        $message = "تنظیمات با موفقیت ذخیره شدند.";
        
        $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("SELECT * FROM ShopSliders ORDER BY display_order");
        $sliders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "خطا در ذخیره تنظیمات: " . $e->getMessage();
    }
}

function extract_username($url, $platform) {
    if (empty($url)) return '';
    
    $patterns = [
        'instagram' => ['~https?://(www\.)?instagram\.com/([^/]+)~i', '~^@?([^/]+)$~i'],
        'telegram' => ['~https?://(t\.me/|telegram\.me/)([^/]+)~i', '~^@?([^/]+)$~i'],
        'whatsapp' => ['~https?://wa\.me/(\d+)~i', '~^(\d+)$~i'],
        'youtube' => ['~https?://(www\.)?youtube\.com/(c/|channel/|user/)?([^/]+)~i', '~^([^/]+)$~i']
    ];
    
    foreach ($patterns[$platform] as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[count($matches) - 1];
        }
    }
    
    return $url;
}

$social_usernames = [];
$social_bases = [];
if ($settings) {
    $social_platforms = ['instagram', 'telegram', 'whatsapp', 'youtube'];
    foreach ($social_platforms as $platform) {
        $social_usernames[$platform] = extract_username($settings[$platform] ?? '', $platform);
        $social_bases[$platform] = str_replace($social_usernames[$platform], '', $settings[$platform] ?? '');
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات فروشگاه</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-settings.css">
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
                    <i class="fas fa-cog me-2"></i>
                    تنظیمات فروشگاه
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
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                        <i class="fas fa-store me-2"></i>
                        اطلاعات اصلی
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="social-tab" data-bs-toggle="tab" data-bs-target="#social" type="button" role="tab">
                        <i class="fas fa-share-alt me-2"></i>
                        شبکه‌های اجتماعی
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="design-tab" data-bs-toggle="tab" data-bs-target="#design" type="button" role="tab">
                        <i class="fas fa-paint-brush me-2"></i>
                        ظاهر و طراحی
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sliders-tab" data-bs-toggle="tab" data-bs-target="#sliders" type="button" role="tab">
                        <i class="fas fa-images me-2"></i>
                        اسلایدرها
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="settingsTabsContent">
                <div class="tab-pane fade show active" id="general" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="shop_name" class="form-label fw-bold">نام فروشگاه</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-store"></i></span>
                                <input type="text" class="form-control" id="shop_name" name="shop_name" 
                                       value="<?= htmlspecialchars($settings['shop_name'] ?? '') ?>" required>
                            </div>
                            <small class="text-muted">نامی که در بالای صفحه و عنوان مرورگر نمایش داده می‌شود</small>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label for="logo" class="form-label fw-bold">لوگو فروشگاه</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-image"></i></span>
                                <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                            </div>
                            <?php if (!empty($settings['logo_url'])): ?>
                                <div class="mt-3">
                                    <img src="<?= $settings['logo_url'] ?>" alt="لوگو فروشگاه" class="image-preview">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo">
                                        <label class="form-check-label text-danger" for="remove_logo">
                                            حذف لوگو فعلی
                                        </label>
                                    </div>
                                    <input type="hidden" name="existing_logo" value="<?= $settings['logo_url'] ?>">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-12 mb-4">
                            <label for="shop_description" class="form-label fw-bold">معرفی کوتاه فروشگاه</label>
                            <textarea class="form-control" id="shop_description" name="shop_description" rows="3"><?= htmlspecialchars($settings['shop_description'] ?? '') ?></textarea>
                            <small class="text-muted">این متن در صفحه اصلی و توضیحات SEO نمایش داده می‌شود</small>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <label for="email" class="form-label fw-bold">ایمیل</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($settings['email'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <label for="phone" class="form-label fw-bold">شماره تلفن</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?= htmlspecialchars($settings['phone'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <label for="address" class="form-label fw-bold">آدرس</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                <input type="text" class="form-control" id="address" name="address" 
                                       value="<?= htmlspecialchars($settings['address'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label for="location" class="form-label fw-bold">لینک نقشه (لوکیشن)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-map-marked-alt"></i></span>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?= htmlspecialchars($settings['location'] ?? '') ?>">
                            </div>
                            <small class="text-muted">لینک Google Maps یا سایر سرویس‌های نقشه</small>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label for="working_hours" class="form-label fw-bold">ساعت کاری</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                <input type="text" class="form-control" id="working_hours" name="working_hours" 
                                       value="<?= htmlspecialchars($settings['working_hours'] ?? '') ?>">
                            </div>
                            <small class="text-muted">مثال: 9:00 تا 17:00 - پنجشنبه‌ها تعطیل</small>
                        </div>
                        
                        <?php if (!empty($settings['location'])): ?>
                        <div class="col-12 mb-4">
                            <label class="form-label fw-bold">پیش‌نمایش نقشه</label>
                            <div class="ratio ratio-16x9">
                                <iframe src="<?= htmlspecialchars($settings['location']) ?>" 
                                        style="border:0;" allowfullscreen="" loading="lazy" 
                                        referrerpolicy="no-referrer-when-downgrade"></iframe>
                            </div>
                            <small class="text-muted">برای به‌روزرسانی پیش‌نمایش، تنظیمات را ذخیره کنید</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="tab-pane fade" id="social" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="instagram" class="form-label fw-bold">اینستاگرام</label>
                            <div class="input-group">
                                <span class="input-group-text bg-instagram text-white"><i class="fab fa-instagram"></i></span>
                                <input type="text" class="form-control" id="instagram_base" name="instagram_base" 
                                       value="<?= htmlspecialchars($social_bases['instagram'] ?? 'https://instagram.com/') ?>" 
                                       placeholder="آدرس پایه">
                                <span class="input-group-text">/</span>
                                <input type="text" class="form-control" id="instagram" name="instagram" 
                                       value="<?= htmlspecialchars($social_usernames['instagram'] ?? '') ?>" 
                                       placeholder="نام کاربری">
                            </div>
                            <small class="text-muted">مثال: instagram.com/<strong>username</strong> یا @<strong>username</strong></small>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label for="telegram" class="form-label fw-bold">تلگرام</label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white"><i class="fab fa-telegram"></i></span>
                                <input type="text" class="form-control" id="telegram_base" name="telegram_base" 
                                       value="<?= htmlspecialchars($social_bases['telegram'] ?? 'https://t.me/') ?>" 
                                       placeholder="آدرس پایه">
                                <span class="input-group-text">/</span>
                                <input type="text" class="form-control" id="telegram" name="telegram" 
                                       value="<?= htmlspecialchars($social_usernames['telegram'] ?? '') ?>" 
                                       placeholder="نام کاربری">
                            </div>
                            <small class="text-muted">مثال: t.me/<strong>username</strong> یا @<strong>username</strong></small>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label for="whatsapp" class="form-label fw-bold">واتساپ</label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white"><i class="fab fa-whatsapp"></i></span>
                                <input type="text" class="form-control" id="whatsapp_base" name="whatsapp_base" 
                                       value="<?= htmlspecialchars($social_bases['whatsapp'] ?? 'https://wa.me/') ?>" 
                                       placeholder="آدرس پایه">
                                <span class="input-group-text">/</span>
                                <input type="text" class="form-control" id="whatsapp" name="whatsapp" 
                                       value="<?= htmlspecialchars($social_usernames['whatsapp'] ?? '') ?>" 
                                       placeholder="شماره">
                            </div>
                            <small class="text-muted">مثال: wa.me/<strong>989121234567</strong> یا <strong>989121234567</strong></small>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label for="youtube" class="form-label fw-bold">یوتیوب</label>
                            <div class="input-group">
                                <span class="input-group-text bg-danger text-white"><i class="fab fa-youtube"></i></span>
                                <input type="text" class="form-control" id="youtube_base" name="youtube_base" 
                                       value="<?= htmlspecialchars($social_bases['youtube'] ?? 'https://youtube.com/') ?>" 
                                       placeholder="آدرس پایه">
                                <span class="input-group-text">/</span>
                                <input type="text" class="form-control" id="youtube" name="youtube" 
                                       value="<?= htmlspecialchars($social_usernames['youtube'] ?? '') ?>" 
                                       placeholder="کانال">
                            </div>
                            <small class="text-muted">مثال: youtube.com/<strong>channelname</strong></small>
                        </div>
                        
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">پیش‌نمایش لینک‌های اجتماعی</h5>
                                    <div class="d-flex justify-content-center mt-3">
                                        <a href="#" class="social-icon bg-instagram" id="preview-instagram">
                                            <i class="fab fa-instagram"></i>
                                        </a>
                                        <a href="#" class="social-icon bg-primary" id="preview-telegram">
                                            <i class="fab fa-telegram"></i>
                                        </a>
                                        <a href="#" class="social-icon bg-success" id="preview-whatsapp">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                        <a href="#" class="social-icon bg-danger" id="preview-youtube">
                                            <i class="fab fa-youtube"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="tab-pane fade" id="design" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="button_color" class="form-label fw-bold">رنگ اصلی سیستم</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-palette"></i></span>
                                <input type="color" class="form-control form-control-color" id="button_color" name="button_color" 
                                       value="<?= $settings['button_color'] ?? '#4e73df' ?>">
                                <input type="text" class="form-control" value="<?= $settings['button_color'] ?? '#4e73df' ?>" id="button_color_text">
                                <span class="input-group-text color-preview" id="colorPreview" 
                                      style="background-color: <?= $settings['button_color'] ?? '#4e73df' ?>"></span>
                            </div>
                            <small class="text-muted">این رنگ برای دکمه‌ها، هدرها و المان‌های اصلی سیستم استفاده می‌شود</small>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label for="font_family" class="form-label fw-bold">فونت سیستم</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-font"></i></span>
                                <select class="form-select" id="font_family" name="font_family">
                                    <option value="Vazir, sans-serif" <?= ($settings['font_family'] ?? '') === 'Vazir, sans-serif' ? 'selected' : '' ?>>Vazir (پیش‌فرض)</option>
                                    <option value="Samim, sans-serif" <?= ($settings['font_family'] ?? '') === 'Samim, sans-serif' ? 'selected' : '' ?>>Samim</option>
                                    <option value="Shabnam, sans-serif" <?= ($settings['font_family'] ?? '') === 'Shabnam, sans-serif' ? 'selected' : '' ?>>Shabnam</option>
                                    <option value="Yekan, sans-serif" <?= ($settings['font_family'] ?? '') === 'Yekan, sans-serif' ? 'selected' : '' ?>>Yekan</option>
                                    <option value="IRANSans, sans-serif" <?= ($settings['font_family'] ?? '') === 'IRANSans, sans-serif' ? 'selected' : '' ?>>IRANSans</option>
                                    <option value="Arial, sans-serif" <?= ($settings['font_family'] ?? '') === 'Arial, sans-serif' ? 'selected' : '' ?>>Arial</option>
                                    <option value="Tahoma, sans-serif" <?= ($settings['font_family'] ?? '') === 'Tahoma, sans-serif' ? 'selected' : '' ?>>Tahoma</option>
                                    <option value="'Segoe UI', sans-serif" <?= ($settings['font_family'] ?? '') === "'Segoe UI', sans-serif" ? 'selected' : '' ?>>Segoe UI</option>
                                </select>
                            </div>
                            <small class="text-muted">فونت پیش‌فرض برای نمایش متون در سیستم</small>
                        </div>
                        
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">پیش‌نمایش ظاهر سیستم</h5>
                                    <div class="p-4 bg-white rounded mt-3">
                                        <h4 style="color: var(--primary-color);">عنوان نمونه</h4>
                                        <p>این یک متن نمونه است برای نمایش فونت و رنگ‌های سیستم. شما می‌توانید تغییرات را در اینجا مشاهده کنید.</p>
                                        <button class="btn btn-primary me-2">دکمه اصلی</button>
                                        <button class="btn btn-outline-primary">دکمه ثانویه</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="tab-pane fade" id="sliders" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">مدیریت اسلایدرها</h5>
                        <button type="button" class="btn btn-primary" id="addSlider">
                            <i class="fas fa-plus me-2"></i>افزودن اسلایدر جدید
                        </button>
                    </div>
                    
                    <div id="slidersContainer">
                        <?php foreach ($sliders as $index => $slider): ?>
                            <div class="slider-item">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-bold">تصویر اسلایدر</label>
                                        <input type="file" class="form-control" name="slider_image[]" accept="image/*">
                                        <?php if (!empty($slider['image_url'])): ?>
                                            <div class="mt-3">
                                                <img src="<?= $slider['image_url'] ?>" class="image-preview">
                                                <div class="form-check mt-2">
                                                    <input class="form-check-input" type="checkbox" name="remove_slider_image[]" value="<?= $index ?>">
                                                    <label class="form-check-label text-danger">
                                                        حذف این تصویر
                                                    </label>
                                                </div>
                                                <input type="hidden" name="existing_slider_image[]" value="<?= $slider['image_url'] ?>">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <div class="row">
                                            <div class="col-12 mb-3">
                                                <label class="form-label fw-bold">متن روی تصویر</label>
                                                <input type="text" class="form-control" name="slider_caption[]" 
                                                       value="<?= htmlspecialchars($slider['caption'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">متن دکمه</label>
                                                <input type="text" class="form-control" name="slider_button_text[]" 
                                                       value="<?= htmlspecialchars($slider['button_text'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">لینک دکمه</label>
                                                <input type="text" class="form-control" name="slider_button_link[]" 
                                                       value="<?= htmlspecialchars($slider['button_link'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 text-end">
                                        <button type="button" class="btn btn-sm btn-danger remove-slider">
                                            <i class="fas fa-trash me-1"></i> حذف اسلایدر
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle me-2"></i>
                        برای حذف یک اسلایدر، روی دکمه "حذف اسلایدر" مربوط به آن کلیک کنید. ترتیب اسلایدرها بر اساس ترتیب نمایش در این صفحه خواهد بود.
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-save me-2"></i> ذخیره تنظیمات
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script-setting.js"></script>
</body>
</html>