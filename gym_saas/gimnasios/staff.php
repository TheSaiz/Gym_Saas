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

// Obtener mensaje
$message = isset($_GET['msg']) ? htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') : '';
$msg_type = isset($_GET['type']) ? $_GET['type'] : 'success';

// Filtros
$rol_filter = isset($_GET['rol']) ? $_GET['rol'] : 'todos';
$estado_filter = isset($_GET['estado']) ? $_GET['estado'] : 'todos';

// Construir query con filtros
$where_conditions = ["gimnasio_id = ?"];
$params = [$gimnasio_id];
$types = "i";

if($rol_filter !== 'todos') {
    $where_conditions[] = "rol = ?";
    $params[] = $rol_filter;
    $types .= "s";
}

if($estado_filter !== 'todos') {
    $where_conditions[] = "estado = ?";
    $params[] = $estado_filter;
    $types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Obtener staff
$query = "SELECT * FROM staff WHERE $where_clause ORDER BY estado DESC, rol ASC, nombre ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$staff_members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Estadísticas
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM staff WHERE gimnasio_id = ?");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$total_staff = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM staff WHERE gimnasio_id = ? AND rol = 'admin'");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$total_admins = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM staff WHERE gimnasio_id = ? AND rol = 'validador'");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$total_validadores = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM staff WHERE gimnasio_id = ? AND estado = 'activo'");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$total_activos = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="robots" content="noindex, nofollow">
<title>Gestión de Staff - <?= $gimnasio_nombre ?></title>

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

    /* Alert */
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

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Stats */
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

    .stat-icon.primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
    .stat-icon.success { background: linear-gradient(135deg, #51cf66 0%, #37b24d 100%); }
    .stat-icon.info { background: linear-gradient(135deg, #4dabf7 0%, #228be6 100%); }
    .stat-icon.warning { background: linear-gradient(135deg, #ffd43b 0%, #fab005 100%); }

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
        flex-wrap: wrap;
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

    /* Staff Cards Grid */
    .staff-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
    }

    .staff-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 2rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .staff-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    }

    .staff-card.admin::before {
        background: linear-gradient(135deg, #ffd43b 0%, #fab005 100%);
    }

    .staff-card:hover {
        transform: translateY(-10px);
        border-color: var(--primary);
        box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
    }

    .staff-card.inactive {
        opacity: 0.6;
    }

    .staff-card.inactive::before {
        background: linear-gradient(135deg, var(--danger) 0%, #fa5252 100%);
    }

    .staff-header {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .staff-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .staff-avatar.admin {
        background: linear-gradient(135deg, #ffd43b 0%, #fab005 100%);
    }

    .staff-info {
        flex: 1;
        min-width: 0;
    }

    .staff-name {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 0.3rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .staff-username {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .staff-badges {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    .badge-custom {
        padding: 0.3rem 0.8rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }

    .badge-admin {
        background: rgba(255, 212, 59, 0.2);
        color: var(--warning);
        border: 1px solid var(--warning);
    }

    .badge-validador {
        background: rgba(77, 171, 247, 0.2);
        color: var(--info);
        border: 1px solid var(--info);
    }

    .badge-active {
        background: rgba(81, 207, 102, 0.2);
        color: var(--success);
        border: 1px solid var(--success);
    }

    .badge-inactive {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
        border: 1px solid var(--danger);
    }

    .staff-details {
        background: rgba(255, 255, 255, 0.03);
        border-radius: 12px;
        padding: 1rem;
        margin: 1rem 0;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.6rem 0;
    }

    .detail-row:not(:last-child) {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .detail-label {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.85rem;
    }

    .detail-value {
        font-weight: 600;
        font-size: 0.9rem;
    }

    .staff-actions {
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
        text-decoration: none;
        color: inherit;
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
        grid-column: 1 / -1;
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

        .staff-grid {
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
            <i class="fas fa-user-shield me-2"></i>Gestión de Staff
        </h1>
        <p class="page-subtitle">Administra el equipo de trabajo de tu gimnasio</p>
    </div>

    <!-- Alert -->
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
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?= $total_staff ?></div>
            <div class="stat-label">Total Staff</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-crown"></i>
            </div>
            <div class="stat-value"><?= $total_admins ?></div>
            <div class="stat-label">Administradores</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?= $total_validadores ?></div>
            <div class="stat-label">Validadores</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-value"><?= $total_activos ?></div>
            <div class="stat-label">Activos</div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="filter-group">
            <label style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">Filtrar por:</label>
            <form method="GET" style="display: flex; gap: 0.8rem; flex-wrap: wrap;">
                <select class="filter-select" name="rol" onchange="this.form.submit()">
                    <option value="todos" <?= $rol_filter === 'todos' ? 'selected' : '' ?>>Todos los roles</option>
                    <option value="admin" <?= $rol_filter === 'admin' ? 'selected' : '' ?>>Administradores</option>
                    <option value="validador" <?= $rol_filter === 'validador' ? 'selected' : '' ?>>Validadores</option>
                </select>

                <select class="filter-select" name="estado" onchange="this.form.submit()">
                    <option value="todos" <?= $estado_filter === 'todos' ? 'selected' : '' ?>>Todos los estados</option>
                    <option value="activo" <?= $estado_filter === 'activo' ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivo" <?= $estado_filter === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </form>
        </div>

        <a href="<?= $base_url ?>/gimnasios/procesar_staff.php" class="btn-primary-custom">
            <i class="fas fa-user-plus"></i>
            Nuevo Staff
        </a>
    </div>

    <!-- Staff Grid -->
    <div class="staff-grid">
        <?php if(count($staff_members) > 0): ?>
            <?php foreach($staff_members as $staff): 
                $nombre = htmlspecialchars($staff['nombre'], ENT_QUOTES, 'UTF-8');
                $usuario = htmlspecialchars($staff['usuario'], ENT_QUOTES, 'UTF-8');
                $rol = $staff['rol'];
                $estado = $staff['estado'];
                $iniciales = strtoupper(substr($nombre, 0, 2));
            ?>
                <div class="staff-card <?= $rol ?> <?= $estado === 'inactivo' ? 'inactive' : '' ?>">
                    <div class="staff-header">
                        <div class="staff-avatar <?= $rol ?>">
                            <?= $iniciales ?>
                        </div>
                        <div class="staff-info">
                            <h3 class="staff-name"><?= $nombre ?></h3>
                            <div class="staff-username">
                                <i class="fas fa-user"></i>
                                <?= $usuario ?>
                            </div>
                            <div class="staff-badges">
                                <span class="badge-custom badge-<?= $rol ?>">
                                    <?= $rol === 'admin' ? 'Administrador' : 'Validador' ?>
                                </span>
                                <span class="badge-custom badge-<?= $estado === 'activo' ? 'active' : 'inactive' ?>">
                                    <?= ucfirst($estado) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="staff-details">
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-shield-alt me-2"></i>Permisos
                            </span>
                            <span class="detail-value">
                                <?= $rol === 'admin' ? 'Completos' : 'Solo validación' ?>
                            </span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-clock me-2"></i>Estado
                            </span>
                            <span class="detail-value" style="color: <?= $estado === 'activo' ? 'var(--success)' : 'var(--danger)' ?>">
                                <?= $estado === 'activo' ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-key me-2"></i>Acceso
                            </span>
                            <span class="detail-value">
                                Sistema completo
                            </span>
                        </div>
                    </div>

                    <div class="staff-actions">
                        <a href="<?= $base_url ?>/gimnasios/procesar_staff.php?id=<?= $staff['id'] ?>" 
                           class="btn-action btn-edit">
                            <i class="fas fa-edit"></i>
                            Editar
                        </a>
                        <button 
                            onclick="confirmDelete(<?= $staff['id'] ?>, '<?= addslashes($nombre) ?>')" 
                            class="btn-action btn-delete">
                            <i class="fas fa-trash-alt"></i>
                            Eliminar
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-shield"></i>
                <h3>No hay miembros del staff</h3>
                <p>Agrega personal para ayudar en la gestión de tu gimnasio</p>
                <a href="<?= $base_url ?>/gimnasios/procesar_staff.php" class="btn-primary-custom mt-3">
                    <i class="fas fa-user-plus me-2"></i>Agregar Primer Staff
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmDelete(id, nombre) {
        if(confirm(`¿Estás seguro que deseas eliminar a "${nombre}" del staff?\n\nEsta acción no se puede deshacer y el usuario perderá acceso al sistema.`)) {
            window.location.href = `<?= $base_url ?>/gimnasios/procesar_staff.php?action=delete&id=${id}`;
        }
    }

    // Auto-hide alert
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
