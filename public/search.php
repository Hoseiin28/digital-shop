<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
 header("Location: login.php");
 exit;
}



$user_id = $_SESSION['user_id'];

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'products';

$results = [];
$allowed_types = ['products', 'articles'];
if ($query && in_array($type, $allowed_types)) {
 $like = '%' . $conn->real_escape_string($query) . '%';

 if ($type === 'products') {
 $sql = "SELECT p.*, c.name as category_name FROM Products p
 JOIN Categories c ON p.category_id = c.id
 WHERE (p.name LIKE ? OR p.description LIKE ?) AND p.stock > 0
 ORDER BY p.created_at DESC LIMIT 20";

 $stmt = $conn->prepare($sql);
 $stmt->bind_param('ss', $like, $like);

 } elseif ($type === 'articles') {
 $sql = "SELECT a.*, u.name as author_name FROM Articles a
 JOIN Users u ON a.author_id = u.id
 WHERE (a.title LIKE ? OR a.content LIKE ?) AND a.status='active'
 ORDER BY a.created_at DESC LIMIT 20";

 $stmt = $conn->prepare($sql);
 $stmt->bind_param('ss', $like, $like);
 }

 $stmt->execute();
 $res = $stmt->get_result();
 $results = $res->fetch_all(MYSQLI_ASSOC);
 $stmt->close();
}

$conn->close();

function escape($text) {
 return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}


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
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>جستجو برای کاربران | <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet" />
<link rel="stylesheet" href="../assets/css/style-search.css">
<?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
</head>
<body>
<?php include 'header-index.php'; ?>
<div class="container">
 <section class="search-wrapper">
 
 <form action="search.php" method="get" class="row g-3 mb-4" role="search">
   <div class="col-md-9">
     <input type="search" name="query" required placeholder="جستجوی محصولات یا مقالات..." value="<?= escape($query) ?>" class="form-control form-control-lg" autofocus>
   </div>
   <div class="col-md-3">
     <select name="type" class="form-select form-select-lg" aria-label="انتخاب نوع جستجو">
       <option value="products" <?= ($type==='products')?'selected':'' ?>>محصولات</option>
       <option value="articles" <?= ($type==='articles')?'selected':'' ?>>مقالات</option>
     </select>
   </div>
   <div class="col-12 text-center">
     <button class="btn btn-primary btn-lg px-5" type="submit">جستجو</button>
   </div>
 </form>

 <?php if ($query): ?>
   <h6 class="mb-3">نتایج برای: <mark><?= escape($query) ?></mark></h6>

   <?php if(count($results) === 0): ?>
   <div class="alert alert-warning text-center">موردی پیدا نشد.</div>
   <?php else: ?>
     <div class="row g-4">
       <?php foreach($results as $row): ?>

         <?php if ($type === 'products'): ?>
           <div class="col-md-4">
             <div class="card h-100 shadow-sm rounded-3">
              <a href="product-details.php?id=<?= (int)$row['id'] ?>">
               <img src="<?= escape($row['image_url'] ?: 'static/img/default-product.png') ?>" alt="<?= escape($row['name']) ?>" class="card-img-top" />
              </a>
               <div class="card-body d-flex flex-column">
                 <h5 class="card-title"><?= escape($row['name']) ?></h5>
                 <p class="card-text text-truncate"><?= escape(mb_substr($row['description'], 0, 90)) ?>...</p>
                 <p class="fw-bold text-danger"><?= number_format($row['price']) ?> تومان</p>
                 <a href="product-details.php?id=<?= (int)$row['id'] ?>" class="mt-auto btn btn-outline-primary">جزئیات</a>
               </div>
             </div>
           </div>

         <?php elseif ($type === 'articles'): ?>
           <div class="col-md-6">
             <div class="card shadow-sm rounded-3 h-100">
               <img src="<?= escape($row['image_url']) ?>" alt="<?= escape($row['title']) ?>" class="card-img-top" />
               <div class="card-body d-flex flex-column">
                 <h5 class="card-title"><?= escape($row['title']) ?></h5>
                 <small class="text-muted mb-2">نویسنده: <?= escape($row['author_name']) ?></small>
                 <p class="card-text text-truncate"><?= escape(mb_substr(strip_tags($row['content']), 0, 110)) ?>...</p>
                 <a href="articles.php?id=<?= (int)$row['id'] ?>" class="mt-auto btn btn-outline-info">خواندن ادامه مطلب</a>
               </div>
             </div>
           </div>
         <?php endif; ?>

       <?php endforeach; ?>
     </div>
   <?php endif; ?>

 <?php else: ?>
   <p class="text-muted text-center mt-5">عبارتی برای جستجو وارد کنید و دکمه جستجو را بزنید.</p>
 <?php endif; ?>

 </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>