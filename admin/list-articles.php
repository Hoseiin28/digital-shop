<?php
session_start();
require_once 'config.php';
require_once 'csrf_token.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequests();
}

$config = setupPagination();
$articles = fetchArticles($config);
$total_pages = calculateTotalPages($config['items_per_page']);

displayPage($articles, $total_pages, $config, $settings);

function handlePostRequests() {
    global $pdo;
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "توکن امنیتی نامعتبر!";
        header("Location: list-articles.php");
        exit();
    }

    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM Articles WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['message'] = "مقاله با موفقیت حذف شد";
        } catch (PDOException $e) {
            $_SESSION['error'] = "خطا در حذف مقاله: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['bulk_delete']) && !empty($_POST['selected_articles'])) {
        $selected_articles = $_POST['selected_articles'];
        try {
            $placeholders = implode(',', array_fill(0, count($selected_articles), '?'));
            
            $stmt = $pdo->prepare("DELETE FROM Articles WHERE id IN ($placeholders)");
            $stmt->execute($selected_articles);
            
            $_SESSION['message'] = count($selected_articles) . " مقاله با موفقیت حذف شدند";
        } catch (PDOException $e) {
            $_SESSION['error'] = "خطا در حذف گروهی مقالات: " . $e->getMessage();
        }
    }
    
    header("Location: list-articles.php");
    exit();
}

function setupPagination() {
    return [
        'items_per_page' => isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10,
        'page' => max(1, (int)($_GET['page'] ?? 1)),
        'sort' => in_array($_GET['sort'] ?? '', ['id', 'title', 'author', 'category', 'created_at']) ? $_GET['sort'] : 'created_at',
        'direction' => isset($_GET['direction']) && strtoupper($_GET['direction']) === 'ASC' ? 'ASC' : 'DESC'
    ];
}

function fetchArticles($config) {
    global $pdo;
    
    $offset = ($config['page'] - 1) * $config['items_per_page'];
    
    $sql = "SELECT SQL_CALC_FOUND_ROWS 
                a.id, a.title, a.content, a.image_url, a.status, 
                c.name AS category_name, u.name AS author_name, a.created_at 
            FROM Articles a
            LEFT JOIN Categories c ON a.category = c.id
            LEFT JOIN Users u ON a.author_id = u.id
            ORDER BY {$config['sort']} {$config['direction']}
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $config['items_per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt;
}

function calculateTotalPages($items_per_page) {
    global $pdo;
    $total = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    return ceil($total / $items_per_page);
}

function translateStatus($status) {
    switch ($status) {
        case 'active':
            return '<span class="badge bg-success">فعال</span>';
        case 'inactive':
            return '<span class="badge bg-danger">غیرفعال</span>';
        case 'pending':
            return '<span class="badge bg-warning">در انتظار</span>';
        default:
            return '<span class="badge bg-secondary">نامشخص</span>';
    }
}

function displayPage($articles, $total_pages, $config, $settings) {
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>مدیریت مقالات</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
        <link rel="stylesheet" href="../assets/css/style-list-articles.css">
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
                        <i class="fas fa-newspaper me-2"></i>
                        مدیریت مقالات
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
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= $_SESSION['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">لیست مقالات</h5>
                        <a href="add-article.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i>مقاله جدید
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($articles->rowCount() === 0): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-newspaper fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">مقاله‌ای یافت نشد</h5>
                            <p class="text-muted">برای افزودن مقاله جدید روی دکمه «مقاله جدید» کلیک کنید</p>
                        </div>
                    <?php else: ?>
                        <div class="bulk-actions" id="bulkActions">
                            <form method="post" id="bulkForm">
                                <div class="d-flex align-items-center">
                                    <span class="me-3" id="selectedCount">0 مقاله انتخاب شده</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger me-2" id="deleteSelected">
                                        <i class="fas fa-trash me-1"></i> حذف انتخاب شده‌ها
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="cancelBulk">
                                        <i class="fas fa-times me-1"></i> انصراف
                                    </button>
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="bulk_delete" value="1">
                                </div>
                            </form>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="selectAll" class="form-check-input article-checkbox">
                                        </th>
                                        <th>عنوان</th>
                                        <th>نویسنده</th>
                                        <th>دسته‌بندی</th>
                                        <th width="120">وضعیت</th>
                                        <th width="80">تصویر</th>
                                        <th width="120">تاریخ ایجاد</th>
                                        <th width="120">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $counter = ($config['page'] - 1) * $config['items_per_page'] + 1; 
                                    while ($row = $articles->fetch(PDO::FETCH_ASSOC)): 
                                    ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_articles[]" value="<?= $row['id'] ?>" 
                                                       class="form-check-input article-checkbox article-check">
                                            </td>
                                            <td><?= htmlspecialchars($row['title']) ?></td>
                                            <td><?= htmlspecialchars($row['author_name']) ?></td>
                                            <td>
                                                <span class="badge bg-info"><?= htmlspecialchars($row['category_name']) ?></span>
                                            </td>
                                            <td><?= translateStatus($row['status']) ?></td>
                                            <td>
                                                <?php if($row['image_url']): ?>
                                                    <img src="<?= htmlspecialchars($row['image_url']) ?>" class="article-image">
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('Y/m/d', strtotime($row['created_at'])) ?></td>
                                            <td class="text-nowrap">
                                                <a href="edit-article.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-success" title="ویرایش">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="حذف" 
                                                            onclick="return confirm('آیا از حذف این مقاله مطمئن هستید؟');">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="صفحه‌بندی" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($config['page'] > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=1&per_page=<?= $config['items_per_page'] ?>&sort=<?= $config['sort'] ?>&direction=<?= $config['direction'] ?>">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i === $config['page'] ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&per_page=<?= $config['items_per_page'] ?>&sort=<?= $config['sort'] ?>&direction=<?= $config['direction'] ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($config['page'] < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $total_pages ?>&per_page=<?= $config['items_per_page'] ?>&sort=<?= $config['sort'] ?>&direction=<?= $config['direction'] ?>">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../assets/js/script-list-articles.js"></script>
    </body>
    </html>
    <?php
}
?>