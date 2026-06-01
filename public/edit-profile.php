<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM Users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$success_message = '';
$error_message = '';
$redirect = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']); 
    $address = trim($_POST['address']);

    if (!empty($phone) && !preg_match("/^\+?[0-9]{7,15}$/", $phone)) {
        $error_message = "شماره تلفن معتبر نیست.";
    }

    $avatar_path = $user['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $avatars_dir = "../image/avatars/";

        if (!is_dir($avatars_dir)) {
            if (!mkdir($avatars_dir, 0777, true)) {
                $error_message = "خطا در ایجاد پوشه آواتارها.";
            }
        }

        if (empty($error_message)) {
            $avatar_name = basename($_FILES['avatar']['name']);
            $target_file = $avatars_dir . time() . "_" . $avatar_name;

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($_FILES['avatar']['type'], $allowed_types)) {
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
                    $avatar_path = $target_file;
                    
                    if ($user['avatar'] && strpos($user['avatar'], 'default-avatar.jpg') === false) {
                        @unlink($user['avatar']);
                    }
                } else {
                    $error_message = "خطایی در آپلود تصویر رخ داد.";
                }
            } else {
                $error_message = "فرمت تصویر معتبر نیست. فقط JPG، PNG و GIF قابل قبول هستند.";
            }
        }
    }

    if (empty($error_message)) {
        $update_stmt = $conn->prepare("UPDATE Users SET name = ?, email = ?, phone = ?, address = ?, avatar = ? WHERE id = ?");
        $update_stmt->bind_param("sssssi", $name, $email, $phone, $address, $avatar_path, $user_id);

        if ($update_stmt->execute()) {
            $success_message = "پروفایل شما با موفقیت به‌روزرسانی شد.";
            $redirect = true;

            $_SESSION['user_name'] = $name;
            $_SESSION['user_avatar'] = $avatar_path;

            header("Location: profile.php");
            exit();
        } else {
            $error_message = "خطایی در به‌روزرسانی پروفایل رخ داد.";
        }

        $update_stmt->close();
    }
}

$settings = [];
$settings_query = $conn->query("SELECT * FROM ShopSettings LIMIT 1");
if ($settings_query && $settings_query->num_rows > 0) {
    $settings = $settings_query->fetch_assoc();
}
$primary_color = $settings['primary_color'] ?? '#4e73df';
$secondary_color = $settings['secondary_color'] ?? '#2c3e50';

$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش پروفایل | <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?></title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style-edit-profile.css">
    
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
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: <?= $settings['primary_color_dark'] ?? '#3a5bcd' ?>;
            border-color: <?= $settings['primary_color_dark'] ?? '#3a5bcd' ?>;
            transform: translateY(-2px);
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
                <div class="col-md-3 text-center text-md-end">
                    <a href="profile.php" class="btn btn-outline-light btn-sm me-2">
                        <i class="bi bi-arrow-left-circle me-1"></i> بازگشت
                    </a>
                </div>
            </div>
        </div>
        
        <div class="profile-card animate__animated animate__fadeInUp">
            <div class="card-header">
                <i class="bi bi-pencil-square"></i> ویرایش پروفایل
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form action="edit-profile.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="avatar-preview mb-4">
                        <img id="avatarPreview" src="<?= htmlspecialchars($user['avatar'] ?? '../image/default-avatar.jpg') ?>" alt="پیش‌نمایش آواتار">
                        <label for="avatar" class="avatar-upload-btn">
                            <i class="bi bi-camera-fill"></i> تغییر تصویر
                        </label>
                        <input type="file" id="avatar" name="avatar" class="d-none" accept="image/*">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label fw-bold">
                                <i class="bi bi-person me-2"></i> نام کامل
                            </label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($user['name']) ?>" required>
                            <div class="invalid-feedback">
                                لطفاً نام خود را وارد کنید
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label fw-bold">
                                <i class="bi bi-envelope me-2"></i> آدرس ایمیل
                            </label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                            <div class="invalid-feedback">
                                لطفاً یک ایمیل معتبر وارد کنید
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label fw-bold">
                                <i class="bi bi-telephone me-2"></i> شماره تلفن
                            </label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            <div class="invalid-feedback">
                                لطفاً شماره تلفن معتبر وارد کنید
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label fw-bold">
                            <i class="bi bi-house me-2"></i> آدرس
                        </label>
                        <textarea id="address" name="address" class="form-control" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary py-2">
                            <i class="bi bi-save me-2"></i> ذخیره تغییرات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/script-edit-profile.js"></script>
    <script>
        <?php if ($success_message && $redirect): ?>
            Swal.fire({
                title: 'موفقیت‌آمیز!',
                text: '<?= addslashes($success_message) ?>',
                icon: 'success',
                confirmButtonText: 'باشه',
                confirmButtonColor: '<?= $primary_color ?>',
                timer: 3000
            }).then(() => {
                window.location.href = 'profile.php';
            });
        <?php endif; ?>
    </script>
</body>
</html>