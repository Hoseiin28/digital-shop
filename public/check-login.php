<?php
session_start();

$response = [
    'isLoggedIn' => isset($_SESSION['user_id']) && !empty($_SESSION['user_id']),
];

header('Content-Type: application/json');
echo json_encode($response);
exit;