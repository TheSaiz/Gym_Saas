-- ==========================================================
-- BASE DE DATOS: gimnasio_saas
-- ==========================================================

CREATE DATABASE IF NOT EXISTS gimnasio_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE gimnasio_saas;

-- ==========================================================
-- TABLA: superadmin
-- ==========================================================
CREATE TABLE superadmin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(255),
    email VARCHAR(255),
    logo VARCHAR(255) DEFAULT NULL,
    favicon VARCHAR(255) DEFAULT NULL,
    mp_public_key VARCHAR(255) DEFAULT NULL,
    mp_access_token VARCHAR(255) DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Usuario de prueba SuperAdmin
INSERT INTO superadmin (usuario, password, nombre, email)
VALUES ('admin', MD5('admin123'), 'Super Administrador', 'admin@sistema.com');


-- ==========================================================
-- TABLA: licencias
-- ==========================================================
CREATE TABLE licencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    dias INT NOT NULL,
    estado ENUM('activo','inactivo') DEFAULT 'activo'
);

-- Licencias iniciales
INSERT INTO licencias (nombre, precio, dias, estado) VALUES
('Plan Básico', 15000.00, 30, 'activo'),
('Plan Premium', 25000.00, 60, 'activo'),
('Plan Anual', 120000.00, 365, 'activo');


-- ==========================================================
-- TABLA: gimnasios
-- ==========================================================
CREATE TABLE gimnasios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    direccion VARCHAR(255),
    altura VARCHAR(50),
    localidad VARCHAR(100),
    partido VARCHAR(100),
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    licencia_id INT DEFAULT NULL,
    estado ENUM('activo','suspendido') DEFAULT 'activo',
    fecha_inicio DATE DEFAULT NULL,
    fecha_fin DATE DEFAULT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    favicon VARCHAR(255) DEFAULT NULL,
    mp_key VARCHAR(255) DEFAULT NULL,
    mp_token VARCHAR(255) DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (licencia_id) REFERENCES licencias(id) ON DELETE SET NULL
);

-- Gimnasio de prueba
INSERT INTO gimnasios (nombre, direccion, altura, localidad, partido, email, password, licencia_id, fecha_inicio, fecha_fin)
VALUES ('FitZone', 'Av. Siempre Viva', '742', 'Las Breñas', 'Chaco', 'fitzone@gym.com', MD5('gym123'), 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY));


-- ==========================================================
-- TABLA: membresias
-- ==========================================================
CREATE TABLE membresias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gimnasio_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    dias INT NOT NULL,
    estado ENUM('activo','inactivo') DEFAULT 'activo',
    FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE CASCADE
);

-- Membresías ejemplo
INSERT INTO membresias (gimnasio_id, nombre, precio, dias, estado) VALUES
(1, 'Mensual', 8000.00, 30, 'activo'),
(1, 'Trimestral', 22000.00, 90, 'activo');


-- ==========================================================
-- TABLA: socios
-- ==========================================================
CREATE TABLE socios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gimnasio_id INT NOT NULL,
    nombre VARCHAR(100),
    apellido VARCHAR(100),
    dni VARCHAR(20) UNIQUE,
    telefono VARCHAR(30),
    telefono_emergencia VARCHAR(30),
    email VARCHAR(255),
    password VARCHAR(255),
    estado ENUM('activo','inactivo') DEFAULT 'activo',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE CASCADE
);

-- Socio de prueba
INSERT INTO socios (gimnasio_id, nombre, apellido, dni, telefono, telefono_emergencia, email, password)
VALUES (1, 'Juan', 'Pérez', '40123456', '1122334455', '1199887766', 'juanperez@mail.com', MD5('socio123'));


-- ==========================================================
-- TABLA: licencias_socios
-- ==========================================================
CREATE TABLE licencias_socios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    socio_id INT NOT NULL,
    gimnasio_id INT NOT NULL,
    membresia_id INT DEFAULT NULL,
    fecha_inicio DATE DEFAULT NULL,
    fecha_fin DATE DEFAULT NULL,
    estado ENUM('activa','vencida','pendiente') DEFAULT 'pendiente',
    pago_id VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (socio_id) REFERENCES socios(id) ON DELETE CASCADE,
    FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE CASCADE,
    FOREIGN KEY (membresia_id) REFERENCES membresias(id) ON DELETE SET NULL
);

-- Licencia activa para el socio de prueba
INSERT INTO licencias_socios (socio_id, gimnasio_id, membresia_id, fecha_inicio, fecha_fin, estado)
VALUES (1, 1, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'activa');


-- ==========================================================
-- TABLA: staff (subusuarios del gimnasio)
-- ==========================================================
CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gimnasio_id INT NOT NULL,
    nombre VARCHAR(100),
    usuario VARCHAR(100),
    password VARCHAR(255),
    rol ENUM('validador','admin') DEFAULT 'validador',
    estado ENUM('activo','inactivo') DEFAULT 'activo',
    FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id) ON DELETE CASCADE
);

-- Staff de ejemplo
INSERT INTO staff (gimnasio_id, nombre, usuario, password, rol)
VALUES (1, 'María López', 'validador1', MD5('valida123'), 'validador');


-- ==========================================================
-- TABLA: pagos
-- ==========================================================
CREATE TABLE pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('licencia_gimnasio','membresia_socio') NOT NULL,
    usuario_id INT DEFAULT NULL,
    gimnasio_id INT DEFAULT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente','pagado','fallido') DEFAULT 'pendiente',
    mp_id VARCHAR(255) DEFAULT NULL
);


-- ==========================================================
-- TABLA: config (para ajustes globales)
-- ==========================================================
CREATE TABLE config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(255) UNIQUE,
    valor TEXT
);

-- Configuración inicial
INSERT INTO config (clave, valor) VALUES
('nombre_sitio', 'Gimnasio System SAAS'),
('logo', '/assets/img/logo.png'),
('favicon', '/assets/img/favicon.png'),
('contacto_email', 'info@sistema.com'),
('contacto_telefono', '+54 9 11 9999 9999'),
('footer_texto', '© 2025 Gimnasio System SAAS - Todos los derechos reservados'),
('mp_public_key', ''),
('mp_access_token', '');
