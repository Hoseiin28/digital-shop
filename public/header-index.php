<?php
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] : '';
$userRole = $isLoggedIn ? $_SESSION['user_role'] : '';

$shopSettings = [];
try {
    $stmt = $pdo->query("SELECT shop_name, logo_url, phone, email, address, instagram, telegram, whatsapp, youtube FROM ShopSettings LIMIT 1");
    $shopSettings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching shop settings: " . $e->getMessage());
}

$logo_url = $shopSettings['logo_url'] ?? '/digital-shop/image/logo.jpg';

$productCategories = [];
try {
    $stmt = $pdo->query("SELECT id, name, image_url FROM Categories WHERE parent_id IS NULL AND id NOT IN (SELECT DISTINCT category FROM Articles WHERE category IS NOT NULL) ORDER BY name");
    $productCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching product categories: " . $e->getMessage());
}

$articleCategories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT c.id, c.name, c.image_url FROM Categories c JOIN Articles a ON c.id = a.category WHERE c.parent_id IS NULL ORDER BY c.name");
    $articleCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching article categories: " . $e->getMessage());
}

$cartCount = 0;
if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("
            SELECT SUM(od.quantity) as total 
            FROM Orders o 
            JOIN OrderDetails od ON o.id = od.order_id 
            WHERE o.user_id = ? 
            AND o.status = 'pending' 
            AND o.payment_status = 'unpaid'
            AND o.created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND NOT EXISTS (
                SELECT 1 FROM Payments p 
                WHERE p.order_id = o.id 
                AND p.payment_method = 'cash_on_delivery'
            )
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $cartCount = $result['total'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error fetching cart count: " . $e->getMessage());
    }
}

$compareCount = 0;
try {
    if ($isLoggedIn) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM ProductComparison WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        if (!isset($_SESSION['compare_session_id'])) {
            $_SESSION['compare_session_id'] = session_id();
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM ProductComparison WHERE session_id = ?");
        $stmt->execute([$_SESSION['compare_session_id']]);
    }
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $compareCount = $result['count'] ?? 0;
} catch (PDOException $e) {
    error_log("Error fetching compare count: " . $e->getMessage());
}



$contactInfo = [
    'phone' => $shopSettings['phone'] ?? '۰۹۱۲۳۴۵۶۷۸۹',
    'email' => $shopSettings['email'] ?? 'info@example.com',
    'address' => $shopSettings['address'] ?? 'تهران، خیابان نمونه'
];

