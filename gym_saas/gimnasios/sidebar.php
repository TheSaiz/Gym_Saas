<?php
// sidebar.php
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<nav class="sidebar sidebar-dark bg-dark vh-100 position-fixed" style="width:220px;">
    <div class="p-3 text-center text-white fw-bold fs-4 mb-4">🏋️‍♂️ Gimnasio</div>
    <ul class="nav flex-column">
        <li class="nav-item"><a href="index.php" class="nav-link text-white"><i class="fas fa-home me-2"></i> Dashboard</a></li>
        <li class="nav-item"><a href="dashboard.php" class="nav-link text-white"><i class="fas fa-chart-bar me-2"></i> Estadísticas</a></li>
        <li class="nav-item"><a href="membresias.php" class="nav-link text-white"><i class="fas fa-id-card me-2"></i> Membresías</a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
    </ul>
</nav>
