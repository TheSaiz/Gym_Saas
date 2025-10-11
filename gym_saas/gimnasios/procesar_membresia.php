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
$stmt = $conn->prepare("SELECT estado FROM gimnasios WHERE id = ? AND estado = 'activo' LIMIT 1");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows !== 1){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}
$stmt->close();

// Variables
$membresia = null;
$mensaje = '';
$mensaje_tipo = '';
$id = null;

// Obtener ID para edición
if(isset($_GET['id'])){
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    
    if($id !== false && $id > 0){
        $stmt = $conn->prepare("SELECT * FROM membresias WHERE id = ? AND gimnasio_id = ? LIMIT 1");
        $stmt->bind_param("ii", $id, $gimnasio_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows === 1){
            $membresia = $result->fetch_assoc();
        } else {
            $mensaje = "Membresía no encontrada o no tienes permisos para editarla.";
            $mensaje_tipo = "danger";
            $id = null;
        }
        $stmt->close();
    } else {
        $id = null;
    }
}

// Procesar formulario
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    
    // Validar token CSRF
    if(!isset($_POST['form_token']) || $_POST['form_token'] !== ($_SESSION['form_token'] ?? '')){
        $mensaje = "Solicitud inválida. Por favor, intenta nuevamente.";
        $mensaje_tipo = "danger";
    } else {
        
        // Sanitizar y validar datos
        $nombre = trim($_POST['nombre'] ?? '');
        $precio = floatval($_POST['precio'] ?? 0);
        $dias = intval($_POST['dias'] ?? 0);
        $estado = $_POST['estado'] ?? 'activo';
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        // Validaciones
        $errores = [];
        
        if(empty($nombre) || strlen($nombre) < 3 || strlen($nombre) > 100){
            $errores[] = "El nombre debe tener entre 3 y 100 caracteres.";
        }
        
        if($precio <= 0 || $precio > 999999.99){
            $errores[] = "El precio debe ser mayor a 0 y menor a $999,999.99.";
        }
        
        if($dias <= 0 || $dias > 3650){
            $errores[] = "Los días de vigencia deben estar entre 1 y 3650 (10 años).";
        }
        
        if(!in_array($estado, ['activo', 'inactivo'])){
            $estado = 'activo';
        }
        
        if(!empty($descripcion) && strlen($descripcion) > 500){
            $errores[] = "La descripción no puede superar los 500 caracteres.";
        }
        
        // Verificar nombre duplicado
        if(empty($errores)){
            if($id){
                $stmt = $conn->prepare("SELECT id FROM membresias WHERE nombre = ? AND gimnasio_id = ? AND id != ? LIMIT 1");
                $stmt->bind_param("sii", $nombre, $gimnasio_id, $id);
            } else {
                $stmt = $conn->prepare("SELECT id FROM membresias WHERE nombre = ? AND gimnasio_id = ? LIMIT 1");
                $stmt->bind_param("si", $nombre, $gimnasio_id);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows > 0){
                $errores[] = "Ya existe una membresía con ese nombre.";
            }
            $stmt->close();
        }
        
        // Si hay errores, mostrarlos
        if(!empty($errores)){
            $mensaje = implode('<br>', $errores);
            $mensaje_tipo = "danger";
        } else {
            // Procesar según acción
            
            if($id){
                // EDITAR MEMBRESÍA
                $stmt = $conn->prepare("UPDATE membresias SET 
                                      nombre = ?, 
                                      precio = ?, 
                                      dias = ?, 
                                      estado = ?
                                      WHERE id = ? AND gimnasio_id = ?");
                $stmt->bind_param("sdisii", 
                                 $nombre, 
                                 $precio, 
                                 $dias, 
                                 $estado, 
                                 $id, 
                                 $gimnasio_id);
                
                if($stmt->execute()){
                    $_SESSION['mensaje'] = "Membresía actualizada correctamente.";
                    $_SESSION['mensaje_tipo'] = "success";
                    header("Location: membresias.php");
                    exit;
                } else {
                    $mensaje = "Error al actualizar la membresía. Por favor, intenta nuevamente.";
                    $mensaje_tipo = "danger";
                }
                $stmt->close();
                
            } else {
                // CREAR NUEVA MEMBRESÍA
                $stmt = $conn->prepare("INSERT INTO membresias 
                                      (gimnasio_id, nombre, precio, dias, estado) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isdis", 
                                 $gimnasio_id, 
                                 $nombre, 
                                 $precio, 
                                 $dias, 
                                 $estado);
                
                if($stmt->execute()){
                    $_SESSION['mensaje'] = "Membresía creada correctamente.";
                    $_SESSION['mensaje_tipo'] = "success";
                    header("Location: membresias.php");
                    exit;
                } else {
                    $mensaje = "Error al crear la membresía. Por favor, intenta nuevamente.";
                    $mensaje_tipo = "danger";
                }
                $stmt->close();
            }
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
    <title><?= $id ? 'Editar' : 'Crear' ?> Membresía - Gimnasio System</title>
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
        
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.12);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        .input-group-text {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.7);
            border-radius: 12px 0 0 12px;
        }
        
        .input-group .form-control {
            border-radius: 0 12px 12px 0;
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
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 0.8rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            color: #fff;
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
        
        .price-preview {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            margin-top: 1rem;
        }
        
        .price-preview .amount {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .price-preview .label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
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
            <i class="fas fa-id-card-alt me-3"></i>
            <?= $id ? 'Editar' : 'Crear Nueva' ?> Membresía
        </h2>
    </div>
    
    <?php if(!empty($mensaje)): ?>
        <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $mensaje_tipo === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
            <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="form-card">
        <form method="POST" id="membresiaForm" novalidate>
            <input type="hidden" name="form_token" value="<?= $_SESSION['form_token'] ?>">
            
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label required">Nombre de la Membresía</label>
                        <input type="text" 
                               name="nombre" 
                               class="form-control" 
                               value="<?= htmlspecialchars($membresia['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                               placeholder="Ej: Mensual, Trimestral, Anual"
                               maxlength="100"
                               required>
                        <div class="invalid-feedback">El nombre es obligatorio (mínimo 3 caracteres).</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Precio</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-dollar-sign"></i>
                                </span>
                                <input type="number" 
                                       name="precio" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($membresia['precio'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       placeholder="0.00"
                                       step="0.01"
                                       min="0.01"
                                       max="999999.99"
                                       id="precioInput"
                                       required>
                            </div>
                            <div class="invalid-feedback">El precio debe ser mayor a 0.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Días de Vigencia</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-calendar-alt"></i>
                                </span>
                                <input type="number" 
                                       name="dias" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($membresia['dias'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       placeholder="30"
                                       min="1"
                                       max="3650"
                                       id="diasInput"
                                       required>
                            </div>
                            <div class="invalid-feedback">Los días deben estar entre 1 y 3650.</div>
                            <small class="text-muted d-block mt-1" id="diasHelper"></small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="activo" <?= (!isset($membresia['estado']) || $membresia['estado'] === 'activo') ? 'selected' : '' ?>>
                                <i class="fas fa-check-circle"></i> Activo
                            </option>
                            <option value="inactivo" <?= (isset($membresia['estado']) && $membresia['estado'] === 'inactivo') ? 'selected' : '' ?>>
                                <i class="fas fa-times-circle"></i> Inactivo
                            </option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="price-preview">
                        <div class="label">Vista Previa</div>
                        <div class="amount" id="precioPreview">$0.00</div>
                        <div class="label mt-2" id="diasPreview">Por 0 días</div>
                        <div class="label text-muted mt-3" id="precioXdia">$0.00 por día</div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>
                    <?= $id ? 'Actualizar' : 'Crear' ?> Membresía
                </button>
                <a href="membresias.php" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Validación del formulario
    (function () {
        'use strict';
        
        const form = document.getElementById('membresiaForm');
        const precioInput = document.getElementById('precioInput');
        const diasInput = document.getElementById('diasInput');
        const precioPreview = document.getElementById('precioPreview');
        const diasPreview = document.getElementById('diasPreview');
        const precioXdia = document.getElementById('precioXdia');
        const diasHelper = document.getElementById('diasHelper');
        
        // Actualizar vista previa
        function updatePreview() {
            const precio = parseFloat(precioInput.value) || 0;
            const dias = parseInt(diasInput.value) || 0;
            
            // Actualizar precio
            precioPreview.textContent = ' + precio.toLocaleString('es-AR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            // Actualizar días
            diasPreview.textContent = 'Por ' + dias + ' día' + (dias !== 1 ? 's' : '');
            
            // Calcular precio por día
            if (precio > 0 && dias > 0) {
                const precioDiario = precio / dias;
                precioXdia.textContent = ' + precioDiario.toLocaleString('es-AR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ' por día';
            } else {
                precioXdia.textContent = '$0.00 por día';
            }
            
            // Helper de días
            if (dias > 0) {
                if (dias === 30) {
                    diasHelper.textContent = '≈ 1 mes';
                } else if (dias === 60) {
                    diasHelper.textContent = '≈ 2 meses';
                } else if (dias === 90) {
                    diasHelper.textContent = '≈ 3 meses';
                } else if (dias === 180) {
                    diasHelper.textContent = '≈ 6 meses';
                } else if (dias === 365) {
                    diasHelper.textContent = '≈ 1 año';
                } else if (dias > 30) {
                    const meses = Math.floor(dias / 30);
                    diasHelper.textContent = '≈ ' + meses + ' mes' + (meses !== 1 ? 'es' : '');
                } else {
                    diasHelper.textContent = '';
                }
            } else {
                diasHelper.textContent = '';
            }
        }
        
        // Eventos
        precioInput.addEventListener('input', updatePreview);
        diasInput.addEventListener('input', updatePreview);
        
        // Inicializar preview
        updatePreview();
        
        // Validación del formulario
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
        
        // Solo números y punto decimal en precio
        precioInput.addEventListener('keypress', function(e) {
            const char = String.fromCharCode(e.which);
            if (!/[\d.]/.test(char)) {
                e.preventDefault();
            }
            // Solo un punto decimal
            if (char === '.' && this.value.includes('.')) {
                e.preventDefault();
            }
        });
        
        // Solo números en días
        diasInput.addEventListener('keypress', function(e) {
            const char = String.fromCharCode(e.which);
            if (!/\d/.test(char)) {
                e.preventDefault();
            }
        });
    })();
</script>

</body>
</html>
