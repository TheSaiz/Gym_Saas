<?php
/**
 * Index SuperAdmin
 * Punto de entrada principal - redirige al dashboard
 */

session_start();

// Regenerar ID de sesión para seguridad
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

// Verificar autenticación
if (!isset($_SESSION['superadmin_id']) || empty($_SESSION['superadmin_id'])) {
    session_destroy();
    header("Location: /gym_saas/superadmin/login.php");
    exit;
}

// Verificar timeout de sesión (30 minutos)
$timeout_duration = 1800; // 30 minutos en segundos
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: /gym_saas/superadmin/login.php?timeout=1");
    exit;
}

// Actualizar último tiempo de actividad
$_SESSION['last_activity'] = time();

// Redirigir al dashboard
header("Location: /gym_saas/superadmin/dashboard.php");
exit;
?>