$socialLinks = [];
if ($shopSettings) {
    $socialPlatforms = ['instagram', 'telegram', 'whatsapp', 'youtube'];
    foreach ($socialPlatforms as $platform) {
        $socialLinks[$platform] = $shopSettings[$platform] ?? '';
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($shop_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=IRANSans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="/digital-shop/assets/css/style-header-index.css">
</head>

<body>

    <div class="top-bar">
        <div class="header-container">
            <div class="contact-info">
                <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($contactInfo['phone']); ?></span>
                <span><?php echo htmlspecialchars($contactInfo['email']); ?><i class="fas fa-envelope"></i> </span>
                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($contactInfo['address']); ?></span>
            </div>
            <div class="social-links">
                <?php if (!empty($socialLinks['instagram'])): ?>
                    <a href="<?php echo htmlspecialchars($socialLinks['instagram']); ?>" target="_blank" title="اینستاگرام"><i class="fab fa-instagram"></i></a>
                <?php endif; ?>
                <?php if (!empty($socialLinks['telegram'])): ?>
                    <a href="<?php echo htmlspecialchars($socialLinks['telegram']); ?>" target="_blank" title="تلگرام"><i class="fab fa-telegram"></i></a>
                <?php endif; ?>
                <?php if (!empty($socialLinks['whatsapp'])): ?>
                    <a href="<?php echo htmlspecialchars($socialLinks['whatsapp']); ?>" target="_blank" title="واتساپ"><i class="fab fa-whatsapp"></i></a>
                <?php endif; ?>
                <?php if (!empty($socialLinks['youtube'])): ?>
                    <a href="<?php echo htmlspecialchars($socialLinks['youtube']); ?>" target="_blank" title="یوتیوب"><i class="fab fa-youtube"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="welcome-message" style="display: block;">
        <?php if ($isLoggedIn): ?>
            <div class="welcome-logged-in">
                <span>خوش آمدید <?php echo htmlspecialchars($userName); ?>!</span>
            </div>
        <?php else: ?>
            <div class="welcome-guest">
                <span>برای دسترسی به تمام امکانات لطفا وارد شوید یا ثبت‌نام کنید</span>
                <a href="/digital-shop/public/login.php" class="login-link">ورود / ثبت‌نام</a>
            </div>
        <?php endif; ?>
    </div>

    <header class="main-header">
        <div class="header-container">
            <div class="header-row">
                <div class="logo-container">
                    <a href="/digital-shop/index.php" class="shop-a">
                        <img src="/digital-shop/public/<?php echo htmlspecialchars($logo_url); ?>"
                            alt="<?php echo htmlspecialchars($shop_name); ?>" class="logo">
                    </a>
                </div>

                <div class="search-container">
                    <form action="/digital-shop/public/search.php" method="GET" class="search-form">
                        <input type="text" name="query" placeholder="جستجوی محصولات..." autocomplete="off">
                        <button type="submit"><i class="fas fa-search"></i></button>
                        <div class="search-suggestions"></div>
                    </form>
                </div>

                <div class="user-actions">
                    <?php if ($isLoggedIn): ?>
                        <div class="user-dropdown">
                            <button class="user-btn">
                                <i class="fas fa-user-circle"></i>
                                <span><?php echo htmlspecialchars($userName); ?></span>
                            </button>
                            <div class="dropdown-menu">
                                <a href="/digital-shop/public/profile.php#profile"><i class="fas fa-user"></i> پروفایل کاربری</a>
                                <a href="/digital-shop/public/profile.php#orders"><i class="fas fa-clipboard-list"></i> سفارشات من</a>
                                <a href="/digital-shop/public/profile.php#consultations"><i class="fas fa-comments"></i>مشاوره های من</a>
                                <a href="/digital-shop/public/profile.php#messages"><i class="fas fa-headset"></i>پیام های من</a>
                                <a href="/digital-shop/public/profile.php#favorites"><i class="fas fa-heart"></i> لیست علاقه‌مندی‌ها</a>
                                <?php if ($userRole === 'admin'): ?>
                                    <a href="/digital-shop/admin/admin-panel.php"><i class="fas fa-cog"></i> پنل مدیریت</a>
                                <?php endif; ?>
                                <a href="/digital-shop/public/logout.php"><i class="fas fa-sign-out-alt"></i> خروج از حساب</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/digital-shop/public/login.php" class="auth-btn">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>ورود / ثبت‌نام</span>
                        </a>
                    <?php endif; ?>

                    <a href="javascript:void(0);" class="cart-btn" title="سبد خرید" id="refresh">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-count"><?php echo htmlspecialchars($cartCount); ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="/digital-shop/public/compare.php" class="compare-btn" title="مقایسه محصولات">
                        <i class="fas fa-exchange-alt"></i>
                        <?php if ($compareCount > 0): ?>
                            <span class="compare-count"><?php echo htmlspecialchars($compareCount); ?></span>
                        <?php endif; ?>
                    </a>

                    <button class="mobile-menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <nav class="main-nav">
                <ul class="nav-list">
                    <li class="<?php echo $currentPage == 'index.php' ? 'active' : ''; ?>">
                        <a href="/digital-shop/index.php"><i class="fas fa-home"></i> خانه</a>
                    </li>

                    <li class="mega-menu <?php echo $currentPage == 'products.php' ? 'active' : ''; ?>">
                        <a href="/digital-shop/public/products.php"><i class="fas fa-box-open"></i> محصولات <i class="fas fa-chevron-down"></i></a>
                        <div class="mega-menu-content">
                            <div class="header-container">
                                <div class="mega-menu-row">
                                    <?php foreach ($productCategories as $category): ?>
                                        <div class="mega-menu-col">
                                            <h4>
                                                <a href="/digital-shop/public/products.php?category=<?php echo $category['id']; ?>">
                                                    <?php if ($category['image_url']): ?>
                                                        <img src="/digital-shop/public/<?php echo htmlspecialchars($category['image_url']); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" class="category-icon">
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </a>
                                            </h4>
                                            <?php $subCategories = getSubCategories($category['id'], 'product'); ?>
                                            <?php if (!empty($subCategories)): ?>
                                                <ul>
                                                    <?php foreach ($subCategories as $subCat): ?>
                                                        <li>
                                                            <a href="/digital-shop/public/products.php?category=<?php echo $subCat['id']; ?>">
                                                                <?php echo htmlspecialchars($subCat['name']); ?>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </li>

                    <li class="mega-menu <?php echo $currentPage == 'articles.php' ? 'active' : ''; ?>">
                        <a href="/digital-shop/public/articles.php"><i class="fas fa-newspaper"></i> مقالات <i class="fas fa-chevron-down"></i></a>
                        <div class="mega-menu-content">
                            <div class="header-container">
                                <div class="mega-menu-row">
                                    <?php foreach ($articleCategories as $category): ?>
                                        <div class="mega-menu-col">
                                            <h4>
                                                <a href="/digital-shop/public/articles.php?category=<?php echo $category['id']; ?>">
                                                    <?php if ($category['image_url']): ?>
                                                        <img src="/digital-shop/public/<?php echo htmlspecialchars($category['image_url']); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" class="category-icon">
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </a>
                                            </h4>
                                            <?php $subCategories = getSubCategories($category['id'], 'article'); ?>
                                            <?php if (!empty($subCategories)): ?>
                                                <ul>
                                                    <?php foreach ($subCategories as $subCat): ?>
                                                        <li>
                                                            <a href="/digital-shop/public/articles.php?category=<?php echo $subCat['id']; ?>">
                                                                <?php echo htmlspecialchars($subCat['name']); ?>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </li>

                    <li class="<?php echo $currentPage == 'faq.php' ? 'active' : ''; ?>">
                        <a href="/digital-shop/public/faq.php"><i class="fas fa-question-circle"></i> سوالات متداول</a>
                    </li>

                    <li class="<?php echo $currentPage == 'about.php' ? 'active' : ''; ?>">
                        <a href="/digital-shop/public/about.php"><i class="fas fa-info-circle"></i> درباره ما</a>
                    </li>

                    <li class="<?php echo $currentPage == 'contact.php' ? 'active' : ''; ?>">
                        <a href="/digital-shop/public/contact.php"><i class="fas fa-headset"></i> تماس با ما</a>
                    </li>

                    <li class="<?php echo $currentPage == 'consultations.php' ? 'active' : ''; ?>">
                        <a href="/digital-shop/public/consultations.php"><i class="fas fa-comments"></i> مشاوره</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="cart-modal-overlay"></div>
    <div class="cart-modal">
        <div class="cart-modal-header">
            <h3>سبد خرید شما</h3>
            <button class="close-cart-modal">&times;</button>
        </div>

        <div class="cart-modal-body">
            <div class="cart-items-container">
                <div class="empty-cart-message">
                    <i class="fas fa-shopping-cart"></i>
                    <p>سبد خرید شما خالی است</p>
                    <a href="/digital-shop/public/products.php" class="btn btn-primary">مشاهده محصولات</a>
                </div>
            </div>

            <div class="cart-summary">
                <div class="summary-row">
                    <span>جمع کل:</span>
                    <span class="cart-total-price">۰ تومان</span>
                </div>
                <div class="summary-row discount-row">
                    <span>تخفیف:</span>
                    <span class="cart-discount">۰ تومان</span>
                </div>
                <div class="summary-row final-price-row">
                    <span>مبلغ قابل پرداخت:</span>
                    <span class="cart-final-price">۰ تومان</span>
                </div>
            </div>
        </div>

        <div class="cart-modal-footer">
            <button class="checkout-btn" disabled>
                <i class="fas fa-credit-card"></i> تسویه حساب
            </button>
        </div>
    </div>

    <div class="mobile-nav-overlay"></div>

    <nav class="mobile-nav">
        <div class="mobile-nav-header">
            <img src="/digital-shop/public/<?php echo htmlspecialchars($logo_url); ?>"
                alt="<?php echo htmlspecialchars($shop_name); ?>" class="mobile-logo">
            <button class="mobile-nav-close"><i class="fas fa-times"></i></button>
        </div>

        <div class="mobile-user-section">
            <?php if ($isLoggedIn): ?>
                <div class="mobile-user-info">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo htmlspecialchars($userName); ?></span>
                </div>
                <a href="/digital-shop/public/profile.php#profile" class="mobile-nav-link"><i class="fas fa-user"></i> پروفایل کاربری</a>
                <a href="/digital-shop/public/profile.php#orders" class="mobile-nav-link"><i class="fas fa-clipboard-list"></i> سفارشات من</a>
                <a href="/digital-shop/public/profile.php#consultations" class="mobile-nav-link"><i class="fas fa-comments"></i>مشاوره های من</a>
                <a href="/digital-shop/public/profile.php#messages" class="mobile-nav-link"><i class="fas fa-headset"></i>پیام های من</a>
                <a href="/digital-shop/public/profile.php#favorites" class="mobile-nav-link"><i class="fas fa-heart"></i> لیست علاقه‌مندی‌ها</a>
                <?php if ($userRole === 'admin'): ?>
                    <a href="/digital-shop/admin/admin-panel.php" class="mobile-nav-link"><i class="fas fa-cog"></i> پنل مدیریت</a>
                <?php endif; ?>
                <a href="/digital-shop/public/logout.php" class="mobile-nav-link"><i class="fas fa-sign-out-alt"></i> خروج از حساب</a>
            <?php else: ?>
                <a href="/digital-shop/public/login.php" class="mobile-auth-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    ورود / ثبت‌نام
                </a>
            <?php endif; ?>

            <div class="mobile-action-buttons" id="refresh">
                <a href="/digital-shop/public/cart.php" class="mobile-cart-btn">
                    <?php if ($cartCount > 0): ?>
                        <span class="mobile-cart-count"><?php echo htmlspecialchars($cartCount); ?></span>
                    <?php endif; ?>
                    <i class="fas fa-shopping-cart"></i>
                </a>

                <a href="/digital-shop/public/compare.php" class="mobile-compare-btn">
                    <?php if ($compareCount > 0): ?>
                        <span class="mobile-compare-count"><?php echo htmlspecialchars($compareCount); ?></span>
                    <?php endif; ?>
                    <i class="fas fa-exchange-alt"></i>
                </a>
            </div>
        </div>

        <ul class="mobile-nav-list">
            <li>
                <a href="/digital-shop/index.php" class="mobile-nav-link"><i class="fas fa-home"></i> خانه</a>
            </li>

            <li class="mobile-nav-dropdown">
                <a href="/digital-shop/public/products.php" class="mobile-nav-link">
                    <i class="fas fa-box-open"></i>
                    محصولات
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="mobile-sub-nav">
                    <?php foreach ($productCategories as $category): ?>
                        <li class="mobile-nav-dropdown">
                            <a href="/digital-shop/public/products.php?category=<?php echo $category['id']; ?>" class="mobile-nav-link">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </a>
                            <?php $subCategories = getSubCategories($category['id'], 'product'); ?>
                            <?php if (!empty($subCategories)): ?>
                                <ul class="mobile-sub-nav">
                                    <?php foreach ($subCategories as $subCat): ?>
                                        <li>
                                            <a href="/digital-shop/public/products.php?category=<?php echo $subCat['id']; ?>" class="mobile-nav-link">
                                                <?php echo htmlspecialchars($subCat['name']); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </li>

            <li class="mobile-nav-dropdown">
                <button class="mobile-dropdown-toggle">
                    <i class="fas fa-newspaper"></i>
                    مقالات
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="mobile-sub-nav">
                    <?php foreach ($articleCategories as $category): ?>
                        <li>
                            <a href="/digital-shop/public/articles.php?category=<?php echo $category['id']; ?>" class="mobile-nav-link">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </li>

            <li>
                <a href="/digital-shop/public/faq.php" class="mobile-nav-link"><i class="fas fa-question-circle"></i> سوالات متداول</a>
            </li>

            <li>
                <a href="/digital-shop/public/about.php" class="mobile-nav-link"><i class="fas fa-info-circle"></i> درباره ما</a>
            </li>

            <li>
                <a href="/digital-shop/public/contact.php" class="mobile-nav-link"><i class="fas fa-headset"></i> تماس با ما</a>
            </li>

            <li>
                <a href="/digital-shop/public/consultations.php" class="mobile-nav-link"><i class="fas fa-comments"></i> مشاوره</a>
            </li>
        </ul>
    </nav>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="/digital-shop/assets/js/script-header-index.js"></script>

    <button class="back-to-top">
        <i class="fas fa-arrow-up"></i>
    </button>

</body>

</html>

<?php
function getSubCategories($parentId, $type = 'product')
{
    global $pdo;
    try {
        if ($type === 'product') {
            $stmt = $pdo->prepare("SELECT id, name, image_url FROM Categories WHERE parent_id = ? AND id NOT IN (SELECT DISTINCT category FROM Articles WHERE category IS NOT NULL) ORDER BY name");
        } else {
            $stmt = $pdo->prepare("SELECT id, name, image_url FROM Categories WHERE parent_id = ? AND id IN (SELECT DISTINCT category FROM Articles WHERE category IS NOT NULL) ORDER BY name");
        }
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching subcategories: " . $e->getMessage());
        return [];
    }
}
?>