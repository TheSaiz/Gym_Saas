<?php
// ==============================================
// Funciones Comunes - Gimnasio System SAAS
// ==============================================

/**
 * Formatea moneda en pesos argentinos
 */
function formatMoney($amount) {
    return '$' . number_format($amount, 2, ',', '.');
}

/**
 * Calcula días restantes hasta una fecha
 */
function diasRestantes($fecha_fin) {
    $hoy = new DateTime();
    $fin = new DateTime($fecha_fin);
    $diff = $hoy->diff($fin);
    return ($diff->invert == 1) ? 0 : $diff->days;
}

/**
 * Escapa cadenas para SQL (si no se usa mysqli_real_escape_string antes)
 */
function esc($conn, $str) {
    return $conn->real_escape_string($str);
}

/**
 * Redirige a una URL
 */
function redirect($url){
    header("Location: $url");
    exit;
}
?>
