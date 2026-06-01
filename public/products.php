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

$category_id = isset($_GET['category']) ? intval($_GET['category']) : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 24;
$offset = ($page - 1) * $limit;

$sql = "
    SELECT 
        p.id,
        p.name,
        p.price,
        p.description,
        p.image_url,
        p.created_at,
        c.name as category_name,
        GROUP_CONCAT(pa.attribute_name, ': ', pa.attribute_value SEPARATOR '|') AS attributes,
        d.discount_type,
        d.discount_value,
        d.start_date,
        d.end_date,
        CASE 
            WHEN d.discount_type = 'percentage' THEN p.price * (1 - d.discount_value / 100)
            WHEN d.discount_type = 'fixed' THEN GREATEST(0, p.price - d.discount_value)
            ELSE p.price
        END as final_price,
        (SELECT COUNT(*) FROM OrderDetails od WHERE od.product_id = p.id) as sales_count,
        (SELECT AVG(rating) FROM Reviews WHERE product_id = p.id) as avg_rating,
        (SELECT COUNT(*) FROM Reviews WHERE product_id = p.id) as review_count,
        COALESCE((SELECT SUM(view_count) FROM ProductViews WHERE product_id = p.id), 0) as view_count
    FROM Products p
    INNER JOIN Categories c ON p.category_id = c.id
    LEFT JOIN ProductAttributes pa ON p.id = pa.product_id
    LEFT JOIN Discounts d ON p.id = d.product_id
        AND (d.start_date IS NULL OR d.start_date <= NOW())
        AND (d.end_date IS NULL OR d.end_date >= NOW())
    WHERE p.stock > 0
";

$where_conditions = [];
$params = [];

if ($category_id) {
    $where_conditions[] = "p.category_id = :category_id";
    $params[':category_id'] = $category_id;
}

if (!empty($search_query)) {
    $where_conditions[] = "(p.name LIKE :search_query OR p.description LIKE :search_query)";
    $params[':search_query'] = "%$search_query%";
}

if (!empty($where_conditions)) {
    $sql .= " AND " . implode(" AND ", $where_conditions);
}

$sql .= " GROUP BY p.id";

switch ($sort_by) {
    case 'price_asc':
        $sql .= " ORDER BY final_price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY final_price DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY sales_count DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY avg_rating DESC";
        break;
    case 'views':
        $sql .= " ORDER BY view_count DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.created_at DESC";
        break;
}

$sql .= " LIMIT :limit OFFSET :offset";
$params[':limit'] = $limit;
$params[':offset'] = $offset;

try {
    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => &$val) {
        $param_type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindParam($key, $val, $param_type);
    }

    $stmt->execute();
    $products = $stmt->fetchAll();

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
        $product['avg_rating'] = floatval($product['avg_rating'] ?? 0);
    }
    unset($product);
} catch (PDOException $e) {
    die("خطا در دریافت محصولات: " . $e->getMessage());
}

$total_products_sql = "
    SELECT COUNT(*) as total 
    FROM Products p
    WHERE p.stock > 0
";

if (!empty($where_conditions)) {
    $total_products_sql .= " AND " . implode(" AND ", $where_conditions);
}

try {
    $stmt = $pdo->prepare($total_products_sql);

    foreach ($params as $key => &$val) {
        if ($key === ':limit' || $key === ':offset') continue;
        $param_type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindParam($key, $val, $param_type);
    }

    $stmt->execute();
    $total_products = $stmt->fetch()['total'];
    $total_pages = ceil($total_products / $limit);
} catch (PDOException $e) {
    die("خطا در شمارش محصولات: " . $e->getMessage());
}

