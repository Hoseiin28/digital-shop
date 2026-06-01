<?php
session_start();
session_unset(); 
session_destroy();
header("Location: /digital-shop/index.php");
header("Location: /digital-shop/public/login.php");
exit();

