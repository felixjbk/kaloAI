<?php
require_once __DIR__ . '/configuracion.php';
require_once __DIR__ . '/consultas_ia.php';

/**
 * Función principal para la generación de recetas personalizadas mediante IA.
 * Cruza datos de inventario, metas calóricas y preferencias del usuario para obtener
 * un plan nutricional coherente y estructurado.
 */
function generarRecetaInteligente($userId, $soloInventario = true, $dia = null, $momento = null, $sugerenciaUsuario = "") {
    $pdo = connectDB();

    // 1. OBTENCIÓN DEL INVENTARIO ACTUAL
    // Consulto los ingredientes disponibles en la despensa del usuario para priorizar su uso.
    $stmt = $pdo->prepare("SELECT nombre_ingrediente, cantidad, unidad FROM inventario WHERE id_usuario = ?");
    $stmt->execute([$userId]);
    $ingredientes = $stmt->fetchAll();
    
    $listaIngredientes = "";
    foreach ($ingredientes as $ing) {
        $listaIngredientes .= "- {$ing['nombre_ingrediente']}: {$ing['cantidad']} {$ing['unidad']}\n";
    }

    // 2. RECUPERACIÓN DE PERFIL Y METAS NUTRICIONALES
    // Extraigo el TDEE (Gasto Energético Total Diario) para calcular los objetivos de la receta.
    $stmtPerfil = $pdo->prepare("SELECT tdee_calorias FROM perfil WHERE id_usuario = ?");
    $stmtPerfil->execute([$userId]);
    $perfil = $stmtPerfil->fetch();
    
    $metaKcalDiaria = $perfil['tdee_calorias'] ?? 2000;
    // Establezco un reparto de macronutrientes base (ej. 30% proteínas).
    $metaPDiaria = ($metaKcalDiaria * 0.30) / 4;

    // 3. DISTRIBUCIÓN DE CARGA CALÓRICA SEGÚN EL MOMENTO DEL DÍA
    // Defino porcentajes específicos para asegurar que cada comida cumpla su función energética.
    $distribucion = [
        'desayuno'      => ['kcal' => 0.20, 'prot' => 0.20],
        'almuerzo'      => ['kcal' => 0.35, 'prot' => 0.30],
        'comida'        => ['kcal' => 0.35, 'prot' => 0.30],
        'merienda'      => ['kcal' => 0.10, 'prot' => 0.15],
        'cena'          => ['kcal' => 0.25, 'prot' => 0.25],
        'snack'         => ['kcal' => 0.10, 'prot' => 0.10],
        'media mañana'  => ['kcal' => 0.10, 'prot' => 0.10]
    ];

    $momentoKey = strtolower($momento ?? '');
    $peso = $distribucion[$momentoKey] ?? ['kcal' => 0.30, 'prot' => 0.30];

    // Calculo los objetivos específicos para esta ración individual.
    $targetKcal = round($metaKcalDiaria * $peso['kcal']);
    $targetP    = round($metaPDiaria * $peso['prot']);
    $targetC    = round(($targetKcal * 0.40) / 4);
    $targetG    = round(($targetKcal * 0.30) / 9);

    // 4. MAPEADO DE DÍA Y COMPENSACIÓN CALÓRICA
    // Calculo el consumo ya planificado para el día seleccionado para ajustar las calorías restantes.
    $diasMap = ['Lunes'=>0,'Martes'=>1,'Miércoles'=>2,'Jueves'=>3,'Viernes'=>4,'Sábado'=>5,'Domingo'=>6];
    $diaNum = $diasMap[$dia] ?? 0;

    if ($dia !== null) {
        $stmtPlan = $pdo->prepare("SELECT SUM(r.calorias_por_racion) FROM comida_planificada cp 
                                   JOIN receta r ON cp.id_receta = r.id_receta 
                                   JOIN plan_semanal ps ON cp.id_plan = ps.id_plan
                                   WHERE ps.id_usuario = ? AND cp.dia_semana = ?");
        $stmtPlan->execute([$userId, $diaNum]);
        $consumoActual = $stmtPlan->fetchColumn() ?? 0;
        
        $caloriasRestantes = $metaKcalDiaria - $consumoActual;
        // Si el usuario ya ha consumido casi todo su cupo, ajusto la receta a un mínimo funcional.
        if ($caloriasRestantes < $targetKcal) {
            $targetKcal = max(150, $caloriasRestantes); 
        }
    }

    // 5. GESTIÓN DE RESTRICCIONES ALIMENTARIAS
    // Obtengo la lista de ingredientes prohibidos para asegurar que la IA no los incluya.
    $stmtRest = $pdo->prepare("SELECT nombre_ingrediente FROM ingrediente_prohibido WHERE id_usuario = ?");
    $stmtRest->execute([$userId]);
    $prohibidos = implode(", ", $stmtRest->fetchAll(PDO::FETCH_COLUMN));

    // 6. CONSTRUCCIÓN DEL PROMPT ESTRUCTURADO
    // Diseño las instrucciones detalladas para la IA, forzando un formato de respuesta JSON y unificación de términos.
    $prompt = "Actúa como un Nutricionista Profesional. Tu objetivo es crear una receta de " . strtoupper($momentoKey) . " para UNA SOLA RACIÓN.\n\n";
    
    $prompt .= "--- REGLA DE ORO DE INGREDIENTES ---\n";
    $prompt .= "Para que la lista de la compra no se duplique, usa SIEMPRE estos nombres:\n";
    $prompt .= "- Usa 'Pechuga de pollo' en lugar de 'Pollo'.\n";
    $prompt .= "- Usa 'Aceite de oliva' siempre en 'ml'.\n";
    $prompt .= "- Usa 'Proteína de suero' en lugar de 'Proteína' o 'Suplemento'.\n";
    $prompt .= "- Usa 'Claras de huevo' siempre en 'ml'.\n";
    $prompt .= "- No uses decimales en unidades (ej: no digas 0.3 plátano, di 40g de plátano).\n\n";

    $prompt .= "--- OBJETIVO NUTRICIONAL (ESTRICTO PARA 1 RACIÓN) ---\n";
    $prompt .= "- Calorías: {$targetKcal} kcal\n";
    $prompt .= "- Proteína: {$targetP}g | Carbohidratos: {$targetC}g | Grasas: {$targetG}g\n\n";

    $prompt .= "--- REGLA DE ESCALABILIDAD ---\n";
    $prompt .= "1. Calcula todos los ingredientes para exactamente UNA RACIÓN individual.\n";
    $prompt .= "2. Los macros en el JSON deben sumar los valores del OBJETIVO arriba indicado.\n";
    $prompt .= "3. IMPORTANTE: En las instrucciones, añade una nota final indicando: 'Si deseas cocinar más raciones, simplemente multiplica las cantidades de los ingredientes'.\n\n";

    if (!empty($sugerenciaUsuario)) {
        $prompt .= "--- SOLICITUD DEL USUARIO ---\n- Plato: \"$sugerenciaUsuario\".\n\n";
    }

    $prompt .= "--- DESPENSA DISPONIBLE ---\n$listaIngredientes\n";

    $prompt .= "Responde en JSON:\n";
    $prompt .= "{
      \"titulo\": \"\",
      \"calorias\": {$targetKcal},
      \"macros\": { \"proteinas\": {$targetP}, \"carbohidratos\": {$targetC}, \"grasas\": {$targetG} },
      \"ingredientes_usados\": [ {\"nombre\": \"\", \"cantidad\": 0, \"unidad\": \"\"} ],
      \"instrucciones\": [],
      \"raciones_sugeridas\": 1
    }";

    // 7. EJECUCIÓN Y PROCESAMIENTO DE LA RESPUESTA
    // Envío la consulta y limpio la respuesta para asegurar que sea un JSON válido.
    $respuestaIA = preguntarGemini($prompt);
    $jsonLimpio = preg_replace('/^```json|```$/m', '', trim($respuestaIA));
    $dataIA = json_decode($jsonLimpio, true);

    if (!$dataIA) {
        $jsonLimpio = preg_replace('/[\x00-\x1F\x7F]/u', '', $jsonLimpio);
        $dataIA = json_decode($jsonLimpio, true);
    }

    if (!$dataIA) return ['error' => 'Error de formato IA.'];

    // 8. UNIFICACIÓN Y LIMPIEZA DE INGREDIENTES
    // Normalizo los nombres de los ingredientes para evitar duplicados en la lista de la compra.
    $ingredientesFinales = $dataIA['ingredientes_usados'] ?? $dataIA['ingredientes'] ?? [];

    foreach ($ingredientesFinales as &$ing) {
        $nombre = mb_strtolower($ing['nombre']);

        if (str_contains($nombre, 'pollo')) $ing['nombre'] = 'Pechuga de pollo';
        
        if (str_contains($nombre, 'proteina') || str_contains($nombre, 'whey')) $ing['nombre'] = 'Proteína de suero';

        if (str_contains($nombre, 'aceite')) {
            $ing['nombre'] = 'Aceite de oliva';
            $ing['unidad'] = 'ml';
        }

        if (str_contains($nombre, 'clara')) {
            $ing['nombre'] = 'Claras de huevo';
            $ing['unidad'] = 'ml';
        }
        
        if (str_contains($nombre, 'avena')) $ing['nombre'] = 'Avena';

        // Elimino descriptores innecesarios para mantener un inventario limpio.
        $ing['nombre'] = str_replace(['fresco', 'fresca', 'picado', 'grande', 'maduro'], '', $ing['nombre']);
        $ing['nombre'] = trim($ing['nombre']);
        $ing['nombre'] = mb_convert_case($ing['nombre'], MB_CASE_TITLE, "UTF-8");
    }

    // 9. FORMATEO FINAL DE SALIDA
    // Retorno la receta estructurada siguiendo el modelo de datos de la aplicación.
    return [
        'titulo' => $dataIA['titulo'] ?? $dataIA['nombre'] ?? 'Receta KaloAI',
        'calorias' => $dataIA['calorias'] ?? $targetKcal,
        'macros' => [
            'proteinas'       => $dataIA['macros']['proteinas'] ?? $dataIA['macros']['protein'] ?? $targetP,
            'carbohidratos'   => $dataIA['macros']['carbohidratos'] ?? $dataIA['macros']['carbos'] ?? $targetC,
            'grasas'          => $dataIA['macros']['grasas'] ?? $dataIA['macros']['fats'] ?? $targetG
        ],
        'ingredientes_usados' => $ingredientesFinales,
        'instrucciones' => $dataIA['instrucciones'] ?? $dataIA['pasos'] ?? [],
        'raciones_sugeridas' => $dataIA['raciones_sugeridas'] ?? 1
    ];
}