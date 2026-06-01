<?php
session_start();
require_once 'public/config.php';

function isUserLoggedIn()
{
    return isset($_SESSION['user_id']);
}

$shopSettings = [];
$shopSliders = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch();

    $stmt = $pdo->query("SELECT * FROM ShopSliders WHERE is_active = 1 ORDER BY display_order");
    $shopSliders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

$main_categories = [];
try {
    $stmt = $pdo->query("
        SELECT c.id, c.name, c.image_url, COUNT(p.id) AS product_count
        FROM Categories c
        INNER JOIN Products p ON c.id = p.category_id
        WHERE c.parent_id IS NULL AND p.stock > 0
        GROUP BY c.id, c.name, c.image_url
        ORDER BY c.name ASC
    ");
    $main_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching main categories: " . $e->getMessage());
}

$featured_products = [];
try {
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.price,
            p.image_url,
            c.name AS category_name,
            GROUP_CONCAT(pa.attribute_name, ': ', pa.attribute_value SEPARATOR '|') AS attributes,
            d.discount_type,
            d.discount_value,
            d.start_date,
            d.end_date,
            (SELECT AVG(rating) FROM Reviews WHERE product_id = p.id) AS avg_rating,
            (SELECT COUNT(*) FROM Reviews WHERE product_id = p.id) AS review_count,
            (SELECT COUNT(*) FROM OrderDetails WHERE product_id = p.id) AS sales_count
        FROM Products p
        LEFT JOIN Categories c ON p.category_id = c.id
        LEFT JOIN ProductAttributes pa ON p.id = pa.product_id
        LEFT JOIN Discounts d ON p.id = d.product_id
            AND (d.start_date IS NULL OR d.start_date <= NOW())
            AND (d.end_date IS NULL OR d.end_date >= NOW())
        WHERE d.id IS NOT NULL
        GROUP BY p.id
        ORDER BY sales_count DESC
        LIMIT 12
    ");

    $featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($featured_products as &$product) {
        $finalPrice = $product['price'];
        if ($product['discount_type'] === 'percentage') {
            $discountAmount = ($product['price'] * $product['discount_value']) / 100;
            $finalPrice = $product['price'] - $discountAmount;
        } elseif ($product['discount_type'] === 'fixed') {
            $finalPrice = max(0, $product['price'] - $product['discount_value']);
        }
        $product['final_price'] = $finalPrice;

        $attributes = [];
        if (!empty($product['attributes'])) {
            $attr_pairs = explode('|', $product['attributes']);
            foreach ($attr_pairs as $pair) {
                if (!empty($pair)) {
                    list($name, $value) = explode(': ', $pair, 2);
                    $attributes[$name] = $value;
                }
            }
        }
        $product['attributes'] = $attributes;
    }
    unset($product);
} catch (PDOException $e) {
    error_log("Error fetching featured products: " . $e->getMessage());
}

$new_products = [];
try {
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.price,
            p.image_url,
            c.name AS category_name,
            GROUP_CONCAT(pa.attribute_name, ': ', pa.attribute_value SEPARATOR '|') AS attributes,
            d.discount_type,
            d.discount_value,
            d.start_date,
            d.end_date,
            (SELECT AVG(rating) FROM Reviews WHERE product_id = p.id) AS avg_rating,
            (SELECT COUNT(*) FROM Reviews WHERE product_id = p.id) AS review_count
        FROM Products p
        LEFT JOIN Categories c ON p.category_id = c.id
        LEFT JOIN ProductAttributes pa ON p.id = pa.product_id
        LEFT JOIN Discounts d ON p.id = d.product_id
            AND (d.start_date IS NULL OR d.start_date <= NOW())
            AND (d.end_date IS NULL OR d.end_date >= NOW())
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 12
    ");

    $new_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($new_products as &$product) {
        $finalPrice = $product['price'];
        if (!empty($product['discount_type']) && !empty($product['discount_value'])) {
            if ($product['discount_type'] === 'percentage') {
                $discountAmount = ($product['price'] * $product['discount_value']) / 100;
                $finalPrice = $product['price'] - $discountAmount;
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
                }
            }
        }
        $product['attributes'] = $attributes;
    }
    unset($product);
} catch (PDOException $e) {
    error_log("Error fetching new products: " . $e->getMessage());
}

