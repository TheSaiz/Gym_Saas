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

// Filtros y búsqueda
$search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
$estado_filter = isset($_GET['estado']) ? $_GET['estado'] : 'todos';

// Paginación
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Construir query con filtros
$where_conditions = ["gimnasio_id = ?"];
$params = [$gimnasio_id];
$types = "i";

if(!empty($search)) {
    $where_conditions[] = "(nombre LIKE ? OR apellido LIKE ? OR dni LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}

if($estado_filter !== 'todos') {
    $where_conditions[] = "estado = ?";
    $params[] = $estado_filter;
    $types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Contar total de registros
$count_query = "SELECT COUNT(*) as total FROM socios WHERE $where_clause";
$stmt = $conn->prepare($count_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$total_pages = ceil($total_records / $per_page);

// Obtener socios con paginación
$query = "SELECT s.*, 
          (SELECT COUNT(*) FROM licencias_socios ls WHERE ls.socio_id = s.id AND ls.estado = 'activa') as licencias_activas
          FROM socios s 
          WHERE $where_clause 
          ORDER BY s.creado DESC 
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";
$stmt->bind_param($types, ...$params);
$stmt->execute();
$socios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Estadísticas
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM socios WHERE gimnasio_id = ? AND estado = 'activo'");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$total_activos = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM socios WHERE gimnasio_id = ? AND estado = 'inactivo'");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$total_inactivos = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="robots" content="noindex, nofollow">
<title>Gestión de Socios - <?= $gimnasio_nombre ?></title>

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

    /* Main Content - CORREGIDO */
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

    /* Stats Cards */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.3rem;
    }

    .stat-label {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
    }

    /* Search Bar */
    .search-section {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .search-input-group {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .search-input {
        flex: 1;
        min-width: 250px;
        position: relative;
    }

    .search-input input,
    .search-input select {
        width: 100%;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 0.9rem 1rem 0.9rem 3rem;
        color: #fff;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .search-input input:focus,
    .search-input select:focus {
        outline: none;
        border-color: var(--primary);
        background: rgba(255, 255, 255, 0.12);
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }

    .search-input i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255, 255, 255, 0.4);
    }

    .search-input select {
        padding-left: 3rem;
        cursor: pointer;
    }

    .search-input option {
        background: #1a1f3a;
        color: #fff;
    }

    .btn-primary-custom {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border: none;
        padding: 0.9rem 2rem;
        border-radius: 12px;
        color: #fff;
        font-weight: 600;
        transition: all 0.3s ease;
        cursor: pointer;
        white-space: nowrap;
    }

    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }

    /* Table */
    .table-container {
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
        white-space: nowrap;
    }

    .custom-table thead th:first-child {
        border-radius: 12px 0 0 12px;
    }

    .custom-table thead th:last-child {
        border-radius: 0 12px 12px 0;
    }

    .custom-table tbody tr {
        background: rgba(255, 255, 255, 0.03);
        transition: all 0.3s ease;
    }

    .custom-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.08);
        transform: scale(1.01);
    }

    .custom-table tbody td {
        padding: 1rem;
        border: none;
        vertical-align: middle;
    }

    .custom-table tbody tr td:first-child {
        border-radius: 12px 0 0 12px;
    }

    .custom-table tbody tr td:last-child {
        border-radius: 0 12px 12px 0;
    }

    .avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
    }

    .badge-custom {
        padding: 0.4rem 0.9rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-block;
    }

    .badge-success {
        background: rgba(81, 207, 102, 0.2);
        color: var(--success);
        border: 1px solid var(--success);
    }

    .badge-danger {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
        border: 1px solid var(--danger);
    }

    .badge-info {
        background: rgba(77, 171, 247, 0.2);
        color: var(--info);
        border: 1px solid var(--info);
    }

    .action-btn {
        width: 35px;
        height: 35px;
        border-radius: 8px;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        margin: 0 0.2rem;
    }

    .action-btn.edit {
        background: rgba(77, 171, 247, 0.2);
        color: var(--info);
    }

    .action-btn.edit:hover {
        background: var(--info);
        color: #fff;
    }

    .action-btn.delete {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
    }

    .action-btn.delete:hover {
        background: var(--danger);
        color: #fff;
    }

    /* Pagination */
    .pagination-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin-top: 2rem;
        flex-wrap: wrap;
    }

    .pagination-btn {
        padding: 0.6rem 1rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: #fff;
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    .pagination-btn:hover {
        background: rgba(102, 126, 234, 0.2);
        border-color: var(--primary);
        color: #fff;
    }

    .pagination-btn.active {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-color: var(--primary);
    }

    .pagination-btn.disabled {
        opacity: 0.5;
        pointer-events: none;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: rgba(255, 255, 255, 0.5);
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
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

        .search-input-group {
            flex-direction: column;
        }

        .search-input {
            min-width: 100%;
        }

        .table-container {
            overflow-x: auto;
        }

        .custom-table {
            min-width: 800px;
        }
    }
</style>
</head>
<body>

<?php include_once(__DIR__ . "/sidebar.php"); ?>

<div class="content-wrapper">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-users me-2"></i>Gestión de Socios
        </h1>
        <p class="page-subtitle">Administra y controla todos los socios de tu gimnasio</p>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value" style="color: var(--primary);"><?= $total_records ?></div>
            <div class="stat-label">Total Socios</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: var(--success);"><?= $total_activos ?></div>
            <div class="stat-label">Activos</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: var(--danger);"><?= $total_inactivos ?></div>
            <div class="stat-label">Inactivos</div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <form method="GET" action="" id="searchForm">
            <div class="search-input-group">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Buscar por nombre, DNI o email..." 
                        value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="search-input" style="min-width: 180px;">
                    <i class="fas fa-filter"></i>
                    <select name="estado" onchange="this.form.submit()">
                        <option value="todos" <?= $estado_filter === 'todos' ? 'selected' : '' ?>>Todos los estados</option>
                        <option value="activo" <?= $estado_filter === 'activo' ? 'selected' : '' ?>>Activos</option>
                        <option value="inactivo" <?= $estado_filter === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary-custom">
                    <i class="fas fa-search me-2"></i>Buscar
                </button>

                <a href="<?= $base_url ?>/gimnasios/procesar_socio.php" class="btn-primary-custom">
                    <i class="fas fa-user-plus me-2"></i>Nuevo Socio
                </a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-container">
        <?php if(count($socios) > 0): ?>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Socio</th>
                        <th>DNI</th>
                        <th>Contacto</th>
                        <th>Estado</th>
                        <th>Licencias</th>
                        <th>Fecha Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($socios as $socio): 
                        $nombre_completo = htmlspecialchars($socio['nombre'] . ' ' . $socio['apellido'], ENT_QUOTES, 'UTF-8');
                        $iniciales = strtoupper(substr($socio['nombre'], 0, 1) . substr($socio['apellido'], 0, 1));
                    ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div class="avatar"><?= $iniciales ?></div>
                                    <div>
                                        <div style="font-weight: 600;"><?= $nombre_completo ?></div>
                                        <div style="font-size: 0.85rem; color: rgba(255,255,255,0.5);">
                                            <?= htmlspecialchars($socio['email'] ?? 'Sin email', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code style="background: rgba(255,255,255,0.1); padding: 0.3rem 0.6rem; border-radius: 6px;">
                                    <?= htmlspecialchars($socio['dni'], ENT_QUOTES, 'UTF-8') ?>
                                </code>
                            </td>
                            <td>
                                <?php if(!empty($socio['telefono'])): ?>
                                    <div><i class="fas fa-phone me-2" style="color: var(--info);"></i><?= htmlspecialchars($socio['telefono'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php else: ?>
                                    <span style="color: rgba(255,255,255,0.3);">Sin teléfono</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-custom <?= $socio['estado'] === 'activo' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= ucfirst($socio['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if($socio['licencias_activas'] > 0): ?>
                                    <span class="badge-custom badge-info">
                                        <i class="fas fa-check-circle me-1"></i><?= $socio['licencias_activas'] ?> activa(s)
                                    </span>
                                <?php else: ?>
                                    <span style="color: rgba(255,255,255,0.3);">Sin licencias</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">
                                <?= date('d/m/Y', strtotime($socio['creado'])) ?>
                            </td>
                            <td>
                                <a href="<?= $base_url ?>/gimnasios/procesar_socio.php?id=<?= $socio['id'] ?>" 
                                   class="action-btn edit" 
                                   title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button 
                                    onclick="confirmDelete(<?= $socio['id'] ?>, '<?= addslashes($nombre_completo) ?>')" 
                                    class="action-btn delete" 
                                    title="Eliminar">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
                <div class="pagination-container">
                    <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>&estado=<?= $estado_filter ?>" 
                       class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>

                    <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&estado=<?= $estado_filter ?>" 
                           class="pagination-btn <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search) ?>&estado=<?= $estado_filter ?>" 
                       class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <h3>No se encontraron socios</h3>
                <p>No hay socios registrados con los filtros aplicados</p>
                <a href="<?= $base_url ?>/gimnasios/procesar_socio.php" class="btn-primary-custom mt-3">
                    <i class="fas fa-user-plus me-2"></i>Registrar primer socio
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmDelete(id, nombre) {
        if(confirm(`¿Estás seguro que deseas eliminar al socio "${nombre}"?\n\nEsta acción no se puede deshacer.`)) {
            window.location.href = `<?= $base_url ?>/gimnasios/procesar_socio.php?action=delete&id=${id}`;
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
