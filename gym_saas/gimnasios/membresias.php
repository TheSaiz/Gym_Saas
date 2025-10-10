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

// Obtener mensaje de éxito/error
$message = isset($_GET['msg']) ? htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') : '';
$msg_type = isset($_GET['type']) ? $_GET['type'] : 'success';

// Filtro de estado
$estado_filter = isset($_GET['estado']) ? $_GET['estado'] : 'todos';

// Construir query con filtros
$where_conditions = ["gimnasio_id = ?"];
$params = [$gimnasio_id];
$types = "i";

if($estado_filter !== 'todos') {
    $where_conditions[] = "estado = ?";
    $params[] = $estado_filter;
    $types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Obtener membresías con estadísticas
$query = "SELECT m.*, 
          (SELECT COUNT(*) FROM licencias_socios ls WHERE ls.membresia_id = m.id AND ls.estado = 'activa') as socios_activos,
          (SELECT SUM(p.monto) FROM pagos p 
           INNER JOIN licencias_socios ls ON p.id = ls.pago_id 
           WHERE ls.membresia_id = m.id AND p.estado = 'pagado') as ingresos_totales
          FROM membresias m 
          WHERE $where_clause 
          ORDER BY m.estado DESC, m.precio ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$membresias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Estadísticas generales
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM membresias WHERE gimnasio_id = ?");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$total_membresias = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM membresias WHERE gimnasio_id = ? AND estado = 'activo'");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$total_activas = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM membresias WHERE gimnasio_id = ? AND estado = 'inactivo'");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$total_inactivas = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Total de socios con membresías activas
$stmt = $conn->prepare("SELECT COUNT(DISTINCT ls.socio_id) as total 
                        FROM licencias_socios ls 
                        INNER JOIN membresias m ON ls.membresia_id = m.id 
                        WHERE ls.gimnasio_id = ? AND ls.estado = 'activa'");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$total_socios_activos = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="robots" content="noindex, nofollow">
<title>Gestión de Membresías - <?= $gimnasio_nombre ?></title>

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

    /* Content Wrapper - CORREGIDO */
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

    /* Alert Messages */
    .alert-custom {
        background: rgba(81, 207, 102, 0.15);
        border: 1px solid rgba(81, 207, 102, 0.3);
        border-radius: 15px;
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        animation: slideDown 0.5s ease;
    }

    .alert-custom.error {
        background: rgba(255, 107, 107, 0.15);
        border-color: rgba(255, 107, 107, 0.3);
        color: var(--danger);
    }

    .alert-custom i {
        font-size: 1.5rem;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Stats Cards */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 1.5rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
        transform: translate(30%, -30%);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 1.5rem;
    }

    .stat-icon.primary {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    }

    .stat-icon.success {
        background: linear-gradient(135deg, #51cf66 0%, #37b24d 100%);
    }

    .stat-icon.danger {
        background: linear-gradient(135deg, #ff6b6b 0%, #fa5252 100%);
    }

    .stat-icon.info {
        background: linear-gradient(135deg, #4dabf7 0%, #228be6 100%);
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.3rem;
    }

    .stat-label {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
    }

    /* Toolbar */
    .toolbar {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 1.2rem;
        margin-bottom: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .filter-group {
        display: flex;
        gap: 0.8rem;
        align-items: center;
    }

    .filter-select {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        padding: 0.7rem 1rem;
        color: #fff;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .filter-select:focus {
        outline: none;
        border-color: var(--primary);
        background: rgba(255, 255, 255, 0.12);
    }

    .filter-select option {
        background: #1a1f3a;
        color: #fff;
    }

    .btn-primary-custom {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border: none;
        padding: 0.8rem 1.8rem;
        border-radius: 10px;
        color: #fff;
        font-weight: 600;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        color: #fff;
    }

    /* Membership Cards Grid */
    .membresias-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
    }

    .membresia-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 2rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .membresia-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    }

    .membresia-card:hover {
        transform: translateY(-10px);
        border-color: var(--primary);
        box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
    }

    .membresia-card.inactive {
        opacity: 0.6;
    }

    .membresia-card.inactive::before {
        background: linear-gradient(135deg, var(--danger) 0%, #fa5252 100%);
    }

    .card-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
    }

    .card-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 0.5rem;
    }

    .card-status {
        padding: 0.4rem 0.9rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-active {
        background: rgba(81, 207, 102, 0.2);
        color: var(--success);
        border: 1px solid var(--success);
    }

    .status-inactive {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
        border: 1px solid var(--danger);
    }

    .price-section {
        margin: 1.5rem 0;
        text-align: center;
        padding: 1.5rem;
        background: rgba(102, 126, 234, 0.1);
        border-radius: 15px;
    }

    .price {
        font-size: 3rem;
        font-weight: 800;
        background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        line-height: 1;
    }

    .price-label {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
        margin-top: 0.5rem;
    }

    .card-details {
        margin: 1.5rem 0;
    }

    .detail-item {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 0.8rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .detail-item:last-child {
        border-bottom: none;
    }

    .detail-icon {
        width: 35px;
        height: 35px;
        border-radius: 8px;
        background: rgba(102, 126, 234, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
    }

    .detail-text {
        flex: 1;
    }

    .detail-label {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.5);
    }

    .detail-value {
        font-weight: 600;
        font-size: 0.95rem;
    }

    .card-actions {
        display: flex;
        gap: 0.8rem;
        margin-top: 1.5rem;
    }

    .btn-action {
        flex: 1;
        padding: 0.8rem;
        border-radius: 10px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-edit {
        background: rgba(77, 171, 247, 0.2);
        color: var(--info);
        border: 1px solid var(--info);
    }

    .btn-edit:hover {
        background: var(--info);
        color: #fff;
        transform: translateY(-2px);
    }

    .btn-delete {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
        border: 1px solid var(--danger);
    }

    .btn-delete:hover {
        background: var(--danger);
        color: #fff;
        transform: translateY(-2px);
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: rgba(255, 255, 255, 0.5);
    }

    .empty-state i {
        font-size: 5rem;
        margin-bottom: 1.5rem;
        opacity: 0.3;
    }

    .empty-state h3 {
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    @media (max-width: 1024px) {
        .content-wrapper {
            margin-left: 0;
        }
    }

    @media (max-width: 768px) {
        .stats-row {
            grid-template-columns: 1fr;
        }

        .membresias-grid {
            grid-template-columns: 1fr;
        }

        .toolbar {
            flex-direction: column;
            align-items: stretch;
        }

        .filter-group {
            flex-direction: column;
        }
    }
</style>
</head>
<body>

<?php include_once(__DIR__ . "/sidebar.php"); ?>

<div class="content-wrapper">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-id-card-alt me-2"></i>Gestión de Membresías
        </h1>
        <p class="page-subtitle">Crea y administra los planes de membresía de tu gimnasio</p>
    </div>

    <!-- Alert Message -->
    <?php if($message): ?>
        <div class="alert-custom <?= $msg_type === 'error' ? 'error' : '' ?>">
            <i class="fas fa-<?= $msg_type === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
            <span><?= $message ?></span>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-id-card"></i>
            </div>
            <div class="stat-value"><?= $total_membresias ?></div>
            <div class="stat-label">Total Membresías</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?= $total_activas ?></div>
            <div class="stat-label">Activas</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-value"><?= $total_inactivas ?></div>
            <div class="stat-label">Inactivas</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?= $total_socios_activos ?></div>
            <div class="stat-label">Socios con Membresía</div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="filter-group">
            <label style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">Filtrar por:</label>
            <select class="filter-select" onchange="window.location.href='?estado=' + this.value">
                <option value="todos" <?= $estado_filter === 'todos' ? 'selected' : '' ?>>Todos los estados</option>
                <option value="activo" <?= $estado_filter === 'activo' ? 'selected' : '' ?>>Activas</option>
                <option value="inactivo" <?= $estado_filter === 'inactivo' ? 'selected' : '' ?>>Inactivas</option>
            </select>
        </div>

        <a href="<?= $base_url ?>/gimnasios/procesar_membresia.php" class="btn-primary-custom">
            <i class="fas fa-plus-circle"></i>
            Nueva Membresía
        </a>
    </div>

    <!-- Membresías Grid -->
    <?php if(count($membresias) > 0): ?>
        <div class="membresias-grid">
            <?php foreach($membresias as $membresia): 
                $nombre = htmlspecialchars($membresia['nombre'], ENT_QUOTES, 'UTF-8');
                $precio = number_format($membresia['precio'], 2, ',', '.');
                $dias = (int)$membresia['dias'];
                $estado = $membresia['estado'];
                $socios = (int)($membresia['socios_activos'] ?? 0);
                $ingresos = (float)($membresia['ingresos_totales'] ?? 0);
            ?>
                <div class="membresia-card <?= $estado === 'inactivo' ? 'inactive' : '' ?>">
                    <div class="card-header-custom">
                        <div>
                            <h3 class="card-title"><?= $nombre ?></h3>
                        </div>
                        <span class="card-status <?= $estado === 'activo' ? 'status-active' : 'status-inactive' ?>">
                            <?= ucfirst($estado) ?>
                        </span>
                    </div>

                    <div class="price-section">
                        <div class="price">$<?= $precio ?></div>
                        <div class="price-label">por membresía</div>
                    </div>

                    <div class="card-details">
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="detail-text">
                                <div class="detail-label">Duración</div>
                                <div class="detail-value"><?= $dias ?> días</div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="detail-text">
                                <div class="detail-label">Socios Activos</div>
                                <div class="detail-value"><?= $socios ?> socio<?= $socios != 1 ? 's' : '' ?></div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="detail-text">
                                <div class="detail-label">Ingresos Totales</div>
                                <div class="detail-value">$<?= number_format($ingresos, 2, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-actions">
                        <a href="<?= $base_url ?>/gimnasios/procesar_membresia.php?id=<?= $membresia['id'] ?>" 
                           class="btn-action btn-edit">
                            <i class="fas fa-edit"></i>
                            Editar
                        </a>
                        <button 
                            onclick="confirmDelete(<?= $membresia['id'] ?>, '<?= addslashes($nombre) ?>')" 
                            class="btn-action btn-delete">
                            <i class="fas fa-trash-alt"></i>
                            Eliminar
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="membresias-grid">
            <div class="empty-state" style="grid-column: 1 / -1;">
                <i class="fas fa-id-card-alt"></i>
                <h3>No hay membresías creadas</h3>
                <p>Crea tu primera membresía para comenzar a gestionar los planes de tu gimnasio</p>
                <a href="<?= $base_url ?>/gimnasios/procesar_membresia.php" class="btn-primary-custom mt-3">
                    <i class="fas fa-plus-circle me-2"></i>Crear Primera Membresía
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmDelete(id, nombre) {
        if(confirm(`¿Estás seguro que deseas eliminar la membresía "${nombre}"?\n\nEsta acción no se puede deshacer y puede afectar a los socios que la tengan asignada.`)) {
            window.location.href = `<?= $base_url ?>/gimnasios/procesar_membresia.php?action=delete&id=${id}`;
        }
    }

    // Auto-hide alert message
    const alert = document.querySelector('.alert-custom');
    if(alert) {
        setTimeout(() => {
            alert.style.transition = 'all 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
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
