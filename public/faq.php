<?php
session_start();
require_once 'config.php';

$page_title = "سوالات متداول";

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

$sql_categories = "SELECT DISTINCT category FROM FAQs";
$result_categories = $pdo->query($sql_categories);

$categories = ['all' => 'همه سوالات'];

if ($result_categories->rowCount() > 0) {
    while ($row = $result_categories->fetch(PDO::FETCH_ASSOC)) {
        $categories[$row['category']] = htmlspecialchars($row['category']);
    }
}

$sql_faqs = "SELECT * FROM FAQs";
$result_faqs = $pdo->query($sql_faqs);
$faqs = [];

if ($result_faqs->rowCount() > 0) {
    while ($row = $result_faqs->fetch(PDO::FETCH_ASSOC)) {
        $faqs[] = [
            'question' => $row['question'],
            'answer' => $row['answer'],
            'category' => $row['category'],
            'tags' => !empty($row['tags']) ? explode(',', $row['tags']) : []
        ];
    }
}

function filter_faqs($faqs, $params) {
    $search_query = isset($params['search']) ? strtolower(trim($params['search'])) : '';
    $category_filter = isset($params['category']) ? $params['category'] : 'all';
    
    return array_filter($faqs, function($faq) use ($search_query, $category_filter) {
        $matches_category = ($category_filter == 'all' || $faq['category'] == $category_filter);
        
        if (empty($search_query)) {
            return $matches_category;
        }
        
        $matches_search = strpos(strtolower($faq['question']), $search_query) !== false || 
                          strpos(strtolower($faq['answer']), $search_query) !== false ||
                          (!empty($faq['tags']) && in_array(strtolower($search_query), array_map('strtolower', $faq['tags'])));
        
        return $matches_category && $matches_search;
    });
}

$filtered_faqs = filter_faqs($faqs, $_GET);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سوالات متداول | <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style-faq.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($settings['button_color'] ?? '#4e73df') ?>;
            --secondary-color: #f8f9fc;
            --dark-color: #5a5c69;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: <?= htmlspecialchars($settings['font_family'] ?? 'Vazir, sans-serif') ?>;
            background-color: var(--secondary-color);
            color: var(--dark-color);
        }
    </style>
</head>
<body>

<?php include 'header-index.php'; ?>

<div class="container faq-page">
    <div class="faq-header">
        <h1 class="page-title">سوالات متداول</h1>
        <p class="page-subtitle">پاسخ به رایج‌ترین سوالات شما</p>
    </div>

    <div class="faq-searchh-box">
        <div class="searchh-container">
            <form method="get" action="" class="searchh-form">
                <input type="text" name="search" placeholder="چه سوالی دارید؟ جستجو کنید..." 
                       value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>"
                       aria-label="جستجوی سوالات">
                <button type="submit" class="searchh-button">
                    <i class="fas fa-search"></i>
                    <span>جستجو</span>
                </button>
            </form>
        </div>
    </div>

    <div class="faq-content">
        <aside class="faq-sidebar">
            <div class="sidebar-section categories-section">
                <h3 class="sidebar-title"><i class="fas fa-tags"></i> دسته‌بندی سوالات</h3>
                <ul class="category-list">
                    <?php foreach ($categories as $key => $name): ?>
                        <li>
                            <a href="?category=<?= urlencode($key) ?>" 
                               class="<?= (isset($_GET['category']) && $_GET['category'] == $key) || (!isset($_GET['category']) && $key == 'all' ? 'active' : '' )?>">
                                <?= $name ?>
                                <span class="count">
                                    <?= count(array_filter($faqs, function($faq) use ($key) { 
                                        return $key == 'all' || $faq['category'] == $key; 
                                    })) ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </aside>

        <main class="faq-main">
            <div class="faq-accordion">
                <?php if (empty($filtered_faqs)): ?>
                    <div class="no-results">
                        <div class="no-results-icon">
                            <i class="far fa-question-circle"></i>
                        </div>
                        <h3>سوالی یافت نشد</h3>
                        <p>متأسفانه هیچ سوالی با معیارهای جستجوی شما مطابقت ندارد. لطفاً عبارت دیگری را امتحان کنید.</p>
                        <a href="?category=all" class="reset-button">نمایش همه سوالات</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($filtered_faqs as $index => $faq): ?>
                        <div class="faq-item" id="faq-<?= $index + 1 ?>">
                            <div class="faq-question">
                                <h3><?= htmlspecialchars($faq['question']) ?></h3>
                                <span class="faq-toggle"><i class="fas fa-chevron-down"></i></span>
                            </div>
                            <div class="faq-answer">
                                <div class="answer-content">
                                    <?= nl2br(htmlspecialchars($faq['answer'])) ?>
                                </div>
                                <?php if (!empty($faq['tags'])): ?>
                                    <div class="faq-tags">
                                        <span>برچسب‌ها:</span>
                                        <?php foreach ($faq['tags'] as $tag): ?>
                                            <a href="?search=<?= urlencode($tag) ?>"><?= htmlspecialchars($tag) ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include 'footer-index.php'; ?>

<script src="../assets/js/script-faq.js"></script>
</body>
</html>