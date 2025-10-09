<?php
session_start();
include_once(__DIR__ . "/../includes/db_connect.php");
if(!isset($_SESSION['gimnasio_id'])){
    header("Location: /login.php"); exit;
}
$gimnasio_id = $_SESSION['gimnasio_id'];

// Reporte de pagos de socios
$reportes = $conn->query("
    SELECT ls.id, s.nombre, s.apellido, m.nombre AS membresia, ls.fecha_inicio, ls.fecha_fin, ls.estado, p.monto
    FROM licencias_socios ls
    LEFT JOIN socios s ON ls.socio_id = s.id
    LEFT JOIN membresias m ON ls.membresia_id = m.id
    LEFT JOIN pagos p ON ls.pago_id = p.id
    WHERE ls.gimnasio_id=$gimnasio_id
")->fetch_all(MYSQLI_ASSOC);
?>
<?php include_once(__DIR__ . "/sidebar.php"); ?>

<div class="container mt-5 pt-4">
    <h2>Reportes de Socios y Pagos</h2>
    <table class="table table-striped table-hover shadow-sm">
        <thead class="table-dark">
            <tr>
                <th>Socio</th>
                <th>Membresía</th>
                <th>Inicio</th>
                <th>Fin</th>
                <th>Estado</th>
                <th>Monto</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($reportes as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['nombre'].' '.$r['apellido']) ?></td>
                    <td><?= htmlspecialchars($r['membresia']) ?></td>
                    <td><?= $r['fecha_inicio'] ?></td>
                    <td><?= $r['fecha_fin'] ?></td>
                    <td><?= ucfirst($r['estado']) ?></td>
                    <td>$<?= number_format($r['monto'],2,",",".") ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
