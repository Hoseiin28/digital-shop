<?php
session_start();
require_once 'config.php';

$settings = [];
$about_us = [];
$team_members = [];

try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch();

    $stmt = $pdo->query("SELECT * FROM AboutUs LIMIT 1");
    $about_us = $stmt->fetch();

    if ($about_us) {
        $stmt = $pdo->prepare("SELECT * FROM TeamMembers WHERE about_us_id = ? ORDER BY id ASC");
        $stmt->execute([$about_us['id']]);
        $team_members = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error = "خطا در دریافت اطلاعات: " . $e->getMessage();
}

$page_title = "درباره ما";
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>درباره ما | <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style-about.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?= $settings['button_color'] ?? '#4e73df' ?>;
            --instagram-gradient: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
            --linkedin-color: #0077b5;
            --telegram-color: #0088cc;
            --whatsapp-color: #25d366;
            --email-color: #d44638;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
        }
    </style>
</head>
<body>
<?php include 'header-index.php'; ?>
    <?php if ($about_us): ?>
    <section class="py-5">
        <div class="container">
            <div class="about-content animate__animated animate__fadeIn">
                <div class="about-text col-lg-6">
                    <h2 class="animate__animated animate__fadeInDown">داستان ما</h2>
                    <div class="animate__animated animate__fadeIn animate__delay-1s">
                        <?= htmlspecialchars_decode($about_us['description']) ?>
                    </div>
                    
                    <?php if ($about_us['established_date']): ?>
                    <div class="established-date animate__animated animate__fadeInUp animate__delay-2s">
                        <i class="fas fa-calendar-alt"></i>
                        تأسیس در: <?= date('Y/m/d', strtotime($about_us['established_date'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="about-image col-lg-6 animate__animated animate__fadeInRight animate__delay-1s">
                    <img src="../image/new/image-12.jpeg" alt="تیم در حال کار">
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($team_members)): ?>
    <section class="py-5 bg-light">
        <div class="container">
            <div class="sectionn-title animate__animated animate__fadeIn">
                <h2>اعضای تیم ما</h2>
                <p>متخصصان خلاق و با تجربه که با Passion در خدمت شما هستند</p>
            </div>
            <div class="row">
                <?php foreach ($team_members as $index => $member): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="team-card animate__animated animate__fadeInUp animate__delay-<?= ($index % 3) * 0.2 ?>s">
                        <div class="team-img">
                            <?php if (!empty($member['avatar'])): ?>
                                <img src="<?= htmlspecialchars($member['avatar']) ?>" alt="<?= htmlspecialchars($member['name']) ?>">
                            <?php else: ?>
                                <div class="no-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="team-info">
                            <h3><?= htmlspecialchars($member['name']) ?></h3>
                            <p class="position"><?= htmlspecialchars($member['position']) ?></p>
                            <p class="bio"><?= htmlspecialchars($member['bio']) ?></p>
                            
                            <ul class="social-linkks">
                                <?php if (!empty($member['instagram_link'])): ?>
                                <li>
                                    <a href="<?= htmlspecialchars($member['instagram_link']) ?>" target="_blank" class="social-linkk instagram">
                                        <i class="fab fa-instagram"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($member['linkedin_link'])): ?>
                                <li>
                                    <a href="<?= htmlspecialchars($member['linkedin_link']) ?>" target="_blank" class="social-linkk linkedin">
                                        <i class="fab fa-linkedin-in"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($member['telegram_link'])): ?>
                                <li>
                                    <a href="<?= htmlspecialchars($member['telegram_link']) ?>" target="_blank" class="social-linkk telegram">
                                        <i class="fab fa-telegram-plane"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($member['contact_email'])): ?>
                                <li>
                                    <a href="mailto:<?= htmlspecialchars($member['contact_email']) ?>" target="_blank" class="social-linkk email">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($about_us && ($about_us['mission'] || $about_us['vision'])): ?>
    <section class="py-5">
        <div class="container">
            <div class="sectionn-title animate__animated animate__fadeIn">
                <h2>ارزش‌ها و اهداف ما</h2>
                <p>اصولی که به آن‌ها پایبند هستیم و برای آن‌ها تلاش می‌کنیم</p>
            </div>
            <div class="row">
                <?php if ($about_us['mission']): ?>
                <div class="col-md-6 mb-4">
                    <div class="value-card animate__animated animate__fadeInLeft">
                        <div class="value-icon">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <h3>ماموریت ما</h3>
                        <p><?= htmlspecialchars($about_us['mission']) ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($about_us['vision']): ?>
                <div class="col-md-6 mb-4">
                    <div class="value-card animate__animated animate__fadeInRight">
                        <div class="value-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h3>چشم انداز ما</h3>
                        <p><?= htmlspecialchars($about_us['vision']) ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php include 'footer-index.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.animate__animated').waypoint(function() {
                $(this.element).addClass('animate__fadeInUp');
            }, {
                offset: '80%'
            });
        });
    </script>
</body>
</html>