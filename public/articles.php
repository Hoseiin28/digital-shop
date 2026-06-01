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


const ITEMS_PER_PAGE = 15;
$current_page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$offset = ($current_page - 1) * ITEMS_PER_PAGE;

$article_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : null;

function getRelatedArticles($pdo, $article_id, $category_id, $limit = 4) {
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.title,
            a.image_url,
            a.created_at
        FROM Articles a
        WHERE a.category = :category_id 
          AND a.status = 'active' 
          AND a.id != :article_id
        ORDER BY a.created_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
    $stmt->bindValue(':article_id', $article_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buildNestedCategories($categories, $parentId = null) {
    $result = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parentId) {
            $children = buildNestedCategories($categories, $category['id']);
            if ($children) {
                $category['children'] = $children;
            }
            $result[] = $category;
        }
    }
    return $result;
}

function renderCategoryTree($categories, $level = 0) {
    $html = '';
    foreach ($categories as $category) {
        $html .= '<div class="ms-' . ($level * 3) . ' mb-2">';
        $html .= '<a href="articles.php?category=' . $category['id'] . '" class="text-decoration-none">';
        $html .= str_repeat('&nbsp;&nbsp;', $level) . htmlspecialchars($category['name']);
        $html .= '</a>';
        if (!empty($category['children'])) {
            $html .= renderCategoryTree($category['children'], $level + 1);
        }
        $html .= '</div>';
    }
    return $html;
}

function getCategories($pdo) {
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM Categories c
        WHERE EXISTS (
            SELECT 1 
            FROM Articles a 
            WHERE a.category = c.id 
            AND a.status = 'active'
        )
        ORDER BY c.parent_id, c.name
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getArticles($pdo, $category_id = null, $search = null, $offset = 0, $limit = ITEMS_PER_PAGE) {
    $sql = "
        SELECT SQL_CALC_FOUND_ROWS
            a.id,
            a.title,
            SUBSTRING(a.content, 1, 200) AS excerpt,
            a.image_url,
            a.created_at,
            c.name AS category_name
        FROM Articles a
        LEFT JOIN Categories c ON a.category = c.id
        WHERE a.status = 'active'
    ";
    
    $params = [];
    
    if ($category_id) {
        $sql .= " AND a.category = :category_id";
        $params[':category_id'] = $category_id;
    }
    
    if ($search) {
        $sql .= " AND (a.title LIKE :search OR a.content LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    
    $sql .= " ORDER BY a.created_at DESC LIMIT :offset, :limit";
    
    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    
    return [$articles, $total];
}

function getSingleArticle($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            c.name AS category_name,
            u.name AS author_name,
            u.avatar AS author_avatar
        FROM Articles a
        LEFT JOIN Categories c ON a.category = c.id
        LEFT JOIN Users u ON a.author_id = u.id
        WHERE a.id = :id AND a.status = 'active'
    ");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$nested_categories = buildNestedCategories(getCategories($pdo));
[$articles, $total_articles] = getArticles($pdo, $category_id, $search_query, $offset, ITEMS_PER_PAGE);
$total_pages = ceil($total_articles / ITEMS_PER_PAGE);

if ($article_id) {
    $article = getSingleArticle($pdo, $article_id);
    if (!$article) {
        header("HTTP/1.0 404 Not Found");
        die('مقاله مورد نظر یافت نشد');
    }
    $related_articles = getRelatedArticles($pdo, $article_id, $article['category']);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> مقالات | <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-articles.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
</head>
<body class="master-bg">
<?php include 'header-index.php'; ?>
<div class="container-xl py-4">
    <?php if ($article_id && $article): ?>
        <div class="row g-4">

            <main class="col-lg-8">
                <article class="unified-card">
                    <img src="<?= $article['image_url'] ?>" 
                         class="unified-image w-100"
                         loading="lazy">

                    <div class="p-4">
                        <div class="d-flex gap-3 align-items-center mb-3">
                            <span class="unified-badge"><?= $article['category_name'] ?></span>
                            <small class="text-muted">
                                <?= date('Y/m/d', strtotime($article['created_at'])) ?>
                            </small>
                        </div>

                        <h1 class="h3 fw-bold mb-4"><?= $article['title'] ?></h1>

                        <div class="unified-typography article-content">
                        <?= $article['content'] ?>
                        </div>

                        <div class="unified-divider"></div>

                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= $article['author_avatar'] ?>" 
                                 class="rounded-circle" 
                                 width="48" 
                                 height="48"
                                 alt="<?= $article['author_name'] ?>">
                            <div class="unified-author">
                                <div class="fw-medium"><?= $article['author_name'] ?></div>
                                <small class="text-muted">نویسنده &nbsp;&nbsp; </small>
                            </div>
                        </div>
                    </div>
                </article>
            </main>

            <aside class="col-lg-4">
                <div class="sticky-top" style="top: 1rem;">
                    <div class="unified-card p-3">
                        <h2 class="h6 fw-bold mb-3">دسته‌بندی‌ها</h2>
                        <nav class="unified-nav nav flex-column">
                            <?php foreach ($nested_categories as $cat): ?>
                                <a href="?category=<?= $cat['id'] ?>" 
                                   class="nav-link <?= $category_id == $cat['id'] ? 'active' : '' ?>">
                                   <?= $cat['name'] ?>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                </div>
            </aside>
        </div>

    <?php else: ?>
         
        <div class="row g-4">
            <aside class="col-lg-3">
                <div class="sticky-top" style="top: 1rem;">
                    <div class="unified-card p-3">
                        <form class="mb-4">
                            <input type="search" 
                                   name="search" 
                                   value="<?= $search_query ?>" 
                                   placeholder="جستجو..." 
                                   class="form-controll"
                                   aria-label="Search">
                        </form>
                        
                        <h2 class="h6 fw-bold mb-3">فیلتر مقالات</h2>
                        <nav class="unified-nav nav flex-column">
                            <a href="articles.php" 
                               class="nav-link <?= !$category_id ? 'active' : '' ?>">
                               همه مقالات
                            </a>
                            <?php foreach ($nested_categories as $cat): ?>
                                <a href="?category=<?= $cat['id'] ?>" 
                                   class="nav-link <?= $category_id == $cat['id'] ? 'active' : '' ?>">
                                   <?= $cat['name'] ?>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                </div>
            </aside>

            <main class="col-lg-9">
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                    <?php foreach ($articles as $article): ?>
                        <div class="col">
                            <article class="unified-card h-100">
                                <a href="articles.php?id=<?= $article['id'] ?>" class="text-decoration-none text-dark">
                                    <img src="<?= $article['image_url'] ?>" 
                                         class="unified-image w-100"
                                         
                                         loading="lazy">
                                    <div class="p-3">
                                        <div class="d-flex justify-content-between small text-muted mb-2">
                                            <span><?= date('Y/m/d', strtotime($article['created_at'])) ?></span>
                                            <span class="unified-badge"><?= $article['category_name'] ?></span>
                                        </div>
                                        <h2 class="h6 fw-bold mb-0"><?= $article['title'] ?></h2>
                                    </div>
                                </a>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                    <a class="page-link" 
                                       href="?page=<?= $i ?>&category=<?= $category_id ?>&search=<?= $search_query ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </main>
        </div>
    <?php endif; ?>
</div>
<?php include 'footer-index.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>