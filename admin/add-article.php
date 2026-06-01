<?php
session_start();
include 'config.php';
include 'csrf_token.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$showSuccess = false;
$error_message = '';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("توکن امنیتی نامعتبر!");
    }

    $title = $_POST['title'];
    $content = $_POST['content'];
    $author_id = $_SESSION['user_id'];
    $category = $_POST['category'];
    $status = $_POST['status'];
    $uploadOk = 1;

    $target_dir = "../image/article-uploads/";
    $image_url = '';

    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == UPLOAD_ERR_OK) {
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $imageFileType = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $new_filename;

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($imageFileType, $allowed_types)) {
            $error_message = "فقط فرمت‌های JPG, JPEG, PNG, GIF و WEBP مجاز هستند.";
            $uploadOk = 0;
        }

        if ($_FILES["image"]["size"] > 5 * 1024 * 1024) {
            $error_message = "حداکثر حجم فایل ۵ مگابایت است.";
            $uploadOk = 0;
        }

        if ($uploadOk == 1) {
            if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $error_message = "خطا در بارگذاری تصویر. کد خطا: " . $_FILES["image"]["error"];
                $uploadOk = 0;
            } else {
                $image_url = $target_file;
            }
        }
    } else {
        $error_message = "لطفا تصویر مقاله را انتخاب کنید.";
        $uploadOk = 0;
    }

    if ($uploadOk == 1) {
        $content_cleaned = preg_replace('/<p[^>]*>/i', '', $content);
        $content_cleaned = str_replace('</p>', '', $content_cleaned);

        $stmt = $conn->prepare("INSERT INTO Articles (title, content, author_id, image_url, status, category) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisss", $title, $content_cleaned, $author_id, $image_url, $status, $category);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "مقاله با موفقیت اضافه شد.";
            $showSuccess = true;
        } else {
            $error_message = "خطا در اضافه کردن مقاله: " . $conn->error;
        }
    }
}

if ($showSuccess) {
?>

    <!DOCTYPE html>
    <html lang="fa" dir="rtl">

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="refresh" content="3;url=list-articles.php">
        <title>  مقاله ثبت شد</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        
    </head>

    <body class="bg-gray-100">
        <div class="min-h-screen flex items-center justify-center">
            <div class="bg-white p-8 rounded-xl shadow-lg text-center max-w-md w-full">
                <div class="text-green-500 text-6xl mb-4">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="text-2xl font-bold mb-4">مقاله جدید با موفقیت ثبت شد!</h1>
                <p class="text-gray-600 mb-6">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                </p>
                <div class="flex justify-center space-x-2 text-gray-500">
                    <span>انتقال خودکار پس از</span>
                    <span id="countdown">3</span>
                    <span>ثانیه...</span>
                </div>
            </div>
        </div>

        <script>
            let seconds = 3;
            const countdownEl = document.getElementById('countdown');

            setInterval(() => {
                seconds--;
                countdownEl.textContent = seconds;
                if (seconds <= 0) {
                    window.location.href = 'list-articles.php';
                }
            }, 1000);
        </script>
    </body>

    </html>
<?php
    unset($_SESSION['success_message']);
    exit();
}

$categories = [];
$result = $conn->query("SELECT * FROM Categories");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}
?>

<?php
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
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>افزودن مقاله</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.tiny.cloud/1/wg44oo2hmgdua5gqdi3nx446gjf6eslfu1l9bdznn9oo6iwd/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style-add-article.css">
    <?php if (!empty($settings['logo_url'])): ?>
            <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
        <?php endif; ?>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <div class="bg-white rounded-xl shadow-lg p-6 max-w-4xl mx-auto">
            <h1 class="text-2xl font-bold text-center mb-8">افزودن مقاله جدید</h1>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 text-red-800 p-4 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-exclamation-circle ml-2"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" enctype="multipart/form-data" id="articleForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="mb-6">
                    <label class="block text-gray-700 mb-2 font-medium">عنوان مقاله<span class="text-red-500">*</span></label>
                    <input type="text"
                        name="title"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        required
                        minlength="10">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 mb-2 font-medium">محتوای مقاله<span class="text-red-500">*</span></label>
                    <textarea id="content"
                        name="content"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                        rows="10"
                        required></textarea>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 mb-2 font-medium">تصویر شاخص<span class="text-red-500">*</span></label>
                    <div class="drop-area" id="dropArea">
                        <div class="space-y-2">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400"></i>
                            <p class="text-gray-600">تصویر را اینجا رها کنید یا کلیک کنید</p>
                            <p class="text-sm text-gray-400">فرمت‌های مجاز: JPG, PNG, GIF, WEBP - حداکثر حجم: 5MB</p>
                        </div>
                        <input type="file"
                            id="image"
                            name="image"
                            accept="image/*"
                            class="hidden"
                            required>
                        <img id="imagePreview" class="preview-image" src="#" alt="پیش‌نمایش تصویر">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">دسته‌بندی<span class="text-red-500">*</span></label>
                        <select name="category"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            required>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['id']) ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">وضعیت<span class="text-red-500">*</span></label>
                        <select name="status"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            required>
                            <option value="active">فعال</option>
                            <option value="inactive">غیرفعال</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-4">
                    <button type="submit"
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                        <i class="fas fa-plus ml-2"></i>
                        افزودن مقاله
                    </button>
                    <a href="list-articles.php"
                        class="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-200 transition-colors">
                        بازگشت
                    </a>
                </div>
            </form>
        </div>
    </div>
    <script src="../assets/js/add-article.js"></script>
</body>

</html>