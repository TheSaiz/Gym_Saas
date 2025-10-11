<?php
session_start();

// Seguridad: Regenerar ID de sesión
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";

// Verificar sesión de gimnasio
if(!isset($_SESSION['gimnasio_id']) || empty($_SESSION['gimnasio_id'])){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

// Sanitizar ID de gimnasio
$gimnasio_id = filter_var($_SESSION['gimnasio_id'], FILTER_VALIDATE_INT);
if($gimnasio_id === false || $gimnasio_id <= 0){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

$gimnasio_nombre = isset($_SESSION['gimnasio_nombre']) ? htmlspecialchars($_SESSION['gimnasio_nombre'], ENT_QUOTES, 'UTF-8') : 'Gimnasio';

// Obtener datos de licencia del gimnasio
$stmt = $conn->prepare("
    SELECT 
        g.*,
        l.nombre AS licencia_nombre,
        l.dias AS licencia_dias,
        l.precio AS licencia_precio,
        DATEDIFF(g.fecha_fin, CURDATE()) as dias_restantes
    FROM gimnasios g
    LEFT JOIN licencias l ON g.licencia_id = l.id
    WHERE g.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$gimnasio_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$dias_restantes = max(0, (int)($gimnasio_data['dias_restantes'] ?? 0));
$fecha_inicio = $gimnasio_data['fecha_inicio'] ?? null;
$fecha_fin = $gimnasio_data['fecha_fin'] ?? null;
$estado_licencia = $gimnasio_data['estado'] ?? 'suspendido';

// Obtener licencias disponibles para renovación
$stmt = $conn->prepare("SELECT * FROM licencias WHERE estado = 'activo' ORDER BY precio ASC");
$stmt->execute();
$licencias_disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Historial de licencias
$stmt = $conn->prepare("
    SELECT 
        p.id,
        p.monto,
        p.fecha,
        p.estado,
        l.nombre as licencia_nombre
    FROM pagos p
    LEFT JOIN licencias l ON p.usuario_id = l.id
    WHERE p.gimnasio_id = ? AND p.tipo = 'licencia_gimnasio'
    ORDER BY p.fecha DESC
    LIMIT 10
");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="robots" content="noindex, nofollow">
<title>Mi Licencia - <?= $gimnasio_nombre ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --primary: #667eea;
        --secondary: #764ba2;
        --success: #51cf66;
        --danger: #ff6b6b;
        --warning: #ffd43b;
        --info: #4dabf7;
        --dark: #0a0e27;
        --sidebar-width: 280px;
        --navbar-height: 70px;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
        color: #fff;
    }

    .content-wrapper {
        margin-left: var(--sidebar-width);
        margin-top: var(--navbar-height);
        padding: 2rem;
        min-height: calc(100vh - var(--navbar-height));
    }

    .page-header {
        margin-bottom: 2rem;
    }

    .page-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .page-subtitle {
        color: rgba(255, 255, 255, 0.6);
        font-size: 1rem;
    }

    /* License Status Card */
    .license-status-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 2.5rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .license-status-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 6px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    }

    .license-status-card.warning::before {
        background: linear-gradient(135deg, var(--warning) 0%, #fab005 100%);
    }

    .license-status-card.danger::before {
        background: linear-gradient(135deg, var(--danger) 0%, #fa5252 100%);
    }

    .license-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .license-info {
        flex: 1;
    }

    .license-plan {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .license-description {
        color: rgba(255, 255, 255, 0.6);
        font-size: 1rem;
    }

    .license-badge {
        padding: 0.6rem 1.5rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .license-badge.active {
        background: rgba(81, 207, 102, 0.2);
        color: var(--success);
        border: 2px solid var(--success);
    }

    .license-badge.warning {
        background: rgba(255, 212, 59, 0.2);
        color: var(--warning);
        border: 2px solid var(--warning);
    }

    .license-badge.suspended {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
        border: 2px solid var(--danger);
    }

    .license-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .detail-item {
        background: rgba(255, 255, 255, 0.03);
        padding: 1.5rem;
        border-radius: 15px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .detail-label {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .detail-value {
        font-size: 1.5rem;
        font-weight: 700;
    }

    .days-countdown {
        text-align: center;
        padding: 2rem;
        background: rgba(102, 126, 234, 0.1);
        border-radius: 15px;
        margin-bottom: 2rem;
    }

    .days-number {
        font-size: 4rem;
        font-weight: 800;
        background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        line-height: 1;
    }

    .days-label {
        font-size: 1.2rem;
        color: rgba(255, 255, 255, 0.7);
        margin-top: 0.5rem;
    }

    /* Alert */
    .alert-custom {
        padding: 1.5rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .alert-custom.danger {
        background: rgba(255, 107, 107, 0.15);
        border: 1px solid rgba(255, 107, 107, 0.3);
    }

    .alert-custom.warning {
        background: rgba(255, 212, 59, 0.15);
        border: 1px solid rgba(255, 212, 59, 0.3);
    }

    .alert-custom i {
        font-size: 2rem;
    }

    /* Plans Grid */
    .plans-section {
        margin-bottom: 2rem;
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }

    .section-title i {
        color: var(--primary);
    }

    .plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    .plan-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 2rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .plan-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    }

    .plan-card:hover {
        transform: translateY(-10px);
        border-color: var(--primary);
        box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
    }

    .plan-card.featured {
        border: 2px solid var(--primary);
        background: rgba(102, 126, 234, 0.08);
    }

    .featured-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: #fff;
        padding: 0.4rem 0.9rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .plan-name {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .plan-price {
        font-size: 3rem;
        font-weight: 800;
        background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        line-height: 1;
        margin-bottom: 0.5rem;
    }

    .plan-duration {
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 1.5rem;
    }

    .btn-select-plan {
        width: 100%;
        padding: 1rem;
        border-radius: 12px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: #fff;
    }

    .btn-select-plan:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }

    /* History Table */
    .history-table {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 1.5rem;
        overflow-x: auto;
    }

    .custom-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 0.5rem;
    }

    .custom-table thead th {
        background: rgba(102, 126, 234, 0.1);
        color: rgba(255, 255, 255, 0.9);
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.9rem;
        border: none;
    }

    .custom-table thead th:first-child {
        border-radius: 12px 0 0 12px;
    }

    .custom-table thead th:last-child {
        border-radius: 0 12px 12px 0;
    }

    .custom-table tbody tr {
        background: rgba(255, 255, 255, 0.03);
    }

    .custom-table tbody td {
        padding: 1rem;
        border: none;
    }

    .custom-table tbody tr td:first-child {
        border-radius: 12px 0 0 12px;
    }

    .custom-table tbody tr td:last-child {
        border-radius: 0 12px 12px 0;
    }

    .badge-status {
        padding: 0.4rem 0.9rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .badge-pagado {
        background: rgba(81, 207, 102, 0.2);
        color: var(--success);
        border: 1px solid var(--success);
    }

    .badge-pendiente {
        background: rgba(255, 212, 59, 0.2);
        color: var(--warning);
        border: 1px solid var(--warning);
    }

    .badge-fallido {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
        border: 1px solid var(--danger);
    }

    @media (max-width: 1024px) {
        .content-wrapper {
            margin-left: 0;
        }
    }

    @media (max-width: 768px) {
        .license-details {
            grid-template-columns: 1fr;
        }

        .plans-grid {
            grid-template-columns: 1fr;
        }

        .custom-table {
            min-width: 600px;
        }
    }
</style>
</head>
<body>

<?php include_once(__DIR__ . "/sidebar.php"); ?>

<div class="content-wrapper">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-key me-2"></i>Mi Licencia
        </h1>
        <p class="page-subtitle">Estado y renovación de tu licencia de gimnasio</p>
    </div>

    <!-- Alert si está por vencer o suspendido -->
    <?php if($estado_licencia === 'suspendido'): ?>
        <div class="alert-custom danger">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>¡Licencia Suspendida!</strong>
                <p style="margin: 0; margin-top: 0.5rem;">Tu gimnasio está suspendido. Renueva tu licencia para continuar operando.</p>
            </div>
        </div>
    <?php elseif($dias_restantes <= 7): ?>
        <div class="alert-custom warning">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <strong>¡Atención! Tu licencia está por vencer</strong>
                <p style="margin: 0; margin-top: 0.5rem;">Te quedan solo <?= $dias_restantes ?> días. Renueva ahora para evitar la suspensión del servicio.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- License Status Card -->
    <div class="license-status-card <?= $dias_restantes <= 7 ? ($dias_restantes <= 3 ? 'danger' : 'warning') : '' ?>">
        <div class="license-header">
            <div class="license-info">
                <h2 class="license-plan">
                    <?= htmlspecialchars($gimnasio_data['licencia_nombre'] ?? 'Sin Licencia', ENT_QUOTES, 'UTF-8') ?>
                </h2>
                <p class="license-description">Plan actual de tu gimnasio</p>
            </div>
            <div class="license-badge <?= $estado_licencia === 'activo' ? 'active' : ($dias_restantes <= 7 ? 'warning' : 'suspended') ?>">
                <?= $estado_licencia === 'activo' ? 'Activa' : 'Suspendida' ?>
            </div>
        </div>

        <?php if($fecha_fin): ?>
            <div class="days-countdown">
                <div class="days-number"><?= $dias_restantes ?></div>
                <div class="days-label">días restantes</div>
            </div>
        <?php endif; ?>

        <div class="license-details">
            <div class="detail-item">
                <div class="detail-label">
                    <i class="fas fa-calendar-check"></i>
                    Fecha de Inicio
                </div>
                <div class="detail-value">
                    <?= $fecha_inicio ? date('d/m/Y', strtotime($fecha_inicio)) : 'N/A' ?>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-label">
                    <i class="fas fa-calendar-times"></i>
                    Fecha de Vencimiento
                </div>
                <div class="detail-value">
                    <?= $fecha_fin ? date('d/m/Y', strtotime($fecha_fin)) : 'N/A' ?>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-label">
                    <i class="fas fa-clock"></i>
                    Duración
                </div>
                <div class="detail-value">
                    <?= $gimnasio_data['licencia_dias'] ?? 0 ?> días
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-label">
                    <i class="fas fa-dollar-sign"></i>
                    Precio
                </div>
                <div class="detail-value">
                    $<?= number_format($gimnasio_data['licencia_precio'] ?? 0, 2, ',', '.') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Plans Section -->
    <div class="plans-section">
        <h3 class="section-title">
            <i class="fas fa-star"></i>
            Planes Disponibles
        </h3>

        <div class="plans-grid">
            <?php foreach($licencias_disponibles as $index => $plan): ?>
                <div class="plan-card <?= $index === 1 ? 'featured' : '' ?>">
                    <?php if($index === 1): ?>
                        <span class="featured-badge">MÁS POPULAR</span>
                    <?php endif; ?>

                    <h4 class="plan-name"><?= htmlspecialchars($plan['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
                    <div class="plan-price">$<?= number_format($plan['precio'], 0, ',', '.') ?></div>
                    <p class="plan-duration"><?= $plan['dias'] ?> días de acceso completo</p>

                    <button onclick="renovarLicencia(<?= $plan['id'] ?>)" class="btn-select-plan">
                        <i class="fas fa-check-circle me-2"></i>Seleccionar Plan
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- History -->
    <?php if(count($historial) > 0): ?>
        <div class="plans-section">
            <h3 class="section-title">
                <i class="fas fa-history"></i>
                Historial de Pagos
            </h3>

            <div class="history-table">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Plan</th>
                            <th>Monto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($historial as $item): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($item['fecha'])) ?></td>
                                <td><?= htmlspecialchars($item['licencia_nombre'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="font-weight: 600;">$<?= number_format($item['monto'], 2, ',', '.') ?></td>
                                <td>
                                    <span class="badge-status badge-<?= $item['estado'] ?>">
                                        <?= ucfirst($item['estado']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function renovarLicencia(licenciaId) {
        if(confirm('¿Deseas proceder con la renovación de tu licencia?\n\nSerás redirigido a la página de pago.')) {
            window.location.href = '<?= $base_url ?>/gimnasios/checkout.php?licencia_id=' + licenciaId;
        }
    }

    // Session timeout
    let sessionTimeout;
    const TIMEOUT_DURATION = 1800000;

    function resetSessionTimeout() {
        clearTimeout(sessionTimeout);
        sessionTimeout = setTimeout(function() {
            alert('Tu sesión ha expirado por inactividad.');
            window.location.href = '<?= $base_url ?>/gimnasios/logout.php';
        }, TIMEOUT_DURATION);
    }

    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function(event) {
        document.addEventListener(event, resetSessionTimeout, true);
    });

    resetSessionTimeout();
</script>

</body>
</html>