$categories = [];
try {
    $stmt = $pdo->query("
        SELECT c.id, c.name, COUNT(p.id) as product_count, c.image_url as category_image
        FROM Categories c
        INNER JOIN Products p ON c.id = p.category_id
        WHERE p.stock > 0
        GROUP BY c.id, c.name, c.image_url
        HAVING product_count > 0
        ORDER BY c.name
    ");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}
$current_category_name = "همه محصولات";
$current_category_image = "../image/default-category.jpeg";
if ($category_id) {
    try {
        $stmt = $pdo->prepare("SELECT name, image_url FROM Categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch();
        if ($category) {
            $current_category_name = $category['name'];
            $current_category_image = $category['image_url'] ?: $current_category_image;
        }
    } catch (PDOException $e) {
        error_log("Error fetching category name: " . $e->getMessage());
    }
}
function isProductInFavorites($pdo, $user_id, $product_id)
{
    if (!$user_id) return false;

    $sql = "SELECT id FROM Favorites WHERE user_id = ? AND product_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $product_id]);
    return $stmt->rowCount() > 0;
}
function build_query_string($params)
{
    $current_params = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($current_params[$key]);
        } else {
            $current_params[$key] = $value;
        }
    }
    $current_params = array_filter($current_params, function ($value) {
        return $value !== '' && $value !== null;
    });
    return http_build_query($current_params);
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

    $color_parts = str_split($hex, 2);
    $return = '#';

    foreach ($color_parts as $color) {
        $color   = hexdec($color);
        $color   = max(0, min(255, $color + $steps));
        $return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT);
    }

    return $return;
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
    <title><?= htmlspecialchars($current_category_name) ?> | <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=IRANSans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
    <link rel="stylesheet" href="../assets/css/style-products.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($buttonColor); ?>;
            --primary-hover: <?php echo htmlspecialchars(adjustBrightness($buttonColor, -20)); ?>;
            --primary-active: <?php echo htmlspecialchars(adjustBrightness($buttonColor, -30)); ?>;
        }

        .category-header {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('<?= $current_category_image ?>');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 60px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .btn-primary,
        .add-to-cart,
        .filter-option.active,
        .pagination a:hover,
        .pagination .current,
        #compare-button {
            background-color: var(--primary-color);
        }

        .btn-primary:hover,
        .add-to-cart:hover {
            background-color: var(--primary-hover);
        }

        .btn-primary:active,
        .add-to-cart:active,
        #compare-button:active {
            background-color: var(--primary-active);
        }

        .btn-secondary {
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-secondary:hover {
            background-color: var(--primary-color);
            color: white;
        }
    </style>
</head>

<body>
    <?php include 'header-index.php'; ?>

    <div class="category-header">
        <h1><?= htmlspecialchars($current_category_name) ?></h1>
        <p>بهترین محصولات با کیفیت عالی و قیمت مناسب</p>
    </div>

    <div class="container">
        <aside class="filters-section">
            <div class="filter-group">
                <h3 class="filter-title"><i class="fas fa-list"></i> دسته‌بندی‌ها</h3>
                <div class="filter-options">
                    <a href="products.php" class="filter-option <?= !$category_id ? 'active' : '' ?>">
                        همه محصولات
                        <span>(<?= $total_products ?>)</span>
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="products.php?<?= build_query_string(['category' => $cat['id'], ['page' => null]]) ?>"
                            class="filter-option <?= $category_id == $cat['id'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                            <span>(<?= $cat['product_count'] ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="filter-group">
                <h3 class="filter-title"><i class="fas fa-sort"></i> مرتب‌سازی</h3>
                <div class="filter-options">
                    <a href="?<?= build_query_string(['sort' => 'newest']) ?>"
                        class="filter-option <?= $sort_by == 'newest' ? 'active' : '' ?>">جدیدترین</a>
                    <a href="?<?= build_query_string(['sort' => 'price_asc']) ?>"
                        class="filter-option <?= $sort_by == 'price_asc' ? 'active' : '' ?>">ارزان‌ترین</a>
                    <a href="?<?= build_query_string(['sort' => 'price_desc']) ?>"
                        class="filter-option <?= $sort_by == 'price_desc' ? 'active' : '' ?>">گران‌ترین</a>
                    <a href="?<?= build_query_string(['sort' => 'popular']) ?>"
                        class="filter-option <?= $sort_by == 'popular' ? 'active' : '' ?>">پرفروش‌ترین</a>
                    <a href="?<?= build_query_string(['sort' => 'rating']) ?>"
                        class="filter-option <?= $sort_by == 'rating' ? 'active' : '' ?>">بالاترین امتیاز</a>
                    <a href="?<?= build_query_string(['sort' => 'views']) ?>"
                        class="filter-option <?= $sort_by == 'views' ? 'active' : '' ?>">پربازدیدترین</a>
                </div>
            </div>

            <div class="filter-group">
                <h3 class="filter-title"><i class="fas fa-search"></i> جستجوی محصولات</h3>
                <form method="GET" action="products.php" class="searchh-form">
                    <input type="text" name="search" placeholder="نام محصول را جستجو کنید..."
                        value="<?= htmlspecialchars($search_query) ?>">
                    <?php if ($category_id): ?>
                        <input type="hidden" name="category" value="<?= $category_id ?>">
                    <?php endif; ?>
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </aside>
        <button class="mobile-filter-toggle" id="mobileFilterToggle">
            <i class="fas fa-filter"></i> فیلترها
        </button>
        <main class="products-container">
            <div class="products-header">
                <div class="products-count"><?= $total_products ?> محصول یافت شد</div>
            </div>

            <div class="products-grid">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <?php if ($product['final_price'] < $product['price']): ?>
                                <span class="product-badge">تخفیف ویژه</span>
                            <?php elseif (strtotime($product['created_at']) > strtotime('-7 days')): ?>
                                <span class="product-badge">جدید</span>
                            <?php endif; ?>

                            <div class="product-image">
                                <a href="product-details.php?id=<?= $product['id'] ?>">
                                    <img src="<?= htmlspecialchars($product['image_url'] ?: '../image/default-product.jpg') ?>"
                                        alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                                </a>
                                <div class="product-actions">
                                    <button class="quick-view-btn" data-product-id="<?= $product['id'] ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="favorite-btn <?= $isLoggedIn && isProductInFavorites($pdo, $_SESSION['user_id'], $product['id']) ? 'active' : '' ?>"
                                        data-product-id="<?= $product['id'] ?>">
                                        <i class="<?= $isLoggedIn && isProductInFavorites($pdo, $_SESSION['user_id'], $product['id']) ? 'fas' : 'far' ?> fa-heart"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="product-info">
                                <h3 class="product-title">
                                    <a href="product-details.php?id=<?= $product['id'] ?>">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </a>
                                </h3>

                                <div class="tech-specs">
                                    <?php
                                    $attributes = [];
                                    if (!empty($product['attributes'])) {
                                        $attr_pairs = explode('|', $product['attributes']);
                                        foreach ($attr_pairs as $pair) {
                                            if (!empty($pair)) {
                                                list($name, $value) = explode(': ', $pair, 2);
                                                $attributes[$name] = $value;
                                            }
                                        }

                                        $display_attrs = array_slice($attributes, 0, 3);
                                        foreach ($display_attrs as $name => $value):
                                            if (!empty($value)): ?>
                                                <div>
                                                    <span><?= htmlspecialchars($name) ?>:</span>
                                                    <span><?= htmlspecialchars($value) ?></span>
                                                </div>
                                    <?php endif;
                                        endforeach;
                                    }
                                    ?>
                                </div>

                                <div class="product-rating">
                                    <div class="star-rating">
                                        <?php
                                        $avg_rating = $product['avg_rating'];
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
                                    <span class="rating-count">(<?= $product['review_count'] ?>)</span>
                                </div>

                                <div class="product-price">
                                    <?php if ($product['final_price'] < $product['price']): ?>
                                        <span class="current-price"><?= number_format($product['final_price']) ?> تومان</span>
                                        <span class="old-price"><?= number_format($product['price']) ?></span>
                                        <span class="discount-badge">
                                            <?= round((($product['price'] - $product['final_price']) / $product['price'] * 100)) ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="current-price"><?= number_format($product['price']) ?> تومان</span>
                                    <?php endif; ?>
                                </div>

                                <div class="product-footer">
                                    <input type="checkbox" class="compare-checkbox" id="compare-<?= $product['id'] ?>"
                                        data-product-id="<?= $product['id'] ?>">
                                    <label for="compare-<?= $product['id'] ?>" class="compare-label">
                                        <i class="fas fa-exchange-alt"></i> مقایسه
                                    </label>
                                    <button class="add-to-cart" data-product-id="<?= $product['id'] ?>">
                                        <i class="fas fa-shopping-cart"></i> افزودن
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-products" style="grid-column: 1 / -1; text-align: center; padding: 50px 0;">
                        <i class="fas fa-box-open" style="font-size: 3rem; color: #ccc; margin-bottom: 20px;"></i>
                        <h3 style="color: #555; margin-bottom: 10px;">محصولی یافت نشد</h3>
                        <p style="color: #888; margin-bottom: 20px;">متأسفانه هیچ محصولی با معیارهای جستجوی شما مطابقت ندارد.</p>
                        <a href="products.php" style="display: inline-block; background: var(--primary-color); color: white; padding: 10px 20px; border-radius: var(--border-radius); text-decoration: none;">مشاهده همه محصولات</a>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= build_query_string(['page' => $page - 1]) ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= build_query_string(['page' => $i]) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= build_query_string(['page' => $page + 1]) ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <div id="cart-message" class="cart-message"></div>

    <div id="chatPopup" class="chat-popup">
        <div class="chat-header">
            <span><i class="fas fa-headset"></i> نیاز به مشاوره دارید؟</span>
            <button id="closeChat" class="close-btn">&times;</button>
        </div>
        <div class="chat-body">
            <p>کارشناسان ما آماده پاسخگویی به سوالات شما هستند</p>
        </div>
        <div class="chat-footer">
            <a href="Consultations.php" class="chat-btn">شروع گفتگو</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
    <script src="../assets/js/script-products.js"></script>
    <script>
        document.getElementById('mobileFilterToggle').addEventListener('click', function() {
            document.querySelector('.filters-section').classList.toggle('active');

            if (document.querySelector('.filters-section').classList.contains('active')) {
                this.innerHTML = '<i class="fas fa-times"></i> بستن فیلترها';
            } else {
                this.innerHTML = '<i class="fas fa-filter"></i> فیلترها';
            }
        });

        document.addEventListener('click', function(event) {
            const filtersSection = document.querySelector('.filters-section');
            const filterToggle = document.getElementById('mobileFilterToggle');

            if (!filtersSection.contains(event.target) && event.target !== filterToggle) {
                filtersSection.classList.remove('active');
                filterToggle.innerHTML = '<i class="fas fa-filter"></i> فیلترها';
            }
        });

        setTimeout(() => {
            document.getElementById('chatPopup').classList.add('show');
        }, 10000);

        document.getElementById('closeChat').addEventListener('click', () => {
            document.getElementById('chatPopup').classList.remove('show');
        });

        function toggleFavorite(button, productId) {
            if (!<?= $isLoggedIn ? 'true' : 'false' ?>) {
                showLoginMessage();
                return;
            }

            const isActive = button.classList.contains('active');
            const icon = button.querySelector('i');

            fetch('toggle_favorite.php', {
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

                        showMessage(isActive ? 'محصول از لیست علاقه‌مندی‌ها حذف شد' : 'محصول به لیست علاقه‌مندی‌ها اضافه شد',
                            isActive ? 'warning' : 'success');
                    } else {
                        showMessage(data.message || 'خطا در بروزرسانی لیست علاقه‌مندی‌ها', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('خطا در ارتباط با سرور', 'error');
                });
        }

        function addToCart(productId) {
            fetch('add-to-cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}&quantity=1`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage('محصول به سبد خرید اضافه شد', 'success');

                        if (data.cart_count) {
                            const cartCount = document.querySelector('.cart-count');
                            if (cartCount) {
                                cartCount.textContent = data.cart_count;
                            }
                        }
                    } else {
                        showMessage(data.message || 'خطا در افزودن به سبد خرید', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('خطا در ارتباط با سرور', 'error');
                });
        }

        function showMessage(message, type = 'success') {
            const cartMessage = document.getElementById('cart-message');
            cartMessage.textContent = message;
            cartMessage.className = 'cart-message';

            switch (type) {
                case 'success':
                    cartMessage.style.backgroundColor = '#4CAF50';
                    break;
                case 'error':
                    cartMessage.style.backgroundColor = '#f44336';
                    break;
                case 'warning':
                    cartMessage.style.backgroundColor = '#ff9800';
                    break;
                default:
                    cartMessage.style.backgroundColor = '#4CAF50';
            }

            cartMessage.classList.add('show');

            setTimeout(() => {
                cartMessage.classList.remove('show');
            }, 3000);
        }
    </script>
</body>

</html>