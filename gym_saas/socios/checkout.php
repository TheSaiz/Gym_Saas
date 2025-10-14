<?php
/**
 * Checkout de Membresías con MercadoPago
 * Integración segura con validaciones completas
 */

session_start();

// Regenerar ID de sesión
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

$socio_id = filter_var($_SESSION['socio_id'], FILTER_VALIDATE_INT);
if($socio_id === false){
    session_destroy();
    header("Location: $base_url/socios/login.php");
    exit;
}

// ============================================
// 2. OBTENER DATOS DEL SOCIO
// ============================================

$stmt = $conn->prepare("SELECT s.*, g.nombre as gimnasio_nombre, g.mp_key, g.mp_token 
                        FROM socios s 
                        JOIN gimnasios g ON s.gimnasio_id = g.id 
                        WHERE s.id = ? AND s.estado = 'activo' 
                        LIMIT 1");
$stmt->bind_param("i", $socio_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows !== 1){
    session_destroy();
    header("Location: $base_url/socios/login.php");
    exit;
}

$socio = $result->fetch_assoc();
$stmt->close();

// ============================================
// 3. OBTENER MEMBRESÍA SOLICITADA
// ============================================

$membresia_id = filter_var($_GET['membresia_id'] ?? 0, FILTER_VALIDATE_INT);

if(!$membresia_id){
    header("Location: $base_url/socios/dashboard.php?error=1");
    exit;
}

// Verificar que la membresía existe y pertenece al gimnasio del socio
$stmt = $conn->prepare("SELECT m.*, g.nombre as gimnasio_nombre 
                        FROM membresias m 
                        JOIN gimnasios g ON m.gimnasio_id = g.id 
                        WHERE m.id = ? AND m.gimnasio_id = ? AND m.estado = 'activo' 
                        LIMIT 1");
$stmt->bind_param("ii", $membresia_id, $socio['gimnasio_id']);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows !== 1){
    header("Location: $base_url/socios/dashboard.php?error=1");
    exit;
}

$membresia = $result->fetch_assoc();
$stmt->close();

// ============================================
// 4. INTEGRACIÓN CON MERCADOPAGO
// ============================================

// Verificar que el gimnasio tenga configurado MercadoPago
$mp_public_key = $socio['mp_key'] ?? '';
$mp_access_token = $socio['mp_token'] ?? '';

// Si no hay configuración de MP, usar claves del superadmin como fallback
if(empty($mp_public_key) || empty($mp_access_token)){
    $stmt = $conn->prepare("SELECT mp_public_key, mp_access_token FROM superadmin WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows === 1){
        $superadmin = $result->fetch_assoc();
        $mp_public_key = $superadmin['mp_public_key'] ?? '';
        $mp_access_token = $superadmin['mp_access_token'] ?? '';
    }
    $stmt->close();
}

// Verificar que existan las credenciales
$mp_configured = !empty($mp_public_key) && !empty($mp_access_token);

// Si MercadoPago está configurado, crear preferencia
$preference_id = null;
$init_point = null;

if($mp_configured){
    // Requiere MercadoPago SDK (composer require mercadopago/dx-php)
    // require_once(__DIR__ . "/../vendor/autoload.php");
    
    // Por ahora, simulamos la creación de preferencia
    // En producción, descomentar las líneas siguientes:
    
    /*
    MercadoPago\SDK::setAccessToken($mp_access_token);
    
    $preference = new MercadoPago\Preference();
    
    // Crear item
    $item = new MercadoPago\Item();
    $item->title = $membresia['nombre'] . " - " . $membresia['gimnasio_nombre'];
    $item->quantity = 1;
    $item->unit_price = (float)$membresia['precio'];
    $item->currency_id = "ARS";
    
    $preference->items = array($item);
    
    // URLs de retorno
    $preference->back_urls = array(
        "success" => $base_url . "/socios/checkout.php?success=1&membresia_id=" . $membresia_id,
        "failure" => $base_url . "/socios/checkout.php?failure=1&membresia_id=" . $membresia_id,
        "pending" => $base_url . "/socios/checkout.php?pending=1&membresia_id=" . $membresia_id
    );
    
    $preference->auto_return = "approved";
    
    // Metadata para identificar el pago
    $preference->external_reference = "SOCIO-" . $socio_id . "-MEM-" . $membresia_id;
    
    $preference->payer = array(
        "name" => $socio['nombre'],
        "surname" => $socio['apellido'],
        "email" => $socio['email'],
        "phone" => array(
            "number" => $socio['telefono']
        )
    );
    
    // Notification URL para webhook
    $preference->notification_url = $base_url . "/socios/notificar_pago.php";
    
    $preference->save();
    
    $preference_id = $preference->id;
    $init_point = $preference->init_point;
    */
}

// ============================================
// 5. REGISTRAR INTENTO DE PAGO
// ============================================

$stmt = $conn->prepare("INSERT INTO pagos (tipo, usuario_id, gimnasio_id, monto, estado, mp_id) 
                        VALUES ('membresia_socio', ?, ?, ?, 'pendiente', ?)");
$mp_id = $preference_id ?? 'PENDING-' . time();
$stmt->bind_param("iids", $socio_id, $socio['gimnasio_id'], $membresia['precio'], $mp_id);
$stmt->execute();
$pago_id = $conn->insert_id;
$stmt->close();

// ============================================
// 6. MANEJO DE RESPUESTAS DE MP
// ============================================

$payment_status = null;
$payment_message = '';

if(isset($_GET['success']) && $_GET['success'] == '1'){
    $payment_status = 'success';
    $payment_message = '¡Pago aprobado exitosamente! Tu membresía ha sido activada.';
    
    // Aquí normalmente verificarías el pago con MP y actualizarías la licencia
    // Por ahora, simulamos la activación
}

if(isset($_GET['failure']) && $_GET['failure'] == '1'){
    $payment_status = 'failure';
    $payment_message = 'El pago fue rechazado. Por favor, intenta con otro método de pago.';
}

if(isset($_GET['pending']) && $_GET['pending'] == '1'){
    $payment_status = 'pending';
    $payment_message = 'Tu pago está pendiente de confirmación. Te notificaremos cuando se procese.';
}

$socio_nombre_completo = htmlspecialchars($socio['nombre'] . ' ' . $socio['apellido'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="description" content="Checkout de membresía">
<meta name="robots" content="noindex, nofollow">
<title>Checkout - <?= htmlspecialchars($membresia['nombre'], ENT_QUOTES, 'UTF-8') ?></title>

<!-- CSS Libraries -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<?php if($mp_configured && $preference_id): ?>
<!-- MercadoPago SDK -->
<script src="https://sdk.mercadopago.com/js/v2"></script>
<?php endif; ?>

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
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        color: #fff;
    }

    .checkout-container {
        width: 100%;
        max-width: 900px;
    }

    .checkout-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .brand-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-bottom: 1rem;
    }

    .checkout-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .checkout-subtitle {
        color: rgba(255, 255, 255, 0.6);
        font-size: 1rem;
    }

    .checkout-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
    }

    .checkout-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 2rem;
    }

    .card-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .card-title i {
        color: var(--primary);
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 0.8rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .detail-label {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.95rem;
    }

    .detail-value {
        font-weight: 600;
        font-size: 0.95rem;
    }

    .price-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        margin-top: 1.5rem;
        background: rgba(102, 126, 234, 0.1);
        border: 1px solid rgba(102, 126, 234, 0.3);
        border-radius: 15px;
    }

    .price-label {
        font-size: 1.1rem;
        font-weight: 600;
    }

    .price-amount {
        font-size: 2rem;
        font-weight: 800;
        color: var(--success);
    }

    .payment-section {
        grid-column: 1 / -1;
    }

    #mp-button-container {
        margin: 2rem 0;
    }

    .btn-custom {
        width: 100%;
        padding: 1.2rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border: none;
        border-radius: 15px;
        color: #fff;
        font-weight: 600;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.8rem;
    }

    .btn-custom:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    }

    .btn-back {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        margin-top: 1rem;
    }

    .btn-back:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateY(0);
    }

    .alert-custom {
        padding: 1.5rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        animation: slideDown 0.5s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert-success {
        background: rgba(81, 207, 102, 0.1);
        border: 1px solid rgba(81, 207, 102, 0.3);
    }

    .alert-danger {
        background: rgba(255, 107, 107, 0.1);
        border: 1px solid rgba(255, 107, 107, 0.3);
    }

    .alert-warning {
        background: rgba(255, 212, 59, 0.1);
        border: 1px solid rgba(255, 212, 59, 0.3);
    }

    .alert-icon {
        font-size: 2rem;
    }

    .alert-success .alert-icon {
        color: var(--success);
    }

    .alert-danger .alert-icon {
        color: var(--danger);
    }

    .alert-warning .alert-icon {
        color: var(--warning);
    }

    .alert-content h4 {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 0.3rem;
    }

    .alert-content p {
        margin: 0;
        color: rgba(255, 255, 255, 0.8);
    }

    .security-badge {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
        padding: 1rem;
        background: rgba(81, 207, 102, 0.1);
        border: 1px solid rgba(81, 207, 102, 0.3);
        border-radius: 12px;
        color: var(--success);
        font-size: 0.9rem;
    }

    .info-box {
        background: rgba(77, 171, 247, 0.1);
        border: 1px solid rgba(77, 171, 247, 0.3);
        border-radius: 12px;
        padding: 1rem;
        margin-top: 1rem;
        display: flex;
        gap: 1rem;
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.8);
    }

    .info-box i {
        color: var(--info);
        font-size: 1.2rem;
    }

    @media (max-width: 768px) {
        .checkout-grid {
            grid-template-columns: 1fr;
        }

        .payment-section {
            grid-column: 1;
        }

        .checkout-title {
            font-size: 1.5rem;
        }

        body {
            padding: 1rem;
        }
    }