$popular_products = [];
try {
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.price,
            p.image_url,
            c.name AS category_name,
            GROUP_CONCAT(pa.attribute_name, ': ', pa.attribute_value SEPARATOR '|') AS attributes,
            d.discount_type,
            d.discount_value,
            d.start_date,
            d.end_date,
            (SELECT COUNT(*) FROM OrderDetails WHERE product_id = p.id) as sales_count,
            (SELECT AVG(rating) FROM Reviews WHERE product_id = p.id) AS avg_rating,
            (SELECT COUNT(*) FROM Reviews WHERE product_id = p.id) AS review_count
        FROM Products p
        LEFT JOIN Categories c ON p.category_id = c.id
        LEFT JOIN ProductAttributes pa ON p.id = pa.product_id
        LEFT JOIN Discounts d ON p.id = d.product_id
            AND (d.start_date IS NULL OR d.start_date <= NOW())
            AND (d.end_date IS NULL OR d.end_date >= NOW())
        GROUP BY p.id
        ORDER BY sales_count DESC
        LIMIT 12
    ");

    $popular_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($popular_products as &$product) {
        $finalPrice = $product['price'];
        if (!empty($product['discount_type']) && !empty($product['discount_value'])) {
            if ($product['discount_type'] === 'percentage') {
                $discountAmount = ($product['price'] * $product['discount_value']) / 100;
                $finalPrice = $product['price'] - $discountAmount;
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
                }
            }
        }
        $product['attributes'] = $attributes;
    }
    unset($product);
} catch (PDOException $e) {
    error_log("Error fetching popular products: " . $e->getMessage());
}

$recent_articles = [];
try {
    $stmt = $pdo->query("
        SELECT id, title, image_url, created_at 
        FROM Articles 
        WHERE status = 'active' 
        ORDER BY created_at DESC 
        LIMIT 8
    ");
    $recent_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching recent articles: " . $e->getMessage());
}

function isProductInFavorites($pdo, $user_id, $product_id)
{
    if (!$user_id) return false;

    $sql = "SELECT id FROM Favorites WHERE user_id = ? AND product_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $product_id]);
    return $stmt->rowCount() > 0;
}

function generateTechGradient($id)
{
    $tech_colors = [
        '#3498db',
        '#2ecc71',
        '#e74c3c',
        '#f39c12',
        '#9b59b6',
        '#1abc9c',
        '#d35400',
        '#34495e',
        '#16a085',
        '#c0392b'
    ];
    $color1 = $tech_colors[$id % count($tech_colors)];
    $color2 = $tech_colors[($id + 5) % count($tech_colors)];
    return "linear-gradient(135deg, $color1, $color2)";
}
?>

<?php
function adjustBrightness($hex, $steps)
{
    $steps = max(-255, min(255, $steps));
    $hex = str_replace('#', '', $hex);

    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
    }

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));

    $r_hex = str_pad(dechex($r), 2, '0', STR_PAD_LEFT);
    $g_hex = str_pad(dechex($g), 2, '0', STR_PAD_LEFT);
    $b_hex = str_pad(dechex($b), 2, '0', STR_PAD_LEFT);

    return '#' . $r_hex . $g_hex . $b_hex;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> صفحه اصلی | <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style-index.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="public/<?= htmlspecialchars($settings['logo_url']) ?>" type="png">
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?= !empty($shopSettings['button_color']) ? $shopSettings['button_color'] : '#3498db' ?>;
            --primary-hover: <?= !empty($shopSettings['button_color']) ? adjustBrightness($shopSettings['button_color'], -20) : '#2980b9' ?>;
        }

        .slide-btn,
        .add-to-cart,
        .consultation-btn,
        .btn-primary {
            background-color: var(--primary-color);
            transition: background-color 0.3s ease;
        }

        .slide-btn:hover,
        .add-to-cart:hover,
        .consultation-btn:hover,
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }
    </style>
