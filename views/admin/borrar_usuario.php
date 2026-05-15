<?php
/**
 * CONTROLADOR: ELIMINACIÓN DE USUARIOS - KaloAI
 * Gestiona el borrado integral de un usuario y registra la acción en los
 * logs de auditoría antes de finalizar la transacción.
 */

session_start();
require_once __DIR__ . '/../../core/configuracion.php';
require_once __DIR__ . '/../../core/utilidades.php';

/* ==========================================================================
   SEGURIDAD Y CONTROL DE ACCESO
   ========================================================================== */
if (!isset($_SESSION['user_role_id']) || $_SESSION['user_role_id'] != 1) {
    header("Location: ../dashboard.php");
    exit;
}

/* ==========================================================================
   PROCESO DE ELIMINACIÓN Y AUDITORÍA
   ========================================================================== */
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_a_borrar = $_GET['id'];
    $pdo = connectDB();

    try {
        // 1. PREPARACIÓN DE AUDITORÍA: Obtenemos datos del usuario antes de borrarlo
        $stmtInfo = $pdo->prepare("
            SELECT p.nombre, u.correo 
            FROM usuario u 
            LEFT JOIN perfil p ON u.id_usuario = p.id_usuario 
            WHERE u.id_usuario = ?
        ");
        $stmtInfo->execute([$id_a_borrar]);
        $usuarioAfectado = $stmtInfo->fetch(PDO::FETCH_ASSOC);
        
        // Creamos un mensaje descriptivo para el log[cite: 1]
        if ($usuarioAfectado) {
            $nombre = !empty($usuarioAfectado['nombre']) ? $usuarioAfectado['nombre'] : "Sin nombre";
            $detalleLog = "<b>ELIMINACIÓN</b>: Se eliminó permanentemente al usuario <b>$nombre</b> ({$usuarioAfectado['correo']})";
        } else {
            $detalleLog = "<b>ELIMINACIÓN</b>: Se eliminó al usuario con ID #$id_a_borrar (No se encontró perfil previo).";
        }

        // 2. INICIO DE TRANSACCIÓN: Aseguramos integridad total
        $pdo->beginTransaction();

        /**
         * Mapeo de dependencias:
         * Definimos tablas que deben limpiarse para evitar errores de clave foránea.
         */
        $mapeo_tablas = [
            'perfil'                  => 'id_usuario',
            'receta'                  => 'id_usuario',
            'inventario'              => 'id_usuario',
            'solicitudes_vinculacion' => ['id_paciente', 'id_nutricionista']
        ];

        // 3. LIMPIEZA DE TABLAS DEPENDIENTES[cite: 2]
        foreach ($mapeo_tablas as $tabla => $columnas) {
            $res = $pdo->query("SHOW TABLES LIKE '$tabla'");
            if ($res->rowCount() > 0) {
                $cols_target = is_array($columnas) ? $columnas : [$columnas];
                foreach ($cols_target as $col) {
                    $checkCol = $pdo->query("SHOW COLUMNS FROM `$tabla` LIKE '$col'");
                    if ($checkCol->rowCount() > 0) {
                        $stmt = $pdo->prepare("DELETE FROM `$tabla` WHERE `$col` = ?");
                        $stmt->execute([$id_a_borrar]);
                    }
                }
            }
        }

        // 4. ELIMINACIÓN DEL REGISTRO MAESTRO[cite: 2]
        $stmtUser = $pdo->prepare("DELETE FROM usuario WHERE id_usuario = ?");
        $stmtUser->execute([$id_a_borrar]);

        // 5. REGISTRO EN LOGS DE ACTIVIDAD: Para que aparezca en el Dashboard Admin[cite: 1, 3]
        $stmtLog = $pdo->prepare("INSERT INTO logs_actividad (detalle) VALUES (?)");
        $stmtLog->execute([$detalleLog]);

        // Confirmamos todos los cambios en la BD
        $pdo->commit();

        /* ==========================================================================
           REDIRECCIÓN CON ÉXITO
           ========================================================================== */
        header("Location: dashboard_admin.php?msg=borrado_exitoso");
        exit;

    } catch (Exception $e) {
        // Si algo falla, revertimos para no dejar datos huérfanos o inconsistentes[cite: 2]
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error Crítico en el Proceso de Borrado: " . $e->getMessage());
    }
} else {
    header("Location: dashboard_admin.php");
    exit;
}