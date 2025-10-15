<?php
/**
 * Gestión de Licencias - SuperAdmin
 * CRUD completo de planes de licencias para gimnasios
 */

session_start();

if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";

// Verificación de sesión
if(!isset($_SESSION['superadmin_id']) || empty($_SESSION['superadmin_id'])){
    session_destroy();
    header("Location: $base_url/superadmin/login.php");
    exit;
}

$timeout_duration = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: $base_url/superadmin/login.php?timeout=1");
    exit;
}

$_SESSION['last_activity'] = time();

// Obtener todas las licencias
$stmt = $conn->prepare("SELECT l.*, 
                       COUNT(DISTINCT g.id) as gimnasios_activos,
                       COALESCE(SUM(CASE WHEN g.estado = 'activo' THEN 1 ELSE 0 END), 0) as gimnasios_con_licencia
                       FROM licencias l
                       LEFT JOIN gimnasios g ON l.id = g.licencia_id
                       GROUP BY l.id
                       ORDER BY l.precio ASC");
$stmt->execute();
$licencias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Mensajes
$success_message = '';
$error_message = '';

if(isset($_GET['success'])){
    $messages = [
        '1' => '¡Licencia creada exitosamente!',
        '2' => '¡Licencia actualizada correctamente!',
        '3' => '¡Licencia eliminada correctamente!'
    ];
    $success_message = $messages[$_GET['success']] ?? '';
}

if(isset($_GET['error'])){
    $messages = [
        '1' => 'Error al procesar la solicitud.',
        '2' => 'La licencia no puede eliminarse porque tiene gimnasios asociados.',
        '3' => 'Licencia no encontrada.'
    ];
    $error_message = $messages[$_GET['error']] ?? '';
}

// Generar CSRF token
if(!isset($_SESSION['csrf_token_licencias'])){
    $_SESSION['csrf_token_licencias'] = bin2hex(random_bytes(32));
}

$superadmin_nombre = htmlspecialchars($_SESSION['superadmin_nombre'] ?? 'SuperAdmin', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="description" content="Gestión de Licencias">
<meta name="robots" content="noindex, nofollow">
<title>Licencias - SuperAdmin</title>

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
        --primary-dark: #5568d3;
        --secondary: #764ba2;
        --success: #51cf66;
        --danger: #ff6b6b;
        --warning: #ffd43b;
        --info: #4dabf7;
        --dark: #0a0e27;
        --dark-light: #1a1f3a;
        --sidebar-width: 280px;
        --navbar-height: 70px;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
        color: #fff;
        overflow-x: hidden;
    }

    .navbar-custom {
        position: fixed;
        top: 0;
        left: var(--sidebar-width);
        right: 0;
        height: var(--navbar-height);
        background: rgba(10, 14, 39, 0.95);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 0 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        z-index: 999;
    }

    .navbar-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .navbar-left h2 {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
        background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .mobile-menu-btn {
        display: none;
        background: rgba(255, 255, 255, 0.1);
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        color: #fff;
        font-size: 1.2rem;
        cursor: pointer;
    }

    .btn-add {
        padding: 0.7rem 1.5rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border: none;
        border-radius: 12px;
        color: #fff;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
    }

    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        color: #fff;
    }

    .main-content {
        margin-left: var(--sidebar-width);
        margin-top: var(--navbar-height);
        padding: 2rem;
        min-height: calc(100vh - var(--navbar-height));
    }

    .alert-custom {
        padding: 1rem 1.5rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        animation: slideDown 0.4s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert-success {
        background: rgba(81, 207, 102, 0.1);
        border: 1px solid rgba(81, 207, 102, 0.3);
        color: var(--success);
    }

    .alert-danger {
        background: rgba(255, 107, 107, 0.1);
        border: 1px solid rgba(255, 107, 107, 0.3);
        color: var(--danger);
    }

    .licenses-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .license-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 2rem;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .license-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .license-card.inactive {
        opacity: 0.6;
        border-color: rgba(255, 107, 107, 0.3);
    }

    .license-header {
        text-align: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .license-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-bottom: 1rem;
    }

    .license-name {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .license-price {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--success);
        margin-bottom: 0.3rem;
    }

    .license-duration {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
    }

    .license-details {
        margin-bottom: 2rem;
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
        background: rgba(102, 126, 234, 0.1);
        border-radius: 8px;
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

    .license-status {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .status-active {
        background: rgba(81, 207, 102, 0.2);
        color: var(--success);
    }

    .status-inactive {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
    }

    .license-actions {
        display: flex;
        gap: 0.8rem;
    }

    .btn-action {
        flex: 1;
        padding: 0.8rem;
        border: none;
        border-radius: 12px;
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
        border: 1px solid rgba(77, 171, 247, 0.3);
    }

    .btn-edit:hover {
        background: rgba(77, 171, 247, 0.3);
    }

    .btn-delete {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
        border: 1px solid rgba(255, 107, 107, 0.3);
    }

    .btn-delete:hover {
        background: rgba(255, 107, 107, 0.3);
    }

    .btn-toggle {
        background: rgba(255, 212, 59, 0.2);
        color: var(--warning);
        border: 1px solid rgba(255, 212, 59, 0.3);
    }

    .btn-toggle:hover {
        background: rgba(255, 212, 59, 0.3);
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(5px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
    }

    .modal.active {
        display: flex;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background: rgba(26, 31, 58, 0.98);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 25px;
        padding: 2.5rem;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        animation: slideUp 0.4s ease;
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .modal-title {
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }

    .btn-close-modal {
        background: none;
        border: none;
        color: rgba(255, 255, 255, 0.5);
        font-size: 1.5rem;
        cursor: pointer;
        transition: color 0.3s ease;
    }

    .btn-close-modal:hover {
        color: #fff;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 0.6rem;
        color: rgba(255, 255, 255, 0.9);
    }

    .required {
        color: var(--danger);
    }

    .form-control {
        width: 100%;
        padding: 0.9rem 1rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        color: #fff;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        outline: none;
        background: rgba(255, 255, 255, 0.08);
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-control::placeholder {
        color: rgba(255, 255, 255, 0.3);
    }

    .form-select {
        cursor: pointer;
    }

    .form-help {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.5);
        margin-top: 0.3rem;
    }

    .btn-submit {
        width: 100%;
        padding: 1rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border: none;
        border-radius: 12px;
        color: #fff;
        font-weight: 700;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 1rem;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }

    .btn-submit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 20px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .empty-icon {
        font-size: 4rem;
        color: rgba(255, 255, 255, 0.2);
        margin-bottom: 1.5rem;
    }

    @media (max-width: 1024px) {
        :root {
            --sidebar-width: 0;
        }

        .navbar-custom {
            left: 0;
        }

        .main-content {
            margin-left: 0;
        }

        .mobile-menu-btn {
            display: block !important;
        }

        .licenses-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
</head>
<body>

<?php include_once('sidebar.php'); ?>

<nav class="navbar-custom">
    <div class="navbar-left">
        <button class="mobile-menu-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h2><i class="fas fa-certificate me-2"></i>Gestión de Licencias</h2>
    </div>
    <div>
        <button class="btn-add" onclick="openModal('create')">
            <i class="fas fa-plus"></i>
            Nueva Licencia
        </button>
    </div>
</nav>

<main class="main-content">
    
    <?php if(!empty($success_message)): ?>
        <div class="alert-custom alert-success">
            <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
            <div><?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <?php endif; ?>

    <?php if(!empty($error_message)): ?>
        <div class="alert-custom alert-danger">
            <i class="fas fa-exclamation-circle" style="font-size: 1.5rem;"></i>
            <div><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <?php endif; ?>

    <?php if(count($licencias) > 0): ?>
        <div class="licenses-grid">
            <?php foreach($licencias as $lic): ?>
                <div class="license-card <?= $lic['estado'] === 'inactivo' ? 'inactive' : '' ?>">
                    <span class="license-status status-<?= $lic['estado'] === 'activo' ? 'active' : 'inactive' ?>">
                        <?= $lic['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?>
                    </span>

                    <div class="license-header">
                        <div class="license-icon">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <div class="license-name"><?= htmlspecialchars($lic['nombre'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="license-price">$<?= number_format($lic['precio'], 0, ',', '.') ?></div>
                        <div class="license-duration"><?= $lic['dias'] ?> días de duración</div>
                    </div>

                    <div class="license-details">
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="detail-text">
                                <div class="detail-label">Duración</div>
                                <div class="detail-value"><?= $lic['dias'] ?> días</div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="detail-text">
                                <div class="detail-label">Precio por día</div>
                                <div class="detail-value">$<?= number_format($lic['precio'] / $lic['dias'], 2, ',', '.') ?></div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-dumbbell"></i>
                            </div>
                            <div class="detail-text">
                                <div class="detail-label">Gimnasios Activos</div>
                                <div class="detail-value"><?= $lic['gimnasios_con_licencia'] ?> gimnasios</div>
                            </div>
                        </div>
                    </div>

                    <div class="license-actions">
                        <button class="btn-action btn-edit" onclick='editLicense(<?= json_encode($lic, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                            <i class="fas fa-edit"></i>
                            Editar
                        </button>
                        <button class="btn-action btn-toggle" onclick="toggleLicense(<?= $lic['id'] ?>, '<?= $lic['estado'] ?>')">
                            <i class="fas fa-power-off"></i>
                            <?= $lic['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-certificate"></i></div>
            <h3>No hay licencias registradas</h3>
            <p style="color: rgba(255, 255, 255, 0.6); margin-top: 1rem;">
                Crea tu primera licencia para comenzar
            </p>
            <button class="btn-add" onclick="openModal('create')" style="margin-top: 2rem;">
                <i class="fas fa-plus"></i>
                Crear Primera Licencia
            </button>
        </div>
    <?php endif; ?>

</main>

<!-- Modal Crear/Editar -->
<div class="modal" id="licenseModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">
                <i class="fas fa-plus-circle"></i>
                Nueva Licencia
            </h3>
            <button class="btn-close-modal" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="licenseForm" action="<?= $base_url ?>/superadmin/procesar_licencia.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token_licencias'] ?>">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="licenseId" value="">

            <div class="form-group">
                <label class="form-label" for="nombre">
                    Nombre del Plan <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="nombre" 
                    name="nombre" 
                    class="form-control" 
                    placeholder="Ej: Plan Básico"
                    required
                    maxlength="100"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="precio">
                    Precio <span class="required">*</span>
                </label>
                <input 
                    type="number" 
                    id="precio" 
                    name="precio" 
                    class="form-control" 
                    placeholder="15000"
                    required
                    min="0"
                    step="0.01"
                >
                <small class="form-help">
                    <i class="fas fa-info-circle"></i> Precio en pesos argentinos
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="dias">
                    Duración en Días <span class="required">*</span>
                </label>
                <input 
                    type="number" 
                    id="dias" 
                    name="dias" 
                    class="form-control" 
                    placeholder="30"
                    required
                    min="1"
                    max="365"
                >
                <small class="form-help">
                    <i class="fas fa-info-circle"></i> Cantidad de días de validez
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="estado">
                    Estado <span class="required">*</span>
                </label>
                <select id="estado" name="estado" class="form-control form-select" required>
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-save me-2"></i>
                <span id="btnText">Crear Licencia</span>
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
    }

    function openModal(action, license = null) {
        const modal = document.getElementById('licenseModal');
        const form = document.getElementById('licenseForm');
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const btnText = document.getElementById('btnText');

        form.reset();

        if (action === 'create') {
            modalTitle.innerHTML = '<i class="fas fa-plus-circle"></i> Nueva Licencia';
            formAction.value = 'create';
            btnText.textContent = 'Crear Licencia';
            document.getElementById('licenseId').value = '';
        } else if (action === 'edit' && license) {
            modalTitle.innerHTML = '<i class="fas fa-edit"></i> Editar Licencia';
            formAction.value = 'edit';
            btnText.textContent = 'Actualizar Licencia';
            document.getElementById('licenseId').value = license.id;
            document.getElementById('nombre').value = license.nombre;
            document.getElementById('precio').value = license.precio;
            document.getElementById('dias').value = license.dias;
            document.getElementById('estado').value = license.estado;
        }

        modal.classList.add('active');
    }

    function closeModal() {
        document.getElementById('licenseModal').classList.remove('active');
    }

    function editLicense(license) {
        openModal('edit', license);
    }

    function toggleLicense(id, currentState) {
        const newState = currentState === 'activo' ? 'inactivo' : 'activo';
        const action = newState === 'activo' ? 'activar' : 'desactivar';
        
        if(confirm(`¿Estás seguro de ${action} esta licencia?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= $base_url ?>/superadmin/procesar_licencia.php';
            
            const fields = {
                'csrf_token': '<?= $_SESSION['csrf_token_licencias'] ?>',
                'action': 'toggle',
                'id': id,
                'estado': newState
            };
            
            for (let key in fields) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Cerrar modal al hacer click fuera
    document.getElementById('licenseModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Auto-dismiss alerts
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert-custom');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 5000);

    // Validación del formulario
    document.getElementById('licenseForm').addEventListener('submit', function(e) {
        const precio = parseFloat(document.getElementById('precio').value);
        const dias = parseInt(document.getElementById('dias').value);

        if (precio <= 0) {
            e.preventDefault();
            alert('El precio debe ser mayor a 0');
            return false;
        }

        if (dias < 1 || dias > 365) {
            e.preventDefault();
            alert('La duración debe estar entre 1 y 365 días');
            return false;
        }

        // Loading state
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Procesando...';
    });

    // Protección contra clickjacking
    if (window.top !== window.self) {
        window.top.location = window.self.location;
    }

    // Timeout de sesión
    let sessionTimeout;
    const TIMEOUT_DURATION = 1800000;

    function resetSessionTimeout() {
        clearTimeout(sessionTimeout);
        sessionTimeout = setTimeout(function() {
            alert('Tu sesión ha expirado por inactividad.');
            window.location.href = '<?= $base_url ?>/superadmin/logout.php';
        }, TIMEOUT_DURATION);
    }

    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function(event) {
        document.addEventListener(event, resetSessionTimeout, true);
    });

    resetSessionTimeout();
</script>

</body>
</html>
