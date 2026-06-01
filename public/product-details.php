<?php
session_start();
require_once 'config.php';

$buttonColor = '#007bff';
$settingsQuery = "SELECT button_color, shop_name, logo_url FROM ShopSettings LIMIT 1";
$settingsResult = $conn->query($settingsQuery);
if ($settingsResult && $settingsResult->num_rows > 0) {
    $settings = $settingsResult->fetch_assoc();
    $buttonColor = $settings['button_color'] ?? '';
    $shopName = $settings['shop_name'] ?? '';
    $logoUrl = $settings['logo_url'] ?? '';
}

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header("Location: forbidden.php");
    exit;
}

$sql = "SELECT p.*, c.name AS category_name 
        FROM Products p
        JOIN Categories c ON p.category_id = c.id
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: 404.php");
    exit;
}

$product = $result->fetch_assoc();
$stmt->close();

$sql_review_count = "SELECT COUNT(*) AS review_count, AVG(rating) AS avg_rating FROM Reviews WHERE product_id = ?";
$stmt_review_count = $conn->prepare($sql_review_count);
$stmt_review_count->bind_param("i", $product_id);
$stmt_review_count->execute();
$result_review_count = $stmt_review_count->get_result();
$review_count_data = $result_review_count->fetch_assoc();
$product['review_count'] = $review_count_data['review_count'];
$product['avg_rating'] = $review_count_data['avg_rating'] ? floatval($review_count_data['avg_rating']) : 0;
$stmt_review_count->close();

$discount = null;
$sql_discount = "SELECT discount_type, discount_value, start_date, end_date 
                 FROM Discounts 
                 WHERE product_id = ? 
                 AND (start_date IS NULL OR start_date <= NOW()) 
                 AND (end_date IS NULL OR end_date >= NOW())";
$stmt_discount = $conn->prepare($sql_discount);
$stmt_discount->bind_param("i", $product_id);
$stmt_discount->execute();
$result_discount = $stmt_discount->get_result();

if ($result_discount->num_rows > 0) {
    $discount = $result_discount->fetch_assoc();
    if ($discount['discount_type'] === 'percentage') {
        $discountAmount = ($product['price'] * $discount['discount_value']) / 100;
        $product['discount_price'] = $product['price'] - $discountAmount;
    } elseif ($discount['discount_type'] === 'fixed') {
        $product['discount_price'] = max(0, $product['price'] - $discount['discount_value']);
    }
}
$stmt_discount->close();

$product_attributes = [];
$sql_attributes = "SELECT attribute_name, attribute_value 
                   FROM ProductAttributes 
                   WHERE product_id = ? 
                   ORDER BY id";
$stmt_attributes = $conn->prepare($sql_attributes);
$stmt_attributes->bind_param("i", $product_id);
$stmt_attributes->execute();
$result_attributes = $stmt_attributes->get_result();

while ($row = $result_attributes->fetch_assoc()) {
    $product_attributes[] = $row;
}
$stmt_attributes->close();

$product_images = [];
$sql_images = "SELECT image_url 
               FROM ProductImages 
               WHERE product_id = ? 
               ORDER BY id";
$stmt_images = $conn->prepare($sql_images);
$stmt_images->bind_param("i", $product_id);
$stmt_images->execute();
$result_images = $stmt_images->get_result();

while ($row = $result_images->fetch_assoc()) {
    $product_images[] = $row['image_url'];
}
$stmt_images->close();

$is_favorite = false;
if (isset($_SESSION['user_id'])) {
    $favoriteStmt = $conn->prepare("SELECT id FROM Favorites WHERE user_id = ? AND product_id = ?");
    $favoriteStmt->bind_param("ii", $_SESSION['user_id'], $product_id);
    $favoriteStmt->execute();
    $is_favorite = $favoriteStmt->get_result()->num_rows > 0;
    $favoriteStmt->close();
}

$reviews = [];
$avg_rating = 0;
$review_count = 0;

