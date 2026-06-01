<?php
if (isset($_GET['id'])) {
    $productId = (int)$_GET['id'];

require_once 'config.php';

    $sql = "SELECT Products.*, Categories.name AS category_name 
            FROM Products
            INNER JOIN Categories ON Products.category_id = Categories.id
            WHERE Products.id = $productId";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        echo json_encode($product);
    } else {
        echo json_encode(['error' => 'محصول یافت نشد']);
    }

    $conn->close();
} else {
    echo json_encode(['error' => 'شناسه محصول ارسال نشده است']);
}
?>