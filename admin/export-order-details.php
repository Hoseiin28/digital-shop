<?php
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

require_once 'config.php';

if (!isset($_POST['order_id'])) {
    die("شماره سفارش ارسال نشده است.");
}

$orderId = (int) $_POST['order_id'];

$userSql = "SELECT Users.name AS user_name, Users.email, Users.phone, Users.address, Orders.created_at AS order_date 
            FROM Orders
            JOIN Users ON Orders.user_id = Users.id
            WHERE Orders.id = $orderId";
$userResult = $conn->query($userSql);

if ($userResult->num_rows === 0) {
    die("اطلاعات کاربر یافت نشد.");
}

$userInfo = $userResult->fetch_assoc();

$sql = "SELECT OrderDetails.order_id, Products.name AS product_name, OrderDetails.price, OrderDetails.quantity, OrderDetails.subtotal
        FROM OrderDetails
        JOIN Products ON OrderDetails.product_id = Products.id
        WHERE OrderDetails.order_id = $orderId";
$result = $conn->query($sql);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("جزئیات سفارش $orderId");

$sheet->setCellValue('A1', 'مشخصات کاربر');
$sheet->mergeCells('A1:E1');
$sheet->getStyle('A1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 16,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '004F87'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
]);

$sheet->setCellValue('A2', 'نام کاربر');
$sheet->setCellValue('B2', $userInfo['user_name']);
$sheet->setCellValue('A3', 'ایمیل');
$sheet->setCellValue('B3', $userInfo['email']);
$sheet->setCellValue('A4', 'تلفن');
$sheet->setCellValue('B4', $userInfo['phone']);
$sheet->setCellValue('A5', 'آدرس');
$sheet->setCellValue('B5', $userInfo['address']);
$sheet->setCellValue('A6', 'تاریخ سفارش');
$sheet->setCellValue('B6', $userInfo['order_date']);

$userDetailsStyle = [
    'font' => [
        'bold' => true,
        'size' => 12,
        'color' => ['rgb' => '000000'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F0F8FF'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => 'thin',
            'color' => ['rgb' => 'CCCCCC'],
        ],
    ],
];
$sheet->getStyle('A2:B6')->applyFromArray($userDetailsStyle);
$sheet->getRowDimension('2')->setRowHeight(25);
$sheet->getRowDimension('3')->setRowHeight(25);
$sheet->getRowDimension('4')->setRowHeight(25);
$sheet->getRowDimension('5')->setRowHeight(25);
$sheet->getRowDimension('6')->setRowHeight(25);

$startRow = 8;
$sheet->setCellValue("A$startRow", 'شماره سفارش');
$sheet->setCellValue("B$startRow", 'نام محصول');
$sheet->setCellValue("C$startRow", 'قیمت');
$sheet->setCellValue("D$startRow", 'تعداد');
$sheet->setCellValue("E$startRow", 'جمع کل');

$titleStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '004F87'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];
$sheet->getStyle("A$startRow:E$startRow")->applyFromArray($titleStyle);
$sheet->getRowDimension("$startRow")->setRowHeight(30);

$dataStyle = [
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => 'thin',
            'color' => ['rgb' => 'CCCCCC'],
        ],
    ],
];

$rowNumber = $startRow + 1;
while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue("A$rowNumber", $row['order_id']);
    $sheet->setCellValue("B$rowNumber", $row['product_name']);
    $sheet->setCellValue("C$rowNumber", number_format($row['price'], 0) . ' تومان');
    $sheet->setCellValue("D$rowNumber", $row['quantity']);
    $sheet->setCellValue("E$rowNumber", number_format($row['subtotal'], 0) . ' تومان');

    $fillColor = $rowNumber % 2 == 0 ? 'F0F8FF' : 'FFFFFF';
    $rowStyle = $dataStyle;
    $rowStyle['fill'] = [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $fillColor],
    ];
    $sheet->getStyle("A$rowNumber:E$rowNumber")->applyFromArray($rowStyle);

    $sheet->getRowDimension($rowNumber)->setRowHeight(25);
    $rowNumber++;
}

foreach (range('A', 'E') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"order-details-$orderId.xlsx\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();