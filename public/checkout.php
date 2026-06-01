<?php
session_start();
require_once 'config.php';

$shop_settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $shop_settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $shop_settings = [
        'shop_name' => 'فروشگاه دیجیتال',
        'button_color' => '#4e73df',
        'font_family' => 'Vazir, Arial, sans-serif',
        'logo_url' => ''
    ];
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=checkout");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$pending_order = null;
$order_items = [];
$total_discount = 0;

try {
    $db = $pdo;
    $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = :user_id AND payment_status = 'unpaid' ORDER BY created_at DESC LIMIT 1");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $pending_order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pending_order) {
        header("Location: ../index.php");
        exit();
    }

    $stmt = $db->prepare("
        SELECT od.*, p.name as product_name, p.image_url, 
               (SELECT discount_value FROM discounts WHERE product_id = od.product_id 
                AND (start_date IS NULL OR start_date <= NOW()) 
                AND (end_date IS NULL OR end_date >= NOW())
                LIMIT 1) as discount_value,
               (SELECT discount_type FROM discounts WHERE product_id = od.product_id 
                AND (start_date IS NULL OR start_date <= NOW()) 
                AND (end_date IS NULL OR end_date >= NOW())
                LIMIT 1) as discount_type
        FROM orderdetails od 
        JOIN products p ON od.product_id = p.id 
        WHERE od.order_id = :order_id
    ");
    $stmt->bindParam(':order_id', $pending_order['id']);
    $stmt->execute();
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($order_items) === 0) {
        header("Location: index.php");
        exit();
    }

    $original_order_total = 0;
    foreach ($order_items as &$item) {
        $item['original_price'] = $item['price'];
        $item['discount_amount'] = 0;

        if ($item['discount_value'] && $item['discount_type']) {
            if ($item['discount_type'] == 'percentage') {
                $item['discount_amount'] = ($item['price'] * $item['discount_value']) / 100;
            } else {
                $item['discount_amount'] = $item['discount_value'];
            }

            $item['price'] = $item['price'] - $item['discount_amount'];
            $item['subtotal'] = $item['price'] * $item['quantity'];
            $total_discount += $item['discount_amount'] * $item['quantity'];
        }
        $original_order_total += $item['original_price'] * $item['quantity'];
    }
    unset($item);

    if ($total_discount > 0 && $pending_order['total_price'] == $original_order_total) {
        $new_total = $original_order_total - $total_discount;
        $stmt = $db->prepare("UPDATE orders SET total_price = :total_price WHERE id = :order_id");
        $stmt->bindParam(':total_price', $new_total);
        $stmt->bindParam(':order_id', $pending_order['id']);
        $stmt->execute();
        $pending_order['total_price'] = $new_total;
    }
} catch (PDOException $e) {
    $error = "خطا در ارتباط با پایگاه داده: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_now'])) {
    try {
        $db->beginTransaction();

        $payment_method = $_POST['payment_method'] ?? 'cash_on_delivery';
        
        if ($payment_method === 'online') {
            $payment_status = 'paid';
            $order_status = 'processing';
        } else {
            $payment_status = 'unpaid';
            $order_status = 'pending';
        }

        $stmt = $db->prepare("UPDATE orders SET payment_status = :payment_status, status = :order_status WHERE id = :order_id AND user_id = :user_id");
        $stmt->bindParam(':payment_status', $payment_status);
        $stmt->bindParam(':order_status', $order_status);
        $stmt->bindParam(':order_id', $pending_order['id']);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        $payment_status_for_record = ($payment_method === 'online') ? 'successful' : 'pending';
        $stmt = $db->prepare("
            INSERT INTO payments (order_id, user_id, amount, payment_method, status) 
            VALUES (:order_id, :user_id, :amount, :payment_method, :status)
        ");
        $stmt->bindParam(':order_id', $pending_order['id']);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':amount', $pending_order['total_price']);
        $stmt->bindParam(':payment_method', $payment_method);
        $stmt->bindParam(':status', $payment_status_for_record);
        $stmt->execute();

        foreach ($order_items as $item) {
            $stmt = $db->prepare("UPDATE products SET stock = stock - :quantity WHERE id = :product_id");
            $stmt->bindParam(':quantity', $item['quantity']);
            $stmt->bindParam(':product_id', $item['product_id']);
            $stmt->execute();
        }

        $db->commit();

        header("Location: order_success.php?order_id=" . $pending_order['id'] . "&payment_method=" . $payment_method);
        exit();
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "خطا در پردازش پرداخت: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تکمیل فرآیند پرداخت | <?php echo htmlspecialchars($shop_settings['shop_name'] ?? 'فروشگاه دیجیتال'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style-checkout.css">
    <style>
        :root {
            --primary-color: <?php echo $shop_settings['button_color'] ?? '#4e73df'; ?>;
            --primary-hover: <?php echo isset($shop_settings['button_color']) ? adjustBrightness($shop_settings['button_color'], -20) : '#2e59d9'; ?>;
            --font-family: <?php echo $shop_settings['font_family'] ?? 'Vazirmatn, Vazir, Arial, sans-serif'; ?>;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }
    </style>
</head>

<body>

    <div class="checkout-container animate__animated animate__fadeIn">
        <?php if (isset($shop_settings['logo_url']) && !empty($shop_settings['logo_url'])): ?>
            <div class="text-center">
                <img src="../<?php echo htmlspecialchars($shop_settings['logo_url']); ?>" alt="<?php echo htmlspecialchars($shop_settings['shop_name']); ?>" class="header-logo animate__animated animate__fadeInDown">
            </div>
        <?php endif; ?>

        <div class="progress-steps animate__animated animate__fadeIn">
            <div class="step completed">
                <div class="step-number">1</div>
                <div class="step-title">سبد خرید</div>
            </div>
            <div class="step completed">
                <div class="step-number">2</div>
                <div class="step-title">اطلاعات ارسال</div>
            </div>
            <div class="step active">
                <div class="step-number">3</div>
                <div class="step-title">پرداخت</div>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <div class="step-title">تکمیل سفارش</div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__shakeX" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($pending_order): ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4 border-0 shadow-sm animate__animated animate__fadeInLeft">
                        <div class="card-header">
                            <h5 class="mb-0">روش پرداخت</h5>
                        </div>
                        <div class="card-body">
                            <form id="payment-form" method="post">
                                <div class="payment-methods-container">
                                    <div class="bank-card selected" onclick="selectPaymentMethod('online')">
                                        <div class="d-flex align-items-center">
                                            <img src="../image/banks/online.png" alt="بانک سامان" class="card-logo">
                                            <div>
                                                <h6 class="card-title">درگاه بانک سامان</h6>
                                                <p class="card-desc">پرداخت امن با کارت‌های بانکی</p>
                                            </div>
                                        </div>
                                        <input type="radio" name="payment_method" id="online-payment" value="online" checked style="display: none;">
                                    </div>

                                    <div class="bank-card" onclick="selectPaymentMethod('cash')">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-wallet2 text-muted" style="font-size: 2rem; margin-left: 10px;"></i>
                                            <div>
                                                <h6 class="card-title">پرداخت در محل</h6>
                                                <p class="card-desc">پرداخت در زمان تحویل کالا</p>
                                            </div>
                                        </div>
                                        <input type="radio" name="payment_method" id="cash-payment" value="cash_on_delivery" style="display: none;">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm animate__animated animate__fadeInLeft animate__delay-02s">
                        <div class="card-header">
                            <h5 class="mb-0">اطلاعات تحویل</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
                            $stmt->bindParam(':user_id', $user_id);
                            $stmt->execute();
                            $user = $stmt->fetch(PDO::FETCH_ASSOC);
                            ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">نام و نام خانوادگی</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">شماره تماس</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" readonly>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">آدرس</label>
                                    <textarea class="form-control" rows="3" readonly><?php echo htmlspecialchars($user['address']); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm sticky-top animate__animated animate__fadeInRight" style="top: 20px;">
                        <div class="card-header">
                            <h5 class="mb-0">خلاصه سفارش</h5>
                        </div>
                        <div class="card-body">
                            <div class="order-summary mb-4">
                                <h6 class="mb-3 fw-bold">محصولات</h6>
                                <?php foreach ($order_items as $item): ?>
                                    <div class="product-item">
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="product-img">
                                        <div class="product-info flex-grow-1">
                                            <h6 class="mb-1 fw-semibold"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <?php if ($item['discount_amount'] > 0): ?>
                                                        <span class="original-price"><?php echo number_format($item['original_price']); ?> تومان</span>
                                                        <span class="text-danger fw-semibold"><?php echo number_format($item['price']); ?> تومان</span>
                                                        <span class="discount-badge"><?php echo $item['discount_type'] == 'percentage' ? $item['discount_value'] . '%' : number_format($item['discount_value']) . 'تومان'; ?></span>
                                                    <?php else: ?>
                                                        <span class="fw-semibold"><?php echo number_format($item['price']); ?> تومان</span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="text-muted">× <?php echo $item['quantity']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <hr class="my-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>جمع کل:</span>
                                    <span><?php echo number_format($pending_order['total_price'] + $total_discount); ?> تومان</span>
                                </div>
                                <?php if ($total_discount > 0): ?>
                                    <div class="d-flex justify-content-between mb-2 text-success">
                                        <span>تخفیف محصولات:</span>
                                        <span>- <?php echo number_format($total_discount); ?> تومان</span>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>هزینه ارسال:</span>
                                    <span>رایگان</span>
                                </div>
                                <hr class="my-3">
                                <div class="d-flex justify-content-between fw-bold fs-5 mt-3">
                                    <span>مبلغ قابل پرداخت:</span>
                                    <span class="total-price"><?php echo number_format($pending_order['total_price']); ?> تومان</span>
                                </div>
                            </div>

                            <button type="submit" form="payment-form" name="pay_now" class="btn btn-pay animate__animated animate__pulse animate__infinite">
                                <i class="bi bi-credit-card-fill me-2"></i> پرداخت و تکمیل سفارش
                            </button>

                            <div class="security-badge animate__animated animate__fadeIn animate__delay-04s">
                                <i class="bi bi-shield-lock"></i>
                                <span>پرداخت ۱۰۰% ایمن و مطمئن</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script-checkout.js"></script>
</body>

</html>

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