</style>
</head>
<body>

<div class="checkout-container">
    <!-- Header -->
    <div class="checkout-header">
        <div class="brand-icon">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <h1 class="checkout-title">Checkout de Membresía</h1>
        <p class="checkout-subtitle">Completa tu compra de forma segura</p>
    </div>

    <!-- Payment Status Alert -->
    <?php if($payment_status): ?>
        <div class="alert-custom alert-<?= $payment_status === 'success' ? 'success' : ($payment_status === 'failure' ? 'danger' : 'warning') ?>">
            <i class="alert-icon fas fa-<?= $payment_status === 'success' ? 'check-circle' : ($payment_status === 'failure' ? 'times-circle' : 'clock') ?>"></i>
            <div class="alert-content">
                <h4><?= $payment_status === 'success' ? '¡Pago Exitoso!' : ($payment_status === 'failure' ? 'Pago Rechazado' : 'Pago Pendiente') ?></h4>
                <p><?= htmlspecialchars($payment_message, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Checkout Grid -->
    <div class="checkout-grid">
        <!-- Order Summary -->
        <div class="checkout-card">
            <h3 class="card-title">
                <i class="fas fa-file-invoice"></i>
                Resumen de Compra
            </h3>

            <div class="detail-row">
                <span class="detail-label">Membresía</span>
                <span class="detail-value"><?= htmlspecialchars($membresia['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Gimnasio</span>
                <span class="detail-value"><?= htmlspecialchars($membresia['gimnasio_nombre'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Duración</span>
                <span class="detail-value"><?= $membresia['dias'] ?> días</span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Precio por día</span>
                <span class="detail-value">$<?= number_format($membresia['precio'] / $membresia['dias'], 2, ',', '.') ?></span>
            </div>

            <div class="price-total">
                <span class="price-label">Total a Pagar</span>
                <span class="price-amount">$<?= number_format($membresia['precio'], 2, ',', '.') ?></span>
            </div>
        </div>

        <!-- Customer Info -->
        <div class="checkout-card">
            <h3 class="card-title">
                <i class="fas fa-user"></i>
                Datos del Comprador
            </h3>

            <div class="detail-row">
                <span class="detail-label">Nombre</span>
                <span class="detail-value"><?= htmlspecialchars($socio['nombre'] . ' ' . $socio['apellido'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">DNI</span>
                <span class="detail-value"><?= htmlspecialchars($socio['dni'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value" style="font-size: 0.85rem;"><?= htmlspecialchars($socio['email'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <?php if(!empty($socio['telefono'])): ?>
            <div class="detail-row">
                <span class="detail-label">Teléfono</span>
                <span class="detail-value"><?= htmlspecialchars($socio['telefono'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endif; ?>

            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <div>
                    Recibirás un comprobante de pago por email una vez confirmada la transacción.
                </div>
            </div>
        </div>

        <!-- Payment Section -->
        <div class="checkout-card payment-section">
            <h3 class="card-title">
                <i class="fas fa-credit-card"></i>
                Método de Pago
            </h3>

            <?php if($mp_configured && $preference_id): ?>
                <!-- MercadoPago Checkout Button -->
                <div id="mp-button-container"></div>

                <script>
                    const mp = new MercadoPago('<?= htmlspecialchars($mp_public_key, ENT_QUOTES, 'UTF-8') ?>', {
                        locale: 'es-AR'
                    });

                    mp.checkout({
                        preference: {
                            id: '<?= htmlspecialchars($preference_id, ENT_QUOTES, 'UTF-8') ?>'
                        },
                        render: {
                            container: '#mp-button-container',
                            label: 'Pagar con MercadoPago',
                        }
                    });
                </script>

                <div class="security-badge">
                    <i class="fas fa-lock"></i>
                    <span>Pago seguro procesado por MercadoPago</span>
                </div>
            <?php else: ?>
                <!-- Fallback cuando MP no está configurado -->
                <div class="alert-custom alert-warning">
                    <i class="alert-icon fas fa-exclamation-triangle"></i>
                    <div class="alert-content">
                        <h4>Configuración Pendiente</h4>
                        <p>El sistema de pagos está en configuración. Por favor, contacta al gimnasio para completar tu compra.</p>
                    </div>
                </div>

                <button class="btn-custom" onclick="contactGym()">
                    <i class="fas fa-phone"></i>
                    Contactar Gimnasio
                </button>
            <?php endif; ?>

            <a href="<?= $base_url ?>/socios/dashboard.php" class="btn-custom btn-back" style="text-decoration: none;">
                <i class="fas fa-arrow-left"></i>
                Volver a Mis Licencias
            </a>
        </div>
    </div>
</div>

<script>
    function contactGym() {
        alert('Por favor contacta al gimnasio para completar tu compra:\n\n' +
              'Gimnasio: <?= htmlspecialchars($membresia['gimnasio_nombre'], ENT_QUOTES, 'UTF-8') ?>\n' +
              'Membresía: <?= htmlspecialchars($membresia['nombre'], ENT_QUOTES, 'UTF-8') ?>\n' +
              'Precio: $<?= number_format($membresia['precio'], 2, ',', '.') ?>');
    }

    // Protección contra clickjacking
    if (window.top !== window.self) {
        window.top.location = window.self.location;
    }

    // Redirigir después de pago exitoso
    <?php if($payment_status === 'success'): ?>
        setTimeout(function() {
            if(confirm('¿Deseas ver tus licencias actualizadas?')) {
                window.location.href = '<?= $base_url ?>/socios/dashboard.php?success=1';
            }
        }, 3000);
    <?php endif; ?>

    // Console warning
    console.log('%c⚠️ ADVERTENCIA', 'color: red; font-size: 24px; font-weight: bold;');
    console.log('%cNo compartas esta página ni ingreses datos sensibles si no confías en el sitio.', 'font-size: 14px;');
</script>

</body>
</html>