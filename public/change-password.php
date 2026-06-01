<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'تمام فیلدها الزامی هستند.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'رمز عبور جدید با تایید رمز عبور مطابقت ندارد.';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'رمز عبور جدید باید حداقل ۸ کاراکتر باشد.';
    } else {
        $stmt = $conn->prepare("SELECT password FROM Users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!password_verify($current_password, $user['password'])) {
            $error_message = 'رمز عبور فعلی اشتباه است.';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update_stmt = $conn->prepare("UPDATE Users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $success_message = 'رمز عبور شما با موفقیت تغییر یافت.';
            } else {
                $error_message = 'خطایی در تغییر رمز عبور رخ داد.';
            }
            
            $update_stmt->close();
        }
    }
}

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch();
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تغییر رمز عبور</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-profile.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
</head>
<body>
    <div class="container py-5">
        <div class="profile-header p-4 text-center">
            <i class="bi bi-key-fill change-password-icon mb-3" style="font-size: 3rem; color: var(--primary-color);"></i>
            <h3 class="mb-4">تغییر رمز عبور</h3>
            <p class="mb-4">لطفاً رمز عبور فعلی خود را وارد کرده و رمز جدید را تنظیم کنید.</p>
        </div>

        <div class="profile-card mt-4">
            <div class="card-header">
                <i class="bi bi-pencil-square"></i> تنظیم رمز عبور
            </div>
            <div class="card-body">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="change-password.php">
                    <div class="mb-3">
                        <label for="current_password" class="form-label"><i class="bi bi-lock"></i> رمز عبور فعلی</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label"><i class="bi bi-lock-fill"></i> رمز عبور جدید</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label"><i class="bi bi-lock-fill"></i> تایید رمز عبور جدید</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-stone w-100">
                        <i class="bi bi-save"></i> ذخیره تغییرات
                    </button>
                    <a href="profile.php" class="btn btn-outline-light w-100 mt-3">
                        <i class="bi bi-arrow-right-circle"></i> بازگشت به پروفایل
                    </a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>