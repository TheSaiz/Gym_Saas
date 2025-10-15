<?php
session_start();

if (!isset($_SESSION['superadmin_id'])) {
    header('Location: /gym_saas/superadmin/login.php');
    exit;
}

require_once __DIR__ . '/../includes/db_connect.php';

// Función para sanitizar entrada
function sanitize_input($data) {
    return htmlspecialchars(trim(stripslashes($data)), ENT_QUOTES, 'UTF-8');
}

// Validar y sanitizar datos
function validate_licencia_data($nombre, $precio, $dias) {
    $errors = [];

    if (empty($nombre)) {
        $errors[] = "El nombre es obligatorio";
    }

    if (!is_numeric($precio) || $precio < 0) {
        $errors[] = "El precio debe ser un número válido";
    }

    if (!is_numeric($dias) || $dias <= 0) {
        $errors[] = "Los días deben ser un número positivo";
    }

    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'crear':
            $nombre = sanitize_input($_POST['nombre']);
            $precio = floatval($_POST['precio']);
            $dias = intval($_POST['dias']);
            $estado = in_array($_POST['estado'] ?? 'activo', ['activo', 'inactivo']) ? $_POST['estado'] : 'activo';

            $errors = validate_licencia_data($nombre, $precio, $dias);
            if (!empty($errors)) {
                header('Location: /gym_saas/superadmin/licencias.php?error=' . urlencode(implode(', ', $errors)));
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO licencias (nombre, precio, dias, estado) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sdis", $nombre, $precio, $dias, $estado);

            if ($stmt->execute()) {
                $stmt->close();
                header('Location: /gym_saas/superadmin/licencias.php?success=created');
            } else {
                $stmt->close();
                header('Location: /gym_saas/superadmin/licencias.php?error=db_error');
            }
            break;

        case 'editar':
            $id = intval($_POST['id']);
            $nombre = sanitize_input($_POST['nombre']);
            $precio = floatval($_POST['precio']);
            $dias = intval($_POST['dias']);
            $estado = in_array($_POST['estado'] ?? 'activo', ['activo', 'inactivo']) ? $_POST['estado'] : 'activo';

            if ($id <= 0) {
                header('Location: /gym_saas/superadmin/licencias.php?error=invalid_id');
                exit;
            }

            $errors = validate_licencia_data($nombre, $precio, $dias);
            if (!empty($errors)) {
                header('Location: /gym_saas/superadmin/licencias.php?error=' . urlencode(implode(', ', $errors)));
                exit;
            }

            $stmt = $conn->prepare("UPDATE licencias SET nombre = ?, precio = ?, dias = ?, estado = ? WHERE id = ?");
            $stmt->bind_param("sdisi", $nombre, $precio, $dias, $estado, $id);

            if ($stmt->execute()) {
                $stmt->close();
                header('Location: /gym_saas/superadmin/licencias.php?success=updated');
            } else {
                $stmt->close();
                header('Location: /gym_saas/superadmin/licencias.php?error=db_error');
            }
            break;

        case 'eliminar':
            $id = intval($_POST['id']);

            if ($id <= 0) {
                header('Location: /gym_saas/superadmin/licencias.php?error=invalid_id');
                exit;
            }

            // Verificar si hay gimnasios usando esta licencia
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM gimnasios WHERE licencia_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($result['count'] > 0) {
                header('Location: /gym_saas/superadmin/licencias.php?error=in_use&count=' . $result['count']);
                exit;
            }

            // Eliminar licencia
            $stmt = $conn->prepare("DELETE FROM licencias WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $stmt->close();
                header('Location: /gym_saas/superadmin/licencias.php?success=deleted');
            } else {
                $stmt->close();
                header('Location: /gym_saas/superadmin/licencias.php?error=not_found');
            }
            break;

        case 'toggle_estado':
            $id = intval($_POST['id']);

            if ($id <= 0) {
                header('Location: /gym_saas/superadmin/licencias.php?error=invalid_id');
                exit;
            }

            // Obtener estado actual
            $stmt = $conn->prepare("SELECT estado FROM licencias WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$result) {
                header('Location: /gym_saas/superadmin/licencias.php?error=not_found');
                exit;
            }

            // Cambiar estado
            $nuevo_estado = ($result['estado'] === 'activo') ? 'inactivo' : 'activo';
            $stmt = $conn->prepare("UPDATE licencias SET estado = ? WHERE id = ?");
            $stmt->bind_param("si", $nuevo_estado, $id);

            if ($stmt->execute()) {
                $stmt->close();
                header('Location: /gym_saas/superadmin/licencias.php?success=status_changed');
            } else {
                $stmt->close();
                header('Location: /gym_saas/superadmin/licencias.php?error=db_error');
            }
            break;

        default:
            header('Location: /gym_saas/superadmin/licencias.php?error=invalid_action');
            break;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    // Acciones GET (solo para eliminar con confirmación)
    $action = $_GET['action'];
    
    if ($action === 'eliminar' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        
        if ($id <= 0) {
            header('Location: /gym_saas/superadmin/licencias.php?error=invalid_id');
            exit;
        }

        // Verificar si hay gimnasios usando esta licencia
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM gimnasios WHERE licencia_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result['count'] > 0) {
            header('Location: /gym_saas/superadmin/licencias.php?error=in_use&count=' . $result['count']);
            exit;
        }

        // Eliminar licencia
        $stmt = $conn->prepare("DELETE FROM licencias WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            header('Location: /gym_saas/superadmin/licencias.php?success=deleted');
        } else {
            $stmt->close();
            header('Location: /gym_saas/superadmin/licencias.php?error=not_found');
        }
    } else {
        header('Location: /gym_saas/superadmin/licencias.php');
    }
} else {
    header('Location: /gym_saas/superadmin/licencias.php');
}

$conn->close();
exit;
?>
