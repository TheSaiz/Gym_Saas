<?php
session_start();
if(!isset($_SESSION['socio_id'])){
    header("Location: /index.php");
    exit;
}

include_once(__DIR__ . "/db_connect.php");
include_once("sidebar.php");

$socio_id = $_SESSION['socio_id'];

// Obtener licencias del socio
$sql = "SELECT ls.*, g.nombre AS gimnasio, m.nombre AS membresia
        FROM licencias_socios ls
        JOIN gimnasios g ON ls.gimnasio_id = g.id
        LEFT JOIN membresias m ON ls.membresia_id = m.id
        WHERE ls.socio_id = $socio_id";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Estado Licencias</title>
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<style>
  body { margin-left: 220px; padding: 20px; background:#f4f4f4; }
  table { background:#fff; }
</style>
</head>
<body>
<div class="container-fluid">
<h2>Estado de tus licencias</h2>
<table class="table table-bordered shadow-sm">
<thead class="table-dark">
<tr>
<th>Gimnasio</th>
<th>Membresía</th>
<th>Fecha Inicio</th>
<th>Fecha Fin</th>
<th>Estado</th>
</tr>
</thead>
<tbody>
<?php while($row = $result->fetch_assoc()): ?>
<tr>
<td><?php echo $row['gimnasio']; ?></td>
<td><?php echo $row['membresia'] ?? '-'; ?></td>
<td><?php echo $row['fecha_inicio']; ?></td>
<td><?php echo $row['fecha_fin']; ?></td>
<td>
  <?php
    if($row['estado'] == 'activa') echo '<span class="badge bg-success">Activa</span>';
    elseif($row['estado'] == 'vencida') echo '<span class="badge bg-danger">Vencida</span>';
    else echo '<span class="badge bg-warning">Pendiente</span>';
  ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</body>
</html>
