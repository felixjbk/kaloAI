<?php
/**
 * ARCHIVO DE ACCIONES ADMINISTRATIVAS (AJAX) - KaloAI
 * Este script procesa las peticiones en segundo plano para gestionar usuarios,
 * roles y asignaciones de nutricionistas sin recargar la página.
 */

session_start();
require_once __DIR__ . '/../../core/configuracion.php';

// Limpieza de búfer para asegurar que solo se envíe JSON al cliente
if (ob_get_level()) ob_end_clean();
ob_start();

$pdo = connectDB();

/* ==========================================================================
   CONTROL DE ACCESO (SEGURIDAD)
   ========================================================================== */
// Verificamos que el usuario esté logueado y tenga el id_rol = 1 (Administrador)
if (!isset($_SESSION['user_role_id']) || $_SESSION['user_role_id'] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acceso denegado: Se requieren permisos de administrador']);
    exit;
}

/**
 * Registra una acción en la tabla de auditoría (logs_actividad).
 * @param PDO $pdo Instancia de conexión.
 * @param string $detalle Texto descriptivo de lo ocurrido.
 */
function guardarLog($pdo, $detalle) {
    $stmt = $pdo->prepare("INSERT INTO logs_actividad (detalle) VALUES (?)");
    $stmt->execute([$detalle]);
}

/**
 * Genera una cadena HTML representativa del usuario (Nombre + Email).
 * Útil para que los logs sean legibles de un vistazo.
 */
function getInfoUsuario($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT p.nombre, u.correo 
        FROM usuario u 
        LEFT JOIN perfil p ON u.id_usuario = p.id_usuario 
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $nombre = !empty($user['nombre']) ? $user['nombre'] : "Sin nombre";
        return "<b>$nombre</b> <span class='text-gray-500 text-[11px] font-normal'>({$user['correo']})</span>";
    }
    return "<b>Usuario #$id</b>";
}

/* ==========================================================================
   PROCESAMIENTO DE LA PETICIÓN POST
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false];
    try {
        $userId = $_POST['user_id'] ?? null;
        $action = $_POST['action'] ?? null;
        $value = $_POST['value'] ?? null;

        if (!$userId) throw new Exception("ID de usuario no proporcionado");

        // Obtenemos el "nombre visual" del usuario afectado antes de modificarlo
        $identificadorAfectado = getInfoUsuario($pdo, $userId);

        /* ------------------------------------------------------------------
           CASO 1: Cambio de Rol (Admin, Nutri, Usuario)
           ------------------------------------------------------------------ */
        if ($action === 'rol') {
            $roles = [1 => 'Admin', 2 => 'Nutricionista', 3 => 'Usuario'];
            $nombreRol = $roles[$value] ?? "Desconocido";
            
            $stmt = $pdo->prepare("UPDATE usuario SET id_rol = ? WHERE id_usuario = ?");
            $stmt->execute([$value, $userId]);
            
            guardarLog($pdo, "Rol: $identificadorAfectado ahora es <b>$nombreRol</b>");
            $response = ['success' => true];
        } 
        
        /* ------------------------------------------------------------------
           CASO 2: Vinculación de Nutricionista
           ------------------------------------------------------------------ */
        elseif ($action === 'nutri') {
            $val = ($value === "") ? null : $value;
            $stmt = $pdo->prepare("UPDATE perfil SET id_nutricionista = ? WHERE id_usuario = ?");
            $stmt->execute([$val, $userId]);
            
            if ($val) {
                $identificadorNutri = getInfoUsuario($pdo, $val);
                $msg = "Vinculación: $identificadorNutri es el nuevo nutri de $identificadorAfectado";
            } else {
                $msg = "Desvinculación: Se quitó el nutricionista a $identificadorAfectado";
            }
            
            guardarLog($pdo, $msg);
            $response = ['success' => true];
        }
        
        /* ------------------------------------------------------------------
           CASO 3: Activar / Bloquear Usuario (Baneo)
           ------------------------------------------------------------------ */
        elseif ($action === 'toggle_status') {
            $stmt = $pdo->prepare("UPDATE usuario SET activo = ? WHERE id_usuario = ?");
            $stmt->execute([$value, $userId]);
            
            $estado = ($value == 1) ? "ACTIVADO" : "BLOQUEADO";
            guardarLog($pdo, "Estado: $identificadorAfectado ha sido <b>$estado</b>");
            $response = ['success' => true];
        }

    } catch (Exception $e) {
        // En caso de error, capturamos el mensaje para enviarlo al frontend
        $response = ['success' => false, 'error' => $e->getMessage()];
    }

    // Limpiamos cualquier salida accidental y devolvemos JSON puro
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}