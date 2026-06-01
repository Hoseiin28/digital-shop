<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit();
}

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطا در دریافت تنظیمات فروشگاه: " . $e->getMessage());
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$total_sales_query = "SELECT 
    SUM(total_price) as total_sales,
    COUNT(*) as total_orders,
    AVG(total_price) as avg_order_value
FROM Orders
WHERE created_at BETWEEN ? AND ?
AND status = 'completed'";
$stmt = $pdo->prepare($total_sales_query);
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$sales_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$payment_stats_query = "SELECT 
    payment_method,
    COUNT(*) as count,
    SUM(amount) as total_amount
FROM Payments
WHERE payment_date BETWEEN ? AND ?
AND status = 'successful'
GROUP BY payment_method";
$stmt = $pdo->prepare($payment_stats_query);
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$payment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$top_products_query = "SELECT 
    p.name as product_name,
    SUM(od.quantity) as total_quantity,
    SUM(od.subtotal) as total_revenue
FROM OrderDetails od
JOIN Products p ON od.product_id = p.id
JOIN Orders o ON od.order_id = o.id
WHERE o.created_at BETWEEN ? AND ?
AND o.status = 'completed'
GROUP BY od.product_id
ORDER BY total_revenue DESC
LIMIT 10";
$stmt = $pdo->prepare($top_products_query);
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$daily_sales_query = "SELECT 
    DATE(created_at) as date,
    SUM(total_price) as daily_sales,
    COUNT(*) as daily_orders
FROM Orders
WHERE created_at BETWEEN ? AND ?
AND status = 'completed'
GROUP BY DATE(created_at)
ORDER BY DATE(created_at)";
$stmt = $pdo->prepare($daily_sales_query);
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_payments = array_sum(array_column($payment_stats, 'total_amount'));

$total_revenue = array_sum(array_column($top_products, 'total_revenue'));

require_once('../tcpdf/tcpdf.php');

class MYPDF extends TCPDF {

    public function Header() {

        $this->SetFont('dejavusans', 'B', 16);

        $this->Cell(0, 15, 'گزارش مالی فروشگاه', 0, false, 'C', 0, '', 0, false, 'M', 'M');

        $this->Ln(10);
    }

    public function Footer() {

        $this->SetY(-15);
 
        $this->SetFont('dejavusans', 'I', 8);

        $this->Cell(0, 10, 'صفحه '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('Digital Shop');
$pdf->SetAuthor($settings['shop_name'] ?? 'فروشگاه دیجیتال');
$pdf->SetTitle('گزارش مالی فروشگاه');
$pdf->SetSubject('گزارش مالی');
$pdf->SetKeywords('گزارش, مالی, فروشگاه');

$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(TRUE, 25);

$pdf->AddPage();

$pdf->SetFont('dejavusans', '', 10);

$primary_color = hex2rgb($settings['button_color'] ?? '#4e73df');
$secondary_color = hex2rgb('#f8f9fc');
$success_color = hex2rgb('#1cc88a');
$info_color = hex2rgb('#36b9cc');
$warning_color = hex2rgb('#f6c23e');
$danger_color = hex2rgb('#e74a3b');

function hex2rgb($hex) {
    $hex = str_replace('#', '', $hex);
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex,0,1).substr($hex,0,1));
        $g = hexdec(substr($hex,1,1).substr($hex,1,1));
        $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    return array($r, $g, $b);
}

$pdf->SetFillColorArray($primary_color);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('dejavusans', 'B', 18);
$pdf->Cell(0, 15, $settings['shop_name'] ?? 'فروشگاه دیجیتال', 0, 1, 'C', 1);
$pdf->SetFont('dejavusans', '', 12);
$pdf->Cell(0, 10, 'گزارش مالی فروشگاه', 0, 1, 'C', 1);
$pdf->Ln(10);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('dejavusans', 'B', 14);
$pdf->Cell(0, 10, 'گزارش مالی دوره ' . $start_date . ' تا ' . $end_date, 0, 1, 'R');
$pdf->SetFont('dejavusans', '', 10);
$pdf->Cell(0, 10, 'تاریخ تولید گزارش: ' . date('Y/m/d H:i'), 0, 1, 'R');
$pdf->Ln(10);

$pdf->SetFont('dejavusans', 'B', 12);
$pdf->Cell(0, 10, 'آمار کلی فروش', 0, 1, 'R');
$pdf->SetFont('dejavusans', '', 10);

$pdf->SetFillColorArray($secondary_color);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetDrawColor(200, 200, 200);

$pdf->Cell(60, 10, 'فروش کل', 1, 0, 'C', 1);
$pdf->Cell(60, 10, 'تعداد سفارشات', 1, 0, 'C', 1);
$pdf->Cell(60, 10, 'میانگین ارزش سفارش', 1, 1, 'C', 1);

$pdf->SetFont('dejavusans', 'B', 14);
$pdf->Cell(60, 15, number_format($sales_stats['total_sales'] ?? 0) . ' تومان', 1, 0, 'C');
$pdf->Cell(60, 15, number_format($sales_stats['total_orders'] ?? 0), 1, 0, 'C');
$pdf->Cell(60, 15, number_format($sales_stats['avg_order_value'] ?? 0) . ' تومان', 1, 1, 'C');
$pdf->Ln(15);

$pdf->SetFont('dejavusans', 'B', 12);
$pdf->Cell(0, 10, 'روش‌های پرداخت', 0, 1, 'R');
$pdf->SetFont('dejavusans', '', 10);

$pdf->SetFillColorArray($primary_color);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(70, 10, 'روش پرداخت', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'تعداد پرداخت‌ها', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'مبلغ کل', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'درصد از کل', 1, 1, 'C', 1);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);
$fill = false;

