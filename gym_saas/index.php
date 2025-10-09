<?php
// ==============================================
// Gimnasio System SAAS - Página Principal
// ==============================================
include_once(__DIR__ . "/includes/db_connect.php");

// Obtener licencias activas
$licencias = $conn->query("SELECT * FROM licencias WHERE estado='activo' ORDER BY precio ASC");
$licencias_array = [];
if($licencias) {
    $licencias_array = $licencias->fetch_all(MYSQLI_ASSOC);
}

// Obtener configuración del sitio
$config = [];
$result = $conn->query("SELECT clave, valor FROM config");
if($result) {
    while ($row = $result->fetch_assoc()) {
        $config[$row['clave']] = $row['valor'];
    }
}

$base_url = "/gym_saas";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $config['nombre_sitio'] ?? 'Gimnasio System SAAS'; ?></title>
    <link rel="icon" href="<?= $base_url ?><?= $config['favicon'] ?? '/assets/img/favicon.png'; ?>">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #0a0e27;
            color: #fff;
            overflow-x: hidden;
        }

        /* Navbar Moderno */
        .navbar-custom {
            background: rgba(10, 14, 39, 0.95);
            backdrop-filter: blur(10px);
            padding: 1.2rem 0;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .navbar-custom.scrolled {
            padding: 0.8rem 0;
            background: rgba(10, 14, 39, 0.98);
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
        }

        .nav-link {
            color: #fff !important;
            margin: 0 0.8rem;
            font-weight: 500;
            position: relative;
            transition: all 0.3s ease;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .btn-outline-custom {
            border: 2px solid #667eea;
            color: #667eea;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-custom:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.5);
        }

        /* Hero Section Ultra Moderno */
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(102, 126, 234, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(118, 75, 162, 0.1) 0%, transparent 50%);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 2rem;
        }

        .hero-title {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #fff 0%, #667eea 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: fadeInUp 1s ease;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 2.5rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            animation: fadeInUp 1s ease 0.2s both;
        }

        .hero-cta {
            animation: fadeInUp 1s ease 0.4s both;
        }

        .btn-hero {
            padding: 1rem 3rem;
            font-size: 1.1rem;
            border-radius: 50px;
            margin: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-hero-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: #fff;
        }

        .btn-hero-primary:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
        }

        .btn-hero-secondary {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }

        .btn-hero-secondary:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-5px);
        }

        /* Floating Animation */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .floating-icon {
            position: absolute;
            animation: float 3s ease-in-out infinite;
            opacity: 0.1;
        }

        .floating-icon-1 {
            top: 20%;
            left: 10%;
            font-size: 3rem;
            animation-delay: 0s;
        }

        .floating-icon-2 {
            top: 60%;
            right: 15%;
            font-size: 4rem;
            animation-delay: 1s;
        }

        .floating-icon-3 {
            bottom: 20%;
            left: 15%;
            font-size: 3.5rem;
            animation-delay: 2s;
        }

        /* Sección de Licencias */
        .licencias-section {
            padding: 6rem 0;
            background: linear-gradient(180deg, #0a0e27 0%, #1a1f3a 100%);
            position: relative;
        }

        .section-title {
            font-size: 3rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff 0%, #667eea 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .section-subtitle {
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 4rem;
            font-size: 1.1rem;
        }

        .pricing-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.4s ease;
            height: 100%;
        }

        .pricing-card:hover {
            transform: translateY(-10px);
            background: rgba(255, 255, 255, 0.08);
            border-color: #667eea;
            box-shadow: 0 20px 50px rgba(102, 126, 234, 0.3);
        }

        .pricing-card.featured {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
            border: 2px solid #667eea;
            transform: scale(1.05);
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #667eea;
        }

        .plan-price {
            font-size: 3rem;
            font-weight: 800;
            margin: 1.5rem 0;
            background: linear-gradient(135deg, #fff 0%, #667eea 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .plan-duration {
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 2rem;
        }

        .plan-features {
            list-style: none;
            padding: 0;
            margin: 2rem 0;
        }

        .plan-features li {
            padding: 0.8rem 0;
            color: rgba(255, 255, 255, 0.8);
        }

        .plan-features li i {
            color: #667eea;
            margin-right: 0.5rem;
        }

        /* Features Section */
        .features-section {
            padding: 6rem 0;
            background: #0a0e27;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            border-color: #667eea;
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-size: 2rem;
        }

        .feature-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .feature-description {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
        }

        /* Contact Section */
        .contact-section {
            padding: 6rem 0;
            background: linear-gradient(135deg, #1a1f3a 0%, #0a0e27 100%);
        }

        .contact-info {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
        }

        .contact-item {
            margin: 2rem 0;
        }

        .contact-item i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 1rem;
        }

        .contact-item h5 {
            margin-bottom: 0.5rem;
        }

        .contact-item p {
            color: rgba(255, 255, 255, 0.7);
        }

        /* Footer Moderno */
        footer {
            background: #0a0e27;
            padding: 3rem 0 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-text {
            color: rgba(255, 255, 255, 0.6);
            text-align: center;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .pricing-card.featured {
                transform: scale(1);
            }
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-dumbbell me-2"></i>
                <?= $config['nombre_sitio'] ?? 'Gimnasio System SAAS'; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item"><a href="#licencias" class="nav-link">Planes</a></li>
                    <li class="nav-item"><a href="#features" class="nav-link">Características</a></li>
                    <li class="nav-item"><a href="#contacto" class="nav-link">Contacto</a></li>
                    <li class="nav-item"><a href="<?= $base_url ?>/login.php" class="btn btn-outline-custom ms-3">Iniciar Sesión</a></li>
                    <li class="nav-item"><a href="<?= $base_url ?>/register.php" class="btn btn-gradient ms-2">Registrarse</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <!-- Floating Icons -->
        <i class="fas fa-dumbbell floating-icon floating-icon-1"></i>
        <i class="fas fa-heartbeat floating-icon floating-icon-2"></i>
        <i class="fas fa-running floating-icon floating-icon-3"></i>

        <div class="hero-content">
            <h1 class="hero-title">Gestiona tu Gimnasio<br>de Forma Inteligente</h1>
            <p class="hero-subtitle">
                Plataforma SaaS completa para administrar socios, membresías, pagos y más. 
                Todo en un solo lugar, simple y eficiente.
            </p>
            <div class="hero-cta">
                <a href="<?= $base_url ?>/register.php" class="btn btn-hero btn-hero-primary">
                    <i class="fas fa-rocket me-2"></i>Comenzar Gratis
                </a>
                <a href="#features" class="btn btn-hero btn-hero-secondary">
                    <i class="fas fa-play-circle me-2"></i>Ver Demo
                </a>
            </div>
        </div>
    </section>

    <!-- Licencias Section -->
    <section id="licencias" class="licencias-section">
        <div class="container">
            <h2 class="section-title">Planes y Precios</h2>
            <p class="section-subtitle">Elige el plan perfecto para tu gimnasio</p>
            
            <div class="row g-4">
                <?php foreach ($licencias_array as $index => $lic): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="pricing-card <?= $index === 1 ? 'featured' : '' ?>">
                            <?php if($index === 1): ?>
                                <div class="badge bg-gradient text-white mb-3">Más Popular</div>
                            <?php endif; ?>
                            <h3 class="plan-name"><?= htmlspecialchars($lic['nombre']); ?></h3>
                            <div class="plan-price">$<?= number_format($lic['precio'], 0, ',', '.'); ?></div>
                            <p class="plan-duration"><?= $lic['dias']; ?> días de acceso</p>
                            <ul class="plan-features">
                                <li><i class="fas fa-check-circle"></i> Gestión de socios ilimitada</li>
                                <li><i class="fas fa-check-circle"></i> Control de membresías</li>
                                <li><i class="fas fa-check-circle"></i> Reportes en tiempo real</li>
                                <li><i class="fas fa-check-circle"></i> Pagos con MercadoPago</li>
                                <li><i class="fas fa-check-circle"></i> Soporte 24/7</li>
                            </ul>
                            <a href="<?= $base_url ?>/register.php" class="btn btn-gradient w-100">
                                <i class="fas fa-shopping-cart me-2"></i>Contratar Plan
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <h2 class="section-title">¿Por qué elegirnos?</h2>
            <p class="section-subtitle">Funcionalidades diseñadas para tu éxito</p>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h4 class="feature-title">SuperAdmin Completo</h4>
                        <p class="feature-description">
                            Gestiona múltiples gimnasios, licencias y configura todo el sistema desde un panel centralizado.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-dumbbell"></i>
                        </div>
                        <h4 class="feature-title">Panel para Gimnasios</h4>
                        <p class="feature-description">
                            Cada gimnasio tiene su propio panel para gestionar socios, membresías, staff y validación de accesos.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 class="feature-title">Portal de Socios</h4>
                        <p class="feature-description">
                            Los socios pueden ver su estado, renovar membresías y gestionar sus datos personales fácilmente.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <h4 class="feature-title">Pagos Integrados</h4>
                        <p class="feature-description">
                            Integración completa con MercadoPago para procesar pagos de forma segura y automática.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4 class="feature-title">Reportes Avanzados</h4>
                        <p class="feature-description">
                            Visualiza estadísticas, ingresos, socios activos y más con gráficos interactivos en tiempo real.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4 class="feature-title">100% Responsive</h4>
                        <p class="feature-description">
                            Accede desde cualquier dispositivo: computadora, tablet o smartphone con diseño adaptable.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contacto" class="contact-section">
        <div class="container">
            <h2 class="section-title">¿Necesitas ayuda?</h2>
            <p class="section-subtitle">Estamos aquí para ayudarte</p>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="contact-info">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="contact-item">
                                    <i class="fas fa-envelope"></i>
                                    <h5>Email</h5>
                                    <p><?= $config['contacto_email'] ?? 'info@sistema.com'; ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="contact-item">
                                    <i class="fas fa-phone"></i>
                                    <h5>Teléfono</h5>
                                    <p><?= $config['contacto_telefono'] ?? '+54 9 11 9999 9999'; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="<?= $base_url ?>/register.php" class="btn btn-gradient btn-lg">
                                <i class="fas fa-rocket me-2"></i>Comenzar Ahora
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p class="footer-text">
                <?= $config['footer_texto'] ?? '© 2025 Gimnasio System SAAS - Todos los derechos reservados'; ?>
            </p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Scroll Effect para Navbar -->
    <script>
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar-custom');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scroll para enlaces internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
