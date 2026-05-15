<?php

/**
 * GESTOR DE ELIMINACIÓN DE RECETAS - KaloAI
 * Este script se encarga de dar de baja una receta del sistema, eliminando
 * tanto sus metadatos como su presencia en el calendario de planificación.
 */
session_start();
require_once __DIR__ . '/configuracion.php';
require_once __DIR__ . '/utilidades.php';

/**
 * Validamos la sesión y la existencia del parámetro ID antes de proceder.
 * Si no se cumplen los requisitos, redirigimos a la biblioteca de recetas.
 */
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../views/user/mis_recetas.php");
    exit;
}

$pdo = connectDB();
$userId = $_SESSION['user_id'];
$recetaId = $_GET['id'];

try {
    /**
     * Verificamos que la receta existe y que el usuario actual es su creador.
     * Esto garantiza que nadie pueda borrar recetas ajenas mediante la URL.
     */
    $stmtCheck = $pdo->prepare("SELECT id_receta FROM receta WHERE id_receta = ? AND id_usuario_creador = ?");
    $stmtCheck->execute([$recetaId, $userId]);
    
    if ($stmtCheck->fetch()) {
        // Iniciamos una transacción para asegurar que todas las eliminaciones se realicen correctamente
        $pdo->beginTransaction();
        
        /**
         * A. ELIMINACIÓN DEL PLAN SEMANAL
         * Limpiamos las referencias de esta receta en el calendario de comidas planificadas.
         */
        $pdo->prepare("DELETE FROM comida_planificada WHERE id_receta = ?")->execute([$recetaId]);

        /**
         * B. ELIMINACIÓN DE DETALLES
         * Borramos los ingredientes y pasos asociados a la receta en la tabla de detalles.
         */
        $pdo->prepare("DELETE FROM detalle_receta WHERE id_receta = ?")->execute([$recetaId]);

        /**
         * C. ELIMINACIÓN DE LA CABECERA
         * Finalmente, eliminamos el registro principal de la receta.
         */
        $pdo->prepare("DELETE FROM receta WHERE id_receta = ?")->execute([$recetaId]);
        
        // Confirmamos todos los cambios en la base de datos
        $pdo->commit();
        
        $_SESSION['mensaje'] = "La receta ha sido eliminada de tu biblioteca y de tu plan semanal.";
        $_SESSION['mensaje_tipo'] = "success";
    } else {
        // Notificamos si el usuario intenta borrar una receta sobre la que no tiene permisos
        $_SESSION['mensaje'] = "No tienes permiso para eliminar esta receta.";
        $_SESSION['mensaje_tipo'] = "error";
    }

} catch (Exception $e) {
    // Si algo falla, revertimos los cambios para mantener la integridad de los datos
    if ($pdo->inTransaction()) $pdo->rollBack();
    
    // Registramos el error técnico en el log y mostramos un mensaje amigable al usuario
    error_log("Error al eliminar receta ID $recetaId: " . $e->getMessage());
    $_SESSION['mensaje'] = "No se pudo eliminar: La receta está siendo utilizada en el sistema.";
    $_SESSION['mensaje_tipo'] = "error";
}

// Redirigimos de vuelta a la vista de mis recetas con el mensaje de estado
header("Location: ../views/user/mis_recetas.php");
exit;