</head>
</head>

<body>

    <div id="chatPopup" class="chat-popup">
        <div class="chat-header">
            <span><i class="fas fa-headset"></i> نیاز به مشاوره دارید؟</span>
            <button id="closeChat" class="close-btn">&times;</button>
        </div>
        <div class="chat-body">
            <p>کارشناسان ما آماده پاسخگویی به سوالات شما هستند</p>
        </div>
        <div class="chat-footer">
            <a href="public/Consultations.php" class="chat-btn">شروع گفتگو</a>
        </div>
    </div>

    <div id="notification" class="notification">
        <span id="notification-message"></span>
    </div>

    <div id="login-message-container" class="login-message">
        <p>لطفاً ابتدا وارد حساب کاربری خود شوید.</p>
        <button class="login-message-btn" onclick="hideLoginMessage()">متوجه شدم</button>
    </div>

    <?php include 'public/header-index.php'; ?>

    <section class="hero-slider">
        <?php if (!empty($shopSliders)): ?>
            <?php foreach ($shopSliders as $index => $slider): ?>
                <div class="slide <?= $index === 0 ? 'active' : '' ?>" style="background-image: url('public/<?= htmlspecialchars($slider['image_url']) ?>');">
                    <div class="overlay-gradient"></div>
                    <div class="slide-content">
                        <h2 class="slide-title"><?= htmlspecialchars($slider['caption']) ?></h2>
                        <?php if (!empty($slider['button_text'])): ?>
                            <a href="<?= htmlspecialchars($slider['button_link']) ?>" class="slide-btn"><?= htmlspecialchars($slider['button_text']) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="slide active" style="background-image: url('image/new/image-11.jpeg');">
                <div class="overlay-gradient"></div>
                <div class="slide-content">
                    <h2 class="slide-title">جدیدترین لپتاپ‌های روز دنیا</h2>
                    <p class="slide-description">با بهترین قیمت و گارانتی معتبر</p>
                    <a href="public/products.php?category=لپتاپ" class="slide-btn">مشاهده محصولات</a>
                </div>
            </div>
            <div class="slide" style="background-image: url('image/new/image-12.jpeg');">
                <div class="overlay-gradient"></div>
                <div class="slide-content">
                    <h2 class="slide-title">گوشی‌های هوشمند پرچمدار</h2>
                    <p class="slide-description">برترین برندها با آخرین فناوری‌ها</p>
                    <a href="public/products.php?category=گوشی" class="slide-btn">مشاهده محصولات</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="slider-controls">
            <button class="slider-control prev" aria-label="اسلاید قبلی">
                <i class="fas fa-chevron-right"></i>
            </button>
            <button class="slider-control next" aria-label="اسلاید بعدی">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>

        <div class="slider-dots">
            <?php if (!empty($shopSliders)): ?>
                <?php foreach ($shopSliders as $index => $slider): ?>
                    <span class="dot <?= $index === 0 ? 'active' : '' ?>" data-slide="<?= $index ?>"></span>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="dot active" data-slide="0"></span>
                <span class="dot" data-slide="1"></span>
            <?php endif; ?>
        </div>

        <div class="progress-bar">
            <div class="progress"></div>
        </div>
    </section>

    <section class="why-us-section">
        <div class="container">
            <div class="section-title">
                <h2>چرا ما را انتخاب می‌کنید؟</h2>
                <p>مزایای خرید از فروشگاه ما</p>
            </div>
            <div class="why-us-grid">
                <div class="why-us-card">
                    <div class="why-us-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h3>ضمانت اصالت کالا</h3>
                    <p>تمامی محصولات با گارانتی اصالت و سلامت فیزیکی ارائه می‌شوند</p>
                </div>
                <div class="why-us-card">
                    <div class="why-us-icon">
                        <i class="fas fa-truck-fast"></i>
                    </div>
                    <h3>تحویل سریع و رایگان</h3>
                    <p>ارسال رایگان برای خریدهای بالای 1 میلیون تومان در کمتر از 24 ساعت</p>
                </div>
                <div class="why-us-card">
                    <div class="why-us-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>پشتیبانی 24/7</h3>
                    <p>پشتیبانی تلفنی و آنلاین در تمام ساعات شبانه روز</p>
                </div>
            </div>
        </div>
    </section>

    <section class="categories-section">
        <div class="container">
            <div class="section-title">
                <h2>دسته‌بندی محصولات </h2>
                <p>انتخاب نوع محصول مورد نظر...</p>
            </div>
            <div class="categories-grid">
                <?php foreach ($main_categories as $category): ?>
                    <a href="public/products.php?category_id=<?= $category['id'] ?>" class="category-card" style="background: <?= generateTechGradient($category['id']) ?>;">
                        <div class="category-image-wrapper">
                            <img src="public/<?= htmlspecialchars(!empty($category['image_url']) ? $category['image_url'] : 'image/default-category.jpg') ?>" alt="<?= htmlspecialchars($category['name']) ?>" class="category-image">
                        </div>
                        <h3 class="category-name"><?= htmlspecialchars($category['name']) ?></h3>
                        <span class="product-count"><?= $category['product_count'] ?> محصول</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-title">
                <h2>پیشنهادهای ویژه</h2>
                <p>بهترین تخفیف‌ها روی محصولات </p>
            </div>

            <div class="products-grid">
                <?php foreach ($featured_products as $product): ?>
                    <div class="product-card">
                        <?php if ($product['final_price'] < $product['price']): ?>
                            <span class="product-badge">تخفیف ویژه</span>
                        <?php endif; ?>

                        <div class="product-image">
                            <a href="public/product-details.php?id=<?= $product['id'] ?>">
                                <img src="public/<?= htmlspecialchars(!empty($product['image_url']) ? $product['image_url'] : 'image/default-product.jpg') ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                            </a>
                            <div class="product-actions">
                                <button class="favorite-btn <?= isUserLoggedIn() && isProductInFavorites($pdo, $_SESSION['user_id'] ?? 0, $product['id']) ? 'active' : '' ?>"
                                    data-product-id="<?= $product['id'] ?>"
                                    onclick="toggleFavorite(this, <?= $product['id'] ?>)">
                                    <i class="<?= isUserLoggedIn() && isProductInFavorites($pdo, $_SESSION['user_id'] ?? 0, $product['id']) ? 'fas' : 'far' ?> fa-heart"></i>
                                </button>
                                <button class="quick-view-btn" data-product-id="<?= $product['id'] ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="product-info">
                            <a href="public/product-details.php?id=<?= $product['id'] ?>" class="link-details">
                                <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                                <div class="product-category"><?= htmlspecialchars($product['category_name']) ?></div>
                            </a>
                            <div class="tech-specs">
                                <?php
                                $display_attrs = array_slice($product['attributes'], 0, 3);
                                foreach ($display_attrs as $name => $value):
                                    if (!empty($value)): ?>
                                        <div>
                                            <span><?= htmlspecialchars($name) ?>:</span>
                                            <span><?= htmlspecialchars($value) ?></span>
                                        </div>
                                <?php endif;
                                endforeach;
                                ?>
                            </div>

                            <div class="product-price">
                                <?php if ($product['final_price'] < $product['price']): ?>
                                    <span class="current-price"><?= number_format($product['final_price']) ?> تومان</span>
                                    <span class="old-price"><?= number_format($product['price']) ?> تومان</span>
                                    <span class="discount-badge">
                                        <?= round((($product['price'] - $product['final_price']) / $product['price'] * 100)) ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="current-price"><?= number_format($product['price']) ?> تومان</span>
                                <?php endif; ?>
                            </div>

                            <div class="product-meta">
                                <div class="rating">
                                    <?php
                                    $avg_rating = !empty($product['avg_rating']) ? floatval($product['avg_rating']) : 0;
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
                                    <small class="text-muted">(<?= $product['review_count'] ?? 0 ?>)</small>
                                </div>

                                <div class="product-action-buttons">
                                    <input type="checkbox" class="compare-checkbox" id="compare-<?= $product['id'] ?>"
                                        data-product-id="<?= $product['id'] ?>" onchange="updateCompareList(this)">
                                    <label for="compare-<?= $product['id'] ?>" class="compare-label">
                                        <i class="fas fa-exchange-alt"></i> مقایسه
                                    </label>

                                    <button class="add-to-cart" data-product-id="<?= $product['id'] ?>">
                                        <i class="fas fa-shopping-cart"></i> افزودن
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </section>

    <section class="section consultation-section">
        <div class="container">
            <div class="consultation-card">
                <div class="consultation-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h2 class="consultation-title">مشاوره فنی رایگان</h2>
                <p class="consultation-description">کارشناسان فنی ما آماده پاسخگویی به سوالات و ارائه راهنمایی تخصصی برای خرید محصولات هستند</p>
                <a href="public/consultations.php" class="consultation-btn">درخواست مشاوره</a>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-title">
                <h2>جدیدترین محصولات </h2>
                <p>آخرین محصولات اضافه شده به فروشگاه</p>
            </div>

            <div class="products-grid">
                <?php foreach ($new_products as $product): ?>
                    <div class="product-card">
                        <span class="product-badge">جدید</span>

                        <div class="product-image">
                            <a href="public/product-details.php?id=<?= $product['id'] ?>">
                                <img src="public/<?= htmlspecialchars(!empty($product['image_url']) ? $product['image_url'] : 'image/default-product.jpg') ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                            </a>
                            <div class="product-actions">
                                <button class="favorite-btn <?= isUserLoggedIn() && isProductInFavorites($pdo, $_SESSION['user_id'] ?? 0, $product['id']) ? 'active' : '' ?>"
                                    data-product-id="<?= $product['id'] ?>"
                                    onclick="toggleFavorite(this, <?= $product['id'] ?>)">
                                    <i class="<?= isUserLoggedIn() && isProductInFavorites($pdo, $_SESSION['user_id'] ?? 0, $product['id']) ? 'fas' : 'far' ?> fa-heart"></i>
                                </button>
                                <button class="quick-view-btn" data-product-id="<?= $product['id'] ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="product-info">
                            <a href="public/product-details.php?id=<?= $product['id'] ?>" class="link-details">
                                <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                                <div class="product-category"><?= htmlspecialchars($product['category_name']) ?></div>
                            </a>
                            <div class="tech-specs">
                                <?php
                                $display_attrs = array_slice($product['attributes'], 0, 3);
                                foreach ($display_attrs as $name => $value):
                                    if (!empty($value)): ?>
                                        <div>
                                            <span><?= htmlspecialchars($name) ?>:</span>
                                            <span><?= htmlspecialchars($value) ?></span>
                                        </div>
                                <?php endif;
                                endforeach;
                                ?>
                            </div>

                            <div class="product-price">
                                <?php if ($product['final_price'] < $product['price']): ?>
                                    <span class="current-price"><?= number_format($product['final_price']) ?> تومان</span>
                                    <span class="old-price"><?= number_format($product['price']) ?> تومان</span>
                                    <span class="discount-badge">
                                        <?= round((($product['price'] - $product['final_price']) / $product['price'] * 100)) ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="current-price"><?= number_format($product['price']) ?> تومان</span>
                                <?php endif; ?>
                            </div>

                            <div class="product-meta">
                                <div class="rating">
                                    <?php
                                    $avg_rating = !empty($product['avg_rating']) ? floatval($product['avg_rating']) : 0;
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
                                    <small class="text-muted">(<?= $product['review_count'] ?? 0 ?>)</small>
                                </div>

                                <div class="product-action-buttons">
                                    <input type="checkbox" class="compare-checkbox" id="compare-new-<?= $product['id'] ?>"
                                        data-product-id="<?= $product['id'] ?>" onchange="updateCompareList(this)">
                                    <label for="compare-new-<?= $product['id'] ?>" class="compare-label">
                                        <i class="fas fa-exchange-alt"></i> مقایسه
                                    </label>

                                    <button class="add-to-cart" data-product-id="<?= $product['id'] ?>">
                                        <i class="fas fa-shopping-cart"></i> افزودن
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="features-section">
        <div class="container">
            <div class="features-grid">
                <div class="feature-item">
                    <div class="feature-icon-wrapper">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="feature-title">گارانتی اصالت کالا</h3>
                    <p class="feature-description">تمامی محصولات با گارانتی اصالت و سلامت فیزیکی</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon-wrapper">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h3 class="feature-title">تحویل سریع</h3>
                    <p class="feature-description">ارسال سریع به سراسر کشور در کمترین زمان ممکن</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon-wrapper">
                        <i class="fas fa-undo"></i>
                    </div>
                    <h3 class="feature-title">بازگشت ۷ روزه</h3>
                    <p class="feature-description">امکان بازگشت کالا تا ۷ روز پس از تحویل</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon-wrapper">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3 class="feature-title">پشتیبانی ۲۴ ساعته</h3>
                    <p class="feature-description">پشتیبانی تلفنی و آنلاین در تمام ساعات</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section" style="background-color: var(--section-bg);">
        <div class="container">
            <div class="section-title">
                <h2>آخرین مقالات و راهنماها</h2>
                <p>مقالات آموزشی و راهنمای خرید محصولات </p>
            </div>

            <div class="articles-grid">
                <?php foreach ($recent_articles as $article): ?>
                    <div class="article-card">
                        <div class="article-image">
                            <a href="public/articles.php?id=<?= $article['id'] ?>">
                                <img src="public/<?= htmlspecialchars(!empty($article['image_url']) ? $article['image_url'] : 'image/default-article.jpg') ?>" alt="<?= htmlspecialchars($article['title']) ?>" loading="lazy">
                            </a>
                        </div>
                        <div class="article-content">
                            <div class="article-date">
                                <i class="far fa-calendar-alt"></i>
                                <?= date('Y/m/d', strtotime($article['created_at'])) ?>
                            </div>
                            <h3 class="article-title"><?= htmlspecialchars($article['title']) ?></h3>
                            <p class="article-excerpt">راهنمای خرید و بررسی تخصصی محصولات دیجیتال...</p>
                            <a href="public/articles.php?id=<?= $article['id'] ?>" class="read-more">
                                ادامه مطلب
                                <i class="fas fa-arrow-left"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-title">
                <h2>پرفروش‌ترین محصولات </h2>
                <p>محصولات پرطرفدار بر اساس فروش</p>
            </div>

            <div class="products-grid">
                <?php foreach ($popular_products as $product): ?>
                    <div class="product-card">
                        <span class="product-badge">پرفروش</span>

                        <div class="product-image">
                            <a href="public/product-details.php?id=<?= $product['id'] ?>" class="link-details">
                                <img src="public/<?= htmlspecialchars(!empty($product['image_url']) ? $product['image_url'] : 'image/default-product.jpg') ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                            </a>
                            <div class="product-actions">
                                <button class="favorite-btn <?= isUserLoggedIn() && isProductInFavorites($pdo, $_SESSION['user_id'] ?? 0, $product['id']) ? 'active' : '' ?>"
                                    data-product-id="<?= $product['id'] ?>"
                                    onclick="toggleFavorite(this, <?= $product['id'] ?>)">
                                    <i class="<?= isUserLoggedIn() && isProductInFavorites($pdo, $_SESSION['user_id'] ?? 0, $product['id']) ? 'fas' : 'far' ?> fa-heart"></i>
                                </button>
                                <button class="quick-view-btn" data-product-id="<?= $product['id'] ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="product-info">
                            <a href="public/product-details.php?id=<?= $product['id'] ?>" class="link-details">
                                <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                                <div class="product-category"><?= htmlspecialchars($product['category_name']) ?></div>
                            </a>
                            <div class="tech-specs">
                                <?php
                                $display_attrs = array_slice($product['attributes'], 0, 3);
                                foreach ($display_attrs as $name => $value):
                                    if (!empty($value)): ?>
                                        <div>
                                            <span><?= htmlspecialchars($name) ?>:</span>
                                            <span><?= htmlspecialchars($value) ?></span>
                                        </div>
                                <?php endif;
                                endforeach;
                                ?>
                            </div>

                            <div class="product-price">
                                <?php if ($product['final_price'] < $product['price']): ?>
                                    <span class="current-price"><?= number_format($product['final_price']) ?> تومان</span>
                                    <span class="old-price"><?= number_format($product['price']) ?> تومان</span>
                                    <span class="discount-badge">
                                        <?= round((($product['price'] - $product['final_price']) / $product['price'] * 100)) ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="current-price"><?= number_format($product['price']) ?> تومان</span>
                                <?php endif; ?>
                            </div>

                            <div class="product-meta">
                                <div class="rating">
                                    <?php
                                    $avg_rating = !empty($product['avg_rating']) ? floatval($product['avg_rating']) : 0;
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
                                    <small class="text-muted">(<?= $product['review_count'] ?? 0 ?>)</small>
                                </div>

                                <div class="product-action-buttons">
                                    <input type="checkbox" class="compare-checkbox" id="compare-popular-<?= $product['id'] ?>"
                                        data-product-id="<?= $product['id'] ?>" onchange="updateCompareList(this)">
                                    <label for="compare-popular-<?= $product['id'] ?>" class="compare-label">
                                        <i class="fas fa-exchange-alt"></i> مقایسه
                                    </label>

                                    <button class="add-to-cart" data-product-id="<?= $product['id'] ?>">
                                        <i class="fas fa-shopping-cart"></i> افزودن
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php include 'public/footer-index.php'; ?>
    <script src="assets/js/script-index.js"></script>
    <script>
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function() {
                if (!<?= isUserLoggedIn() ? 'true' : 'false' ?>) {
                    showLoginMessage();
                    return;
                }

                const productId = this.getAttribute('data-product-id');
                if (productId) {
                    fetch('public/add-to-cart.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `product_id=${productId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showNotification('محصول به سبد خرید اضافه شد', 'success');

                                if (data.cart_count) {
                                    const cartCount = document.querySelector('.cart-count');
                                    if (cartCount) {
                                        cartCount.textContent = data.cart_count;
                                    }
                                }
                            } else {
                                showNotification(data.message || 'خطا در افزودن به سبد خرید', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showNotification('خطا در ارتباط با سرور', 'error');
                        });
                }
            });
        });

        function toggleFavorite(button, productId) {
            if (!<?= isUserLoggedIn() ? 'true' : 'false' ?>) {
                showLoginMessage();
                return;
            }

            const isActive = button.classList.contains('active');
            const icon = button.querySelector('i');

            fetch('public/toggle_favorite.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}&action=${isActive ? 'remove' : 'add'}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        button.classList.toggle('active');
                        icon.classList.toggle('far');
                        icon.classList.toggle('fas');

                        showNotification(
                            isActive ? 'محصول از لیست علاقه‌مندی‌ها حذف شد' : 'محصول به لیست علاقه‌مندی‌ها اضافه شد',
                            isActive ? 'warning' : 'success'
                        );
                    } else {
                        showNotification(data.message || 'خطا در بروزرسانی لیست علاقه‌مندی‌ها', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('خطا در ارتباط با سرور', 'error');
                });
        }
    </script>
</body>

</html>