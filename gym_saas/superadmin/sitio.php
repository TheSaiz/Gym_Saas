<?php
/**
 * Edición del Sitio Público - SuperAdmin
 * Configuración de contenido, logos y textos del sitio principal
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

// Obtener configuración actual
$stmt = $conn->prepare("SELECT clave, valor FROM config");
$stmt->execute();
$result = $stmt->get_result();

$config = [];
while($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}
$stmt->close();

// Valores por defecto
$nombre_sitio = $config['nombre_sitio'] ?? 'Gimnasio System SAAS';
$contacto_email = $config['contacto_email'] ?? 'info@sistema.com';
$contacto_telefono = $config['contacto_telefono'] ?? '+54 9 11 9999 9999';
$footer_texto = $config['footer_texto'] ?? '© 2025 Gimnasio System SAAS';
$hero_titulo = $config['hero_titulo'] ?? 'Gestiona tu Gimnasio de forma Inteligente';
$hero_subtitulo = $config['hero_subtitulo'] ?? 'Sistema integral para la administración de gimnasios';
$descripcion_sistema = $config['descripcion_sistema'] ?? 'Plataforma completa para gestionar tu gimnasio';

// Mensajes
$success_message = '';
$error_message = '';

if(isset($_GET['success']) && $_GET['success'] == '1'){
    $success_message = '¡Configuración actualizada correctamente!';
}

if(isset($_GET['error'])){
    $messages = [
        '1' => 'Error al actualizar la configuración.',
        '2' => 'Error al subir los archivos.',
    ];
    $error_message = $messages[$_GET['error']] ?? 'Error desconocido.';
}

// Generar CSRF token
if(!isset($_SESSION['csrf_token_sitio'])){
    $_SESSION['csrf_token_sitio'] = bin2hex(random_bytes(32));
}

$superadmin_nombre = htmlspecialchars($_SESSION['superadmin_nombre'] ?? 'SuperAdmin', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sitio Web - SuperAdmin</title>
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
.btn-preview{padding:0.7rem 1.5rem;background:rgba(77,171,247,0.2);border:1px solid rgba(77,171,247,0.3);border-radius:12px;color:var(--info);font-weight:600;cursor:pointer;transition:all 0.3s ease;text-decoration:none;display:inline-flex;align-items:center;gap:0.6rem;}
.btn-preview:hover{background:rgba(77,171,247,0.3);color:var(--info);}
.main-content{margin-left:var(--sidebar-width);margin-top:var(--navbar-height);padding:2rem;min-height:calc(100vh - var(--navbar-height));}
.alert-custom{padding:1rem 1.5rem;border-radius:15px;margin-bottom:2rem;display:flex;align-items:center;gap:1rem;animation:slideDown 0.4s ease;}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
.alert-success{background:rgba(81,207,102,0.1);border:1px solid rgba(81,207,102,0.3);color:var(--success);}
.alert-danger{background:rgba(255,107,107,0.1);border:1px solid rgba(255,107,107,0.3);color:var(--danger);}
.form-container{max-width:900px;margin:0 auto;}
.form-card{background:rgba(255,255,255,0.05);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:2.5rem;margin-bottom:2rem;}
.section-title{font-size:1.3rem;font-weight:700;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.8rem;padding-bottom:1rem;border-bottom:1px solid rgba(255,255,255,0.1);}
.section-title i{color:var(--primary);}
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem;margin-bottom:1.5rem;}
.form-group{display:flex;flex-direction:column;}
.form-label{font-weight:600;font-size:0.9rem;color:rgba(255,255,255,0.9);margin-bottom:0.6rem;display:flex;align-items:center;gap:0.3rem;}
.required{color:var(--danger);}
.form-control{width:100%;padding:0.9rem 1rem;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:12px;color:#fff;font-size:0.95rem;transition:all 0.3s ease;}
.form-control:focus{outline:none;background:rgba(255,255,255,0.08);border-color:var(--primary);box-shadow:0 0 0 3px rgba(102,126,234,0.1);}
.form-control::placeholder{color:rgba(255,255,255,0.3);}
textarea.form-control{min-height:120px;resize:vertical;}
.form-help{font-size:0.8rem;color:rgba(255,255,255,0.5);margin-top:0.3rem;}
.btn-submit{width:100%;padding:1.1rem;background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);border:none;border-radius:12px;color:#fff;font-weight:700;font-size:1rem;cursor:pointer;transition:all 0.3s ease;margin-top:2rem;display:flex;align-items:center;justify-content:center;gap:0.6rem;}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 10px 25px rgba(102,126,234,0.4);}
.btn-submit:disabled{opacity:0.6;cursor:not-allowed;transform:none;}
.info-box{background:rgba(77,171,247,0.1);border:1px solid rgba(77,171,247,0.3);border-radius:12px;padding:1rem;margin-bottom:2rem;display:flex;gap:1rem;}
.info-box i{color:var(--info);font-size:1.2rem;}
.info-text{font-size:0.9rem;color:rgba(255,255,255,0.8);line-height:1.6;}
@media(max-width:1024px){:root{--sidebar-width:0;}.navbar-custom{left:0;}.main-content{margin-left:0;}.mobile-menu-btn{display:block!important;}.form-row{grid-template-columns:1fr;}}
</style>
</head>
<body>
<?php include_once('sidebar.php'); ?>
<nav class="navbar-custom">
<div class="navbar-left">
<button class="mobile-menu-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
<h2><i class="fas fa-globe me-2"></i>Configuración del Sitio</h2>
</div>
<div><a href="<?= $base_url ?>/index.php" target="_blank" class="btn-preview"><i class="fas fa-external-link-alt"></i>Vista Previa</a></div>
</nav>
<main class="main-content">
<?php if(!empty($success_message)): ?>
<div class="alert-custom alert-success"><i class="fas fa-check-circle" style="font-size:1.5rem;"></i><div><?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?></div></div>
<?php endif; ?>
<?php if(!empty($error_message)): ?>
<div class="alert-custom alert-danger"><i class="fas fa-exclamation-circle" style="font-size:1.5rem;"></i><div><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div></div>
<?php endif; ?>
<div class="form-container">
<div class="info-box"><i class="fas fa-info-circle"></i><div class="info-text">Configura el contenido y la apariencia del sitio web público. Los cambios se reflejarán inmediatamente en la página principal.</div></div>
<form action="<?= $base_url ?>/superadmin/procesar_config.php" method="POST" enctype="multipart/form-data" id="siteForm">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token_sitio'] ?>">
<input type="hidden" name="action" value="sitio">
<div class="form-card">
<h3 class="section-title"><i class="fas fa-info-circle"></i>Información General</h3>
<div class="form-row">
<div class="form-group" style="grid-column:1/-1;">
<label class="form-label" for="nombre_sitio">Nombre del Sitio <span class="required">*</span></label>
<input type="text" id="nombre_sitio" name="nombre_sitio" class="form-control" value="<?= htmlspecialchars($nombre_sitio, ENT_QUOTES, 'UTF-8') ?>" required maxlength="100">
</div>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label" for="contacto_email">Email de Contacto <span class="required">*</span></label>
<input type="email" id="contacto_email" name="contacto_email" class="form-control" value="<?= htmlspecialchars($contacto_email, ENT_QUOTES, 'UTF-8') ?>" required maxlength="255">
</div>
<div class="form-group">
<label class="form-label" for="contacto_telefono">Teléfono de Contacto</label>
<input type="text" id="contacto_telefono" name="contacto_telefono" class="form-control" value="<?= htmlspecialchars($contacto_telefono, ENT_QUOTES, 'UTF-8') ?>" maxlength="50">
</div>
</div>
</div>
<div class="form-card">
<h3 class="section-title"><i class="fas fa-heading"></i>Contenido del Hero</h3>
<div class="form-group" style="margin-bottom:1.5rem;">
<label class="form-label" for="hero_titulo">Título Principal</label>
<input type="text" id="hero_titulo" name="hero_titulo" class="form-control" value="<?= htmlspecialchars($hero_titulo, ENT_QUOTES, 'UTF-8') ?>" maxlength="200">
<small class="form-help"><i class="fas fa-info-circle"></i> Título principal de la página de inicio</small>
</div>
<div class="form-group" style="margin-bottom:1.5rem;">
<label class="form-label" for="hero_subtitulo">Subtítulo</label>
<input type="text" id="hero_subtitulo" name="hero_subtitulo" class="form-control" value="<?= htmlspecialchars($hero_subtitulo, ENT_QUOTES, 'UTF-8') ?>" maxlength="200">
<small class="form-help"><i class="fas fa-info-circle"></i> Subtítulo descriptivo</small>
</div>
<div class="form-group">
<label class="form-label" for="descripcion_sistema">Descripción del Sistema</label>
<textarea id="descripcion_sistema" name="descripcion_sistema" class="form-control" maxlength="500"><?= htmlspecialchars($descripcion_sistema, ENT_QUOTES, 'UTF-8') ?></textarea>
<small class="form-help"><i class="fas fa-info-circle"></i> Descripción breve del sistema (máx. 500 caracteres)</small>
</div>
</div>
<div class="form-card">
<h3 class="section-title"><i class="fas fa-footer"></i>Footer</h3>
<div class="form-group">
<label class="form-label" for="footer_texto">Texto del Footer</label>
<input type="text" id="footer_texto" name="footer_texto" class="form-control" value="<?= htmlspecialchars($footer_texto, ENT_QUOTES, 'UTF-8') ?>" maxlength="200">
<small class="form-help"><i class="fas fa-info-circle"></i> Texto que aparecerá en el pie de página</small>
</div>
</div>
<button type="submit" class="btn-submit" id="submitBtn"><i class="fas fa-save"></i><span id="btnText">Guardar Cambios</span><span id="btnLoading" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Guardando...</span></button>
</form>
</div>
</main>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('active');}
document.getElementById('siteForm').addEventListener('submit',function(e){const submitBtn=document.getElementById('submitBtn');const btnText=document.getElementById('btnText');const btnLoading=document.getElementById('btnLoading');submitBtn.disabled=true;btnText.style.display='none';btnLoading.style.display='inline-flex';});
let formChanged=false;const formInputs=document.querySelectorAll('#siteForm input:not([type="hidden"]),#siteForm textarea');formInputs.forEach(input=>{input.addEventListener('change',function(){formChanged=true;});});
window.addEventListener('beforeunload',function(e){if(formChanged&&!document.getElementById('submitBtn').disabled){e.preventDefault();e.returnValue='';return '';}});
document.getElementById('siteForm').addEventListener('submit',function(){formChanged=false;});
setTimeout(function(){const alerts=document.querySelectorAll('.alert-custom');alerts.forEach(function(alert){alert.style.transition='opacity 0.5s ease';alert.style.opacity='0';setTimeout(function(){alert.remove();},500);});},5000);
if(window.top!==window.self){window.top.location=window.self.location;}
let sessionTimeout;const TIMEOUT_DURATION=1800000;function resetSessionTimeout(){clearTimeout(sessionTimeout);sessionTimeout=setTimeout(function(){alert('Tu sesión ha expirado por inactividad.');window.location.href='<?= $base_url ?>/superadmin/logout.php';},TIMEOUT_DURATION);}
['mousedown','keydown','scroll','touchstart'].forEach(function(event){document.addEventListener(event,resetSessionTimeout,true);});resetSessionTimeout();
</script>
</body>
</html>
