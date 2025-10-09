<?php
include_once(__DIR__ . "/../includes/db_connect.php");
include_once(__DIR__ . "/../includes/auth_check.php");
checkSuperAdminLogin();
include_once(__DIR__ . "/sidebar.php");
include_once(__DIR__ . "/dashboard.php");
?>
