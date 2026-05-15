<?php
/**
 * PROCESADOR DE GUARDADO - CHEF IA
 * Este script recibe el JSON de la receta generada, lo valida, lo limpia
 * e inserta los datos en las tablas: receta, detalle_receta y comida_planificada.
 */

session_start();

require_once __DIR__ . '/../../core/configuracion.php';
require_once __DIR__ . '/../../core/utilidades.php';

/**
 * Función auxiliar para convertir el nombre del día en su índice numérico
 * Necesario para la compatibilidad con la estructura de la base de datos (0 = Lunes)
 */
function diaANumero($nombreDia) {
    if (!$nombreDia) return null;
    $dias = [
        'Lunes' => 0, 'Martes' => 1, 'Miércoles' => 2, 'Jueves' => 3,
        'Viernes' => 4, 'Sábado' => 5, 'Domingo' => 6
    ];
    return $dias[$nombreDia] ?? null;
}

// Solo se permite el acceso si se envía el formulario de guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_recipe'])) {
    $userId = $_SESSION['user_id'];
    
    /* ==========================================================================
       1. LIMPIEZA Y SANEAMIENTO DE DATOS (JSON)
       Las respuestas de IA a veces incluyen caracteres invisibles o se cortan
       ========================================================================== */
    $raw_data = $_POST['receta_data'] ?? '';
    $raw_data = trim($raw_data);
    
    // Eliminación de caracteres de control (00-1F) que rompen la función json_decode
    $raw_data = preg_replace('/[\x00-\x1F\x7F]/u', '', $raw_data);

    $data = json_decode($raw_data, true);

    // MECANISMO DE RECUPERACIÓN: Si el JSON está mal formado o truncado
    if (!$data) {
        $test_json = $raw_data;
        // Si falta el cierre del JSON, intentamos forzarlo
        if (substr(trim($test_json), -1) !== '}') {
            $test_json .= '"]}'; 
            $data = json_decode($test_json, true);
        }
    }

    // Validación final: Si tras los intentos sigue fallando, detenemos el proceso con diagnóstico
    if (!$data) {
        die("Error JSON: " . json_last_error_msg() . "<br>Contenido: " . htmlspecialchars($raw_data));
    }

    // Captura de metadatos de ubicación (Día y Momento de consumo)
    $diaNombre = $_POST['dia_contexto'] ?? null;
    $momentoContexto = $_POST['momento_contexto'] ?? null;
    $diaNumero = diaANumero($diaNombre);

    $pdo = connectDB();

    try {
        // Iniciamos una transacción para asegurar que se guarde TODO o NADA
        $pdo->beginTransaction();

        /* ==========================================================================
           2. INSERCIÓN EN TABLA 'receta'
           Mapeo flexible: La IA puede usar distintos nombres para los macros
           ========================================================================== */
        $sql = "INSERT INTO receta (id_usuario_creador, titulo, tipo_comida, descripcion, calorias_por_racion, proteinas_g_por_racion, carbohidratos_g_por_racion, grasas_g_por_racion, generado_ia) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";

        $stmt = $pdo->prepare($sql);

        // Búsqueda de nutrientes
        $cal = $data['calorias'] ?? 0;
        $prot = $data['macros']['proteinas'] ?? $data['macros']['protein'] ?? 0;
        $carb = $data['macros']['carbohidratos'] ?? $data['macros']['carbos'] ?? $data['macros']['carbs'] ?? 0;
        $gras = $data['macros']['grasas'] ?? $data['macros']['fats'] ?? 0;

        // --- CATEGORIZACIÓN PARA LA BIBLIOTECA (Tabla 'receta') ---
        $tipoParaBiblioteca = 'Almuerzo'; // Valor por defecto

        if (!empty($momentoContexto)) {
            $norm = mb_strtolower($momentoContexto, 'UTF-8');
            // Si es media mañana o merienda, en la biblioteca se guarda como 'Snack'
            if (in_array($norm, ['media mañana', 'merienda', 'snack', 'tentempié'])) {
                $tipoParaBiblioteca = 'Snack';
            } else {
                // Capitalizamos la primera letra (Desayuno, Almuerzo, Cena)
                $tipoParaBiblioteca = mb_convert_case($norm, MB_CASE_TITLE, "UTF-8");
            }
        } elseif (!empty($data['tipo_comida_sugerido'])) {
            $tipoParaBiblioteca = $data['tipo_comida_sugerido'];
        }

        $instrucciones = is_array($data['instrucciones']) ? implode("\n", $data['instrucciones']) : ($data['instrucciones'] ?? '');

        $stmt->execute([
            $userId, 
            $data['titulo'] ?? 'Receta KaloAI', 
            $tipoParaBiblioteca, // <--- Aquí guardamos 'Snack'
            $instrucciones, 
            $cal,
            $prot, 
            $carb, 
            $gras
        ]);

        // Obtenemos el ID generado para vincularlo a los ingredientes y al plan
        $idReceta = $pdo->lastInsertId();

        /* ==========================================================================
           3. INSERCIÓN DE INGREDIENTES (Detalle)
           Recorre el desglose de la receta para poblar la tabla detalle_receta
           ========================================================================== */
        if (isset($data['ingredientes_usados']) && is_array($data['ingredientes_usados'])) {
            $stmtDetalle = $pdo->prepare("INSERT INTO detalle_receta (id_receta, nombre_ingrediente, cantidad, unidad) VALUES (?, ?, ?, ?)");
            foreach ($data['ingredientes_usados'] as $ing) {
                $stmtDetalle->execute([
                    $idReceta, 
                    $ing['nombre'] ?? 'Ingrediente', 
                    $ing['cantidad'] ?? 0, 
                    $ing['unidad'] ?? 'u.'
                ]);
            }
        }

        /* ==========================================================================
           4. VÍNCULO CON EL CALENDARIO (Planificación)
           Si el usuario venía de un día específico, asignamos la receta automáticamente
           ========================================================================== */
        if ($diaNumero !== null && !empty($momentoContexto)) {
            // Localizar el plan activo del usuario
            $stmtPlan = $pdo->prepare("SELECT id_plan FROM plan_semanal WHERE id_usuario = ? LIMIT 1");
            $stmtPlan->execute([$userId]);
            $idPlan = $stmtPlan->fetchColumn();

            // Si es un usuario nuevo sin plan, lo creamos al vuelo
            if (!$idPlan) {
                $stmtNewPlan = $pdo->prepare("INSERT INTO plan_semanal (id_usuario) VALUES (?)");
                $stmtNewPlan->execute([$userId]);
                $idPlan = $pdo->lastInsertId();
            }

            // Sobrescritura: Eliminamos lo que hubiera en ese bloque horario (Lunes-Desayuno, etc.)
            $stmtDel = $pdo->prepare("DELETE FROM comida_planificada WHERE id_plan = ? AND dia_semana = ? AND momento_comida = ?");
            $stmtDel->execute([$idPlan, $diaNumero, $momentoContexto]);

            $stmtInsertPlan = $pdo->prepare("INSERT INTO comida_planificada (id_plan, id_receta, dia_semana, momento_comida, raciones) VALUES (?, ?, ?, ?, ?)");
            $stmtInsertPlan->execute([$idPlan, $idReceta, $diaNumero, $momentoContexto, 1]);
        }

        // Si todo salió bien, guardamos cambios permanentemente
        $pdo->commit();
        
        /* ==========================================================================
           5. REDIRECCIÓN SEGÚN FLUJO
           - Si se planificó -> Vuelve al calendario
           - Si solo se guardó -> Va a la biblioteca de recetas
           ========================================================================== */
        $urlRedir = ($diaNumero !== null) ? "planificacion_semanal.php?success=1" : "mis_recetas.php?success=1";
        header("Location: " . $urlRedir);
        exit;

    } catch (Exception $e) {
        // En caso de fallo, deshacemos todos los cambios en la DB para evitar datos huérfanos
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Error en la base de datos: " . $e->getMessage());
    }
} else {
    // Protección contra accesos directos por URL sin datos POST
    die("Error: Acceso no autorizado.");
}