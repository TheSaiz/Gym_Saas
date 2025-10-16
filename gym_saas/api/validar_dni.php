<?php
/**
 * API: Validar DNI de Socio
 * Versión optimizada con seguridad mejorada
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

require_once(__DIR__ . "/../db_connect.php");
require_once(__DIR__ . "/../config/security.php");

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'error' => true,
        'msg' => 'Método no permitido'
    ]);
    exit;
}

try {
    // Sanitizar y validar entrada
    $dni = filter_input(INPUT_GET, 'dni', FILTER_SANITIZE_STRING);
    $gimnasio_id = filter_input(INPUT_GET, 'gimnasio_id', FILTER_VALIDATE_INT);

    // Validaciones
    if (empty($dni) || !$gimnasio_id || $gimnasio_id <= 0) {
        throw new Exception('Parámetros incompletos o inválidos');
    }

    // Validar formato DNI (solo números, 7-8 dígitos)
    if (!preg_match('/^\d{7,8}$/', $dni)) {
        throw new Exception('Formato de DNI inválido');
    }

    // Prepared statement para prevenir SQL injection
    $stmt = $conn->prepare("
        SELECT 
            s.id, 
            s.nombre, 
            s.apellido, 
            s.dni,
            ls.estado AS licencia_estado, 
            ls.fecha_fin,
            ls.fecha_inicio,
            m.nombre AS membresia_nombre
        FROM socios s
        LEFT JOIN licencias_socios ls ON ls.socio_id = s.id 
            AND ls.gimnasio_id = ? 
            AND ls.estado = 'activa'
        LEFT JOIN membresias m ON m.id = ls.membresia_id
        WHERE s.dni = ? 
            AND s.gimnasio_id = ?
            AND s.estado = 'activo'
        ORDER BY ls.fecha_fin DESC
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('Error en la preparación de la consulta');
    }

    $stmt->bind_param("isi", $gimnasio_id, $dni, $gimnasio_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $socio = $result->fetch_assoc();
        
        // Verificar estado de licencia
        $licencia_valida = false;
        $dias_restantes = null;
        
        if ($socio['licencia_estado'] === 'activa' && $socio['fecha_fin']) {
            $fecha_fin = new DateTime($socio['fecha_fin']);
            $hoy = new DateTime();
            $dias_restantes = $hoy->diff($fecha_fin)->days;
            
            if ($fecha_fin >= $hoy) {
                $licencia_valida = true;
            }
        }

        // Registrar acceso si la licencia es válida
        if ($licencia_valida) {
            $log_stmt = $conn->prepare("
                INSERT INTO accesos_log (socio_id, gimnasio_id, fecha_hora) 
                VALUES (?, ?, NOW())
            ");
            if ($log_stmt) {
                $log_stmt->bind_param("ii", $socio['id'], $gimnasio_id);
                $log_stmt->execute();
                $log_stmt->close();
            }
        }

        echo json_encode([
            'error' => false,
            'socio' => [
                'id' => (int)$socio['id'],
                'nombre' => htmlspecialchars($socio['nombre'], ENT_QUOTES, 'UTF-8'),
                'apellido' => htmlspecialchars($socio['apellido'], ENT_QUOTES, 'UTF-8'),
                'dni' => htmlspecialchars($socio['dni'], ENT_QUOTES, 'UTF-8'),
                'licencia_valida' => $licencia_valida,
                'licencia_estado' => $socio['licencia_estado'],
                'fecha_fin' => $socio['fecha_fin'],
                'dias_restantes' => $dias_restantes,
                'membresia' => $socio['membresia_nombre']
            ]
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'error' => true,
            'msg' => 'Socio no encontrado o inactivo'
        ]);
    }

    $stmt->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'msg' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
