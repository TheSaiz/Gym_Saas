<?php
session_start();

// Verifica si el usuario está logueado, según el tipo
function checkGimnasioLogin() {
    if(!isset($_SESSION['gimnasio_id'])){
        header("Location: /login.php");
        exit;
    }
}

function checkSuperAdminLogin() {
    if(!isset($_SESSION['superadmin_id'])){
        header("Location: /superadmin/login.php");
        exit;
    }
}

function checkSocioLogin() {
    if(!isset($_SESSION['socio_id'])){
        header("Location: /socios/login.php");
        exit;
    }
}
?>
