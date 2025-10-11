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

// Obtener datos del gimnasio
$stmt = $conn->prepare("SELECT * FROM gimnasios WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$gimnasio = $stmt->get_result()->fetch_assoc();
$stmt->close();

$message = '';
$msg_type = 'success';

// Procesar formulario
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $nombre = $conn->real_escape_string(trim($_POST['nombre']));
    $direccion = $conn->real_escape_string(trim($_POST['direccion']));
    $altura = $conn->real_escape_string(trim($_POST['altura']));
    $localidad = $conn->real_escape_string(trim($_POST['localidad']));
    $partido = $conn->real_escape_string(trim($_POST['partido']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $mp_key = $conn->real_escape_string(trim($_POST['mp_key'] ?? ''));
    $mp_token = $conn->real_escape_string(trim($_POST['mp_token'] ?? ''));

    // Validar email
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $message = "El correo electrónico no es válido.";
        $msg_type = 'error';
    } else {
        // Verificar email duplicado (excepto el propio)
        $stmt = $conn->prepare("SELECT id FROM gimnasios WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $gimnasio_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0){
            $message = "El correo electrónico ya está en uso por otro gimnasio.";
            $msg_type = 'error';
        } else {
            // Manejo de logo
            $logo_path = $gimnasio['logo'];
            
            if(isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK){
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = $_FILES['logo']['type'];
                $file_size = $_FILES['logo']['size'];
                
                if(!in_array($file_type, $allowed_types)){
                    $message = "Solo se permiten imágenes (JPG, PNG, GIF, WEBP).";
                    $msg_type = 'error';
                } else if($file_size > 2097152){ // 2MB
                    $message = "El logo no debe superar los 2MB.";
                    $msg_type = 'error';
                } else {
                    $upload_dir = __DIR__ . "/../uploads/";
                    if(!is_dir($upload_dir)){
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                    $new_filename = "logo_" . $gimnasio_id . "_" . time() . "." . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if(move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)){
                        // Eliminar logo anterior si existe
                        if($gimnasio['logo'] && file_exists(__DIR__ . "/../" . $gimnasio['logo'])){
                            unlink(__DIR__ . "/../" . $gimnasio['logo']);
                        }
                        $logo_path = "/uploads/" . $new_filename;
                    } else {
                        $message = "Error al subir el logo.";
                        $msg_type = 'error';
                    }
                }
            }
            
            if($msg_type !== 'error'){
                // Actualizar datos
                $stmt = $conn->prepare("
                    UPDATE gimnasios SET 
                        nombre = ?,
                        direccion = ?,
                        altura = ?,
                        localidad = ?,
                        partido = ?,
                        email = ?,
                        logo = ?,
                        mp_key = ?,
                        mp_token = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("sssssssssi", $nombre, $direccion, $altura, $localidad, $partido, $email, $logo_path, $mp_key, $mp_token, $gimnasio_id);
                
                if($stmt->execute()){
                    $message = "Configuración actualizada correctamente.";
                    $msg_type = 'success';
                    $_SESSION['gimnasio_nombre'] = $nombre;
                    
                    // Recargar datos
                    $stmt_reload = $conn->prepare("SELECT * FROM gimnasios WHERE id = ? LIMIT 1");
                    $stmt_reload->bind_param("i", $gimnasio_id);
                    $stmt_reload->execute();
                    $gimnasio = $stmt_reload->get_result()->fetch_assoc();
                    $stmt_reload->close();
                } else {
                    $message = "Error al actualizar la configuración.";
                    $msg_type = 'error';
                }
                $stmt->close();
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="robots" content="noindex, nofollow">
<title>Configuración - <?= $gimnasio_nombre ?></title>

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
        padding: 1rem 1.5rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        animation: slideDown 0.5s ease;
    }

    .alert-custom.success {
        background: rgba(81, 207, 102, 0.15);
        border: 1px solid rgba(81, 207, 102, 0.3);
        color: var(--success);
    }

    .alert-custom.error {
        background: rgba(255, 107, 107, 0.15);
        border: 1px solid rgba(255, 107, 107, 0.3);
        color: var(--danger);
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Form Container */
    .form-container {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 2.5rem;
        margin-bottom: 2rem;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .section-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .section-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin: 0;
    }

    .section-subtitle {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.6);
        margin: 0;
    }

    /* Form Groups */
    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.95rem;
    }

    .form-label .required {
        color: var(--danger);
        margin-left: 0.2rem;
    }

    .form-control-custom {
        width: 100%;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 0.9rem 1rem;
        color: #fff;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .form-control-custom:focus {
        outline: none;
        border-color: var(--primary);
        background: rgba(255, 255, 255, 0.12);
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }

    .form-control-custom::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }

    textarea.form-control-custom {
        resize: vertical;
        min-height: 100px;
    }

    /* Logo Upload */
    .logo-upload-section {
        display: flex;
        gap: 2rem;
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .current-logo {
        flex-shrink: 0;
    }

    .logo-preview {
        width: 150px;
        height: 150px;
        border-radius: 15px;
        background: rgba(255, 255, 255, 0.05);
        border: 2px dashed rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .logo-preview img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .logo-preview i {
        font-size: 3rem;
        color: rgba(255, 255, 255, 0.3);
    }

    .upload-info {
        flex: 1;
        min-width: 250px;
    }

    .file-input-wrapper {
        position: relative;
        display: inline-block;
        margin-top: 1rem;
    }

    .file-input-custom {
        display: none;
    }

    .file-input-label {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: #fff;
        padding: 0.8rem 1.5rem;
        border-radius: 10px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .file-input-label:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }

    .file-name {
        margin-top: 0.5rem;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.6);
    }

    /* Help Text */
    .form-help {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.5);
        margin-top: 0.5rem;
    }

    /* Buttons */
    .btn-container {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
        flex-wrap: wrap;
    }

    .btn-primary-custom {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border: none;
        padding: 1rem 2.5rem;
        border-radius: 12px;
        color: #fff;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary-custom {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.2);
        padding: 1rem 2rem;
        border-radius: 12px;
        color: #fff;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-secondary-custom:hover {
        background: rgba(255, 255, 255, 0.12);
    }

    /* Security Notice */
    .security-notice {
        background: rgba(77, 171, 247, 0.1);
        border: 1px solid rgba(77, 171, 247, 0.3);
        border-radius: 15px;
        padding: 1.5rem;
        margin-top: 2rem;
        display: flex;
        gap: 1rem;
    }

    .security-notice i {
        color: var(--info);
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    @media (max-width: 1024px) {
        .content-wrapper {
            margin-left: 0;
        }
    }

    @media (max-width: 768px) {
        .form-container {
            padding: 1.5rem;
        }

        .logo-upload-section {
            flex-direction: column;
        }

        .btn-container {
            flex-direction: column;
        }

        .btn-primary-custom,
        .btn-secondary-custom {
            width: 100%;
            justify-content: center;
        }
    }
</style>
</head>
<body>

<?php include_once(__DIR__ . "/sidebar.php"); ?>

<div class="content-wrapper">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-cog me-2"></i>Configuración del Gimnasio
        </h1>
        <p class="page-subtitle">Administra la información general de tu gimnasio</p>
    </div>

    <!-- Alert Message -->
    <?php if($message): ?>
        <div class="alert-custom <?= $msg_type ?>">
            <i class="fas fa-<?= $msg_type === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
            <span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="configForm">
        
        <!-- Información General -->
        <div class="form-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div>
                    <h3 class="section-title">Información General</h3>
                    <p class="section-subtitle">Datos básicos de tu gimnasio</p>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <label class="form-label">
                            Nombre del Gimnasio <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="nombre" 
                            class="form-control-custom" 
                            value="<?= htmlspecialchars($gimnasio['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                            required
                            maxlength="255"
                        >
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label class="form-label">
                            Dirección <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="direccion" 
                            class="form-control-custom" 
                            value="<?= htmlspecialchars($gimnasio['direccion'], ENT_QUOTES, 'UTF-8') ?>"
                            required
                            maxlength="255"
                        >
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label class="form-label">
                            Altura <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="altura" 
                            class="form-control-custom" 
                            value="<?= htmlspecialchars($gimnasio['altura'], ENT_QUOTES, 'UTF-8') ?>"
                            required
                            maxlength="50"
                        >
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">
                            Localidad <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="localidad" 
                            class="form-control-custom" 
                            value="<?= htmlspecialchars($gimnasio['localidad'], ENT_QUOTES, 'UTF-8') ?>"
                            required
                            maxlength="100"
                        >
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">
                            Partido/Provincia <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="partido" 
                            class="form-control-custom" 
                            value="<?= htmlspecialchars($gimnasio['partido'], ENT_QUOTES, 'UTF-8') ?>"
                            required
                            maxlength="100"
                        >
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <label class="form-label">
                            Email de Contacto <span class="required">*</span>
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            class="form-control-custom" 
                            value="<?= htmlspecialchars($gimnasio['email'], ENT_QUOTES, 'UTF-8') ?>"
                            required
                            maxlength="255"
                        >
                        <p class="form-help">Este email se usará para las comunicaciones oficiales</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logo -->
        <div class="form-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-image"></i>
                </div>
                <div>
                    <h3 class="section-title">Logo del Gimnasio</h3>
                    <p class="section-subtitle">Personaliza la imagen de tu marca</p>
                </div>
            </div>

            <div class="logo-upload-section">
                <div class="current-logo">
                    <div class="logo-preview" id="logoPreview">
                        <?php if(!empty($gimnasio['logo'])): ?>
                            <img src="<?= $base_url . htmlspecialchars($gimnasio['logo'], ENT_QUOTES, 'UTF-8') ?>" alt="Logo">
                        <?php else: ?>
                            <i class="fas fa-image"></i>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="upload-info">
                    <label class="form-label">Subir nuevo logo</label>
                    <p class="form-help">
                        Formatos permitidos: JPG, PNG, GIF, WEBP<br>
                        Tamaño máximo: 2MB<br>
                        Recomendado: 500x500px
                    </p>
                    
                    <div class="file-input-wrapper">
                        <input 
                            type="file" 
                            name="logo" 
                            id="logoInput" 
                            class="file-input-custom"
                            accept="image/jpeg,image/png,image/gif,image/webp"
                        >
                        <label for="logoInput" class="file-input-label">
                            <i class="fas fa-upload"></i>
                            Seleccionar archivo
                        </label>
                    </div>
                    <div class="file-name" id="fileName"></div>
                </div>
            </div>
        </div>

        <!-- MercadoPago -->
        <div class="form-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div>
                    <h3 class="section-title">Integración MercadoPago</h3>
                    <p class="section-subtitle">Configura los pagos online</p>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Public Key</label>
                        <input 
                            type="text" 
                            name="mp_key" 
                            class="form-control-custom" 
                            value="<?= htmlspecialchars($gimnasio['mp_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="APP_USR-xxxxxxxx-xxxx-xxxx"
                            maxlength="255"
                        >
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Access Token</label>
                        <input 
                            type="text" 
                            name="mp_token" 
                            class="form-control-custom" 
                            value="<?= htmlspecialchars($gimnasio['mp_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="APP_USR-xxxxxxxx-xxxx-xxxx"
                            maxlength="255"
                        >
                    </div>
                </div>
            </div>

            <p class="form-help">
                <i class="fas fa-info-circle me-1"></i>
                Obtén tus credenciales en tu cuenta de MercadoPago → Configuración → Credenciales
            </p>
        </div>

        <!-- Buttons -->
        <div class="btn-container">
            <button type="submit" class="btn-primary-custom">
                <i class="fas fa-save"></i>
                Guardar Cambios
            </button>
            <button type="button" class="btn-secondary-custom" onclick="window.location.reload()">
                <i class="fas fa-times"></i>
                Cancelar
            </button>
        </div>

        <!-- Security Notice -->
        <div class="security-notice">
            <i class="fas fa-shield-alt"></i>
            <div>
                <strong>Seguridad de tus datos</strong>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: rgba(255,255,255,0.7);">
                    Toda tu información está protegida y encriptada. Nunca compartiremos tus datos con terceros.
                </p>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Preview de logo
    const logoInput = document.getElementById('logoInput');
    const logoPreview = document.getElementById('logoPreview');
    const fileName = document.getElementById('fileName');

    logoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        
        if(file) {
            // Validar tamaño
            if(file.size > 2097152) {
                alert('El archivo es demasiado grande. Máximo 2MB.');
                this.value = '';
                return;
            }

            // Validar tipo
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if(!allowedTypes.includes(file.type)) {
                alert('Formato no permitido. Solo JPG, PNG, GIF, WEBP.');
                this.value = '';
                return;
            }

            // Mostrar nombre
            fileName.textContent = '✓ ' + file.name;

            // Preview
            const reader = new FileReader();
            reader.onload = function(e) {
                logoPreview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        }
    });

    // Confirmación antes de enviar
    document.getElementById('configForm').addEventListener('submit', function(e) {
        const confirmMsg = '¿Estás seguro de que deseas guardar los cambios?';
        if(!confirm(confirmMsg)) {
            e.preventDefault();
        }
    });

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
</script>

</body>
</html>
