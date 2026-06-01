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

$id = $name = $description = $parent_id = $image_url = '';
$errors = [];

$imageDir = '../image/category-images/';

if (!is_dir($imageDir)) {
    mkdir($imageDir, 0755, true);
}

$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM Categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "خطا در دریافت لیست دسته‌بندی‌ها: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $parent_id = empty($_POST['parent_id']) ? null : $_POST['parent_id'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $fileName = basename($_FILES["image"]["name"]);
        $targetFilePath = $imageDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        $allowedTypes = array('jpg', 'png', 'jpeg', 'gif', 'webp');
        if (in_array(strtolower($fileType), $allowedTypes)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                $image_url = $targetFilePath;
            } else {
                $errors[] = "خطا در بارگذاری تصویر.";
            }
        } else {
            $errors[] = "فرمت تصویر مجاز نیست.";
        }
    }

    if (empty($name)) {
        $errors[] = "نام دسته‌بندی الزامی است.";
    }

    if (strlen($name) > 255) {
        $errors[] = "نام دسته‌بندی نمی‌تواند بیش از 255 کاراکتر باشد.";
    }

    if (!empty($parent_id)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Categories WHERE id = ?");
        $stmt->execute([$parent_id]);
        $parentExists = $stmt->fetchColumn();

        if (!$parentExists) {
            $errors[] = "دسته والد انتخاب شده وجود ندارد.";
        }
    }

    if (empty($errors)) {
        try {
            if (empty($id)) {
                $stmt = $pdo->prepare("INSERT INTO Categories (name, description, parent_id, image_url) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $description, $parent_id, $image_url]);
                $_SESSION['success'] = "دسته‌بندی با موفقیت ایجاد شد.";
            } else {
                $stmt = $pdo->prepare("UPDATE Categories SET name = ?, description = ?, parent_id = ?, image_url = ? WHERE id = ?");
                $stmt->execute([$name, $description, $parent_id, $image_url, $id]);
                $_SESSION['success'] = "دسته‌بندی با موفقیت به‌روزرسانی شد.";
            }
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            $errors[] = "خطا در ذخیره‌سازی اطلاعات: " . $e->getMessage();
        }
    }
}

if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM Categories WHERE id = ?");
        $stmt->execute([$edit_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($category) {
            $id = $category['id'];
            $name = $category['name'];
            $description = $category['description'];
            $parent_id = $category['parent_id'];
            $image_url = $category['image_url'];
        } else {
            $_SESSION['error'] = "دسته‌بندی مورد نظر یافت نشد.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (PDOException $e) {
        $errors[] = "خطا در دریافت اطلاعات دسته‌بندی: " . $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Products WHERE category_id = ?");
        $stmt->execute([$delete_id]);
        $productCount = $stmt->fetchColumn();

        if ($productCount > 0) {
            $_SESSION['error'] = "این دسته‌بندی دارای محصول است و نمی‌توان آن را حذف کرد.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM Categories WHERE id = ?");
            $stmt->execute([$delete_id]);
            $_SESSION['success'] = "دسته‌بندی با موفقیت حذف شد.";
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "خطا در حذف دسته‌بندی: " . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

$allCategories = [];
try {
    $query = "SELECT c1.id, c1.name, c1.description, c2.name as parent_name, c1.image_url, c1.created_at 
              FROM Categories c1 
              LEFT JOIN Categories c2 ON c1.parent_id = c2.id 
              ORDER BY c1.parent_id, c1.name";
    $stmt = $pdo->query($query);
    $allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "خطا در دریافت لیست دسته‌بندی‌ها: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت دسته‌بندی‌ها</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-list-categories.css">
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
                    <i class="fas fa-tags me-2"></i>
                    مدیریت دسته‌بندی‌ها
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
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>خطا!</strong>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-<?= empty($id) ? 'plus' : 'edit' ?> me-2"></i>
                            <?= empty($id) ? 'افزودن دسته‌بندی جدید' : 'ویرایش دسته‌بندی' ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">نام دسته‌بندی <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       value="<?= htmlspecialchars($name) ?>" placeholder="نام دسته‌بندی">
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">توضیحات</label>
                                <textarea class="form-control" id="description" name="description" rows="3"
                                          placeholder="توضیحات دسته‌بندی..."><?= htmlspecialchars($description) ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="parent_id" class="form-label">دسته والد (اختیاری)</label>
                                <select class="form-select" id="parent_id" name="parent_id">
                                    <option value="">-- بدون دسته والد --</option>
                                    <?php foreach ($categories as $category): ?>
                                        <?php if ($category['id'] != $id): ?>
                                            <option value="<?= $category['id'] ?>" <?= $parent_id == $category['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['name']) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="image" class="form-label">تصویر دسته‌بندی (اختیاری)</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            </div>
                            
                            <?php if (!empty($image_url)): ?>
                                <div class="mb-3 text-center">
                                    <img src="<?= htmlspecialchars($image_url) ?>" alt="تصویر دسته‌بندی" id="image_preview" class="img-thumbnail">
                                    <input type="hidden" name="image_url" value="<?= htmlspecialchars($image_url) ?>">
                                    <div class="mt-2">
                                        <a href="<?= htmlspecialchars($image_url) ?>" target="_self" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i> مشاهده تصویر
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-end mt-4">
                                <?php if (!empty($id)): ?>
                                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary me-2">
                                        <i class="fas fa-times me-1"></i> انصراف
                                    </a>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    <?= empty($id) ? 'ذخیره دسته‌بندی' : 'به‌روزرسانی' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            لیست دسته‌بندی‌ها
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($allCategories)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">هیچ دسته‌بندی یافت نشد</h5>
                                <p class="text-muted">برای افزودن دسته‌بندی جدید از فرم سمت چپ استفاده کنید</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="50">#</th>
                                            <th>نام</th>
                                            <th>دسته والد</th>
                                            <th width="100">تصویر</th>
                                            <th width="120">عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allCategories as $index => $cat): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td>
                                                    <div class="fw-bold"><?= htmlspecialchars($cat['name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($cat['description'] ?: 'بدون توضیح') ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($cat['parent_name']): ?>
                                                        <span class="badge bg-light text-dark"><?= htmlspecialchars($cat['parent_name']) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-muted">---</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($cat['image_url']): ?>
                                                        <img src="<?= htmlspecialchars($cat['image_url']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>" class="category-image">
                                                    <?php else: ?>
                                                        <span class="text-muted">---</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-nowrap">
                                                    <a href="?edit=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-primary" title="ویرایش">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-danger" title="حذف"
                                                       onclick="return confirm('آیا از حذف دسته‌بندی «<?= htmlspecialchars($cat['name']) ?>» مطمئن هستید؟');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script-list-categories.js"></script>
</body>
</html>