$sql_reviews = "SELECT r.*, u.name AS user_name, u.avatar AS user_avatar
                FROM Reviews r
                JOIN Users u ON r.user_id = u.id
                WHERE r.product_id = ? AND r.parent_id IS NULL
                ORDER BY r.created_at DESC";
$stmt_reviews = $conn->prepare($sql_reviews);
$stmt_reviews->bind_param("i", $product_id);
$stmt_reviews->execute();
$result_reviews = $stmt_reviews->get_result();

while ($row = $result_reviews->fetch_assoc()) {
    $sql_replies = "SELECT r.*, u.name AS user_name, u.avatar AS user_avatar
                    FROM Reviews r
                    JOIN Users u ON r.user_id = u.id
                    WHERE r.parent_id = ?
                    ORDER BY r.created_at ASC";
    $stmt_replies = $conn->prepare($sql_replies);
    $stmt_replies->bind_param("i", $row['id']);
    $stmt_replies->execute();
    $result_replies = $stmt_replies->get_result();
    $row['replies'] = $result_replies->fetch_all(MYSQLI_ASSOC);

    $reviews[] = $row;
    $avg_rating += $row['rating'];
    $review_count++;
}

if ($review_count > 0) {
    $avg_rating = $avg_rating / $review_count;
}
$stmt_reviews->close();

$related_products = [];
$sql_related = "SELECT p.id, p.name, p.price, p.image_url
                FROM Products p
                WHERE p.category_id = ? AND p.id != ? 
                ORDER BY p.created_at DESC 
                LIMIT 12";
$stmt_related = $conn->prepare($sql_related);
$stmt_related->bind_param("ii", $product['category_id'], $product['id']);
$stmt_related->execute();
$result_related = $stmt_related->get_result();

while ($row = $result_related->fetch_assoc()) {
    $related_products[] = $row;
}
$stmt_related->close();

$view_ip = $_SERVER['REMOTE_ADDR'];
$view_user_agent = $_SERVER['HTTP_USER_AGENT'];
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$today = date('Y-m-d');

$check_sql = "SELECT id FROM ProductViews 
              WHERE product_id = ? AND ip_address = ? AND view_date = ? 
              LIMIT 1";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("iss", $product_id, $view_ip, $today);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $sql_view = "INSERT INTO ProductViews (product_id, user_id, ip_address, user_agent, view_date, view_count) 
                 VALUES (?, ?, ?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE view_count = view_count + 1";
    $stmt_view = $conn->prepare($sql_view);
    $stmt_view->bind_param("iisss", $product_id, $user_id, $view_ip, $view_user_agent, $today);
    $stmt_view->execute();
    $stmt_view->close();
}
$check_stmt->close();

$sql_stats = "SELECT 
                SUM(view_count) AS total_views,
                COUNT(DISTINCT ip_address) AS unique_visitors,
                COUNT(DISTINCT CASE WHEN user_id IS NOT NULL THEN user_id END) AS registered_visitors,
                (SELECT SUM(view_count) FROM ProductViews WHERE product_id = ? AND view_date = CURDATE()) AS today_views,
                (SELECT COUNT(DISTINCT ip_address) FROM ProductViews WHERE product_id = ? AND view_date = CURDATE()) AS today_unique_visitors
              FROM ProductViews 
              WHERE product_id = ?";
$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("iii", $product_id, $product_id, $product_id);
$stmt_stats->execute();
$product_stats = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();


$favorite_message = '';
if (isset($_SESSION['user_id'])) {
    $favoriteStmt = $conn->prepare("SELECT id FROM Favorites WHERE user_id = ? AND product_id = ?");
    $favoriteStmt->bind_param("ii", $_SESSION['user_id'], $product_id);
    $favoriteStmt->execute();
    $is_favorite = $favoriteStmt->get_result()->num_rows > 0;
    $favoriteStmt->close();

    $favorite_message = $is_favorite ? 'این محصول در لیست علاقه‌مندی‌های شما موجود است' : '';
}


