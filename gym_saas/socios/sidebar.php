<?php
if(!isset($_SESSION)) session_start();
?>
<nav class="sidebar sidebar-offcanvas" style="width:220px; position:fixed; top:0; left:0; height:100%; background:#141416; color:#eaeaea; padding-top:20px; display:flex; flex-direction:column;">
  <div class="sidebar-brand text-center mb-4">
    <h3>🏋️‍♂️ Panel Socio</h3>
  </div>
  <ul class="nav flex-column">
    <li class="nav-item mb-2"><a href="index.php" class="nav-link text-light">Dashboard</a></li>
    <li class="nav-item mb-2"><a href="dashboard.php" class="nav-link text-light">Estado Licencias</a></li>
    <li class="nav-item mb-2"><a href="configuracion.php" class="nav-link text-light">Configuración</a></li>
    <li class="nav-item mt-auto"><a href="/logout.php" class="nav-link text-danger">Cerrar sesión</a></li>
  </ul>
</nav>
