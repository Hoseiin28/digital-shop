<?php
header('Content-Type: application/json; charset=utf-8');

session_start();

if ($_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'دسترسی غیرمجاز']);
    exit();
}

require_once 'config.php';

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'شناسه کاربر مشخص نشده']);
    exit();
}

$user_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT id, name, email, phone, address, avatar, role, created_at FROM Users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'کاربر یافت نشد']);
    exit();
}

$user = $result->fetch_assoc();

if (empty($user['avatar'])) {
    $user['avatar'] = '../image/default-avatar.jpg';
}

echo json_encode($user);

$stmt->close();
$conn->close();
?>