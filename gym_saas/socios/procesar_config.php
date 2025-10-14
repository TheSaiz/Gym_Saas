<?php
/**
 * Procesamiento seguro de actualización de configuración de socio
 * Incluye validaciones exhaustivas y prevención de vulnerabilidades
 */

session_start();

// Regenerar ID de sesión por seguridad
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";

// ============================================
// 1. VERIFICACIÓN DE SESIÓN
// ============================================

if(!isset($_SESSION['socio_id']) || empty($_SESSION['socio_id'])){
    session_destroy();
    header("Location: $base_url/socios/login.php");
    exit;
}

// Sanitizar ID de socio
$socio_id = filter_var($_SESSION['socio_id'], FILTER_VALIDATE_INT);
if($socio_id === false){
    session_destroy();
    header("Location: $base_url/socios/login.php");
    exit;
}

// ============================================
// 2. VALIDACIÓN CSRF TOKEN
// ============================================

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header("Location: $base_url/socios/configuracion.php");
    exit;
}

if(!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token_config'])){
    die("Error: Token CSRF inválido");
}

if($_POST['csrf_token'] !== $_SESSION['csrf_token_config']){
    die("Error: Token CSRF no coincide");
}

// Regenerar token después de usar
unset($_SESSION['csrf_token_config']);

// ============================================
// 3. VALIDACIÓN DE DATOS DE ENTRADA
// ============================================

// Validar socio_id del formulario
$socio_id_form = filter_var($_POST['socio_id'] ?? 0, FILTER_VALIDATE_INT);
if($socio_id_form !== $socio_id){
    die("Error: ID de socio no coincide con la sesión");
}

// Sanitizar y validar campos
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$telefono_emergencia = trim($_POST['telefono_emergencia'] ?? '');
$password_actual = $_POST['password_actual'] ?? '';
$password_nueva = $_POST['password_nueva'] ?? '';
$password_confirmar = $_POST['password_confirmar'] ?? '';

// ============================================
// 4. VALIDACIONES DE NEGOCIO
// ============================================

// Validar campos requeridos
if(empty($nombre) || empty($apellido) || empty($email)){
    header("Location: $base_url/socios/configuracion.php?error=5");
    exit;
}

// Validar formato de nombre y apellido (solo letras y espacios)
if(!preg_match("/^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/u", $nombre)){
    header("Location: $base_url/socios/configuracion.php?error=2");
    exit;
}

if(!preg_match("/^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/u", $apellido)){
    header("Location: $base_url/socios/configuracion.php?error=2");
    exit;
}

// Validar longitud de campos
if(strlen($nombre) > 100 || strlen($apellido) > 100){
    header("Location: $base_url/socios/configuracion.php?error=2");
    exit;
}

// Validar formato de email
if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
    header("Location: $base_url/socios/configuracion.php?error=2");
    exit;
}

// Validar longitud de email
if(strlen($email) > 255){
    header("Location: $base_url/socios/configuracion.php?error=2");
    exit;
}

// Validar formato de teléfonos (solo números, +, -, espacios y paréntesis)
if(!empty($telefono) && !preg_match("/^[0-9+\-\s()]+$/", $telefono)){
    header("Location: $base_url/socios/configuracion.php?error=2");
    exit;
}

if(!empty($telefono_emergencia) && !preg_match("/^[0-9+\-\s()]+$/", $telefono_emergencia)){
    header("Location: $base_url/socios/configuracion.php?error=2");
    exit;
}

// Validar longitud de teléfonos
if(strlen($telefono) > 30 || strlen($telefono_emergencia) > 30){
    header("Location: $base_url/socios/configuracion.php?error=2");
    exit;
}

// ============================================
// 5. VERIFICAR EMAIL ÚNICO
// ============================================

$stmt = $conn->prepare("SELECT id FROM socios WHERE email = ? AND id != ? LIMIT 1");
$stmt->bind_param("si", $email, $socio_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0){
    $stmt->close();
    header("Location: $base_url/socios/configuracion.php?error=1");
    exit;
}
$stmt->close();

