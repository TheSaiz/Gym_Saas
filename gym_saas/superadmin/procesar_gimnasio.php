<?php
include_once(__DIR__ . "/../includes/auth_check.php");
checkSuperAdminLogin();

$accion = $_GET['accion'] ?? '';
$id = $_GET['id'] ?? '';

if($accion=='eliminar' && $id){
    include_once("../includes/db_connect.php");
    $conn->query("DELETE FROM gimnasios WHERE id='$id'");
    header("Location: gimnasios.php");
    exit;
}
?>
