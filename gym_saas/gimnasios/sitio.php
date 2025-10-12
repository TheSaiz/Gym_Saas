<?php
session_start();

// Configuración de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Regenerar ID de sesión si es necesario
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";

// Verificar sesión con seguridad mejorada
if(!isset($_SESSION['gimnasio_id']) || empty($_SESSION['gimnasio_id'])){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

// Sanitizar y validar ID de gimnasio
$gimnasio_id = filter_var($_SESSION['gimnasio_id'], FILTER_VALIDATE_INT);
if($gimnasio_id === false || $gimnasio_id <= 0){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

// Verificar que el gimnasio está activo
$stmt = $conn->prepare("SELECT * FROM gimnasios WHERE id = ? AND estado = 'activo' LIMIT 1");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows !== 1){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

$gimnasio = $result->fetch_assoc();
$stmt->close();

// Variables para mensajes
$mensaje = $_SESSION['mensaje'] ?? '';
$mensaje_tipo = $_SESSION['mensaje_tipo'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['mensaje_tipo']);

// Procesar formulario
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    
    // Validar token CSRF
    if(!isset($_POST['form_token']) || $_POST['form_token'] !== ($_SESSION['form_token'] ?? '')){
        $mensaje = "Solicitud inválida. Por favor, intenta nuevamente.";
        $mensaje_tipo = "danger";
    } else {
        
        // Sanitizar y validar datos
        $nombre = trim($_POST['nombre'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $altura = trim($_POST['altura'] ?? '');
        $localidad = trim($_POST['localidad'] ?? '');
        $partido = trim($_POST['partido'] ?? '');
        
        // Validaciones
        $errores = [];
        
        if(empty($nombre) || strlen($nombre) < 3 || strlen($nombre) > 255){
            $errores[] = "El nombre debe tener entre 3 y 255 caracteres.";
        }
        
        if(!empty($direccion) && strlen($direccion) > 255){
            $errores[] = "La dirección es demasiado larga.";
        }
        
        if(!empty($altura) && strlen($altura) > 50){
            $errores[] = "La altura es demasiado larga.";
        }
        
        if(!empty($localidad) && strlen($localidad) > 100){
            $errores[] = "La localidad es demasiado larga.";
        }
        
        if(!empty($partido) && strlen($partido) > 100){
            $errores[] = "El partido es demasiado largo.";
        }
        
        // Si hay errores, mostrarlos
        if(!empty($errores)){
            $mensaje = implode('<br>', $errores);
            $mensaje_tipo = "danger";
        } else {
            
            // Actualizar datos
            $stmt = $conn->prepare("UPDATE gimnasios 
                                   SET nombre = ?, 
                                       direccion = ?, 
                                       altura = ?, 
                                       localidad = ?, 
                                       partido = ?
                                   WHERE id = ?");
            $stmt->bind_param("sssssi", 
                             $nombre, 
                             $direccion, 
                             $altura, 
                             $localidad, 
                             $partido, 
                             $gimnasio_id);
            
            if($stmt->execute()){
                $mensaje = "Datos del sitio actualizados correctamente.";
                $mensaje_tipo = "success";
                
                // Actualizar datos en memoria
                $gimnasio['nombre'] = $nombre;
                $gimnasio['direccion'] = $direccion;
                $gimnasio['altura'] = $altura;
                $gimnasio['localidad'] = $localidad;
                $gimnasio['partido'] = $partido;
                
                // Actualizar nombre en sesión
                $_SESSION['gimnasio_nombre'] = $nombre;
                
            } else {
                $mensaje = "Error al actualizar los datos: " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8');
                $mensaje_tipo = "danger";
            }
            
            $stmt->close();
        }
    }
}

// Generar token CSRF
$_SESSION['form_token'] = bin2hex(random_bytes(32));

include_once(__DIR__ . "/sidebar.php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Configuración del Sitio - <?= htmlspecialchars($gimnasio['nombre'], ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .content-wrapper {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }
        
        .page-header {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .page-header h2 {
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .form-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
        }
        
        .form-label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.12);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            backdrop-filter: blur(10px);
        }
        
        .alert-success {
            background: rgba(81, 207, 102, 0.15);
            color: var(--success);
            border: 1px solid rgba(81, 207, 102, 0.3);
        }
        
        .alert-danger {
            background: rgba(255, 107, 107, 0.15);
            color: var(--danger);
            border: 1px solid rgba(255, 107, 107, 0.3);
        }
        
        .required::after {
            content: ' *';
            color: var(--danger);
        }
        
        .info-card {
            background: rgba(77, 171, 247, 0.1);
            border: 1px solid rgba(77, 171, 247, 0.3);
            border-radius: 15px;
            padding: 1.5rem;
        }
        
        .info-card h5 {
            color: var(--info);
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .info-card p {
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
            font-size: 0.95rem;
        }
        
        @media (max-width: 1024px) {
            .content-wrapper {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<div class="content-wrapper">
    <div class="page-header">
        <h2>
            <i class="fas fa-globe me-3"></i>
            Configuración del Sitio Público
        </h2>
    </div>
    
    <?php if(!empty($mensaje)): ?>
        <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $mensaje_tipo === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
            <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="form-card">
                <h4 style="color: rgba(255,255,255,0.9); font-weight: 600; margin-bottom: 1.5rem;">
                    <i class="fas fa-info-circle me-2" style="color: var(--primary);"></i>
                    Información Pública del Gimnasio
                </h4>
                
                <form method="POST" id="sitioForm" novalidate>
                    <input type="hidden" name="form_token" value="<?= $_SESSION['form_token'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label required">Nombre del Gimnasio</label>
                        <input type="text" 
                               name="nombre" 
                               class="form-control" 
                               value="<?= htmlspecialchars($gimnasio['nombre'], ENT_QUOTES, 'UTF-8') ?>" 
                               placeholder="Ej: FitZone"
                               maxlength="255"
                               required>
                        <div class="invalid-feedback">El nombre es obligatorio (mínimo 3 caracteres).</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Dirección</label>
                            <input type="text" 
                                   name="direccion" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($gimnasio['direccion'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                   placeholder="Ej: Av. Siempre Viva"
                                   maxlength="255">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Altura</label>
                            <input type="text" 
                                   name="altura" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($gimnasio['altura'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                   placeholder="Ej: 742"
                                   maxlength="50">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Localidad</label>
                            <input type="text" 
                                   name="localidad" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($gimnasio['localidad'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                   placeholder="Ej: Don Torcuato"
                                   maxlength="100">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Partido / Provincia</label>
                            <input type="text" 
                                   name="partido" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($gimnasio['partido'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                   placeholder="Ej: Tigre, Buenos Aires"
                                   maxlength="100">
                        </div>
                    </div>
                    
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Guardar Cambios
                        </button>
                        <a href="<?= $base_url ?>/gimnasios/configuracion.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Volver a Configuración
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="info-card">
                <h5>
                    <i class="fas fa-lightbulb me-2"></i>
                    Información
                </h5>
                <p style="margin-bottom: 1rem;">
                    Esta información será visible en el sitio público de tu gimnasio y permitirá a los usuarios encontrarte más fácilmente.
                </p>
                <hr style="border-color: rgba(255,255,255,0.1); margin: 1.5rem 0;">
                <div style="display: flex; align-items: start; gap: 0.8rem; margin-bottom: 1rem;">
                    <i class="fas fa-map-marker-alt" style="color: var(--info); margin-top: 0.2rem;"></i>
                    <div>
                        <strong style="color: rgba(255,255,255,0.9); display: block; margin-bottom: 0.3rem;">Dirección Completa</strong>
                        <small style="color: rgba(255,255,255,0.7);">
                            Ayuda a tus socios a encontrarte físicamente
                        </small>
                    </div>
                </div>
                <div style="display: flex; align-items: start; gap: 0.8rem;">
                    <i class="fas fa-eye" style="color: var(--info); margin-top: 0.2rem;"></i>
                    <div>
                        <strong style="color: rgba(255,255,255,0.9); display: block; margin-bottom: 0.3rem;">Visibilidad</strong>
                        <small style="color: rgba(255,255,255,0.7);">
                            Estos datos aparecerán en tu página pública y en las facturas
                        </small>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; background: rgba(255, 212, 59, 0.1); border: 1px solid rgba(255, 212, 59, 0.3); border-radius: 15px; padding: 1.5rem;">
                <div style="display: flex; align-items: start; gap: 0.8rem;">
                    <i class="fas fa-exclamation-triangle" style="color: var(--warning); font-size: 1.5rem; margin-top: 0.2rem;"></i>
                    <div>
                        <strong style="color: var(--warning); display: block; margin-bottom: 0.5rem;">Importante</strong>
                        <p style="color: rgba(255,255,255,0.8); margin: 0; font-size: 0.9rem;">
                            El nombre del gimnasio también se actualizará en el panel de administración y en todas las referencias internas del sistema.
                        </p>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; background: rgba(81, 207, 102, 0.1); border: 1px solid rgba(81, 207, 102, 0.3); border-radius: 15px; padding: 1.5rem;">
                <div style="display: flex; align-items: start; gap: 0.8rem;">
                    <i class="fas fa-search-location" style="color: var(--success); font-size: 1.5rem; margin-top: 0.2rem;"></i>
                    <div>
                        <strong style="color: var(--success); display: block; margin-bottom: 0.5rem;">SEO y Búsquedas</strong>
                        <p style="color: rgba(255,255,255,0.8); margin: 0; font-size: 0.9rem;">
                            Completa todos los campos para mejorar tu posicionamiento en búsquedas locales y ayudar a nuevos clientes a encontrarte.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Vista Previa -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="form-card">
                <h4 style="color: rgba(255,255,255,0.9); font-weight: 600; margin-bottom: 1.5rem;">
                    <i class="fas fa-eye me-2" style="color: var(--info);"></i>
                    Vista Previa
                </h4>
                
                <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 15px; padding: 2rem;">
                    <div style="text-align: center; max-width: 600px; margin: 0 auto;">
                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem;">
                            <i class="fas fa-dumbbell"></i>
                        </div>
                        
                        <h3 style="color: #fff; font-weight: 700; margin-bottom: 0.5rem;" id="preview-nombre">
                            <?= htmlspecialchars($gimnasio['nombre'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>
                        
                        <div id="preview-direccion" style="color: rgba(255,255,255,0.7); margin-bottom: 1rem;">
                            <?php 
                                $direccion_completa = [];
                                if(!empty($gimnasio['direccion'])) $direccion_completa[] = $gimnasio['direccion'];
                                if(!empty($gimnasio['altura'])) $direccion_completa[] = $gimnasio['altura'];
                                echo htmlspecialchars(implode(' ', $direccion_completa), ENT_QUOTES, 'UTF-8');
                            ?>
                        </div>
                        
                        <div id="preview-localidad" style="color: rgba(255,255,255,0.6); font-size: 0.95rem;">
                            <?php 
                                $ubicacion = [];
                                if(!empty($gimnasio['localidad'])) $ubicacion[] = $gimnasio['localidad'];
                                if(!empty($gimnasio['partido'])) $ubicacion[] = $gimnasio['partido'];
                                echo htmlspecialchars(implode(', ', $ubicacion), ENT_QUOTES, 'UTF-8');
                            ?>
                        </div>
                        
                        <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                            <a href="#" class="btn btn-primary" style="pointer-events: none;">
                                <i class="fas fa-phone me-2"></i>
                                Contactar
                            </a>
                            <a href="#" class="btn btn-secondary" style="pointer-events: none;">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                Ver Mapa
                            </a>
                        </div>
                    </div>
                </div>
                
                <p style="text-align: center; color: rgba(255,255,255,0.5); font-size: 0.9rem; margin-top: 1rem; margin-bottom: 0;">
                    <i class="fas fa-info-circle me-1"></i>
                    Esta es una vista previa de cómo se verá tu información pública
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        'use strict';
        
        const form = document.getElementById('sitioForm');
        
        // Validación del formulario
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
        
        // Actualizar vista previa en tiempo real
        const nombreInput = document.querySelector('input[name="nombre"]');
        const direccionInput = document.querySelector('input[name="direccion"]');
        const alturaInput = document.querySelector('input[name="altura"]');
        const localidadInput = document.querySelector('input[name="localidad"]');
        const partidoInput = document.querySelector('input[name="partido"]');
        
        const previewNombre = document.getElementById('preview-nombre');
        const previewDireccion = document.getElementById('preview-direccion');
        const previewLocalidad = document.getElementById('preview-localidad');
        
        function updatePreview() {
            // Actualizar nombre
            const nombre = nombreInput.value.trim() || 'Nombre del Gimnasio';
            previewNombre.textContent = nombre;
            
            // Actualizar dirección
            const direccion = direccionInput.value.trim();
            const altura = alturaInput.value.trim();
            const direccionCompleta = [direccion, altura].filter(v => v).join(' ') || 'Dirección no especificada';
            previewDireccion.textContent = direccionCompleta;
            
            // Actualizar localidad
            const localidad = localidadInput.value.trim();
            const partido = partidoInput.value.trim();
            const ubicacion = [localidad, partido].filter(v => v).join(', ') || 'Ubicación no especificada';
            previewLocalidad.textContent = ubicacion;
        }
        
        // Eventos para actualizar preview
        nombreInput.addEventListener('input', updatePreview);
        direccionInput.addEventListener('input', updatePreview);
        alturaInput.addEventListener('input', updatePreview);
        localidadInput.addEventListener('input', updatePreview);
        partidoInput.addEventListener('input', updatePreview);
        
    })();
</script>

</body>
</html>
