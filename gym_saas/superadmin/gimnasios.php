<?php
/**
 * Gestión de Gimnasios - SuperAdmin
 * Listado completo y administración de gimnasios registrados
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

// Filtros
$filtro_estado = $_GET['estado'] ?? '';
$filtro_licencia = $_GET['licencia'] ?? '';
$busqueda = $_GET['buscar'] ?? '';

// Construir query con filtros
$query = "SELECT g.*, 
          l.nombre as licencia_nombre, 
          l.precio as licencia_precio,
          DATEDIFF(g.fecha_fin, CURDATE()) as dias_restantes,
          DATE_FORMAT(g.creado, '%d/%m/%Y') as fecha_registro,
          (SELECT COUNT(*) FROM socios s WHERE s.gimnasio_id = g.id AND s.estado = 'activo') as total_socios
          FROM gimnasios g
          LEFT JOIN licencias l ON g.licencia_id = l.id
          WHERE 1=1";

if (!empty($filtro_estado)) {
    $query .= " AND g.estado = '" . $conn->real_escape_string($filtro_estado) . "'";
}

if (!empty($filtro_licencia)) {
    $query .= " AND g.licencia_id = " . (int)$filtro_licencia;
}

if (!empty($busqueda)) {
    $search = $conn->real_escape_string($busqueda);
    $query .= " AND (g.nombre LIKE '%$search%' OR g.email LIKE '%$search%' OR g.localidad LIKE '%$search%')";
}

$query .= " ORDER BY g.creado DESC";

$result = $conn->query($query);
$gimnasios = $result->fetch_all(MYSQLI_ASSOC);

// Obtener licencias para filtro
$stmt = $conn->prepare("SELECT id, nombre FROM licencias WHERE estado = 'activo' ORDER BY precio ASC");
$stmt->execute();
$licencias_disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Estadísticas rápidas
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM gimnasios WHERE estado = 'activo'");
$stmt->execute();
$total_activos = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM gimnasios WHERE estado = 'suspendido'");
$stmt->execute();
$total_suspendidos = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM gimnasios WHERE DATEDIFF(fecha_fin, CURDATE()) <= 7 AND estado = 'activo'");
$stmt->execute();
$total_por_vencer = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Mensajes
$success_message = '';
$error_message = '';

if(isset($_GET['success'])){
    $messages = [
        '1' => '¡Gimnasio creado exitosamente!',
        '2' => '¡Gimnasio actualizado correctamente!',
        '3' => '¡Estado actualizado correctamente!'
    ];
    $success_message = $messages[$_GET['success']] ?? '';
}

if(isset($_GET['error'])){
    $messages = [
        '1' => 'Error al procesar la solicitud.',
        '2' => 'El email ya está registrado.',
        '3' => 'Gimnasio no encontrado.'
    ];
    $error_message = $messages[$_GET['error']] ?? '';
}

$superadmin_nombre = htmlspecialchars($_SESSION['superadmin_nombre'] ?? 'SuperAdmin', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gimnasios - SuperAdmin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--primary:#667eea;--secondary:#764ba2;--success:#51cf66;--danger:#ff6b6b;--warning:#ffd43b;--info:#4dabf7;--sidebar-width:280px;--navbar-height:70px;}
body{font-family:'Poppins',sans-serif;background:linear-gradient(135deg,#0a0e27 0%,#1a1f3a 100%);color:#fff;overflow-x:hidden;}
.navbar-custom{position:fixed;top:0;left:var(--sidebar-width);right:0;height:var(--navbar-height);background:rgba(10,14,39,0.95);backdrop-filter:blur(10px);border-bottom:1px solid rgba(255,255,255,0.1);padding:0 2rem;display:flex;align-items:center;justify-content:space-between;z-index:999;}
.navbar-left{display:flex;align-items:center;gap:1rem;}
.navbar-left h2{font-size:1.5rem;font-weight:700;margin:0;background:linear-gradient(135deg,#fff 0%,var(--primary) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.mobile-menu-btn{display:none;background:rgba(255,255,255,0.1);border:none;width:40px;height:40px;border-radius:8px;color:#fff;font-size:1.2rem;cursor:pointer;}
.btn-add{padding:0.7rem 1.5rem;background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);border:none;border-radius:12px;color:#fff;font-weight:600;cursor:pointer;transition:all 0.3s ease;text-decoration:none;display:inline-flex;align-items:center;gap:0.6rem;}
.btn-add:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(102,126,234,0.4);color:#fff;}
.main-content{margin-left:var(--sidebar-width);margin-top:var(--navbar-height);padding:2rem;min-height:calc(100vh - var(--navbar-height));}
.alert-custom{padding:1rem 1.5rem;border-radius:15px;margin-bottom:2rem;display:flex;align-items:center;gap:1rem;animation:slideDown 0.4s ease;}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
.alert-success{background:rgba(81,207,102,0.1);border:1px solid rgba(81,207,102,0.3);color:var(--success);}
.alert-danger{background:rgba(255,107,107,0.1);border:1px solid rgba(255,107,107,0.3);color:var(--danger);}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;margin-bottom:2rem;}
.stat-card{background:rgba(255,255,255,0.05);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.1);border-radius:15px;padding:1.5rem;display:flex;align-items:center;gap:1.2rem;}
.stat-icon{width:55px;height:55px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;}
.stat-icon.primary{background:linear-gradient(135deg,var(--primary) 0%,#5568d3 100%);}
.stat-icon.danger{background:linear-gradient(135deg,#ff6b6b 0%,#fa5252 100%);}
.stat-icon.warning{background:linear-gradient(135deg,#ffd43b 0%,#fab005 100%);}
.stat-content{flex:1;}
.stat-value{font-size:1.8rem;font-weight:800;margin-bottom:0.2rem;}
.stat-label{font-size:0.85rem;color:rgba(255,255,255,0.6);}
.filters-section{background:rgba(255,255,255,0.05);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.1);border-radius:15px;padding:1.5rem;margin-bottom:2rem;}
.filters-grid{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:1rem;align-items:end;}
.filter-group{display:flex;flex-direction:column;gap:0.5rem;}
.filter-label{font-size:0.85rem;font-weight:600;color:rgba(255,255,255,0.8);}
.filter-input{padding:0.8rem 1rem;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;font-size:0.9rem;}
.filter-input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(102,126,234,0.1);}
.btn-filter{padding:0.8rem 1.5rem;background:var(--primary);border:none;border-radius:10px;color:#fff;font-weight:600;cursor:pointer;transition:all 0.3s ease;}
.btn-filter:hover{background:#5568d3;}
.btn-clear{padding:0.8rem 1rem;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:rgba(255,255,255,0.7);font-weight:600;cursor:pointer;transition:all 0.3s ease;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;}
.btn-clear:hover{background:rgba(255,255,255,0.1);color:#fff;}
.table-container{background:rgba(255,255,255,0.05);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.1);border-radius:20px;overflow:hidden;}
.table-header{padding:1.5rem 2rem;border-bottom:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:space-between;}
.table-title{font-size:1.2rem;font-weight:700;display:flex;align-items:center;gap:0.8rem;}
.table-title i{color:var(--primary);}
.table-responsive{overflow-x:auto;}
.gyms-table{width:100%;border-collapse:collapse;}
.gyms-table thead tr{background:rgba(255,255,255,0.03);}
.gyms-table th{padding:1.2rem 1.5rem;text-align:left;font-size:0.85rem;font-weight:700;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid rgba(255,255,255,0.1);}
.gyms-table td{padding:1.2rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.05);vertical-align:middle;}
.gyms-table tbody tr{transition:background 0.3s ease;}
.gyms-table tbody tr:hover{background:rgba(255,255,255,0.03);}
.gym-info{display:flex;align-items:center;gap:1rem;}
.gym-avatar{width:45px;height:45px;background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);border-radius:12px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;flex-shrink:0;}
.gym-details{flex:1;}
.gym-name{font-weight:600;font-size:0.95rem;margin-bottom:0.2rem;}
.gym-location{font-size:0.8rem;color:rgba(255,255,255,0.5);}
.badge{padding:0.4rem 0.9rem;border-radius:50px;font-size:0.75rem;font-weight:700;display:inline-block;}
.badge-active{background:rgba(81,207,102,0.2);color:var(--success);}
.badge-suspended{background:rgba(255,107,107,0.2);color:var(--danger);}
.badge-warning{background:rgba(255,212,59,0.2);color:var(--warning);}
.table-actions{display:flex;gap:0.5rem;}
.btn-icon{width:35px;height:35px;border:none;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.3s ease;}
.btn-view{background:rgba(77,171,247,0.2);color:var(--info);}
.btn-view:hover{background:rgba(77,171,247,0.3);}
.btn-edit{background:rgba(255,212,59,0.2);color:var(--warning);}
.btn-edit:hover{background:rgba(255,212,59,0.3);}
.btn-toggle{background:rgba(255,107,107,0.2);color:var(--danger);}
.btn-toggle:hover{background:rgba(255,107,107,0.3);}
.empty-state{text-align:center;padding:4rem 2rem;}
.empty-icon{font-size:4rem;color:rgba(255,255,255,0.2);margin-bottom:1.5rem;}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);backdrop-filter:blur(5px);z-index:9999;align-items:center;justify-content:center;}
.modal-content{background:rgba(26,31,58,0.98);border:1px solid rgba(255,255,255,0.1);border-radius:25px;padding:2.5rem;max-width:600px;width:90%;max-height:90vh;overflow-y:auto;}
.modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;padding-bottom:1rem;border-bottom:1px solid rgba(255,255,255,0.1);}
.modal-title{font-size:1.5rem;font-weight:700;display:flex;align-items:center;gap:0.8rem;margin:0;}
.btn-close-modal{background:none;border:none;color:rgba(255,255,255,0.5);font-size:1.5rem;cursor:pointer;}
@media(max-width:1024px){:root{--sidebar-width:0;}.navbar-custom{left:0;}.main-content{margin-left:0;}.mobile-menu-btn{display:block!important;}.filters-grid{grid-template-columns:1fr;}.stats-row{grid-template-columns:1fr;}}
</style>
</head>
<body>
<?php include_once('sidebar.php'); ?>
<nav class="navbar-custom">
<div class="navbar-left">
<button class="mobile-menu-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
<h2><i class="fas fa-dumbbell me-2"></i>Gestión de Gimnasios</h2>
</div>
<div><a href="<?= $base_url ?>/superadmin/editar_gimnasio.php" class="btn-add"><i class="fas fa-plus"></i>Nuevo Gimnasio</a></div>
</nav>
<main class="main-content">
<?php if(!empty($success_message)): ?>
<div class="alert-custom alert-success"><i class="fas fa-check-circle" style="font-size:1.5rem;"></i><div><?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?></div></div>
<?php endif; ?>
<?php if(!empty($error_message)): ?>
<div class="alert-custom alert-danger"><i class="fas fa-exclamation-circle" style="font-size:1.5rem;"></i><div><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div></div>
<?php endif; ?>
<div class="stats-row">
<div class="stat-card"><div class="stat-icon primary"><i class="fas fa-dumbbell"></i></div><div class="stat-content"><div class="stat-value"><?= $total_activos ?></div><div class="stat-label">Gimnasios Activos</div></div></div>
<div class="stat-card"><div class="stat-icon danger"><i class="fas fa-ban"></i></div><div class="stat-content"><div class="stat-value"><?= $total_suspendidos ?></div><div class="stat-label">Suspendidos</div></div></div>
<div class="stat-card"><div class="stat-icon warning"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-content"><div class="stat-value"><?= $total_por_vencer ?></div><div class="stat-label">Por Vencer</div></div></div>
</div>
<div class="filters-section">
<form method="GET" action="">
<div class="filters-grid">
<div class="filter-group"><label class="filter-label">Buscar</label><input type="text" name="buscar" class="filter-input" placeholder="Nombre, email o localidad..." value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>"></div>
<div class="filter-group"><label class="filter-label">Estado</label><select name="estado" class="filter-input"><option value="">Todos</option><option value="activo" <?= $filtro_estado === 'activo' ? 'selected' : '' ?>>Activo</option><option value="suspendido" <?= $filtro_estado === 'suspendido' ? 'selected' : '' ?>>Suspendido</option></select></div>
<div class="filter-group"><label class="filter-label">Licencia</label><select name="licencia" class="filter-input"><option value="">Todas</option><?php foreach($licencias_disponibles as $lic): ?><option value="<?= $lic['id'] ?>" <?= $filtro_licencia == $lic['id'] ? 'selected' : '' ?>><?= htmlspecialchars($lic['nombre'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
<div class="filter-group" style="display:flex;gap:0.5rem;"><button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filtrar</button><a href="?" class="btn-clear"><i class="fas fa-times"></i></a></div>
</div>
</form>
</div>
<div class="table-container">
<div class="table-header"><div class="table-title"><i class="fas fa-list"></i>Listado de Gimnasios (<?= count($gimnasios) ?>)</div></div>
<?php if(count($gimnasios) > 0): ?>
<div class="table-responsive">
<table class="gyms-table">
<thead><tr><th>Gimnasio</th><th>Licencia</th><th>Socios</th><th>Vencimiento</th><th>Estado</th><th style="text-align:center;">Acciones</th></tr></thead>
<tbody>
<?php foreach($gimnasios as $gym): ?>
<tr>
<td><div class="gym-info"><div class="gym-avatar"><?= strtoupper(substr($gym['nombre'], 0, 2)) ?></div><div class="gym-details"><div class="gym-name"><?= htmlspecialchars($gym['nombre'], ENT_QUOTES, 'UTF-8') ?></div><div class="gym-location"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($gym['localidad'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div></div></div></td>
<td><?php if($gym['licencia_nombre']): ?><div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($gym['licencia_nombre'], ENT_QUOTES, 'UTF-8') ?></div><div style="font-size:0.8rem;color:rgba(255,255,255,0.5);">$<?= number_format($gym['licencia_precio'], 0, ',', '.') ?></div><?php else: ?><span style="color:rgba(255,255,255,0.4);">Sin licencia</span><?php endif; ?></td>
<td><span style="font-weight:600;font-size:1.1rem;"><?= $gym['total_socios'] ?></span> <span style="font-size:0.8rem;color:rgba(255,255,255,0.5);">socios</span></td>
<td><?php if($gym['fecha_fin']): ?><?php if($gym['dias_restantes'] <= 0): ?><span class="badge badge-suspended"><i class="fas fa-times-circle"></i> Vencida</span><?php elseif($gym['dias_restantes'] <= 7): ?><span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> <?= $gym['dias_restantes'] ?> días</span><?php else: ?><span style="font-size:0.9rem;"><?= $gym['dias_restantes'] ?> días</span><?php endif; ?><?php else: ?><span style="color:rgba(255,255,255,0.4);">N/A</span><?php endif; ?></td>
<td><span class="badge badge-<?= $gym['estado'] === 'activo' ? 'active' : 'suspended' ?>"><?= ucfirst($gym['estado']) ?></span></td>
<td><div class="table-actions" style="justify-content:center;"><button class="btn-icon btn-view" onclick="viewGym(<?= $gym['id'] ?>)" title="Ver detalles"><i class="fas fa-eye"></i></button><a href="<?= $base_url ?>/superadmin/editar_gimnasio.php?id=<?= $gym['id'] ?>" class="btn-icon btn-edit" title="Editar"><i class="fas fa-edit"></i></a><button class="btn-icon btn-toggle" onclick="toggleGym(<?= $gym['id'] ?>, '<?= $gym['estado'] ?>')" title="<?= $gym['estado'] === 'activo' ? 'Suspender' : 'Activar' ?>"><i class="fas fa-<?= $gym['estado'] === 'activo' ? 'ban' : 'check' ?>"></i></button></div></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="empty-state"><div class="empty-icon"><i class="fas fa-dumbbell"></i></div><h3>No se encontraron gimnasios</h3><p style="color:rgba(255,255,255,0.6);margin-top:1rem;"><?php if(!empty($busqueda) || !empty($filtro_estado) || !empty($filtro_licencia)): ?>Intenta ajustar los filtros de búsqueda<?php else: ?>Crea tu primer gimnasio para comenzar<?php endif; ?></p></div>
<?php endif; ?>
</div>
</main>
<div class="modal" id="viewModal" style="display:none;"><div class="modal-content"><div class="modal-header"><h3 class="modal-title"><i class="fas fa-info-circle" style="color:var(--info);"></i> Detalles del Gimnasio</h3><button onclick="closeViewModal()" style="background:none;border:none;color:rgba(255,255,255,0.5);font-size:1.5rem;cursor:pointer;"><i class="fas fa-times"></i></button></div><div id="modalContent"></div></div></div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('active');}
function viewGym(id){const gyms=<?= json_encode($gimnasios, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;const gym=gyms.find(g=>g.id==id);if(!gym)return;const content=`<div style="display:grid;gap:1.5rem;"><div style="text-align:center;padding:1.5rem;background:rgba(255,255,255,0.03);border-radius:15px;"><div style="width:80px;height:80px;background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:1rem;">${gym.nombre.substring(0,2).toUpperCase()}</div><h4 style="font-size:1.3rem;font-weight:700;margin-bottom:0.5rem;">${gym.nombre}</h4><div style="font-size:0.9rem;color:rgba(255,255,255,0.6);"><i class="fas fa-envelope"></i> ${gym.email||'N/A'}</div></div><div style="display:grid;gap:1rem;"><div style="display:flex;justify-content:space-between;padding:1rem;background:rgba(255,255,255,0.03);border-radius:12px;"><span style="color:rgba(255,255,255,0.6);">Dirección:</span><span style="font-weight:600;">${gym.direccion||'N/A'} ${gym.altura||''}</span></div><div style="display:flex;justify-content:space-between;padding:1rem;background:rgba(255,255,255,0.03);border-radius:12px;"><span style="color:rgba(255,255,255,0.6);">Localidad:</span><span style="font-weight:600;">${gym.localidad||'N/A'}</span></div><div style="display:flex;justify-content:space-between;padding:1rem;background:rgba(255,255,255,0.03);border-radius:12px;"><span style="color:rgba(255,255,255,0.6);">Partido:</span><span style="font-weight:600;">${gym.partido||'N/A'}</span></div><div style="display:flex;justify-content:space-between;padding:1rem;background:rgba(255,255,255,0.03);border-radius:12px;"><span style="color:rgba(255,255,255,0.6);">Licencia:</span><span style="font-weight:600;">${gym.licencia_nombre||'Sin licencia'}</span></div><div style="display:flex;justify-content:space-between;padding:1rem;background:rgba(255,255,255,0.03);border-radius:12px;"><span style="color:rgba(255,255,255,0.6);">Total Socios:</span><span style="font-weight:600;color:var(--success);">${gym.total_socios}</span></div><div style="display:flex;justify-content:space-between;padding:1rem;background:rgba(255,255,255,0.03);border-radius:12px;"><span style="color:rgba(255,255,255,0.6);">Estado:</span><span class="badge badge-${gym.estado==='activo'?'active':'suspended'}">${gym.estado}</span></div><div style="display:flex;justify-content:space-between;padding:1rem;background:rgba(255,255,255,0.03);border-radius:12px;"><span style="color:rgba(255,255,255,0.6);">Fecha Registro:</span><span style="font-weight:600;">${gym.fecha_registro}</span></div></div></div>`;document.getElementById('modalContent').innerHTML=content;document.getElementById('viewModal').style.display='flex';}
function closeViewModal(){document.getElementById('viewModal').style.display='none';}
function toggleGym(id,currentState){const newState=currentState==='activo'?'suspendido':'activo';const action=newState==='activo'?'activar':'suspender';if(confirm(`¿Estás seguro de ${action} este gimnasio?`)){const form=document.createElement('form');form.method='POST';form.action='<?= $base_url ?>/superadmin/procesar_gimnasio.php';const fields={'action':'toggle','id':id,'estado':newState};for(let key in fields){const input=document.createElement('input');input.type='hidden';input.name=key;input.value=fields[key];form.appendChild(input);}document.body.appendChild(form);form.submit();}}
document.getElementById('viewModal').addEventListener('click',function(e){if(e.target===this){closeViewModal();}});
setTimeout(function(){const alerts=document.querySelectorAll('.alert-custom');alerts.forEach(function(alert){alert.style.transition='opacity 0.5s ease';alert.style.opacity='0';setTimeout(function(){alert.remove();},500);});},5000);
if(window.top!==window.self){window.top.location=window.self.location;}
let sessionTimeout;const TIMEOUT_DURATION=1800000;function resetSessionTimeout(){clearTimeout(sessionTimeout);sessionTimeout=setTimeout(function(){alert('Tu sesión ha expirado por inactividad.');window.location.href='<?= $base_url ?>/superadmin/logout.php';},TIMEOUT_DURATION);}
['mousedown','keydown','scroll','touchstart'].forEach(function(event){document.addEventListener(event,resetSessionTimeout,true);});resetSessionTimeout();
</script>
</body>
</html>
