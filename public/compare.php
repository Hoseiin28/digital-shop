<?php
session_start();
require_once 'config.php';

$shopSettings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $shopSettings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching shop settings: " . $e->getMessage());
}

$buttonColor = $shopSettings['button_color'] ?? '#007bff';
$isLoggedIn = isset($_SESSION['user_id']);

$user_id = $isLoggedIn ? $_SESSION['user_id'] : null;
$session_id = session_id();

try {
    $sql = "SELECT p.*, 
                   GROUP_CONCAT(pa.attribute_name, ': ', pa.attribute_value SEPARATOR '|') AS attributes,
                   d.discount_type, d.discount_value, d.start_date, d.end_date,
                   (SELECT AVG(rating) FROM Reviews WHERE product_id = p.id) as avg_rating,
                   (SELECT COUNT(*) FROM Reviews WHERE product_id = p.id) as review_count,
                   c.name as category_name
            FROM ProductComparison pc
            JOIN Products p ON pc.product_id = p.id
            LEFT JOIN ProductAttributes pa ON p.id = pa.product_id
            LEFT JOIN Discounts d ON p.id = d.product_id 
                AND (d.start_date IS NULL OR d.start_date <= NOW()) 
                AND (d.end_date IS NULL OR d.end_date >= NOW())
            LEFT JOIN Categories c ON p.category_id = c.id
            WHERE " . ($user_id ? "pc.user_id = ?" : "pc.session_id = ?") . "
            GROUP BY p.id
            ORDER BY pc.added_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id ?: $session_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $allAttributes = [];
    foreach ($products as &$product) {
        $finalPrice = $product['price'];
        if (!empty($product['discount_type']) && !empty($product['discount_value'])) {
            if ($product['discount_type'] === 'percentage') {
                $finalPrice = $product['price'] * (1 - $product['discount_value'] / 100);
            } elseif ($product['discount_type'] === 'fixed') {
                $finalPrice = max(0, $product['price'] - $product['discount_value']);
            }
        }
        $product['final_price'] = $finalPrice;

        $attributes = [];
        if (!empty($product['attributes'])) {
            $attr_pairs = explode('|', $product['attributes']);
            foreach ($attr_pairs as $pair) {
                if (!empty($pair)) {
                    list($name, $value) = explode(': ', $pair, 2);
                    $attributes[$name] = $value;
                    if (!in_array($name, $allAttributes)) {
                        $allAttributes[] = $name;
                    }
                }
            }
        }
        $product['attributes'] = $attributes;
    }
    unset($product);
} catch (PDOException $e) {
    die("خطا در دریافت محصولات برای مقایسه: " . $e->getMessage());
}