// ============================================
// 6. PROCESAR CAMBIO DE CONTRASEÑA
// ============================================

$cambiar_password = false;
$password_hash = null;

// Si se ingresó algún campo de contraseña
if(!empty($password_actual) || !empty($password_nueva) || !empty($password_confirmar)){
    
    // Validar que se completaron todos los campos
    if(empty($password_actual)){
        header("Location: $base_url/socios/configuracion.php?error=3");
        exit;
    }
    
    if(empty($password_nueva)){
        header("Location: $base_url/socios/configuracion.php?error=5");
        exit;
    }
    
    // Validar longitud mínima
    if(strlen($password_nueva) < 6){
        header("Location: $base_url/socios/configuracion.php?error=2");
        exit;
    }
    
    // Validar que las contraseñas nuevas coincidan
    if($password_nueva !== $password_confirmar){
        header("Location: $base_url/socios/configuracion.php?error=4");
        exit;
    }
    
    // Verificar contraseña actual
    $stmt = $conn->prepare("SELECT password FROM socios WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $socio_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $socio_data = $result->fetch_assoc();
    $stmt->close();
    
    if(!$socio_data || md5($password_actual) !== $socio_data['password']){
        header("Location: $base_url/socios/configuracion.php?error=3");
        exit;
    }
    
    // Todo validado, preparar nuevo hash
    $cambiar_password = true;
    $password_hash = md5($password_nueva);
}

// ============================================
// 7. ACTUALIZAR DATOS EN BASE DE DATOS
// ============================================

// Iniciar transacción
$conn->begin_transaction();

try {
    // Preparar consulta base
    if($cambiar_password){
        $sql = "UPDATE socios SET 
                nombre = ?,
                apellido = ?,
                email = ?,
                telefono = ?,
                telefono_emergencia = ?,
                password = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", 
            $nombre, 
            $apellido, 
            $email, 
            $telefono, 
            $telefono_emergencia, 
            $password_hash, 
            $socio_id
        );
    } else {
        $sql = "UPDATE socios SET 
                nombre = ?,
                apellido = ?,
                email = ?,
                telefono = ?,
                telefono_emergencia = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", 
            $nombre, 
            $apellido, 
            $email, 
            $telefono, 
            $telefono_emergencia, 
            $socio_id
        );
    }
    
    // Ejecutar actualización
    if(!$stmt->execute()){
        throw new Exception("Error al ejecutar la actualización");
    }
    
    $stmt->close();
    
    // Confirmar transacción
    $conn->commit();
    
    // ============================================
    // 8. REGISTRAR ACTIVIDAD (OPCIONAL)
    // ============================================
    
    // Aquí podrías registrar la actividad en una tabla de logs
    // $ip = $_SERVER['REMOTE_ADDR'];
    // $user_agent = $_SERVER['HTTP_USER_AGENT'];
    // Insertar en tabla de logs...
    
    // ============================================
    // 9. ACTUALIZAR SESIÓN SI ES NECESARIO
    // ============================================
    
    $_SESSION['socio_nombre'] = $nombre;
    $_SESSION['socio_apellido'] = $apellido;
    
    // Si cambió la contraseña, regenerar sesión por seguridad
    if($cambiar_password){
        session_regenerate_id(true);
        $_SESSION['regenerated'] = true;
    }
    
    // Redirigir con mensaje de éxito
    header("Location: $base_url/socios/configuracion.php?success=1");
    exit;
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    
    // Log del error (en producción, usar un sistema de logs apropiado)
    error_log("Error al actualizar socio ID $socio_id: " . $e->getMessage());
    
    // Redirigir con mensaje de error
    header("Location: $base_url/socios/configuracion.php?error=2");
    exit;
}

// Cerrar conexión
$conn->close();
?>