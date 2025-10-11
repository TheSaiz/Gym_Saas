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
$socio = null;
$mensaje = '';
$mensaje_tipo = '';
$id = null;

// Obtener ID para edición
if(isset($_GET['id'])){
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    
    if($id !== false && $id > 0){
        $stmt = $conn->prepare("SELECT * FROM socios WHERE id = ? AND gimnasio_id = ? LIMIT 1");
        $stmt->bind_param("ii", $id, $gimnasio_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows === 1){
            $socio = $result->fetch_assoc();
        } else {
            $mensaje = "Socio no encontrado o no tienes permisos para editarlo.";
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
    
    // Validar token CSRF (básico)
    if(!isset($_POST['form_token']) || $_POST['form_token'] !== ($_SESSION['form_token'] ?? '')){
        $mensaje = "Solicitud inválida. Por favor, intenta nuevamente.";
        $mensaje_tipo = "danger";
    } else {
        
        // Sanitizar y validar datos
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $dni = trim($_POST['dni'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $telefono_emergencia = trim($_POST['telefono_emergencia'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $estado = $_POST['estado'] ?? 'activo';
        
        // Validaciones
        $errores = [];
        
        if(empty($nombre) || strlen($nombre) < 2 || strlen($nombre) > 100){
            $errores[] = "El nombre debe tener entre 2 y 100 caracteres.";
        }
        
        if(empty($apellido) || strlen($apellido) < 2 || strlen($apellido) > 100){
            $errores[] = "El apellido debe tener entre 2 y 100 caracteres.";
        }
        
        if(empty($dni)){
            $errores[] = "El DNI es obligatorio.";
        } elseif(!preg_match('/^[0-9]{7,9}$/', $dni)){
            $errores[] = "El DNI debe contener entre 7 y 9 dígitos.";
        }
        
        if(!empty($telefono) && !preg_match('/^[0-9\s\+\-\(\)]{7,30}$/', $telefono)){
            $errores[] = "El formato del teléfono no es válido.";
        }
        
        if(!empty($telefono_emergencia) && !preg_match('/^[0-9\s\+\-\(\)]{7,30}$/', $telefono_emergencia)){
            $errores[] = "El formato del teléfono de emergencia no es válido.";
        }
        
        if(!empty($email)){
            if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
                $errores[] = "El formato del email no es válido.";
            } elseif(strlen($email) > 255){
                $errores[] = "El email es demasiado largo.";
            }
        }
        
        if(!empty($password) && strlen($password) < 6){
            $errores[] = "La contraseña debe tener al menos 6 caracteres.";
        }
        
        if(!in_array($estado, ['activo', 'inactivo'])){
            $estado = 'activo';
        }
        
        // Verificar DNI duplicado
        if(empty($errores)){
            if($id){
                // Editando: verificar que DNI no esté usado por otro socio
                $stmt = $conn->prepare("SELECT id FROM socios WHERE dni = ? AND gimnasio_id = ? AND id != ? LIMIT 1");
                $stmt->bind_param("sii", $dni, $gimnasio_id, $id);
            } else {
                // Creando: verificar que DNI no exista
                $stmt = $conn->prepare("SELECT id FROM socios WHERE dni = ? AND gimnasio_id = ? LIMIT 1");
                $stmt->bind_param("si", $dni, $gimnasio_id);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows > 0){
                $errores[] = "Ya existe un socio registrado con ese DNI.";
            }
            $stmt->close();
        }
        
        // Verificar email duplicado si se proporciona
        if(empty($errores) && !empty($email)){
            if($id){
                $stmt = $conn->prepare("SELECT id FROM socios WHERE email = ? AND gimnasio_id = ? AND id != ? LIMIT 1");
                $stmt->bind_param("sii", $email, $gimnasio_id, $id);
            } else {
                $stmt = $conn->prepare("SELECT id FROM socios WHERE email = ? AND gimnasio_id = ? LIMIT 1");
                $stmt->bind_param("si", $email, $gimnasio_id);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows > 0){
                $errores[] = "Ya existe un socio registrado con ese email.";
            }
            $stmt->close();
        }
        
        // Si hay errores, mostrarlos
        if(!empty($errores)){
            $mensaje = implode('<br>', $errores);
            $mensaje_tipo = "danger";
        } else {
            // Procesar según acción (crear o editar)
            
            if($id){
                // EDITAR SOCIO
                if(!empty($password)){
                    // Si se proporciona contraseña, actualizarla (hash seguro)
                    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    
                    $stmt = $conn->prepare("UPDATE socios SET 
                                          nombre = ?, 
                                          apellido = ?, 
                                          dni = ?, 
                                          telefono = ?, 
                                          telefono_emergencia = ?, 
                                          email = ?, 
                                          password = ?,
                                          estado = ?
                                          WHERE id = ? AND gimnasio_id = ?");
                    $stmt->bind_param("ssssssssii", 
                                     $nombre, 
                                     $apellido, 
                                     $dni, 
                                     $telefono, 
                                     $telefono_emergencia, 
                                     $email, 
                                     $password_hash,
                                     $estado,
                                     $id, 
                                     $gimnasio_id);
                } else {
                    // Sin cambio de contraseña
                    $stmt = $conn->prepare("UPDATE socios SET 
                                          nombre = ?, 
                                          apellido = ?, 
                                          dni = ?, 
                                          telefono = ?, 
                                          telefono_emergencia = ?, 
                                          email = ?,
                                          estado = ?
                                          WHERE id = ? AND gimnasio_id = ?");
                    $stmt->bind_param("sssssssii", 
                                     $nombre, 
                                     $apellido, 
                                     $dni, 
                                     $telefono, 
                                     $telefono_emergencia, 
                                     $email,
                                     $estado,
                                     $id, 
                                     $gimnasio_id);
                }
                
                if($stmt->execute()){
                    $_SESSION['mensaje'] = "Socio actualizado correctamente.";
                    $_SESSION['mensaje_tipo'] = "success";
                    header("Location: socios.php");
                    exit;
                } else {
                    $mensaje = "Error al actualizar el socio. Por favor, intenta nuevamente.";
                    $mensaje_tipo = "danger";
                }
                $stmt->close();
                
            } else {
                // CREAR NUEVO SOCIO
                
                // Validar que se proporcione contraseña para nuevos socios
                if(empty($password)){
                    $mensaje = "La contraseña es obligatoria para nuevos socios.";
                    $mensaje_tipo = "danger";
                } else {
                    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    
                    $stmt = $conn->prepare("INSERT INTO socios 
                                          (gimnasio_id, nombre, apellido, dni, telefono, telefono_emergencia, email, password, estado) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issssssss", 
                                     $gimnasio_id, 
                                     $nombre, 
                                     $apellido, 
                                     $dni, 
                                     $telefono, 
                                     $telefono_emergencia, 
                                     $email, 
                                     $password_hash,
                                     $estado);
                    
                    if($stmt->execute()){
                        $_SESSION['mensaje'] = "Socio registrado correctamente.";
                        $_SESSION['mensaje_tipo'] = "success";
                        header("Location: socios.php");
                        exit;
                    } else {
                        $mensaje = "Error al registrar el socio. Por favor, intenta nuevamente.";
                        $mensaje_tipo = "danger";
                    }
                    $stmt->close();
                }
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
    <title><?= $id ? 'Editar' : 'Registrar' ?> Socio - Gimnasio System</title>
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
            <i class="fas fa-user-<?= $id ? 'edit' : 'plus' ?> me-3"></i>
            <?= $id ? 'Editar' : 'Registrar Nuevo' ?> Socio
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
        <form method="POST" id="socioForm" novalidate>
            <input type="hidden" name="form_token" value="<?= $_SESSION['form_token'] ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">Nombre</label>
                    <input type="text" 
                           name="nombre" 
                           class="form-control" 
                           value="<?= htmlspecialchars($socio['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                           placeholder="Ingrese el nombre"
                           maxlength="100"
                           required>
                    <div class="invalid-feedback">El nombre es obligatorio (mínimo 2 caracteres).</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label required">Apellido</label>
                    <input type="text" 
                           name="apellido" 
                           class="form-control" 
                           value="<?= htmlspecialchars($socio['apellido'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                           placeholder="Ingrese el apellido"
                           maxlength="100"
                           required>
                    <div class="invalid-feedback">El apellido es obligatorio (mínimo 2 caracteres).</div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">DNI</label>
                    <input type="text" 
                           name="dni" 
                           class="form-control" 
                           value="<?= htmlspecialchars($socio['dni'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                           placeholder="Ej: 12345678"
                           pattern="[0-9]{7,9}"
                           maxlength="9"
                           required>
                    <div class="invalid-feedback">El DNI debe contener entre 7 y 9 dígitos.</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="tel" 
                           name="telefono" 
                           class="form-control" 
                           value="<?= htmlspecialchars($socio['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                           placeholder="Ej: 1122334455"
                           maxlength="30">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teléfono de Emergencia</label>
                    <input type="tel" 
                           name="telefono_emergencia" 
                           class="form-control" 
                           value="<?= htmlspecialchars($socio['telefono_emergencia'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                           placeholder="Ej: 1199887766"
                           maxlength="30">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" 
                           name="email" 
                           class="form-control" 
                           value="<?= htmlspecialchars($socio['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                           placeholder="socio@ejemplo.com"
                           maxlength="255">
                    <div class="invalid-feedback">El formato del email no es válido.</div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label <?= !$id ? 'required' : '' ?>">
                        Contraseña <?= $id ? '(dejar en blanco para no cambiar)' : '' ?>
                    </label>
                    <input type="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="Mínimo 6 caracteres"
                           minlength="6"
                           <?= !$id ? 'required' : '' ?>>
                    <div class="invalid-feedback">La contraseña debe tener al menos 6 caracteres.</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="activo" <?= (!isset($socio['estado']) || $socio['estado'] === 'activo') ? 'selected' : '' ?>>
                            Activo
                        </option>
                        <option value="inactivo" <?= (isset($socio['estado']) && $socio['estado'] === 'inactivo') ? 'selected' : '' ?>>
                            Inactivo
                        </option>
                    </select>
                </div>
            </div>
            
            <div class="d-flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>
                    <?= $id ? 'Actualizar' : 'Registrar' ?> Socio
                </button>
                <a href="socios.php" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Validación del formulario con Bootstrap
    (function () {
        'use strict';
        
        const form = document.getElementById('socioForm');
        
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
        
        // Validación en tiempo real del DNI
        const dniInput = document.querySelector('input[name="dni"]');
        dniInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Prevenir espacios en DNI
        dniInput.addEventListener('keypress', function(e) {
            if (e.key === ' ') {
                e.preventDefault();
            }
        });
    })();
</script>

</body>
</html>
