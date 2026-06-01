<?php
session_start(); 
require_once 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit(); 
}


if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $messageId = $_GET['id'];

    try {
        $query = "DELETE FROM ContactMessages WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':id', $messageId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            header("Location: list-messages.php?success=deleted");
            exit();
        } else {
            header("Location: list-messages.php?error=notfound");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: list-messages.php?error=exception");
        exit();
    }
} else {
    header("Location: list-messages.php?error=invalidid");
    exit();
}
?>