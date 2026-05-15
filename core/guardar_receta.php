<?php
/**
 * CONTROLADOR: GUARDAR RECETA - KaloAI
 * Gestiona la persistencia de recetas (Creación y Edición).
 * Implementa transacciones SQL para asegurar la integridad entre la receta y sus ingredientes.
 */

session_start();
require_once __DIR__ . '/configuracion.php';
require_once __DIR__ . '/utilidades.php';

// 1. CONTROL DE ACCESO
if (!isset($_SESSION['user_id'])) {
    header("Location: ../views/auth/login.php");
    exit;
}

// Procesar solo si el método es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = connectDB();
    $userId = $_SESSION['user_id'];
    $errores = [];

    /**
     * 2. RECOLECCIÓN Y MAPEO DE DATOS
     * Se ajustan los nombres para que coincidan exactamente con los 'name' del formulario HTML.
     */
    $idReceta      = $_POST['id_receta'] ?? null; // Si existe, es una EDICIÓN
    $titulo        = trim($_POST['titulo'] ?? '');
    $tipo_comida   = $_POST['tipo_comida'] ?? '';
    
    // Mapeo crucial: capturamos los nombres largos del formulario
    $calorias      = filter_var($_POST['calorias_por_racion'] ?? 0, FILTER_VALIDATE_FLOAT) ?: 0.0;
    $proteinas     = filter_var($_POST['proteinas_g_por_racion'] ?? 0, FILTER_VALIDATE_FLOAT) ?: 0.0;
    $carbohidratos = filter_var($_POST['carbohidratos_g_por_racion'] ?? 0, FILTER_VALIDATE_FLOAT) ?: 0.0;
    $grasas        = filter_var($_POST['grasas_g_por_racion'] ?? 0, FILTER_VALIDATE_FLOAT) ?: 0.0;
    
    $ingredientesText = trim($_POST['ingredientes'] ?? '');
    $instrucciones    = trim($_POST['instrucciones'] ?? '');

    /**
     * 3. VALIDACIONES
     */
    if (empty($titulo)) $errores[] = "El título es obligatorio.";
    if (empty($ingredientesText)) $errores[] = "Debes añadir al menos un ingrediente.";
    if (empty($instrucciones)) $errores[] = "Las instrucciones son obligatorias.";

    if (!empty($errores)) {
        $_SESSION['errores_form'] = $errores;
        $_SESSION['post_data'] = $_POST;
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        if ($idReceta) {
            /**
             * 4. MODO EDICIÓN (UPDATE)
             * Solo actualiza si el usuario es el creador original (Seguridad).
             */
            $sql = "UPDATE receta SET 
                        titulo = ?, 
                        tipo_comida = ?, 
                        calorias_por_racion = ?, 
                        proteinas_g_por_racion = ?, 
                        carbohidratos_g_por_racion = ?, 
                        grasas_g_por_racion = ?, 
                        descripcion = ?
                    WHERE id_receta = ? AND id_usuario_creador = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $titulo, 
                ucfirst($tipo_comida), 
                $calorias, 
                $proteinas, 
                $carbohidratos, 
                $grasas, 
                $instrucciones, 
                $idReceta, 
                $userId
            ]);

            // Limpiamos los ingredientes antiguos para re-insertar los nuevos (evita duplicados)
            $stmtDel = $pdo->prepare("DELETE FROM detalle_receta WHERE id_receta = ?");
            $stmtDel->execute([$idReceta]);
            
            $idFinal = $idReceta;

        } else {
            /**
             * 5. MODO CREACIÓN (INSERT)
             */
            $sql = "INSERT INTO receta (
                        titulo, tipo_comida, calorias_por_racion, 
                        proteinas_g_por_racion, carbohidratos_g_por_racion, 
                        grasas_g_por_racion, descripcion, id_usuario_creador, generado_ia
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $titulo, 
                ucfirst($tipo_comida), 
                $calorias, 
                $proteinas, 
                $carbohidratos, 
                $grasas, 
                $instrucciones, 
                $userId
            ]);
            
            $idFinal = $pdo->lastInsertId();
        }

        /**
         * 6. PROCESAMIENTO DE INGREDIENTES
         * Se ejecuta tanto para Insert como para Update (tras el DELETE previo).
         */
        if (!empty($ingredientesText)) {
            $lineas = explode("\n", $ingredientesText);
            $sqlDetalle = "INSERT INTO detalle_receta (id_receta, nombre_ingrediente, cantidad, unidad) VALUES (?, ?, ?, ?)";
            $stmtDetalle = $pdo->prepare($sqlDetalle);

            foreach ($lineas as $linea) {
                $linea = trim($linea);
                if (!empty($linea)) {
                    // Valor por defecto: 1 unidad para ingresos manuales
                    $stmtDetalle->execute([$idFinal, $linea, 1, 'unidad']);
                }
            }
        }

        $pdo->commit();

        $_SESSION['mensaje'] = "Receta guardada correctamente.";
        // Redirigimos al detalle de la receta recién guardada/editada
        header("Location: ../views/user/detalle_receta.php?id=" . $idFinal);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error en guardar_receta: " . $e->getMessage());
        die("Error crítico al procesar la receta: " . $e->getMessage());
    }
}