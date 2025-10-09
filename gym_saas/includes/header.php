<?php
include_once(__DIR__ . "/db_connect.php");

// Obtener configuración del sitio
$config = [];
$result = $conn->query("SELECT clave, valor FROM config");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $config['nombre_sitio'] ?? 'Gimnasio System SAAS'; ?></title>
<link rel="icon" href="<?= $config['favicon'] ?? '/assets/img/favicon.png'; ?>">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- FontAwesome -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<!-- Custom CSS -->
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
