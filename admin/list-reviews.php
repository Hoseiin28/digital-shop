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
    $settings = $stmt->fetch();
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

$items_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$has_reply = isset($_GET['has_reply']) ? $_GET['has_reply'] : '';

$base_query = "FROM Reviews main
              JOIN Users u ON main.user_id = u.id 
              JOIN Products p ON main.product_id = p.id
              WHERE main.parent_id IS NULL";

$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(main.comment LIKE ? OR p.name LIKE ? OR u.name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if ($rating > 0) {
    $where_clauses[] = "main.rating = ?";
    $params[] = $rating;
}

if (!empty($date_from)) {
    $where_clauses[] = "main.created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if (!empty($date_to)) {
    $where_clauses[] = "main.created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

if ($has_reply === 'yes') {
    $where_clauses[] = "EXISTS (SELECT 1 FROM Reviews r WHERE r.parent_id = main.id)";
} elseif ($has_reply === 'no') {
    $where_clauses[] = "NOT EXISTS (SELECT 1 FROM Reviews r WHERE r.parent_id = main.id)";
}

$where = $where_clauses ? ' AND ' . implode(' AND ', $where_clauses) : '';

$total_query = "SELECT COUNT(*) " . $base_query . $where;
$stmt = $pdo->prepare($total_query);
$stmt->execute($params);
$total_reviews = $stmt->fetchColumn();
$total_pages = ceil($total_reviews / $items_per_page);

$query = "SELECT 
            main.*, 
            u.name as user_name, 
            u.avatar, 
            p.name as product_name,
            (SELECT COUNT(*) FROM Reviews r WHERE r.parent_id = main.id) as reply_count
          " . $base_query . $where . "
          ORDER BY main.created_at DESC
          LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($query);

foreach ($params as $i => $param) {
    $stmt->bindValue($i + 1, $param);
}

$stmt->bindValue(count($params) + 1, (int)$items_per_page, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, (int)$offset, PDO::PARAM_INT);

$stmt->execute();
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$products = $pdo->query("SELECT id, name FROM Products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_review'])) {
        $reviewId = $_POST['review_id'];
        $stmt = $pdo->prepare("DELETE FROM Reviews WHERE id = ?");
        $stmt->execute([$reviewId]);
        $_SESSION['success'] = "نظر با موفقیت حذف شد";
        header("Location: " . get_filtered_url());
        exit();
    }

    if (isset($_POST['bulk_delete'])) {
        if (!empty($_POST['selected_reviews'])) {
            $placeholders = implode(',', array_fill(0, count($_POST['selected_reviews']), '?'));
            $stmt = $pdo->prepare("DELETE FROM Reviews WHERE id IN ($placeholders)");
            $stmt->execute($_POST['selected_reviews']);
            $_SESSION['success'] = count($_POST['selected_reviews']) . " نظر با موفقیت حذف شدند";
        } else {
            $_SESSION['error'] = "هیچ نظری برای حذف انتخاب نشده است";
        }
        header("Location: " . get_filtered_url());
        exit();
    }

    if (isset($_POST['reply_review'])) {
        $parentId = $_POST['parent_id'];
        $productId = $_POST['product_id'];
        $comment = trim($_POST['reply_comment']);

        if (empty($comment)) {
            $_SESSION['error'] = "لطفاً متن پاسخ را وارد کنید";
            header("Location: " . get_filtered_url());
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO Reviews (user_id, product_id, parent_id, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $productId, $parentId, $comment]);

        $_SESSION['success'] = "پاسخ شما با موفقیت ثبت شد";
        header("Location: " . get_filtered_url());
        exit();
    }
}

function get_filtered_url()
{
    $params = [];
    if (!empty($_GET['search'])) $params['search'] = $_GET['search'];
    if (!empty($_GET['rating'])) $params['rating'] = $_GET['rating'];
    if (!empty($_GET['date_from'])) $params['date_from'] = $_GET['date_from'];
    if (!empty($_GET['date_to'])) $params['date_to'] = $_GET['date_to'];
    if (!empty($_GET['has_reply'])) $params['has_reply'] = $_GET['has_reply'];
    if (!empty($_GET['page'])) $params['page'] = $_GET['page'];

    return 'list-reviews.php?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت نظرات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-list-reviews.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($settings['button_color'] ?? '#4e73df') ?>;
            --secondary-color: #f8f9fc;
            --dark-color: #5a5c69;
            --light-color: #f8f9fa;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
        }

        body {
            font-family: <?= htmlspecialchars($settings['font_family'] ?? 'Vazir, sans-serif') ?>;
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
                    <i class="fas fa-comments me-2"></i>
                    مدیریت نظرات
                </h1>
                <div>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-store me-1"></i>
                        <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <a href="admin-panel.php" class="back-to-panel" title="بازگشت به پنل مدیریت">
        <i class="fas fa-arrow-left"></i>
    </a>

    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">تعداد کل نظرات:</span>
                    <span class="badge bg-primary"><?= $total_reviews ?></span>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>فیلترها</h5>
            </div>
            <div class="card-body">
                <form method="get" action="list-reviews.php">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">جستجوی متن نظر:</label>
                            <input type="text" name="search" class="form-control"
                                value="<?= htmlspecialchars($search) ?>" placeholder="متن نظر...">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">امتیاز:</label>
                            <select name="rating" class="form-select">
                                <option value="0">همه امتیازها</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i ?>" <?= $rating == $i ? 'selected' : '' ?>>
                                        <?= str_repeat('★', $i) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">تاریخ از:</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">تاریخ تا:</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">پاسخ:</label>
                            <select name="has_reply" class="form-select">
                                <option value="">همه</option>
                                <option value="yes" <?= $has_reply === 'yes' ? 'selected' : '' ?>>دارای پاسخ</option>
                                <option value="no" <?= $has_reply === 'no' ? 'selected' : '' ?>>بدون پاسخ</option>
                            </select>
                        </div>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-2"></i> اعمال فیلتر
                        </button>
                        <a href="list-reviews.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i> حذف فیلترها
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($reviews)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-comment-slash text-muted" style="font-size: 3rem;"></i>
                    <h5 class="mt-3 text-muted">هیچ نظری یافت نشد</h5>
                    <p class="text-muted">با تغییر فیلترها دوباره امتحان کنید</p>
                </div>
            </div>
        <?php else: ?>
            <form id="bulkForm" method="post">
                <div class="bulk-actions">
                    <button type="submit" name="bulk_delete" class="btn btn-danger" id="bulkDeleteBtn" disabled
                        onclick="return confirm('آیا از حذف نظرات انتخاب شده مطمئن هستید؟')">
                        <i class="fas fa-trash me-2"></i>
                        حذف انتخاب شده‌ها
                    </button>

                    <div class="form-check">
                        <input type="checkbox" id="selectAll" class="form-check-input">
                        <label for="selectAll" class="form-check-label">انتخاب همه</label>
                    </div>
                </div>

                <?php foreach ($reviews as $review): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="review-container">
                                <div class="review-header">
                                    <div class="user-info">
                                        <input type="checkbox" name="selected_reviews[]" value="<?= $review['id'] ?>"
                                            class="form-check-input me-2 review-checkbox">
                                        <img src="../<?= htmlspecialchars($review['avatar'] ?? 'static/img/default-avatar.jpg') ?>"
                                            alt="تصویر کاربر" class="avatar">
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($review['user_name']) ?></h6>
                                            <small class="text-muted">
                                                <?= date('Y/m/d H:i', strtotime($review['created_at'])) ?>
                                            </small>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-3">
                                        <?php if ($review['rating']): ?>
                                            <div class="rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?= $i <= $review['rating'] ? '' : '-empty' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($review['reply_count'] > 0): ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-reply me-1"></i> <?= $review['reply_count'] ?> پاسخ
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="review-body">
                                    <div class="mb-3">
                                        <span class="fw-bold">محصول: </span>
                                        <a href="product-details.php?id=<?= $review['product_id'] ?>" class="text-primary">
                                            <?= htmlspecialchars($review['product_name']) ?>
                                        </a>
                                    </div>

                                    <p class="mb-0"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                                </div>

                                <div class="review-footer">
                                    <button class="btn btn-sm btn-primary" onclick="toggleReplyForm(<?= $review['id'] ?>, event)">
                                        <i class="fas fa-reply me-2"></i> پاسخ
                                    </button>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                        <button type="submit" name="delete_review" class="btn btn-sm btn-danger"
                                            onclick="return confirm('آیا از حذف این نظر مطمئن هستید؟')">
                                            <i class="fas fa-trash me-2"></i> حذف
                                        </button>
                                    </form>
                                </div>

                                <div id="reply-form-<?= $review['id'] ?>" class="reply-form">
                                    <form method="post" onsubmit="return submitReplyForm(<?= $review['id'] ?>)">
                                        <input type="hidden" name="parent_id" value="<?= $review['id'] ?>">
                                        <input type="hidden" name="product_id" value="<?= $review['product_id'] ?>">
                                        <div class="mb-3">
                                            <label for="reply-comment-<?= $review['id'] ?>" class="form-label">پاسخ شما:</label>
                                            <textarea class="form-control" id="reply-comment-<?= $review['id'] ?>"
                                                name="reply_comment" rows="3" required></textarea>
                                        </div>
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-secondary btn-sm"
                                                onclick="toggleReplyForm(<?= $review['id'] ?>, event)">انصراف</button>
                                            <button type="submit" name="reply_review" class="btn btn-primary btn-sm">ارسال پاسخ</button>
                                        </div>
                                    </form>
                                </div>

                                <?php
                                $replyQuery = "SELECT r.*, u.name as user_name, u.avatar 
                                              FROM Reviews r 
                                              JOIN Users u ON r.user_id = u.id 
                                              WHERE r.parent_id = ? 
                                              ORDER BY r.created_at ASC";
                                $replies = $pdo->prepare($replyQuery);
                                $replies->execute([$review['id']]);
                                $replies = $replies->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($replies as $reply): ?>
                                    <div class="reply-container">
                                        <div class="review-header">
                                            <div class="user-info">
                                                <img src="<?= htmlspecialchars($reply['avatar'] ?? 'static/img/default-avatar.jpg') ?>"
                                                    alt="تصویر کاربر" class="avatar">
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($reply['user_name']) ?></h6>
                                                    <small class="text-muted">
                                                        <?= date('Y/m/d H:i', strtotime($reply['created_at'])) ?>
                                                    </small>
                                                </div>
                                            </div>

                                            <?php if ($_SESSION['user_id'] == $reply['user_id'] || $_SESSION['role'] === 'admin'): ?>
                                                <form method="post">
                                                    <input type="hidden" name="review_id" value="<?= $reply['id'] ?>">
                                                    <button type="submit" name="delete_review" class="btn btn-sm btn-danger"
                                                        onclick="return confirm('آیا از حذف این پاسخ مطمئن هستید؟')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>

                                        <div class="review-body">
                                            <p class="mb-0"><?= nl2br(htmlspecialchars($reply['comment'])) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </form>

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= get_filtered_url() ?>&page=<?= $current_page - 1 ?>" aria-label="قبلی">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);

                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="' . get_filtered_url() . '&page=1">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= get_filtered_url() ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor;

                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="' . get_filtered_url() . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        }
                        ?>

                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= get_filtered_url() ?>&page=<?= $current_page + 1 ?>" aria-label="بعدی">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script-list-reviews.js"></script>
</body>

</html>