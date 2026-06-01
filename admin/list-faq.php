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

if (isset($_POST['delete_selected'])) {
    if (!empty($_POST['selected_faqs'])) {
        $placeholders = implode(',', array_fill(0, count($_POST['selected_faqs']), '?'));
        try {
            $stmt = $pdo->prepare("DELETE FROM FAQs WHERE id IN ($placeholders)");
            $stmt->execute($_POST['selected_faqs']);
            $_SESSION['success'] = count($_POST['selected_faqs']) . " سوال با موفقیت حذف شد.";
            header("Location: list-faq.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "خطا در حذف سوالات: " . $e->getMessage();
            header("Location: list-faq.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "هیچ سوالی برای حذف انتخاب نشده است.";
        header("Location: list-faq.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add') {
                $stmt = $pdo->prepare("INSERT INTO FAQs (question, answer, category, tags) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['question'],
                    $_POST['answer'],
                    $_POST['category'],
                    $_POST['tags']
                ]);
                $_SESSION['success'] = "سوال جدید با موفقیت اضافه شد.";
            } elseif ($_POST['action'] === 'edit') {
                $stmt = $pdo->prepare("UPDATE FAQs SET question = ?, answer = ?, category = ?, tags = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([
                    $_POST['question'],
                    $_POST['answer'],
                    $_POST['category'],
                    $_POST['tags'],
                    $_POST['id']
                ]);
                $_SESSION['success'] = "سوال با موفقیت ویرایش شد.";
            }
            header("Location: list-faq.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "خطا در عملیات: " . $e->getMessage();
            header("Location: list-faq.php");
            exit();
        }
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM FAQs WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "سوال با موفقیت حذف شد.";
        header("Location: list-faq.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "خطا در حذف سوال: " . $e->getMessage();
    }
}

$edit_faq = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM FAQs WHERE id = ?");
    $stmt->execute([$id]);
    $edit_faq = $stmt->fetch(PDO::FETCH_ASSOC);
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';

$query = "SELECT * FROM FAQs WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (question LIKE ? OR answer LIKE ? OR tags LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($category)) {
    $query .= " AND category = ?";
    $params[] = $category;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT DISTINCT category FROM FAQs ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت سوالات متداول</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-list-faq.css">
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
                    <i class="fas fa-question-circle me-2"></i>
                    مدیریت سوالات متداول
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

        <div class="card mb-4" id="addForm" style="display: none;">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus-circle me-2"></i>
                    افزودن سوال جدید
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="list-faq.php">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label for="add_question" class="form-label">سوال <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="add_question" name="question" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="add_answer" class="form-label">پاسخ <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="add_answer" name="answer" rows="5" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_category" class="form-label">دسته‌بندی <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="add_category" name="category" list="categories" required>
                            <datalist id="categories">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>">
                                    <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="add_tags" class="form-label">برچسب‌ها (با کاما جدا شوند)</label>
                            <input type="text" class="form-control" id="add_tags" name="tags">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="button" id="cancelAdd" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-times me-1"></i> انصراف
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> ذخیره سوال
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($edit_faq): ?>
            <div class="card mb-4" id="editForm">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>
                        ویرایش سوال
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="list-faq.php">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?= $edit_faq['id'] ?>">

                        <div class="mb-3">
                            <label for="edit_question" class="form-label">سوال <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit_question" name="question" rows="3" required><?= htmlspecialchars($edit_faq['question']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="edit_answer" class="form-label">پاسخ <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit_answer" name="answer" rows="5" required><?= htmlspecialchars($edit_faq['answer']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_category" class="form-label">دسته‌بندی <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_category" name="category" value="<?= htmlspecialchars($edit_faq['category']) ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="edit_tags" class="form-label">برچسب‌ها (با کاما جدا شوند)</label>
                                <input type="text" class="form-control" id="edit_tags" name="tags" value="<?= htmlspecialchars($edit_faq['tags']) ?>">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="list-faq.php" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-times me-1"></i> انصراف
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> ذخیره تغییرات
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i>
                    فیلتر سوالات
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="list-faq.php">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <input type="text" class="form-control" name="search" placeholder="جستجو در سوالات و پاسخ‌ها..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <select class="form-select" name="category">
                                <option value="">همه دسته‌بندی‌ها</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>" <?= ($category == $cat) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1 mb-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        لیست سوالات
                    </h5>
                    <div>
                        <button id="toggleAddForm" class="btn btn-primary me-2">
                            <i class="fas fa-plus me-1"></i> افزودن سوال جدید
                        </button>
                        <span class="badge bg-primary">
                            <?= count($faqs) ?> سوال
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($faqs) > 0): ?>
                    <form id="bulkForm" method="POST" action="list-faq.php">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <button type="submit" name="delete_selected" class="btn btn-danger" id="bulkDeleteBtn" disabled>
                                    <i class="fas fa-trash me-1"></i> حذف انتخاب شده‌ها
                                </button>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                                <label class="form-check-label" for="selectAll">انتخاب همه</label>
                            </div>
                        </div>

                        <div class="list-group">
                            <?php foreach ($faqs as $index => $faq): ?>
                                <div class="list-group-item">
                                    <div class="d-flex align-items-start">
                                        <div class="form-check me-3">
                                            <input class="form-check-input faq-checkbox" type="checkbox" name="selected_faqs[]" value="<?= $faq['id'] ?>">
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h6 class="mb-1 faq-question"><?= htmlspecialchars($faq['question']) ?></h6>
                                                <small class="text-muted"><?= date('Y/m/d H:i', strtotime($faq['created_at'])) ?></small>
                                            </div>
                                            <p class="mb-2 faq-answer"><?= htmlspecialchars(mb_substr($faq['answer'], 0, 100)) . (mb_strlen($faq['answer']) > 100 ? '...' : '') ?></p>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-secondary me-2"><?= htmlspecialchars($faq['category']) ?></span>
                                                <?php if (!empty($faq['tags'])): ?>
                                                    <?php $tags = explode(',', $faq['tags']); ?>
                                                    <?php foreach ($tags as $tag): ?>
                                                        <span class="tag"><?= htmlspecialchars(trim($tag)) ?></span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="ms-3">
                                            <a href="list-faq.php?edit=<?= $faq['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="ویرایش">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="list-faq.php?delete=<?= $faq['id'] ?>" class="btn btn-sm btn-outline-danger" title="حذف" onclick="return confirm('آیا از حذف این سوال مطمئن هستید؟')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-question fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">سوالی یافت نشد</h5>
                        <p class="text-muted">هیچ سوالی مطابق با جستجوی شما وجود ندارد.</p>
                        <button id="toggleAddFormEmpty" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> افزودن سوال جدید
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script-list-faq.js"></script>
</body>

</html>