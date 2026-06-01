
<?php
$footerData = [];

try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $footerData['shop'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $stmt = $pdo->query("SELECT id, title FROM Articles WHERE status='active' ORDER BY created_at DESC LIMIT 5");
    $footerData['articles'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    $stmt = $pdo->query("
        SELECT c.id, c.name 
        FROM Categories c
        JOIN Products p ON c.id = p.category_id
        GROUP BY c.id
        ORDER BY COUNT(p.id) DESC
        LIMIT 6
    ");
    $footerData['popular_categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    $stmt = $pdo->query("SELECT phone, email, address FROM ShopSettings LIMIT 1");
    $footerData['contact'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $stmt = $pdo->query("SELECT instagram, telegram, whatsapp, youtube FROM ShopSettings LIMIT 1");
    $footerData['social'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $stmt = $pdo->query("SELECT id, question FROM FAQs ORDER BY created_at DESC LIMIT 5");
    $footerData['faqs'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
} catch (PDOException $e) {
    error_log("Error fetching footer data: " . $e->getMessage());
    $footerData = [
        'shop' => [
            'shop_name' => 'فروشگاه دیجیتال',
            'shop_description' => 'فروشگاه اینترنتی محصولات دیجیتال',
            'logo_url' => '/digital-shop/image/logo.jpg',
            'working_hours' => 'هر روز از ۸ صبح تا ۱۲ شب'
        ],
        'articles' => [],
        'popular_categories' => [],
        'contact' => [
            'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
            'email' => 'info@example.com',
            'address' => 'تهران، خیابان نمونه'
        ],
        'social' => [
            'instagram' => '',
            'telegram' => '',
            'whatsapp' => '',
            'youtube' => ''
        ],
        'faqs' => []
    ];
}

function extractSocialUsername($url, $platform) {
    if (empty($url)) return '';
    
    $patterns = [
        'instagram' => [
            '~https?://(www\.)?instagram\.com/([^/]+)~i',
            '~^@?([^/]+)$~i'
        ],
        'telegram' => [
            '~https?://(t\.me/|telegram\.me/)([^/]+)~i',
            '~^@?([^/]+)$~i'
        ],
        'whatsapp' => [
            '~https?://wa\.me/(\d+)~i',
            '~^(\d+)$~i'
        ],
        'youtube' => [
            '~https?://(www\.)?youtube\.com/(c/|channel/|user/)?([^/]+)~i',
            '~^([^/]+)$~i'
        ]
    ];
    
    foreach ($patterns[$platform] as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[count($matches) - 1];
        }
    }
    
    return $url;
}

$socialUsernames = array_map(
    fn($platform) => extractSocialUsername($footerData['social'][$platform] ?? '', $platform),
    ['instagram', 'telegram', 'whatsapp', 'youtube']
);
?>

<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-top">
            <div class="footer-row">
                
                <div class="footer-col about-col">
                    <h4 class="footer-title">
                        <i class="fas fa-store"></i>
                        درباره فروشگاه
                    </h4>
                    <div class="footer-logo">
                        <img src="/digital-shop/public/<?= htmlspecialchars($footerData['shop']['logo_url'] ?? '/digital-shop/image/logo.jpg') ?>" 
                             alt="<?= htmlspecialchars($footerData['shop']['shop_name'] ?? 'فروشگاه دیجیتال') ?>">
                    </div>
                    <p class="footer-about">
                        <?= htmlspecialchars($footerData['shop']['shop_description'] ?? 'فروشگاه اینترنتی محصولات دیجیتال') ?>
                    </p>
                    
                    <div class="trust-badges">
                        <?php
                        $trustBadges = [];
                        try {
                            $stmt = $pdo->query("SELECT badge_name, badge_icon FROM TrustBadges WHERE is_active = 1 ORDER BY display_order LIMIT 3");
                            $trustBadges = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            error_log("Error fetching trust badges: " . $e->getMessage());
                        }
                        
                        if (empty($trustBadges)) {
                            $trustBadges = [
                                ['badge_name' => 'ضمانت اصالت', 'badge_icon' => 'fa-shield-alt'],
                                ['badge_name' => 'تحویل سریع', 'badge_icon' => 'fa-truck'],
                                ['badge_name' => 'پشتیبانی 24/7', 'badge_icon' => 'fa-headset']
                            ];
                        }
                        
                        foreach ($trustBadges as $badge): ?>
                            <div class="trust-badge">
                                <i class="fas <?= htmlspecialchars($badge['badge_icon']) ?>"></i>
                                <span><?= htmlspecialchars($badge['badge_name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="footer-col links-col">
                    <h4 class="footer-title">
                        <i class="fas fa-link"></i>
                        لینک‌های مفید
                    </h4>
                    <div class="footer-links-grid">
                        
                        <div class="links-section">
                            <h5>دسته‌بندی‌ها</h5>
                            <ul class="footer-links">
                                <?php foreach ($footerData['popular_categories'] as $category): ?>
                                    <li>
                                        <a href="/digital-shop/public/products.php?category=<?= $category['id'] ?>">
                                            <i class="fas fa-chevron-left"></i>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <div class="links-section">
                            <h5>مقالات جدید</h5>
                            <ul class="footer-links">
                                <?php foreach ($footerData['articles'] as $article): ?>
                                    <li>
                                        <a href="/digital-shop/public/articles.php?id=<?= $article['id'] ?>">
                                            <i class="fas fa-chevron-left"></i>
                                            <?= htmlspecialchars($article['title']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <div class="links-section">
                            <h5>سوالات متداول</h5>
                            <ul class="footer-links">
                                <?php foreach ($footerData['faqs'] as $faq): ?>
                                    <li>
                                        <a href="/digital-shop/public/faq.php#faq-<?= $faq['id'] ?>">
                                            <i class="fas fa-chevron-left"></i>
                                            <?= htmlspecialchars($faq['question']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="footer-col contact-col">
                    <h4 class="footer-title">
                        <i class="fas fa-headset"></i>
                        راه‌های ارتباطی
                    </h4>
                    <ul class="contact-infoo">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= htmlspecialchars($footerData['contact']['address'] ?? 'تهران، خیابان نمونه') ?></span>
                        </li>
                        <li>
                            <i class="fas fa-phone"></i>
                            <a href="tel:<?= htmlspecialchars(str_replace([' ', '-', '(', ')'], '', $footerData['contact']['phone'] ?? '09123456789')) ?>">
                                <?= htmlspecialchars($footerData['contact']['phone'] ?? '۰۹۱۲۳۴۵۶۷۸۹') ?>
                            </a>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:<?= htmlspecialchars($footerData['contact']['email'] ?? 'info@example.com') ?>">
                                <?= htmlspecialchars($footerData['contact']['email'] ?? 'info@example.com') ?>
                            </a>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span>ساعات کاری: <?= htmlspecialchars($footerData['shop']['working_hours'] ?? 'هر روز از ۸ صبح تا ۱۲ شب') ?></span>
                        </li>
                    </ul>
                    
                    <div class="newsletter">
                        <h5>عضویت در خبرنامه</h5>
                        <form class="newsletter-form" id="newsletterForm">
                            <input type="email" name="email" placeholder="آدرس ایمیل شما" required>
                            <button type="submit">عضویت</button>
                        </form>
                        <p class="newsletter-note">
                            با عضویت در خبرنامه از جدیدترین تخفیف‌ها و محصولات مطلع شوید
                        </p>
                    </div>
                    
                    <div class="footer-social">
                        <?php foreach (['instagram', 'telegram', 'whatsapp', 'youtube'] as $platform): ?>
                            <?php if (!empty($footerData['social'][$platform])): ?>
                                <a href="<?= htmlspecialchars($footerData['social'][$platform]) ?>" 
                                   target="_blank" 
                                   class="social-icon <?= $platform ?>" 
                                   title="<?= ucfirst($platform) ?>">
                                    <i class="fab fa-<?= $platform ?>"></i>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-row">
                <div class="footer-links-bottom">
                    <?php
                    $footerPages = [];
                    try {
                        $stmt = $pdo->query("SELECT title, slug FROM Pages WHERE show_in_footer = 1 ORDER BY display_order");
                        $footerPages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        error_log("Error fetching footer pages: " . $e->getMessage());
                    }
                    
                    if (empty($footerPages)) {
                        $footerPages = [
                            ['title' => 'قوانین و مقررات', 'slug' => 'terms'],
                            ['title' => 'حریم خصوصی', 'slug' => 'privacy'],
                            ['title' => 'سیاست بازگرداندن کالا', 'slug' => 'return-policy']
                        ];
                    }
                    
                    foreach ($footerPages as $page): ?>
                        <a href="/digital-shop/public/page.php?slug=<?= $page['slug'] ?>">
                            <?= htmlspecialchars($page['title']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="copyright">
                    <p>
                        &copy; <?= date('Y') ?> تمامی حقوق برای 
                        <a href="/digital-shop/index.php"><?= htmlspecialchars($footerData['shop']['shop_name'] ?? 'فروشگاه دیجیتال') ?></a> 
                        محفوظ است.
                    </p>
                    <p>
                        طراحی و توسعه با <i class="fas fa-heart" style="color: #e74c3c;"></i> توسط Hoseiin_28
                    </p>
                </div>
            </div>
        </div>
    </div>
</footer>
<link rel="stylesheet" href="/digital-shop/assets/css/style-footer-index.css">