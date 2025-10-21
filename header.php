<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Barbería - Sistema de Gestión</title>
  
  <!-- Bootstrap 5.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
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
      --sidebar-width: 240px;
      --navbar-height: 70px;
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
      color: #fff;
      overflow-x: hidden;
      min-height: 100vh;
    }
    
    /* Navbar superior */
    .navbar-custom {
      position: fixed;
      top: 0;
      left: var(--sidebar-width);
      right: 0;
      height: var(--navbar-height);
      background: rgba(10, 14, 39, 0.95);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      padding: 0 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      z-index: 999;
      transition: left 0.3s ease;
    }
    
    .navbar-left h2 {
      font-size: 1.5rem;
      font-weight: 700;
      margin: 0;
      background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    
    .navbar-right {
      display: flex;
      align-items: center;
      gap: 1.5rem;
    }
    
    .user-info {
      display: flex;
      align-items: center;
      gap: 1rem;
      color: white;
    }
    
    .user-avatar {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      color: white;
      font-size: 1rem;
    }
    
    .user-details {
      text-align: right;
    }
    
    .user-name {
      font-size: 0.9rem;
      font-weight: 600;
      margin: 0;
    }
    
    .user-role {
      font-size: 0.75rem;
      color: rgba(255, 255, 255, 0.6);
      margin: 0;
    }
    
    .btn-logout {
      background: rgba(255, 107, 107, 0.1);
      border: 1px solid rgba(255, 107, 107, 0.3);
      color: var(--danger);
      padding: 0.6rem 1.2rem;
      border-radius: 12px;
      transition: all 0.3s ease;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .btn-logout:hover {
      background: rgba(255, 107, 107, 0.2);
      transform: translateY(-1px);
      color: var(--danger);
    }
    
    /* Área de contenido - CORREGIDO */
    .content {
      margin-left: var(--sidebar-width);
      margin-top: var(--navbar-height);
      padding: 2rem;
      min-height: calc(100vh - var(--navbar-height));
      transition: margin-left 0.3s ease;
      width: calc(100% - var(--sidebar-width));
    }
    
    /* Cards mejoradas */
    .card {
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 20px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      overflow: hidden;
    }
    
    .card:hover {
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      transform: translateY(-2px);
    }
    
    .card-header {
      background: rgba(255, 255, 255, 0.05);
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      padding: 1.25rem 1.5rem;
      font-weight: 600;
      color: white;
    }
    
    .card-body {
      padding: 1.5rem;
    }
    
    /* Stats cards con gradientes */
    .stat-card {
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 20px;
      padding: 1.5rem;
      position: relative;
      overflow: hidden;
      transition: all 0.3s ease;
    }
    
    .stat-card::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
      animation: pulse 4s ease-in-out infinite;
    }
    
    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 0.5; }
      50% { transform: scale(1.1); opacity: 0.8; }
    }
    
    .stat-card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }
    
    .stat-card-success {
      background: linear-gradient(135deg, rgba(81, 207, 102, 0.2) 0%, rgba(55, 178, 77, 0.2) 100%);
      border: 1px solid rgba(81, 207, 102, 0.3);
    }
    
    .stat-card-primary {
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(85, 104, 211, 0.2) 100%);
      border: 1px solid rgba(102, 126, 234, 0.3);
    }
    
    .stat-card-warning {
      background: linear-gradient(135deg, rgba(255, 212, 59, 0.2) 0%, rgba(250, 176, 5, 0.2) 100%);
      border: 1px solid rgba(255, 212, 59, 0.3);
    }
    
    .stat-card-dark {
      background: linear-gradient(135deg, rgba(26, 31, 58, 0.8) 0%, rgba(10, 14, 39, 0.8) 100%);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .stat-card-title {
      font-size: 0.875rem;
      opacity: 0.9;
      font-weight: 500;
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .stat-card-value {
      font-size: 2.5rem;
      font-weight: 700;
      line-height: 1;
      position: relative;
      z-index: 1;
    }
    
    .stat-card-icon {
      position: absolute;
      right: 1.5rem;
      top: 50%;
      transform: translateY(-50%);
      font-size: 3rem;
      opacity: 0.2;
      transition: all 0.4s ease;
    }
    
    .stat-card:hover .stat-card-icon {
      transform: translateY(-50%) scale(1.2) rotate(10deg);
      opacity: 0.3;
    }
    
    /* Botones modernos */
    .btn {
      border-radius: 12px;
      padding: 0.625rem 1.25rem;
      font-weight: 600;
      transition: all 0.3s ease;
      border: none;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white;
    }
    
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
      color: white;
    }
    
    .btn-success {
      background: linear-gradient(135deg, #51cf66 0%, #37b24d 100%);
      color: white;
    }
    
    .btn-success:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(81, 207, 102, 0.4);
      color: white;
    }
    
    .btn-danger {
      background: linear-gradient(135deg, #ff6b6b 0%, #fa5252 100%);
      color: white;
    }
    
    .btn-danger:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
      color: white;
    }
    
    /* Tablas modernas */
    .table {
      background: rgba(255, 255, 255, 0.05);
      border-radius: 15px;
      overflow: hidden;
      color: white;
    }
    
    .table thead th {
      background: rgba(10, 14, 39, 0.8);
      color: white;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
      padding: 1rem;
      border: none;
    }
    
    .table tbody tr {
      transition: all 0.2s ease;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    
    .table tbody tr:hover {
      background: rgba(255, 255, 255, 0.05);
    }
    
    .table td {
      padding: 1rem;
      vertical-align: middle;
      border: none;
    }
    
    /* Forms */
    .form-control,
    .form-select {
      background: rgba(255, 255, 255, 0.05);
      border: 2px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      padding: 0.75rem 1rem;
      transition: all 0.3s ease;
      color: white;
    }
    
    .form-control:focus,
    .form-select:focus {
      background: rgba(255, 255, 255, 0.08);
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
      color: white;
    }
    
    .form-control::placeholder {
      color: rgba(255, 255, 255, 0.4);
    }
    
    .form-label {
      font-weight: 600;
      color: white;
      margin-bottom: 0.5rem;
    }
    
    /* Alerts mejoradas */
    .alert {
      border: none;
      border-radius: 15px;
      padding: 1rem 1.25rem;
      backdrop-filter: blur(10px);
    }
    
    .alert-success {
      background: rgba(81, 207, 102, 0.2);
      border: 1px solid rgba(81, 207, 102, 0.3);
      color: var(--success);
    }
    
    .alert-danger {
      background: rgba(255, 107, 107, 0.2);
      border: 1px solid rgba(255, 107, 107, 0.3);
      color: var(--danger);
    }
    
    .alert-warning {
      background: rgba(255, 212, 59, 0.2);
      border: 1px solid rgba(255, 212, 59, 0.3);
      color: var(--warning);
    }
    
    .alert-info {
      background: rgba(74, 171, 247, 0.2);
      border: 1px solid rgba(74, 171, 247, 0.3);
      color: var(--info);
    }
    
    /* Responsive - CORREGIDO */
    @media (max-width: 1024px) {
      :root {
        --sidebar-width: 0;
      }
      
      .navbar-custom {
        left: 0;
        width: 100%;
      }
      
      .content {
        margin-left: 0;
        width: 100%;
      }
      
      .sidebar {
        transform: translateX(-100%);
      }
      
      .sidebar.active {
        transform: translateX(0);
      }
    }
    
    @media (max-width: 768px) {
      .content {
        padding: 1rem;
      }
      
      .stat-card-value {
        font-size: 2rem;
      }
      
      .navbar-custom {
        padding: 0 1rem;
      }
      
      .navbar-left h2 {
        font-size: 1.2rem;
      }
      
      .user-details {
        display: none;
      }
    }
    
    @media (max-width: 575.98px) {
      .stat-card-value {
        font-size: 1.75rem !important;
      }
      
      .stat-card-icon {
        font-size: 2.5rem !important;
      }
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar-custom">
    <div class="navbar-left">
      <h2><i class="bi bi-scissors me-2"></i>Sistema Barbería</h2>
    </div>
    <div class="navbar-right">
      <?php if(isset($_SESSION['user_name'])): ?>
        <div class="user-info">
          <div class="user-details">
            <div class="user-name"><?= e($_SESSION['user_name']) ?></div>
            <div class="user-role">
              <i class="bi bi-circle-fill text-success" style="font-size: 0.5rem;"></i> 
              <?= e(ucfirst($_SESSION['user_role'])) ?>
            </div>
          </div>
          <div class="user-avatar">
            <?= strtoupper(substr(e($_SESSION['user_name']), 0, 2)) ?>
          </div>
        </div>
        <a href="logout.php" class="btn-logout">
          <i class="bi bi-box-arrow-right"></i> Salir
        </a>
      <?php endif; ?>
    </div>
  </nav>
  
  <div class="container-fluid p-0">
    <div class="row g-0">