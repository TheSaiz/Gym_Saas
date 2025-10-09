<?php
session_start();
include_once(__DIR__ . "/includes/db_connect.php");

// Verificar sesión
if(!isset($_SESSION['gimnasio_id'])){
    header("Location: /index.php");
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
require_once(__DIR__ . "/vendor/autoload.php"); // SDK MercadoPago

$mp_access_token = '';
$res = $conn->query("SELECT mp_token FROM gimnasios WHERE id=$gimnasio_id");
if($res->num_rows > 0){
    $mp_access_token = $res->fetch_assoc()['mp_token'];
}

MercadoPago\SDK::setAccessToken($mp_access_token);

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $producto = $_POST['producto']; // id de licencia o membresía
    $tipo = $_POST['tipo']; // licencia_gimnasio o membresia_socio
    $monto = floatval($_POST['monto']);

    $preference = new MercadoPago\Preference();
    $item = new MercadoPago\Item();
    $item->title = $producto;
    $item->quantity = 1;
    $item->unit_price = $monto;
    $preference->items = array($item);

    $preference->back_urls = array(
        "success" => "http://localhost/gym_saas/gimnasios/checkout.php?status=success",
        "failure" => "http://localhost/gym_saas/gimnasios/checkout.php?status=failure",
        "pending" => "http://localhost/gym_saas/gimnasios/checkout.php?status=pending"
    );

    $preference->auto_return = "approved";
    $preference->save();

    header("Location: ".$preference->init_point);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout - Gimnasio System SAAS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container my-5">
<h3>Checkout - Selecciona tu plan</h3>
<form method="POST">
    <div class="mb-3">
        <label class="form-label">Producto</label>
        <input type="text" class="form-control" name="producto" placeholder="Licencia o Membresía">
    </div>
    <div class="mb-3">
        <label class="form-label">Tipo</label>
        <select name="tipo" class="form-select">
            <option value="licencia_gimnasio">Licencia Gimnasio</option>
            <option value="membresia_socio">Membresía Socio</option>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Monto</label>
        <input type="number" step="0.01" class="form-control" name="monto" required>
    </div>
    <button type="submit" class="btn btn-success">Pagar con MercadoPago</button>
</form>
</div>
</body>
</html>
