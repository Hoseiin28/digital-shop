<?php
$host = 'localhost';
$db = 'digital_shop';
$user = 'root';
$pass = ''; 

try {
 $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
 PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $e) {
 die("Connection failed: " . $e->getMessage());
}

$host = 'localhost'; 
$db_name = 'digital_shop';
$username = 'root'; 
$password = '';

$conn = new mysqli($host, $username, $password, $db_name);

if ($conn->connect_error) {
    die("اتصال به پایگاه داده ناموفق بود: " . $conn->connect_error);
}

$conn->set_charset("utf8");


$servername = "localhost";
$username = "root";
$password = "";
$dbname = "digital_shop";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}



$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'digital_shop';

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");
    $pdo->exec("SET CHARACTER SET utf8mb4");
} catch (PDOException $e) {
    die("اتصال به دیتابیس ناموفق بود: " . $e->getMessage());
}


$conn = new mysqli("localhost", "root", "", "digital_shop");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}



$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'digital_shop';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}