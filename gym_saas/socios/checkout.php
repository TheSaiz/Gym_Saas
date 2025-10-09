<?php
session_start();
include_once(__DIR__."/db_connect.php");

if(!isset($_SESSION['socio_id'])){
    header("Location: login.php");
    exit;
}

require_once(__DIR__."/vendor/autoload.php"); // MP SDK
MercadoPago\SDK::setAccessToken('TU_ACCESS_TOKEN');

$socio_id = $_SESSION['socio_id'];
$membresia_id = $_GET['membresia_id'] ?? 1;

$sql = "SELECT m.*, g.nombre AS gimnasio FROM membresias m JOIN socios s ON s.gimnasio_id=m.gimnasio_id JOIN gimnasios g ON g.id=s.gimnasio_id WHERE m.id=$membresia_id AND s.id=$socio_id";
$res = $conn->query($sql);
$membresia = $res->fetch_assoc();

$preference = new MercadoPago\Preference();
$item = new MercadoPago\Item();
$item->title = $membresia['nombre'];
$item->quantity = 1;
$item->unit_price = (float)$membresia['precio'];
$preference->items = array($item);
$preference->back_urls = array(
    "success" => "checkout.php?success=1",
    "failure" => "checkout.php?failure=1",
    "pending" => "checkout.php?pending=1"
);
$preference->auto_return = "approved";
$preference->save();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Checkout</title>
<script src="https://sdk.mercadopago.com/js/v2"></script>
</head>
<body>
<h3>Renovar membresía: <?php echo $membresia['nombre']; ?></h3>
<div id="mp-button-container"></div>
<script>
const mp = new MercadoPago('TU_PUBLIC_KEY', {locale: 'es-AR'});
mp.checkout({
    preference: { id: '<?php echo $preference->id; ?>' },
    render: { container: '#mp-button-container', label: 'Pagar ahora' }
});
</script>
</body>
</html>
