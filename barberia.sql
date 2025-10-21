-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 21-10-2025 a las 13:52:17
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `barberia`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 'login_failed', 'Intento de login con email: superadmin@local.test', '::1', NULL, '2025-10-18 21:59:28'),
(2, 1, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-18 21:59:48'),
(3, 1, 'article_updated', 'Artículo ID: 8', '::1', NULL, '2025-10-18 22:34:30'),
(4, 1, 'article_updated', 'Artículo ID: 8', '::1', NULL, '2025-10-18 22:34:35'),
(5, 1, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-19 14:35:37'),
(6, 2, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-19 14:50:08'),
(7, 1, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-19 15:03:04'),
(8, 2, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-19 21:50:26'),
(9, 2, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-19 21:51:09'),
(10, 2, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-19 21:51:18'),
(11, 2, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-19 21:51:32'),
(12, 1, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-19 21:54:06'),
(13, 1, 'barber_updated', 'Barbero ID: 1', '::1', NULL, '2025-10-19 21:55:10'),
(14, 1, 'barber_updated', 'Barbero ID: 2', '::1', NULL, '2025-10-19 21:55:21'),
(15, 1, 'barber_updated', 'Barbero ID: 3', '::1', NULL, '2025-10-19 21:55:29'),
(16, 2, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-19 21:55:54'),
(17, 1, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-20 11:56:48'),
(18, 1, 'config_updated', 'Configuración actualizada', '::1', NULL, '2025-10-20 12:00:19'),
(19, 2, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-20 12:01:10'),
(20, 2, 'cierre_dia', 'Cierre del día: 2025-10-20. Email: no enviado', '::1', NULL, '2025-10-20 12:04:04'),
(21, 1, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-20 12:16:00'),
(22, 1, 'config_updated', 'Configuración actualizada', '::1', NULL, '2025-10-20 17:12:03'),
(23, 1, 'config_updated', 'Configuración actualizada', '::1', NULL, '2025-10-20 17:12:52'),
(24, 2, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-20 17:14:33'),
(25, 1, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-20 22:06:41'),
(26, 2, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-20 22:07:41'),
(27, 2, 'cierre_dia', 'Cierre del dia: 2025-10-21. Email: enviado', '::1', NULL, '2025-10-20 22:30:32'),
(28, 1, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-20 22:30:46'),
(29, 2, 'login', 'Usuario ingresó al sistema', '::1', NULL, '2025-10-20 22:34:39'),
(30, 2, 'cierre_dia', 'Cierre del dia: 2025-10-21. Email: enviado', '::1', NULL, '2025-10-21 03:15:45'),
(31, 2, 'apertura_dia', 'Apertura del día: 2025-10-21', '::1', NULL, '2025-10-21 03:21:21'),
(32, 2, 'apertura_dia', 'Apertura del día: 2025-10-21', '::1', NULL, '2025-10-21 03:22:02'),
(33, 2, 'cierre_dia', 'Cierre del dia: 2025-10-21. Email: enviado', '::1', NULL, '2025-10-21 03:22:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `articles`
--

CREATE TABLE `articles` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `articles`
--

INSERT INTO `articles` (`id`, `name`, `description`, `price`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Corte de Cabello Clásico', 'Corte tradicional con tijera y máquina', 15.00, 1, '2025-10-17 22:28:39', NULL),
(2, 'Corte + Barba', 'Corte de cabello + arreglo de barba', 25.00, 1, '2025-10-17 22:28:39', NULL),
(3, 'Afeitado Clásico', 'Afeitado con navaja y toalla caliente', 12.00, 1, '2025-10-17 22:28:39', NULL),
(4, 'Tinte de Cabello', 'Aplicación de tinte profesional', 35.00, 1, '2025-10-17 22:28:39', NULL),
(5, 'Diseño de Barba', 'Diseño y perfilado de barba', 18.00, 1, '2025-10-17 22:28:39', NULL),
(6, 'Corte Infantil', 'Corte de cabello para niños', 10.00, 1, '2025-10-17 22:28:39', NULL),
(7, 'Alisado', 'Tratamiento de alisado', 45.00, 1, '2025-10-17 22:28:39', NULL),
(8, 'Rapado', 'Rapado completo a máquina', 9.00, 1, '2025-10-17 22:28:39', '2025-10-18 22:34:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `barbers`
--

CREATE TABLE `barbers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `commission_percentage` decimal(5,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `barbers`
--

INSERT INTO `barbers` (`id`, `name`, `email`, `phone`, `specialty`, `commission_percentage`, `is_active`, `photo`, `created_at`, `updated_at`) VALUES
(1, 'Carlos Rodríguez', 'carlos@barberia.com', '+54 11 1234-5678', 'Cortes Clásicos', 50.00, 1, NULL, '2025-10-19 14:43:53', '2025-10-19 21:55:09'),
(2, 'Miguel Fernández', 'miguel@barberia.com', '+54 11 2345-6789', 'Diseños y Degradados', 50.00, 1, NULL, '2025-10-19 14:43:53', '2025-10-19 21:55:20'),
(3, 'Juan Pérez', 'juan@barberia.com', '+54 11 3456-7890', 'Afeitado y Barba', 50.00, 1, NULL, '2025-10-19 14:43:53', '2025-10-19 21:55:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `barber_sales`
--

CREATE TABLE `barber_sales` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `barber_id` int(11) NOT NULL,
  `commission_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `barber_sales`
--

INSERT INTO `barber_sales` (`id`, `sale_id`, `barber_id`, `commission_amount`, `created_at`) VALUES
(8, 10, 1, 14.00, '2025-10-21 03:22:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cierres_dia`
--

CREATE TABLE `cierres_dia` (
  `id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total_ventas` int(11) NOT NULL DEFAULT 0,
  `total_ingresos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `efectivo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tarjeta` decimal(12,2) NOT NULL DEFAULT 0.00,
  `transferencia` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ventas_detalle` text DEFAULT NULL,
  `barberos_detalle` text DEFAULT NULL,
  `email_enviado` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cierres_dia`
--

INSERT INTO `cierres_dia` (`id`, `fecha`, `user_id`, `total_ventas`, `total_ingresos`, `efectivo`, `tarjeta`, `transferencia`, `ventas_detalle`, `barberos_detalle`, `email_enviado`, `created_at`) VALUES
(7, '2025-10-21', 2, 1, 28.00, 28.00, 0.00, 0.00, '[{\"id\":10,\"total\":\"28.00\",\"items\":\"[{\\\"id\\\":6,\\\"name\\\":\\\"Corte Infantil\\\",\\\"price\\\":10,\\\"qty\\\":1},{\\\"id\\\":5,\\\"name\\\":\\\"Diseño de Barba\\\",\\\"price\\\":18,\\\"qty\\\":1}]\",\"payment_method\":\"efectivo\",\"created_at\":\"2025-10-21 00:22:16\"}]', '[{\"id\":1,\"name\":\"Carlos Rodríguez\",\"commission_percentage\":\"50.00\",\"num_ventas\":1,\"total_ventas\":\"28.00\",\"total_comisiones\":\"14.00\"}]', 1, '2025-10-21 03:22:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL,
  `items` text NOT NULL,
  `payment_method` enum('efectivo','tarjeta','transferencia') DEFAULT 'efectivo',
  `notes` text DEFAULT NULL,
  `dia_cerrado` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sales`
--

INSERT INTO `sales` (`id`, `user_id`, `total`, `items`, `payment_method`, `notes`, `dia_cerrado`, `created_at`) VALUES
(10, 2, 28.00, '[{\"id\":6,\"name\":\"Corte Infantil\",\"price\":10,\"qty\":1},{\"id\":5,\"name\":\"Diseño de Barba\",\"price\":18,\"qty\":1}]', 'efectivo', NULL, 1, '2025-10-21 00:22:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','recepcion') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'superadmin@local.test', '$2y$10$diUX9zhqwdrDBnIvKzKyDuiWJkfTVt.NINw7MFjvT/B.ITcWyr/bO', 'superadmin', 1, NULL, '2025-10-17 22:28:39', NULL),
(2, 'Recepción', 'recepcion@local.test', '$2y$10$too2gStejV3tzlF4eMqfD./q2g0h5JDK2hHQ9HbP9hgVlip3Mvndi', 'recepcion', 1, NULL, '2025-10-17 22:28:39', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indices de la tabla `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_price` (`price`);

--
-- Indices de la tabla `barbers`
--
ALTER TABLE `barbers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_email` (`email`);

--
-- Indices de la tabla `barber_sales`
--
ALTER TABLE `barber_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale` (`sale_id`),
  ADD KEY `idx_barber` (`barber_id`);

--
-- Indices de la tabla `cierres_dia`
--
ALTER TABLE `cierres_dia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `fecha` (`fecha`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indices de la tabla `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_total` (`total`),
  ADD KEY `idx_dia_cerrado` (`dia_cerrado`),
  ADD KEY `idx_created_cerrado` (`created_at`,`dia_cerrado`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_active` (`is_active`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de la tabla `articles`
--
ALTER TABLE `articles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `barbers`
--
ALTER TABLE `barbers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `barber_sales`
--
ALTER TABLE `barber_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `cierres_dia`
--
ALTER TABLE `cierres_dia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `barber_sales`
--
ALTER TABLE `barber_sales`
  ADD CONSTRAINT `barber_sales_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `barber_sales_ibfk_2` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cierres_dia`
--
ALTER TABLE `cierres_dia`
  ADD CONSTRAINT `fk_cierre_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