$conn->close();

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
    <title><?php echo htmlspecialchars($product['name']); ?> | <?php echo htmlspecialchars($shopName); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style-product-details.css">
    <?php if (!empty($logoUrl)): ?>
        <link rel="icon" href="<?php echo htmlspecialchars($logoUrl); ?>" type="image/png">
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?php echo $buttonColor; ?>;
            --primary-hover: <?php echo adjustBrightness($buttonColor, -20); ?>;
            --primary-light: <?php echo adjustBrightness($buttonColor, 90); ?>;
            --primary-transparent: <?php echo adjustBrightness($buttonColor, 100); ?>80;
            --shadow-color: <?php echo adjustBrightness($buttonColor, -40); ?>20;
        }
    </style>
</head>

<body class="product-page">
    <?php include 'header-index.php'; ?>

    <main class="container mb-5">
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="product-gallery position-relative">
                    <?php if (!empty($product['discount_price'])): ?>
                        <span class="product-discount-badge">تخفیف ویژه</span>
                    <?php endif; ?>


                    <div class="text-center">
                        <img id="mainImage" src="<?php echo htmlspecialchars($product['image_url']); ?>"
                            class="img-fluid main-image"
                            alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>

                    <div class="d-flex flex-wrap justify-content-center">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>"
                            class="thumbnail active"
                            onclick="changeMainImage(this, '<?php echo htmlspecialchars($product['image_url']); ?>')">

                        <?php foreach ($product_images as $image_url): ?>
                            <img src="<?php echo htmlspecialchars($image_url); ?>"
                                class="thumbnail"
                                onclick="changeMainImage(this, '<?php echo htmlspecialchars($image_url); ?>')">
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-list-ul me-2"></i>ویژگی‌های فنی</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap">
                            <?php foreach ($product_attributes as $attribute): ?>
                                <div class="attribute-badge">
                                    <strong><?php echo htmlspecialchars($attribute['attribute_name']); ?>:</strong>
                                    <?php echo htmlspecialchars($attribute['attribute_value']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="product-info-card">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h1 class="h3 mb-0"><?php echo htmlspecialchars($product['name']); ?></h1>
                        <button class="btn btn-sm btn-outline-secondary" id="addToWishlist">
                            <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                    </div>

                    <div class="d-flex align-items-center mb-3">
                        <div class="star-rating me-2">
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
                        </div>
                        <span class="text-muted">(<?php echo $product['review_count'] ?? 0; ?> نظر)</span>
                        <a href="#reviews" class="btn btn-sm btn-outline-primary ms-3">مشاهده نظرات</a>
                    </div>

                    <p class="text-muted mb-4">
                        <i class="fas fa-tag me-2"></i>
                        دسته‌بندی: <?php echo htmlspecialchars($product['category_name']); ?>
                    </p>

                    <div class="price-section mb-4">
                        <?php if (!empty($product['discount_price'])): ?>
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="current-price"><?php echo number_format($product['discount_price'], 0); ?> تومان</span>
                                    <span class="original-price ms-3"><?php echo number_format($product['price'], 0); ?> تومان</span>
                                </div>
                                <span class="badge bg-danger">
                                    <?php
                                    $discountPercent = round(100 - ($product['discount_price'] / $product['price'] * 100));
                                    echo $discountPercent . '%';
                                    ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="text-center">
                                <span class="current-price"><?php echo number_format($product['price'], 0); ?> تومان</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <h5 class="h6 mb-2">توضیحات کوتاه:</h5>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>

                    <div class="mb-4">
                        <?php if ($product['stock'] > 0): ?>
                            <p class="text-success">
                                <i class="fas fa-check-circle me-2"></i>
                                موجود در انبار (<?php echo $product['stock']; ?> عدد)
                            </p>
                        <?php else: ?>
                            <p class="text-danger">
                                <i class="fas fa-times-circle me-2"></i>
                                ناموجود
                            </p>
                        <?php endif; ?>
                    </div>

                    <form id="addToCartForm" class="mb-4">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

                        <div class="d-flex align-items-center mb-3">
                            <label for="quantity" class="me-2">تعداد:</label>
                            <select class="form-select quantity-selector" id="quantity" name="quantity" required>
                                <?php
                                $max_quantity = min($product['stock'], 10);
                                for ($i = 1; $i <= $max_quantity; $i++) {
                                    echo "<option value='$i'>$i</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 mb-3" id="addToCartBtn" <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>>
                            <i class="fas fa-shopping-cart me-2"></i>
                            <?php echo ($product['stock'] > 0) ? 'افزودن به سبد خرید' : 'ناموجود'; ?>
                        </button>

                        <?php if ($product['stock'] > 0): ?>
                            <button type="button" class="btn btn-outline-primary w-100 py-2" id="buyNowBtn">
                                <i class="fas fa-bolt me-2"></i> خرید سریع
                            </button>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-share-alt me-2"></i>اشتراک گذاری</h5>
                        <div class="d-flex gap-2">
                            <a href="whatsapp://send?text=<?php echo urlencode('محصول ' . $product['name'] . ' را ببینید: ' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>"
                                class="btn btn-success rounded-circle p-2" target="_blank">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                            <a href="https://t.me/share/url?url=<?php echo urlencode((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode('محصول ' . $product['name']); ?>"
                                class="btn btn-primary rounded-circle p-2" target="_blank">
                                <i class="fab fa-telegram"></i>
                            </a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>"
                                class="btn btn-dark rounded-circle p-2" target="_blank">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="mailto:?subject=<?php echo urlencode('محصول ' . $product['name']); ?>&body=<?php echo urlencode('محصول ' . $product['name'] . ' را ببینید: ' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>"
                                class="btn btn-secondary rounded-circle p-2">
                                <i class="fas fa-envelope"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card mt-5">
                    <div class="card-body text-center">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="stat-item">
                                    <i class="fas fa-eye fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo number_format($product_stats['today_views'] ?? 0); ?></h4>
                                    <p class="text-muted mb-0">بازدید امروز</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-item">
                                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                                    <h4 class="mb-1"><?php echo number_format($product_stats['total_views'] ?? 0); ?></h4>
                                    <p class="text-muted mb-0">بازدید کلی</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <ul class="nav nav-tabs" id="productTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab">توضیحات کامل</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="specs-tab" data-bs-toggle="tab" data-bs-target="#specs" type="button" role="tab">مشخصات فنی</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">نظرات (<?php echo $product['review_count'] ?? 0; ?>)</button>
                    </li>
                </ul>

                <div class="tab-content p-3 bg-white border border-top-0 rounded-bottom" id="productTabsContent">
                    <div class="tab-pane fade show active" id="description" role="tabpanel">
                        <h4 class="mb-4"><?php echo htmlspecialchars($product['name']); ?></h4>
                        <div class="product-description">
                            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="specs" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <tbody>
                                    <?php foreach ($product_attributes as $attribute): ?>
                                        <tr>
                                            <th width="30%"><?php echo htmlspecialchars($attribute['attribute_name']); ?></th>
                                            <td><?php echo htmlspecialchars($attribute['attribute_value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="reviews" role="tabpanel">
                        <div class="row">
                            <div class="col-md-4 mb-4 mb-md-0">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <h3 class="display-4 mb-3"><?php echo number_format($avg_rating, 1); ?></h3>
                                        <div class="star-rating mb-3">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= floor($avg_rating) ? '<i class="fas fa-star"></i>' : ($i == ceil($avg_rating) && ($avg_rating - floor($avg_rating)) >= 0.5 ? '<i class="fas fa-star-half-alt"></i>' :
                                                    '<i class="far fa-star"></i>');
                                            }
                                            ?>
                                        </div>
                                        <p class="text-muted">بر اساس <?php echo $product['review_count'] ?? 0; ?> نظر</p>

                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#reviewModal">
                                                <i class="fas fa-pen me-2"></i>ثبت نظر جدید
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-primary mt-3" onclick="showLoginAlert()">
                                                <i class="fas fa-pen me-2"></i>ثبت نظر جدید
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-8">
                                <?php if (!empty($reviews)): ?>
                                    <?php foreach ($reviews as $review): ?>
                                        <div class="review-card card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <img src="../<?php echo htmlspecialchars($review['user_avatar']); ?>"
                                                        alt="<?php echo htmlspecialchars($review['user_name']); ?>"
                                                        class="rounded-circle me-3" width="50" height="50">
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($review['user_name']); ?></h6>
                                                        <?php if (isset($review['rating'])): ?>
                                                            <div class="star-rating small">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <i class="<?php echo $i <= $review['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                                                                <?php endfor; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted ms-auto"><?php echo date('Y/m/d', strtotime($review['created_at'])); ?></small>
                                                </div>
                                                <p class="mb-3"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>

                                                <?php if (isset($_SESSION['user_id'])): ?>
                                                    <button class="btn btn-sm btn-outline-secondary mb-3 toggle-reply-form"
                                                        data-review-id="<?php echo $review['id']; ?>">
                                                        <i class="fas fa-reply me-1"></i> پاسخ دادن
                                                    </button>

                                                    <div class="reply-form mb-3" id="reply-form-<?php echo $review['id']; ?>" style="display: none;">
                                                        <form class="submit-reply-form" method="post" action="submit-reply.php">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                            <input type="hidden" name="parent_id" value="<?php echo $review['id']; ?>">
                                                            <div class="mb-3">
                                                                <textarea class="form-control" name="comment" rows="3" placeholder="پاسخ خود را بنویسید..." required></textarea>
                                                            </div>
                                                            <div class="d-flex justify-content-end gap-2">
                                                                <button type="button" class="btn btn-sm btn-secondary cancel-reply"
                                                                    data-review-id="<?php echo $review['id']; ?>">انصراف</button>
                                                                <button type="submit" class="btn btn-sm btn-primary">ارسال پاسخ</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($review['replies'])): ?>
                                                    <div class="replies-container ps-4 border-start border-2 border-light">
                                                        <?php foreach ($review['replies'] as $reply): ?>
                                                            <div class="reply-card card mb-2">
                                                                <div class="card-body py-2">
                                                                    <div class="d-flex align-items-center mb-2">
                                                                        <img src="../<?php echo (htmlspecialchars($reply['user_avatar'])); ?>"
                                                                            alt="<?php echo (htmlspecialchars($reply['user_name'])); ?>"
                                                                            class="rounded-circle me-2" width="40" height="40">
                                                                        <h6 class="mb-0 small"><?php echo (htmlspecialchars($reply['user_name'])); ?></h6>
                                                                        <small class="text-muted ms-auto"><?php echo date('Y/m/d', strtotime($reply['created_at'])); ?></small>
                                                                    </div>
                                                                    <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($reply['comment'])); ?></p>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if (count($reviews) > 3): ?>
                                        <div id="moreReviews" style="display: none;">
                                            <?php foreach (array_slice($reviews, 3) as $review): ?>
                                                <div class="review-card card mb-3">
                                                    <div class="card-body">
                                                        <div class="d-flex align-items-center mb-3">
                                                            <img src="../<?php echo htmlspecialchars($review['user_avatar']); ?>"
                                                                alt="<?php echo htmlspecialchars($review['user_name']); ?>"
                                                                class="rounded-circle me-3" width="50" height="50">
                                                            <div>
                                                                <h6 class="mb-0"><?php echo htmlspecialchars($review['user_name']); ?></h6>
                                                                <div class="star-rating small">
                                                                    <?php
                                                                    for ($i = 1; $i <= 5; $i++) {
                                                                        echo $i <= $review['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                            <small class="text-muted ms-auto"><?php echo date('Y/m/d', strtotime($review['created_at'])); ?></small>
                                                        </div>
                                                        <p class="mb-3"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>

                                                        <?php if (!empty($review['replies'])): ?>
                                                            <div class="replies-container ps-4 border-start border-2 border-light">
                                                                <?php foreach ($review['replies'] as $reply): ?>
                                                                    <div class="reply-card card mb-2">
                                                                        <div class="card-body py-2">
                                                                            <div class="d-flex align-items-center mb-2">
                                                                                <img src="../<?php echo htmlspecialchars($reply['user_avatar']); ?>"
                                                                                    alt="<?php echo htmlspecialchars($reply['user_name']); ?>"
                                                                                    class="rounded-circle me-2" width="40" height="40">
                                                                                <h6 class="mb-0 small"><?php echo htmlspecialchars($reply['user_name']); ?></h6>
                                                                                <small class="text-muted ms-auto"><?php echo date('Y/m/d', strtotime($reply['created_at'])); ?></small>
                                                                            </div>
                                                                            <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($reply['comment'])); ?></p>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="text-center mt-3">
                                            <button id="toggleReviewsBtn" class="btn btn-outline-primary">
                                                مشاهده همه نظرات
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                                        <h5>هنوز نظری برای این محصول ثبت نشده است</h5>
                                        <p class="text-muted">اولین نفری باشید که نظر می‌دهد</p>
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reviewModal">
                                                <i class="fas fa-pen me-2"></i>ثبت نظر
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-primary" onclick="showLoginAlert()">
                                                <i class="fas fa-pen me-2"></i>ثبت نظر
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section class="related-products mt-5">
            <h2 class="section-title mb-4">
                <span>محصولات مشابه</span>
            </h2>

            <div class="row">
                <?php if (count($related_products) > 0): ?>
                    <?php foreach ($related_products as $related): ?>
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="card h-100">
                                <a href="product-details.php?id=<?php echo $related['id']; ?>">
                                    <img src="<?php echo htmlspecialchars($related['image_url']); ?>"
                                        class="card-img-top"
                                        alt="<?php echo htmlspecialchars($related['name']); ?>">
                                </a>
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <a href="product-details.php?id=<?php echo $related['id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($related['name']); ?>
                                        </a>
                                    </h5>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <span class="text-dark fw-bold"><?php echo number_format($related['price'], 0); ?> تومان</span>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <a href="product-details.php?id=<?php echo $related['id']; ?>" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-eye me-2"></i>مشاهده
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center py-4">
                            <i class="fas fa-info-circle fa-2x mb-3"></i>
                            <h5>محصول مشابهی یافت نشد</h5>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title fw-bold" id="reviewModalLabel">
                        <i class="fas fa-comment-medical me-2"></i>
                        ثبت نظر برای <?php echo htmlspecialchars($product['name']); ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="reviewForm" action="submit-review.php" method="POST">
                    <div class="modal-body py-4">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

                        <div class="rating-section mb-4 p-3 bg-light rounded">
                            <label class="form-label d-block text-center mb-3 fw-bold">امتیاز شما به این محصول</label>
                            <div class="star-rating-input text-center">
                                <input type="hidden" name="rating" id="rating-value" required>
                                <div class="stars d-inline-flex justify-content-center">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="far fa-star fa-2x mx-1" data-rating="<?php echo $i; ?>"
                                            style="color: #ffc107; cursor: pointer; transition: all 0.2s ease;"></i>
                                    <?php endfor; ?>
                                </div>
                                <div class="rating-feedback mt-2 small text-muted" id="ratingFeedback">
                                    لطفاً امتیاز دهید
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="comment" class="form-label fw-bold">متن نظر شما</label>
                            <textarea class="form-control" id="comment" name="comment" rows="5"
                                placeholder="تجربه خود از استفاده این محصول را با دیگران به اشتراک بگذارید..."
                                required style="min-height: 120px;"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>انصراف
                        </button>
                        <button type="submit" class="btn btn-primary px-4" id="submitReviewBtn">
                            <i class="fas fa-paper-plane me-2"></i>ثبت نظر
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            function changeMainImage(element, newSrc) {
                const mainImg = document.getElementById('mainImage');

                mainImg.style.opacity = 0;

                setTimeout(() => {
                    mainImg.src = newSrc;
                    mainImg.style.opacity = 1;

                    document.querySelectorAll('.thumbnail').forEach(thumb => {
                        thumb.classList.remove('active');
                    });

                    element.classList.add('active');
                }, 200);
            }

            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.addEventListener('click', function() {
                    changeMainImage(this, this.src);
                });
            });

            function setupStarRating() {
                const stars = $('.star-rating-input .stars i');
                const ratingFeedback = $('#ratingFeedback');
                const ratingMessages = [
                    "خیلی بد",
                    "بد",
                    "متوسط",
                    "خوب",
                    "عالی"
                ];

                stars.on('mouseover', function() {
                    const rating = $(this).data('rating');
                    stars.removeClass('fas hover-effect').addClass('far');

                    stars.each(function(index) {
                        if (index < rating) {
                            $(this).removeClass('far').addClass('fas hover-effect');
                        }
                    });

                    ratingFeedback.text(ratingMessages[rating - 1])
                        .css('color', getRatingColor(rating));
                });

                stars.on('mouseout', function() {
                    const currentRating = $('#rating-value').val();
                    if (!currentRating) {
                        stars.removeClass('fas hover-effect').addClass('far');
                        ratingFeedback.text("لطفاً امتیاز دهید")
                            .css('color', '#6c757d');
                    }
                });

                stars.on('click', function() {
                    const rating = $(this).data('rating');
                    $('#rating-value').val(rating);

                    stars.removeClass('fas active').addClass('far');
                    stars.each(function(index) {
                        if (index < rating) {
                            $(this).removeClass('far').addClass('fas active');
                        }
                    });

                    ratingFeedback.text(`امتیاز شما: ${ratingMessages[rating - 1]}`)
                        .css('color', getRatingColor(rating));
                });

                function getRatingColor(rating) {
                    const colors = ["#ff4757", "#ff6b81", "#ffa502", "#2ed573", "#1e90ff"];
                    return colors[rating - 1];
                }
            }

            function showCustomToast(message, type = 'success') {
                if (type === 'comment') return;

                const toast = document.createElement('div');
                toast.className = `custom-toast ${type}`;
                toast.innerHTML = `
                <div class="toast-message">${message}</div>
                <div class="toast-progress"></div>
            `;

                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.classList.add('show');
                }, 10);

                const progress = toast.querySelector('.toast-progress');
                progress.style.width = '100%';
                progress.style.transition = 'width 3s linear';

                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        document.body.removeChild(toast);
                    }, 300);
                }, 3000);
            }

            $('#addToCartForm').on('submit', function(e) {
                e.preventDefault();
                const submitBtn = $('#addToCartBtn');
                const originalText = submitBtn.html();

                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>در حال افزودن...');

                $.ajax({
                        url: 'add-to-cart.php',
                        type: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json'
                    })
                    .done(function(response) {
                        if (response.success) {
                            if (response.cart_count > 0) {
                                $('#cart-count').text(response.cart_count).removeClass('d-none');
                                animateCartIcon();
                            }

                            showCustomToast('محصول با موفقیت به سبد خرید اضافه شد', 'success');

                            if ($(this).data('quick-buy')) {
                                setTimeout(() => {
                                    window.location.href = 'checkout.php';
                                }, 1000);
                            }
                        } else {
                            showCustomToast(response.message || 'خطا در افزودن به سبد خرید', 'error');
                        }
                    }.bind(this))
                    .fail(function() {
                        showCustomToast('خطا در ارتباط با سرور', 'error');
                    })
                    .always(function() {
                        submitBtn.html(originalText).prop('disabled', false);
                    });
            });

            $('#buyNowBtn').on('click', function() {
                $('#addToCartForm').data('quick-buy', true).submit();
            });

            $('#addToWishlist').on('click', function() {
                const icon = $(this).find('i');
                const isFavorite = icon.hasClass('fas');
                const productId = <?php echo $product['id']; ?>;
                const action = isFavorite ? 'remove' : 'add';

                $.ajax({
                        url: 'toggle_favorite.php',
                        type: 'POST',
                        data: {
                            product_id: productId,
                            action: action
                        },
                        dataType: 'json'
                    })
                    .done(function(response) {
                        if (response.success) {
                            icon.toggleClass('far fas');
                            const message = isFavorite ?
                                'محصول از لیست علاقه‌مندی‌های شما حذف شد' :
                                'محصول به لیست علاقه‌مندی‌های شما اضافه شد';

                            showCustomToast(message, 'success');

                            if (response.favorite_count !== undefined) {
                                $('#favorite-count').text(response.favorite_count);
                            }
                        } else {
                            showCustomToast(response.message || 'خطایی رخ داده است', 'error');
                        }
                    })
                    .fail(function() {
                        showCustomToast('خطا در ارتباط با سرور', 'error');
                    });
            });

            $('#toggleReviewsBtn').on('click', function() {
                const btn = $(this);
                $('#moreReviews').slideToggle(function() {
                    btn.text(function() {
                        return $(this).is(':visible') ? 'مشاهده نظرات کمتر' : 'مشاهده همه نظرات';
                    });
                });
            });

            function showLoginAlert() {
                Swal.fire({
                    icon: 'info',
                    title: 'ورود به سیستم',
                    text: 'برای ثبت نظر باید وارد حساب کاربری خود شوید',
                    showCancelButton: true,
                    confirmButtonText: 'ورود',
                    cancelButtonText: 'انصراف',
                    confirmButtonColor: 'var(--primary-color)',
                    cancelButtonColor: '#6c757d',
                    customClass: {
                        popup: 'login-alert-popup'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'login.php?return_to=' + encodeURIComponent(window.location.href);
                    }
                });
            }

            $(document)
                .on('click', '.toggle-reply-form', function() {
                    const reviewId = $(this).data('review-id');
                    $(`#reply-form-${reviewId}`).slideToggle();
                })
                .on('click', '.cancel-reply', function() {
                    const reviewId = $(this).data('review-id');
                    $(`#reply-form-${reviewId}`).slideUp();
                })
                .on('submit', '.submit-reply-form', function(e) {
                    e.preventDefault();
                    const form = $(this);
                    const submitBtn = form.find('button[type="submit"]');
                    const originalText = submitBtn.html();

                    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>در حال ارسال...');

                    $.ajax({
                            url: form.attr('action'),
                            type: 'POST',
                            data: form.serialize(),
                            dataType: 'json'
                        })
                        .done(function(response) {
                            if (response.success) {
                                setTimeout(() => {
                                    location.reload();
                                }, 500);
                            } else {
                                showCustomToast(response.message || 'خطا در ثبت پاسخ', 'error');
                            }
                        })
                        .fail(function() {
                            showCustomToast('خطا در ارتباط با سرور', 'error');
                        })
                        .always(function() {
                            submitBtn.html(originalText).prop('disabled', false);
                        });
                });

            (function() {
                'use strict';
                $('.needs-validation').on('submit', function(e) {
                    if (!this.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    $(this).addClass('was-validated');
                });
            })();

            function animateCartIcon() {
                $('#cart-count').addClass('animate__animated animate__bounceIn');
                setTimeout(() => {
                    $('#cart-count').removeClass('animate__animated animate__bounceIn');
                }, 1000);
            }

            setupStarRating();

            <?php if (!empty($favorite_message)): ?>
                $(document).ready(function() {
                    setTimeout(() => {
                        showCustomToast('<?php echo $favorite_message; ?>', 'success');
                    }, 1000);
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>