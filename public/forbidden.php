<?php
session_start();
require_once 'config.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch();
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style-forbidden.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
    <title>دسترسی غیرمجاز</title>

</head>
<body>
    <div class="container">
        <div class="emoji">🚫</div>
        <h1>دسترسی غیرمجاز!</h1>
        <p>متأسفیم، شما اجازه دسترسی به این صفحه را ندارید.</p>
        <p>برای بازگشت به صفحه اصلی، <a href="/digital-shop/index.php">اینجا</a> کلیک کنید.</p>
    </div>
</body>
</html>