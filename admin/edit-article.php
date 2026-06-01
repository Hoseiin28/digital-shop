<?php
session_start();
require_once 'config.php';
include 'csrf_token.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit(); 
}

$article = [];
$id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT a.*, c.name AS category_name 
                      FROM Articles a
                      LEFT JOIN Categories c ON a.category = c.id
                      WHERE a.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$article = $stmt->get_result()->fetch_assoc();

$categories = $conn->query("SELECT * FROM Categories");

$showSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $content = preg_replace('/<p[^>]*>(.*?)<\/p>/is', '$1', $content);
    $content = nl2br($content);
    $status = $_POST['status'];
    $category = (int)$_POST['category'];
    
    if(empty($title)) $errors['title'] = 'عنوان مقاله الزامی است';
    if(empty($content)) $errors['content'] = 'محتوای مقاله نمی‌تواند خالی باشد';
    
    $image_url = $article['image_url'];
    if(!empty($_FILES['image']['name'])) {
        $target_dir = "../image/article-uploads/";
        $imageFileType = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $new_filename;
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
        if(!in_array($imageFileType, $allowed_types)) {
            $errors['image'] = 'فرمت فایل مجاز نیست';
        }
        
        if($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $errors['image'] = 'حداکثر حجم فایل 5 مگابایت';
        }
        
        if(empty($errors['image'])) {
            if(move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                if(!empty($article['image_url']) && file_exists($article['image_url'])) {
                    unlink($article['image_url']);
                }
                $image_url = $target_file;
            } else {
                $errors['image'] = 'خطا در آپلود فایل';
            }
        }
    }
    
    if(empty($errors)) {
        $stmt = $conn->prepare("UPDATE Articles SET 
            title = ?, 
            content = ?, 
            status = ?, 
            category = ?, 
            image_url = ? 
            WHERE id = ?");
        $stmt->bind_param("sssisi", $title, $content, $status, $category, $image_url, $id);
        
        if($stmt->execute()) {
            $_SESSION['success_message'] = 'مقاله با موفقیت ویرایش شد';
            $showSuccess = true;
        } else {
            $errors['general'] = 'خطا در ویرایش مقاله';
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
        <title> مقاله ویرایش شد</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        
    </head>
    <body class="bg-gray-100">
        <div class="min-h-screen flex items-center justify-center">
            <div class="bg-white p-8 rounded-xl shadow-lg text-center max-w-md w-full">
                <div class="text-green-500 text-6xl mb-4">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="text-2xl font-bold mb-4">ویرایش با موفقیت انجام شد!</h1>
                <p class="text-gray-600 mb-6">
                    <?= $_SESSION['success_message'] ?>
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
                if(seconds <= 0) {
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
    <title>ویرایش مقاله</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.tiny.cloud/1/wg44oo2hmgdua5gqdi3nx446gjf6eslfu1l9bdznn9oo6iwd/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style-edit-article.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
</head>
<body class="bg-slate-50">
<div class="max-w-6xl mx-auto p-4 lg:p-8">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-s-4">
                    <i class="fas fa-edit text-2xl text-blue-600"></i>
                    <h1 class="text-2xl font-bold text-gray-800">ویرایش مقاله: <?= htmlspecialchars($article['title']) ?></h1>
                </div>
                <a href="list-articles.php" class="flex items-center text-blue-600 hover:text-blue-800 space-s-2">
                    <span>بازگشت به لیست</span>
                    <i class="fas fa-arrow-left text-sm"></i>
                </a>
            </div>
        </div>

        <div class="p-6 lg:p-8">
            <?php if(isset($errors['general'])): ?>
                <div class="bg-red-50 text-red-800 p-4 rounded-xl mb-6 border border-red-100">
                    <?= $errors['general'] ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-8" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                        عنوان مقاله
                        <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="title" 
                        value="<?= htmlspecialchars($article['title']) ?>"
                        class="w-full px-4 py-3 rounded-xl border-2 focus:border-blue-500 focus:ring-0 <?= isset($errors['title']) ? 'border-red-500' : 'border-gray-200' ?>"
                        placeholder="عنوان مقاله خود را وارد کنید..."
                        required
                        minlength="10">
                    <?php if(isset($errors['title'])): ?>
                        <p class="text-red-500 text-sm mt-1"><i class="fas fa-exclamation-circle ml-1"></i><?= $errors['title'] ?></p>
                    <?php endif; ?>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                        محتوای مقاله
                        <span class="text-red-500">*</span>
                    </label>
                    <textarea name="content" id="content" 
                    class="w-full rounded-xl border-2 <?= isset($errors['content']) ? 'border-red-500' : 'border-gray-200' ?>"
                    rows="15"
                    required><?= str_replace(['<p>', '</p>'], '', $article['content']) ?></textarea>
                    <?php if(isset($errors['content'])): ?>
                        <p class="text-red-500 text-sm mt-1"><i class="fas fa-exclamation-circle ml-1"></i><?= $errors['content'] ?></p>
                    <?php endif; ?>
                </div>

                <div class="space-y-6">
                    <label class="block text-sm font-medium text-gray-700">تصویر شاخص</label>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="group relative">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent rounded-xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <div class="h-full rounded-xl p-4 bg-gray-50 border-2 border-dashed">
                                <img src="<?= htmlspecialchars($article['image_url']) ?>?<?= time() ?>" 
                                    class="w-full h-48 object-cover rounded-lg"
                                    alt="تصویر فعلی"
                                    loading="lazy">
                                <p class="text-center text-sm text-gray-500 mt-3">تصویر فعلی</p>
                            </div>
                        </div>

                        <div>
                            <input type="file" name="image" id="imageInput" class="hidden" accept="image/*">
                            <div 
                                id="uploadZone"
                                class="h-full flex flex-col items-center justify-center p-6 bg-gray-50 hover:bg-blue-50 cursor-pointer border-2 border-dashed rounded-xl transition-colors"
                                onclick="document.getElementById('imageInput').click()"
                            >
                                <div id="newPreview" class="hidden w-full">
                                    <img src="#" class="w-full h-48 object-cover rounded-lg preview-image" alt="پیشنمایش">
                                </div>
                                <div id="uploadText" class="text-center space-y-3">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400"></i>
                                    <div class="space-y-1">
                                        <p class="font-medium text-gray-700">فایل خود را اینجا رها کنید</p>
                                        <p class="text-sm text-gray-500">یا برای انتخاب کلیک کنید</p>
                                    </div>
                                    <p class="text-xs text-gray-400">فرمت‌های مجاز: JPG, PNG, WEBP (حداکثر 5MB)</p>
                                </div>
                            </div>
                            <?php if(isset($errors['image'])): ?>
                                <p class="text-red-500 text-sm mt-2"><i class="fas fa-exclamation-circle ml-1"></i><?= $errors['image'] ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">وضعیت انتشار</label>
                        <div class="relative">
                            <select 
                                name="status" 
                                class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-0 appearance-none">
                                <option value="active" <?= $article['status'] === 'active' ? 'selected' : '' ?>>فعال</option>
                                <option value="inactive" <?= $article['status'] === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
                            </select>
                            <i class="fas fa-chevron-down absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">انتخاب دسته‌بندی</label>
                        <div class="relative">
                            <select 
                                name="category" 
                                class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-0 appearance-none">
                                <?php while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $article['category'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <i class="fas fa-chevron-down absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                </div>

                <div class="pt-8">
                    <button 
                        type="submit" 
                        class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-medium py-4 px-6 rounded-xl flex items-center justify-center space-s-2 hover:shadow-lg transition-all">
                        <i class="fas fa-save"></i>
                        <span>ذخیره تغییرات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="../assets/js/edit-article.js"></script>
</body>
</html>