foreach ($payment_stats as $payment) {
    $percentage = $total_payments > 0 ? round(($payment['total_amount'] / $total_payments) * 100, 2) : 0;
    
    $pdf->Cell(70, 10, $payment['payment_method'] == 'online' ? 'آنلاین' : 'پرداخت در محل', 1, 0, 'R', $fill);
    $pdf->Cell(40, 10, number_format($payment['count']), 1, 0, 'C', $fill);
    $pdf->Cell(40, 10, number_format($payment['total_amount']) . ' تومان', 1, 0, 'L', $fill);
    
    $pdf->Cell(40, 10, $percentage . '%', 1, 1, 'C', $fill);
    
    $pdf->SetFillColorArray($info_color);
    $pdf->Rect(15 + 150, $pdf->GetY() - 10, 40 * ($percentage / 100), 10, 'F');
    $pdf->SetFillColor(255, 255, 255);
    
    $fill = !$fill;
}

if (empty($payment_stats)) {
    $pdf->Cell(190, 10, 'هیچ پرداختی در این بازه زمانی ثبت نشده است', 1, 1, 'C');
}
$pdf->Ln(10);

$pdf->SetFont('dejavusans', 'B', 12);
$pdf->Cell(0, 10, 'پرفروش‌ترین محصولات', 0, 1, 'R');
$pdf->SetFont('dejavusans', '', 10);

$pdf->SetFillColorArray($primary_color);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(90, 10, 'محصول', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'تعداد فروش', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'درآمد کل', 1, 0, 'C', 1);
$pdf->Cell(20, 10, 'درصد', 1, 1, 'C', 1);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);
$fill = false;

foreach ($top_products as $product) {
    $percentage = $total_revenue > 0 ? round(($product['total_revenue'] / $total_revenue) * 100, 2) : 0;
    
    $pdf->Cell(90, 10, $product['product_name'], 1, 0, 'R', $fill);
    $pdf->Cell(40, 10, number_format($product['total_quantity']), 1, 0, 'C', $fill);
    $pdf->Cell(40, 10, number_format($product['total_revenue']) . ' تومان', 1, 0, 'L', $fill);
    $pdf->Cell(20, 10, $percentage . '%', 1, 1, 'C', $fill);
    
    $fill = !$fill;
}

if (empty($top_products)) {
    $pdf->Cell(190, 10, 'هیچ فروشی در این بازه زمانی ثبت نشده است', 1, 1, 'C');
}
$pdf->Ln(10);

$pdf->SetFont('dejavusans', 'B', 12);
$pdf->Cell(0, 10, 'فروش روزانه', 0, 1, 'R');
$pdf->SetFont('dejavusans', '', 10);

$pdf->SetFillColorArray($primary_color);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(60, 10, 'تاریخ', 1, 0, 'C', 1);
$pdf->Cell(65, 10, 'فروش روز (تومان)', 1, 0, 'C', 1);
$pdf->Cell(65, 10, 'تعداد سفارشات', 1, 1, 'C', 1);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);
$fill = false;

foreach ($daily_sales as $day) {
    $pdf->Cell(60, 10, $day['date'], 1, 0, 'R', $fill);
    $pdf->Cell(65, 10, number_format($day['daily_sales']), 1, 0, 'L', $fill);
    $pdf->Cell(65, 10, number_format($day['daily_orders']), 1, 1, 'C', $fill);
    
    $fill = !$fill;
}

if (empty($daily_sales)) {
    $pdf->Cell(190, 10, 'هیچ فروشی در این بازه زمانی ثبت نشده است', 1, 1, 'C');
}
$pdf->Ln(15);

$pdf->SetFont('dejavusans', 'B', 12);
$pdf->Cell(0, 10, 'خلاصه گزارش', 0, 1, 'R');
$pdf->SetFont('dejavusans', '', 10);

$summary = "این گزارش به صورت خودکار توسط سیستم مدیریت فروشگاه " . ($settings['shop_name'] ?? 'فروشگاه دیجیتال') . " تولید شده است.\n";
$summary .= "بازه زمانی گزارش: " . $start_date . " تا " . $end_date . "\n";
$summary .= "تعداد کل سفارشات: " . number_format($sales_stats['total_orders'] ?? 0) . "\n";
$summary .= "فروش کل: " . number_format($sales_stats['total_sales'] ?? 0) . " تومان\n";
$summary .= "میانگین ارزش هر سفارش: " . number_format($sales_stats['avg_order_value'] ?? 0) . " تومان\n";
$summary .= "تاریخ تولید گزارش: " . date('Y/m/d H:i');

$pdf->MultiCell(0, 10, $summary, 0, 'R');
$pdf->Ln(10);

$pdf->SetFont('dejavusans', 'I', 8);
$pdf->Cell(0, 10, '© ' . date('Y') . ' ' . ($settings['shop_name'] ?? 'فروشگاه دیجیتال') . ' - کلیه حقوق محفوظ است', 0, 0, 'C');

$pdf->Output('financial-report-' . date('Y-m-d') . '.pdf', 'I');