<?php
session_start();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="/index.php">
      <img src="<?= $config['logo'] ?? '/assets/img/logo.png'; ?>" alt="Logo" height="35" class="me-2">
      <?= $config['nombre_sitio'] ?? 'Gimnasio System SAAS'; ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav">
        <?php if(isset($_SESSION['gimnasio_id'])): ?>
          <li class="nav-item"><a href="/gimnasios/index.php" class="nav-link">Panel</a></li>
          <li class="nav-item"><a href="/logout.php" class="nav-link">Cerrar Sesión</a></li>
        <?php else: ?>
          <li class="nav-item"><a href="/login.php" class="nav-link">Ingresar</a></li>
          <li class="nav-item"><a href="/register.php" class="nav-link">Registrarse</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
