<?php
// ==============================================
// Gimnasio System SAAS - Página Principal
// ==============================================
include_once(__DIR__ . "/includes/db_connect.php");

// Obtener licencias activas
$licencias = $conn->query("SELECT * FROM licencias WHERE estado='activo' ORDER BY precio ASC")->fetch_all(MYSQLI_ASSOC);

// Obtener configuración del sitio
$config = [];
$result = $conn->query("SELECT clave, valor FROM config");
while ($row = $result->fetch_assoc()) {
  $config[$row['clave']] = $row['valor'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $config['nombre_sitio'] ?? 'Gimnasio System SAAS'; ?></title>
  <link rel="icon" href="<?= $config['favicon'] ?? '/assets/img/favicon.png'; ?>">
  
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body { font-family: 'Poppins', sans-serif; background: #f8f9fa; color: #222; }
    .hero { background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('/assets/img/gym-bg.jpg') center/cover no-repeat; color: #fff; text-align: center; padding: 120px 20px; }
    .hero h1 { font-size: 3rem; font-weight: 700; }
    .hero p { font-size: 1.2rem; margin-top: 15px; color: #ddd; }
    .btn-main { background: #198754; color: #fff; border-radius: 8px; padding: 12px 30px; font-weight: 500; }
    .btn-main:hover { background: #157347; }
    .section-title { text-align: center; font-size: 2rem; font-weight: 600; margin-top: 80px; margin-bottom: 40px; color: #222; }
    .card-licencia { border: none; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); transition: transform .3s; }
    .card-licencia:hover { transform: translateY(-8px); }
    footer { background: #212529; color: #ccc; padding: 40px 0; text-align: center; }
    footer a { color: #00d1b2; text-decoration: none; }
  </style>
</head>

<body>

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="#">
        <img src="<?= $config['logo'] ?? '/assets/img/logo.png'; ?>" alt="Logo" height="40" class="me-2">
        <?= $config['nombre_sitio'] ?? 'Gimnasio System SAAS'; ?>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav">
          <li class="nav-item"><a href="#licencias" class="nav-link">Licencias</a></li>
          <li class="nav-item"><a href="#contacto" class="nav-link">Contacto</a></li>
          <li class="nav-item"><a href="login.php" class="btn btn-outline-light mx-2">Iniciar Sesión</a></li>
          <li class="nav-item"><a href="register.php" class="btn btn-success">Registrarse</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- HERO -->
  <section class="hero d-flex flex-column justify-content-center align-items-center">
    <h1>Administra tu Gimnasio desde un solo lugar</h1>
    <p>Registra socios, controla membresías, genera reportes y gestiona tus pagos fácilmente.</p>
    <a href="register.php" class="btn btn-main mt-3"><i class="fas fa-dumbbell me-2"></i>Comenzar Ahora</a>
  </section>

  <!-- LICENCIAS -->
  <section id="licencias" class="container my-5">
    <h2 class="section-title"><i class="fas fa-id-card-alt me-2 text-success"></i>Planes de Licencias</h2>
    <div class="row justify-content-center">
      <?php foreach ($licencias as $lic): ?>
        <div class="col-md-4 mb-4">
          <div class="card card-licencia text-center p-4">
            <h4 class="fw-bold"><?= htmlspecialchars($lic['nombre']); ?></h4>
            <p class="text-muted"><?= $lic['dias']; ?> días de duración</p>
            <h3 class="text-success mb-3">$<?= number_format($lic['precio'], 2, ',', '.'); ?></h3>
            <a href="login.php" class="btn btn-success"><i class="fas fa-check me-1"></i>Contratar</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- INFORMACION -->
  <section class="container my-5 text-center">
    <h2 class="section-title"><i class="fas fa-layer-group me-2 text-success"></i>¿Cómo funciona?</h2>
    <div class="row">
      <div class="col-md-4">
        <i class="fas fa-user-shield fa-3x text-success mb-3"></i>
        <h5>SuperAdmin</h5>
        <p>Gestiona gimnasios, licencias, pagos y la configuración global del sistema.</p>
      </div>
      <div class="col-md-4">
        <i class="fas fa-dumbbell fa-3x text-success mb-3"></i>
        <h5>Gimnasios</h5>
        <p>Crean membresías, registran socios, validan accesos y administran su propio sitio web.</p>
      </div>
      <div class="col-md-4">
        <i class="fas fa-user fa-3x text-success mb-3"></i>
        <h5>Socios</h5>
        <p>Acceden a su panel, renuevan licencias y consultan sus pagos desde cualquier dispositivo.</p>
      </div>
    </div>
  </section>

  <!-- CONTACTO -->
  <section id="contacto" class="bg-light py-5">
    <div class="container text-center">
      <h2 class="section-title"><i class="fas fa-envelope me-2 text-success"></i>Contacto</h2>
      <p><strong>Email:</strong> <?= $config['contacto_email'] ?? 'info@sistema.com'; ?></p>
      <p><strong>Teléfono:</strong> <?= $config['contacto_telefono'] ?? '+54 9 11 9999 9999'; ?></p>
      <a href="login.php" class="btn btn-success mt-3"><i class="fas fa-sign-in-alt me-2"></i>Ingresar al Sistema</a>
    </div>
  </section>

  <!-- FOOTER -->
  <footer>
    <p><?= $config['footer_texto'] ?? '© 2025 Gimnasio System SAAS - Todos los derechos reservados'; ?></p>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