function adjustBrightness($hex, $steps)
{
    $steps = max(-255, min(255, $steps));
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
    }
    $color_parts = str_split($hex, 2);
    $return = '#';
    foreach ($color_parts as $color) {
        $color = hexdec($color);
        $color = max(0, min(255, $color + $steps));
        $return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT);
    }
    return $return;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مقایسه محصولات | <?= htmlspecialchars($shopSettings['shop_name'] ?? 'فروشگاه') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=IRANSans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-compare.css">
    <?php if (!empty($shopSettings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($shopSettings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($buttonColor); ?>;
            --primary-hover: <?php echo htmlspecialchars(adjustBrightness($buttonColor, -20)); ?>;
            --primary-active: <?php echo htmlspecialchars(adjustBrightness($buttonColor, -30)); ?>;
        }
    </style>
</head>

<body>
    <?php include 'header-index.php'; ?>

    <div class="compare-container">
        <div class="page-header">
            <h1><i class="fas fa-exchange-alt"></i> مقایسه محصولات</h1>
            <p>مشاهده و مقایسه ویژگی‌های محصولات انتخابی</p>
        </div>

        <?php if (count($products) < 2): ?>
            <div class="empty-compare">
                <i class="fas fa-exchange-alt"></i>
                <h2>محصولی برای مقایسه وجود ندارد</h2>
                <p>شما باید حداقل 2 محصول را برای مقایسه انتخاب کنید. می‌توانید از لیست محصولات، موارد مورد نظر خود را برای مقایسه اضافه کنید.</p>
                <div class="action-buttons">
                    <a href="products.php" class="primary-btn">
                        <i class="fas fa-arrow-left"></i> مشاهده محصولات
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="compare-actions">
                <button id="clear-all-compare" class="remove-compare-btn" type="button">
                    <i class="fas fa-trash"></i> پاک کردن همه
                </button>
                <a href="products.php" class="back-to-products">
                    <i class="fas fa-arrow-left"></i> بازگشت به محصولات
                </a>
            </div>

            <div class="compare-wrapper">
                <div class="compare-table-container">
                    <table class="compare-table">
                        <thead>
                            <tr>
                                <th class="fixed-column">ویژگی‌ها</th>
                                <?php foreach ($products as $product): ?>
                                    <th data-product-id="<?= $product['id'] ?>">
                                        <div class="product-header">
                                            <div class="product-image">
                                                <img src="<?= htmlspecialchars($product['image_url'] ?: '../image/default-product.jpg') ?>"
                                                    alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                                            </div>
                                            <h3><?= htmlspecialchars($product['name']) ?></h3>
                                            <div class="product-price">
                                                <?php if ($product['final_price'] < $product['price']): ?>
                                                    <span class="current-price"><?= number_format($product['final_price']) ?> تومان</span>
                                                    <span class="old-price"><?= number_format($product['price']) ?></span>
                                                <?php else: ?>
                                                    <span class="current-price"><?= number_format($product['price']) ?> تومان</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="product-rating">
                                                <div class="star-rating">
                                                    <?php
                                                    $avg_rating = $product['avg_rating'] ?? 0;
                                                    $full_stars = floor($avg_rating);
                                                    $half_star = ($avg_rating - $full_stars) >= 0.5 ? 1 : 0;
                                                    $empty_stars = 5 - $full_stars - $half_star;

                                                    for ($i = 0; $i < $full_stars; $i++) {
                                                        echo '<i class="fas fa-star"></i>';
                                                    }
                                                    if ($half_star) {
                                                        echo '<i class="fas fa-star-half-alt"></i>';
                                                    }
                                                    for ($i = 0; $i < $empty_stars; $i++) {
                                                        echo '<i class="far fa-star"></i>';
                                                    }
                                                    ?>
                                                </div>
                                                <span class="rating-count">(<?= $product['review_count'] ?? 0 ?>)</span>
                                            </div>
                                            <div class="product-actions">
                                                <button class="remove-compare-btn" data-product-id="<?= $product['id'] ?>">
                                                    <i class="fas fa-times"></i> حذف از مقایسه
                                                </button>
                                                <a href="product-details.php?id=<?= $product['id'] ?>" class="view-details-btn">
                                                    <i class="fas fa-eye"></i> مشاهده جزئیات
                                                </a>
                                                <button class="add-to-cart-btn" data-product-id="<?= $product['id'] ?>">
                                                    <i class="fas fa-shopping-cart"></i> افزودن به سبد
                                                </button>
                                            </div>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fixed-column">دسته‌بندی</td>
                                <?php foreach ($products as $product): ?>
                                    <td><?= htmlspecialchars($product['category_name']) ?></td>
                                <?php endforeach; ?>
                            </tr>

                            <tr>
                                <td class="fixed-column">قیمت</td>
                                <?php foreach ($products as $product): ?>
                                    <td>
                                        <?php if ($product['final_price'] < $product['price']): ?>
                                            <span class="current-price"><?= number_format($product['final_price']) ?> تومان</span>
                                            <span class="old-price"><?= number_format($product['price']) ?></span>
                                            <span class="discount-badge">
                                                <?= round((($product['price'] - $product['final_price']) / $product['price'] * 100)) ?>% تخفیف
                                            </span>
                                        <?php else: ?>
                                            <span class="current-price"><?= number_format($product['price']) ?> تومان</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <tr>
                                <td class="fixed-column">امتیاز کاربران</td>
                                <?php foreach ($products as $product): ?>
                                    <td>
                                        <div class="star-rating">
                                            <?php
                                            $avg_rating = $product['avg_rating'] ?? 0;
                                            $full_stars = floor($avg_rating);
                                            $half_star = ($avg_rating - $full_stars) >= 0.5 ? 1 : 0;
                                            $empty_stars = 5 - $full_stars - $half_star;

                                            for ($i = 0; $i < $full_stars; $i++) {
                                                echo '<i class="fas fa-star"></i>';
                                            }
                                            if ($half_star) {
                                                echo '<i class="fas fa-star-half-alt"></i>';
                                            }
                                            for ($i = 0; $i < $empty_stars; $i++) {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                            ?>
                                        </div>
                                        <span class="rating-count">(<?= $product['review_count'] ?? 0 ?> نظر)</span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <tr>
                                <td class="fixed-column">موجودی</td>
                                <?php foreach ($products as $product): ?>
                                    <td>
                                        <?php if ($product['stock'] > 0): ?>
                                            <span class="in-stock"><i class="fas fa-check-circle"></i> موجود</span>
                                        <?php else: ?>
                                            <span class="out-of-stock"><i class="fas fa-times-circle"></i> ناموجود</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <tr>
                                <td class="fixed-column">توضیحات</td>
                                <?php foreach ($products as $product): ?>
                                    <td><?= nl2br(htmlspecialchars(mb_substr($product['description'], 0, 200) . (mb_strlen($product['description']) > 200 ? '...' : ''))) ?></td>
                                <?php endforeach; ?>
                            </tr>

                            <?php foreach ($allAttributes as $attr): ?>
                                <tr>
                                    <td class="fixed-column"><?= htmlspecialchars($attr) ?></td>
                                    <?php foreach ($products as $product): ?>
                                        <td>
                                            <?= isset($product['attributes'][$attr]) ? htmlspecialchars($product['attributes'][$attr]) : '--' ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>

                            <?php
                            $uniqueAttributes = [];
                            foreach ($products as $product) {
                                foreach ($product['attributes'] as $name => $value) {
                                    if (!in_array($name, $allAttributes)) {
                                        $uniqueAttributes[] = $name;
                                    }
                                }
                            }
                            $uniqueAttributes = array_unique($uniqueAttributes);

                            foreach ($uniqueAttributes as $attr): ?>
                                <tr>
                                    <td class="fixed-column"><?= htmlspecialchars($attr) ?></td>
                                    <?php foreach ($products as $product): ?>
                                        <td>
                                            <?= isset($product['attributes'][$attr]) ? htmlspecialchars($product['attributes'][$attr]) : '--' ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="compare-summary">
                <h3><i class="fas fa-chart-pie"></i> نتیجه‌گیری مقایسه</h3>
                <div class="summary-content">
                    <?php if (count($products) >= 2): ?>
                        <?php
                        $cheapest = $products[0];
                        foreach ($products as $product) {
                            if ($product['final_price'] < $cheapest['final_price']) {
                                $cheapest = $product;
                            }
                        }

                        $highestRated = $products[0];
                        foreach ($products as $product) {
                            if (($product['avg_rating'] ?? 0) > ($highestRated['avg_rating'] ?? 0)) {
                                $highestRated = $product;
                            }
                        }
                        ?>

                        <div class="summary-item">
                            <h4>ارزان‌ترین محصول:</h4>
                            <p><a href="product-details.php?id=<?= $cheapest['id'] ?>"><?= htmlspecialchars($cheapest['name']) ?></a> با قیمت <?= number_format($cheapest['final_price']) ?> تومان</p>
                        </div>

                        <div class="summary-item">
                            <h4>پرامتیازترین محصول:</h4>
                            <p><a href="product-details.php?id=<?= $highestRated['id'] ?>"><?= htmlspecialchars($highestRated['name']) ?></a> با امتیاز <?= round($highestRated['avg_rating'] ?? 0, 1) ?> از 5</p>
                        </div>

                        <div class="summary-item">
                            <h4>توصیه ما:</h4>
                            <p>
                                <?php
                                $recommended = $highestRated;
                                if ($cheapest['id'] != $highestRated['id']) {
                                    $balanced = null;
                                    foreach ($products as $product) {
                                        if (
                                            !$balanced ||
                                            (($product['avg_rating'] / $product['final_price']) > ($balanced['avg_rating'] / $balanced['final_price']))
                                        ) {
                                            $balanced = $product;
                                        }
                                    }
                                    $recommended = $balanced;
                                }
                                ?>
                                <a href="product-details.php?id=<?= $recommended['id'] ?>"><?= htmlspecialchars($recommended['name']) ?></a> به عنوان بهترین انتخاب با توجه به توازن قیمت و کیفیت
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/script-compare.js"></script>
</body>

</html>