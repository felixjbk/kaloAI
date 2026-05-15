<?php
/**
 * GESTIÓN DE PERFIL NUTRICIONAL Y OBJETIVOS - KaloAI
 * Este script actúa como el "cerebro" metabólico de la aplicación, calculando
 * el TDEE (Gasto Energético Total) y el reparto de macros según el objetivo.
 */

$pageTitle = "KaloAI | Mi Perfil y Objetivos";

session_start();

require_once __DIR__ . '/../../core/configuracion.php';
require_once __DIR__ . '/../../core/utilidades.php'; 

// 1. SEGURIDAD: Control de acceso para proteger datos sensibles de salud
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php'); 
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = 'success';
$profileData = [];
$pdo = connectDB();

// Diccionario de seguridad alimentaria para la base de datos
$tiposRestriccion = [
    'alergia' => 'Alergia (Riesgo Alto)',
    'intolerancia' => 'Intolerancia (Riesgo Medio)',
    'no_gusto' => 'No Gusto (Preferencia)'
];

/**
 * ==========================================================
 * MAPEOS DE DATOS NUTRICIONALES
 * Factores basados en la fórmula de Harris-Benedict / Mifflin-St Jeor
 * ==========================================================
 */
$activityFactors = [
    'sedentario' => 1.2,
    'ligero' => 1.375,
    'moderado' => 1.55,
    'activo' => 1.725,
    'muy_activo' => 1.9
];

$nivelesActividad = [
    'sedentario' => 'Sedentario (poco o ningún ejercicio)',
    'ligero' => 'Ejercicio ligero (1-3 días/semana)',
    'moderado' => 'Ejercicio moderado (3-5 días/semana)',
    'activo' => 'Ejercicio activo (6-7 días/semana)',
    'muy_activo' => 'Muy Activo (ejercicio diario intenso / doble sesión)'
];

$objetivos = [
    'perdida_rapida' => 'Pérdida de peso Rápida (déficit alto)',
    'perdida_moderada' => 'Pérdida de peso Moderada (déficit medio)',
    'perdida_grasa' => 'Pérdida de Grasa (definición)',
    'mantenimiento' => 'Mantenimiento de peso actual',
    'ganancia_muscular' => 'Ganancia Muscular (volumen)',
    'recomposicion_corporal' => 'Recomposición Corporal (ganar músculo y perder grasa)',
    'rendimiento_deportivo' => 'Rendimiento Deportivo (máximo rendimiento físico)'
];

// Inicialización de variables para cálculos de macronutrientes
$proteinasObjetivo = 0;
$carbohidratosObjetivo = 0;
$grasasObjetivo = 0;
$caloriasNetasObjetivo = 0;

$currentRestrictions = []; 

/**
 * ==========================================================
 * 2. LÓGICA DE ACTUALIZACIÓN (POST)
 * Procesa el formulario, valida límites biológicos y guarda en DB.
 * ==========================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // Sanitización exhaustiva de entradas para prevenir XSS e inyecciones
    $nombre = limpiarInput($_POST['nombre'] ?? '');
    $fechaNacimiento = limpiarInput($_POST['fecha_nacimiento'] ?? '');
    $genero = limpiarInput($_POST['genero'] ?? '');
    $alturaCm = filter_var($_POST['altura_cm'] ?? '', FILTER_VALIDATE_INT);
    $pesoKg = filter_var($_POST['peso_kg'] ?? '', FILTER_VALIDATE_FLOAT);
    $nivelActividad = limpiarInput($_POST['nivel_actividad'] ?? '');
    $objetivo = limpiarInput($_POST['objetivo'] ?? ''); 
    
    // Decodificación de restricciones enviadas vía JSON desde el frontend
    $restriccionesJson = $_POST['restricciones_json'] ?? '[]';
    $restriccionesArray = json_decode($restriccionesJson, true) ?: [];
    $currentRestrictions = $restriccionesArray; 

    // VALIDACIONES DE INTEGRIDAD BIOLÓGICA
    if (strlen($nombre) < 2 || strlen($nombre) > 50) {
        $errors[] = "El nombre debe tener entre 2 y 50 caracteres.";
    }

    if (!$pesoKg || $pesoKg < 30 || $pesoKg > 350) {
        $errors[] = "Por favor, introduce un peso válido entre 30 y 350 kg.";
    }

    if (!$alturaCm || $alturaCm < 100 || $alturaCm > 250) {
        $errors[] = "La altura debe estar entre 100 y 250 cm.";
    }

    // El sistema restringe el uso a rangos donde las fórmulas son precisas (15-100 años)
    if (!empty($fechaNacimiento) && strtotime($fechaNacimiento) !== false) {
        $edad = calcularEdad($fechaNacimiento);
        if ($edad === false || $edad < 15 || $edad > 100) {
            $errors[] = "La edad permitida es entre 15 y 100 años para garantizar cálculos precisos.";
        }
    } else {
        $errors[] = "La fecha de nacimiento no es válida.";
    }

    if (!in_array($genero, ['M', 'F'])) {
        $errors[] = "El género seleccionado no es válido.";
    }

    if (!array_key_exists($nivelActividad, $nivelesActividad)) {
        $errors[] = "El nivel de actividad seleccionado no es válido.";
    } else {
        $activityFactor = $activityFactors[$nivelActividad];
    }
    
    if (!array_key_exists($objetivo, $objetivos)) { 
        $errors[] = "El objetivo seleccionado no es válido.";
    }

    // PROCESAMIENTO MATEMÁTICO: Si los inputs son válidos, calculamos la dieta
    if (empty($errors)) {
        try {
            // 1. TDEE: Calorías de mantenimiento (gasto total diario)
            $tdeeCalorias = calcularTDEE($genero, $pesoKg, $alturaCm, $edad, $activityFactor);
            $tdeeCalorias = (int) round($tdeeCalorias);

            // 2. MACROS: Ajuste de calorías y gramos según objetivo (ej: déficit para perder peso)
            $macros = calcularMacrosObjetivo($tdeeCalorias, $objetivo);
            $proteinasObjetivo = $macros['proteinas'];
            $carbohidratosObjetivo = $macros['carbohidratos'];
            $grasasObjetivo = $macros['grasas'];
            $caloriasNetasObjetivo = $macros['calorias_netas'];
            
        } catch (\Exception $e) {
            $errors[] = "Error al calcular datos nutricionales: " . $e->getMessage();
            $tdeeCalorias = 0; 
        }
    }

    // PERSISTENCIA DE DATOS: Guardado transaccional en la base de datos
    if (empty($errors)) {
        try {
            $pdo->beginTransaction(); 

            // A. GESTIÓN DEL PERFIL: Detectamos si es actualización o creación nueva
            $stmt = $pdo->prepare("SELECT id_perfil FROM perfil WHERE id_usuario = ?");
            $stmt->execute([$userId]);
            $profileExists = $stmt->fetch();

            $params = [
                $nombre, $fechaNacimiento, $genero, $alturaCm, $pesoKg, $nivelActividad, $objetivo, 
                $tdeeCalorias, $proteinasObjetivo, $carbohidratosObjetivo, $grasasObjetivo, $caloriasNetasObjetivo, 
            ];

            $sqlSet = "nombre = ?, fecha_nacimiento = ?, genero = ?, altura_cm = ?, peso_kg = ?, nivel_actividad = ?, objetivo = ?, tdee_calorias = ?, proteinas_objetivo_g = ?, carbohidratos_objetivo_g = ?, grasas_objetivo_g = ?, calorias_netas_objetivo = ?";
            
            if ($profileExists) {
                $sql = "UPDATE perfil SET {$sqlSet} WHERE id_usuario = ?";
                $params[] = $userId;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                $cols = "id_usuario, nombre, fecha_nacimiento, genero, altura_cm, peso_kg, nivel_actividad, objetivo, tdee_calorias, proteinas_objetivo_g, carbohidratos_objetivo_g, grasas_objetivo_g, calorias_netas_objetivo";
                $placeholders = implode(', ', array_fill(0, 13, '?'));
                $sql = "INSERT INTO perfil ({$cols}) VALUES ({$placeholders})";
                array_unshift($params, $userId); 
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            // B. GESTIÓN DE EXCLUSIONES ALIMENTARIAS: Limpiar e insertar nuevas reglas
            $stmt = $pdo->prepare("DELETE FROM ingrediente_prohibido WHERE id_usuario = ?");
            $stmt->execute([$userId]);
            
            if (!empty($restriccionesArray)) {
                $sqlInsert = "INSERT INTO ingrediente_prohibido (id_usuario, nombre_ingrediente, nota) VALUES (?, ?, ?)";
                $stmtInsert = $pdo->prepare($sqlInsert);
                
                foreach ($restriccionesArray as $restriccion) {
                    $ingrediente = limpiarInput($restriccion['ingrediente'] ?? '');
                    $tipo = limpiarInput($restriccion['tipo'] ?? 'no_especificado');
                    
                    if (!empty($ingrediente)) {
                        $nota = $tiposRestriccion[$tipo] ?? 'Restricción Personalizada';
                        $stmtInsert->execute([$userId, $ingrediente, $nota]);
                    }
                }
            }
            
            $pdo->commit(); 
            $message = "Perfil, objetivos y restricciones alimentarias actualizadas.";
            
            // Actualización del estado local para refrescar la vista inmediatamente
            $profileData = [
                'nombre' => $nombre, 'fecha_nacimiento' => $fechaNacimiento, 'genero' => $genero, 
                'altura_cm' => $alturaCm, 'peso_kg' => $pesoKg, 'nivel_actividad' => $nivelActividad,
                'objetivo' => $objetivo, 'tdee_calorias' => $tdeeCalorias,
                'proteinas_objetivo_g' => $proteinasObjetivo, 
                'carbohidratos_objetivo_g' => $carbohidratosObjetivo,
                'grasas_objetivo_g' => $grasasObjetivo,
                'calorias_netas_objetivo' => $caloriasNetasObjetivo,
            ];
            $currentRestrictions = $restriccionesArray;

        } catch (\PDOException $e) {
            $pdo->rollBack(); 
            $errors[] = "Error al guardar el perfil: " . $e->getMessage();
            $messageType = 'error';
        }
    }

    if (!empty($errors)) {
        $message = "<ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
        $messageType = 'error';
        // Mantener datos en memoria para no borrar el formulario en caso de error
        $profileData = array_merge($profileData, [
            'nombre' => $nombre, 'fecha_nacimiento' => $fechaNacimiento, 'genero' => $genero, 
            'altura_cm' => $alturaCm, 'peso_kg' => $pesoKg, 'nivel_actividad' => $nivelActividad,
            'objetivo' => $objetivo, 
        ]);
    }
}

/**
 * ==========================================================
 * 3. LÓGICA DE CARGA (GET)
 * Recupera la información guardada para pre-rellenar el formulario.
 * ==========================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' || !empty($errors)) {
    try {
        // Carga de metadatos del perfil
        $stmt = $pdo->prepare("SELECT nombre, fecha_nacimiento, genero, altura_cm, peso_kg, nivel_actividad, objetivo, tdee_calorias, proteinas_objetivo_g, carbohidratos_objetivo_g, grasas_objetivo_g, calorias_netas_objetivo FROM perfil WHERE id_usuario = ?");
        $stmt->execute([$userId]);
        $fetchedData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fetchedData) {
            $profileData = array_merge($profileData, $fetchedData);
        } else if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($errors)) {
            $message = "¡Bienvenido! Completa tu perfil y objetivos para calcular tu Gasto Calórico Total (TDEE) y personalizar tus planes.";
        }
        
        // Carga y mapeo inverso de restricciones (DB Nota -> Tipo Clave)
        $stmtRestrictions = $pdo->prepare("SELECT nombre_ingrediente, nota FROM ingrediente_prohibido WHERE id_usuario = ?");
        $stmtRestrictions->execute([$userId]);
        $fetchedRestrictions = $stmtRestrictions->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($fetchedRestrictions)) {
            $tipoKeyMap = array_flip($tiposRestriccion);
            foreach ($fetchedRestrictions as $row) {
                $tipo = $tipoKeyMap[$row['nota']] ?? 'no_gusto'; 
                $currentRestrictions[] = [
                    'ingrediente' => $row['nombre_ingrediente'],
                    'tipo' => $tipo
                ];
            }
        }

    } catch (\PDOException $e) {
        error_log("Error al cargar el perfil o restricciones: " . $e->getMessage());
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
             $message = "Error al cargar datos: " . $e->getMessage();
             $messageType = 'error';
        }
    }
}

// Carga del componente de cabecera 
include_once __DIR__ . '/../templates/header.php'; 
?>

<!-- CONTENEDOR PRINCIPAL: Configuración de Biometría y Metas -->
<div class="container mx-auto px-4 py-8">
    
    <!-- ENCABEZADO DINÁMICO: Cambia entre "Crear" o "Editar" según el estado de la BD -->
    <div class="mb-10">
        <div class="flex items-center group">
            <a href="../dashboard.php" class="mr-4 bg-teal-50 p-3 rounded-2xl text-teal-600 hover:bg-teal-600 hover:text-white transition-all shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-5xl font-black text-gray-900 italic tracking-tight">
                <p class=" transition-colors">
                    <?php echo $profileData ? 'Editar' : 'Crear'; ?> <span class="text-teal-600">Perfil</span>
                </p>
            </h1>
        </div>
        <p class="text-gray-500 font-medium mt-4 ml-16">Configura tu biometría y metas para que KaloAI calcule tu plan ideal.</p>
    </div>

    <!-- FEEDBACK DE ESTADO: Mensajes de éxito o error tras el POST -->
    <?php if ($message): ?>
        <div class="mb-8 p-5 rounded-[2rem] font-bold border-2 text-center <?php echo $messageType === 'success' ? 'bg-teal-50 border-teal-100 text-teal-700' : 'bg-red-50 border-red-100 text-red-700'; ?> shadow-sm">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- WIDGET DE RESULTADOS (SOLO SI HAY DATOS): Muestra el cálculo de la IA -->
    <?php if (($profileData['tdee_calorias'] ?? 0) > 0 && empty($errors)): ?>
    <div class="mb-8 bg-white p-8 md:p-10 rounded-[2.5rem] shadow-xl border border-gray-100 relative overflow-hidden">
        <h2 class="text-2xl font-black text-gray-800 mb-8 flex items-center italic">
            <span class="bg-blue-50 p-3 rounded-2xl mr-4">📊</span> Tu Objetivo de Nutrición
        </h2>

        <!-- Resumen de Energía: Comparativa entre Mantenimiento (TDEE) y Objetivo (Déficit/Superávit) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-green-50 p-6 rounded-[2rem] border border-green-100 text-center">
                <p class="text-[10px] font-black text-green-600 uppercase tracking-widest mb-1">TDEE Estimado</p>
                <p class="text-4xl font-black text-green-900 italic">
                    <?php echo htmlspecialchars($profileData['tdee_calorias'] ?? 'N/A'); ?> 
                    <span class="text-sm font-bold text-green-600">kcal</span>
                </p>
            </div>
            <div class="bg-blue-600 p-6 rounded-[2rem] text-center shadow-lg shadow-blue-100">
                <p class="text-[10px] font-black text-blue-100 uppercase tracking-widest mb-1">Calorías Objetivo</p>
                <p class="text-4xl font-black text-white italic">
                    <?php echo htmlspecialchars($profileData['calorias_netas_objetivo'] ?? 'N/A'); ?> 
                    <span class="text-sm font-bold text-blue-200">kcal</span>
                </p>
            </div>
        </div>

        <!-- BARRA DE MACRONUTRIENTES: Visualización porcentual de Proteínas, Carbos y Grasas -->
        <?php
            $prote = $profileData['proteinas_objetivo_g'] ?? 0;
            $carbs = $profileData['carbohidratos_objetivo_g'] ?? 0;
            $gras = $profileData['grasas_objetivo_g'] ?? 0;
            $total = max(1, $prote + $carbs + $gras);
            $wProte = ($prote / $total) * 100;
            $wCarbs = ($carbs / $total) * 100;
            $wGras = ($gras / $total) * 100;
        ?>
        <div class="flex w-full h-24 overflow-hidden rounded-[1.5rem] shadow-inner bg-gray-100 mb-4">
            <div class="flex flex-col justify-center items-center text-white" style="width: <?php echo $wProte; ?>%; background-color: #3b82f6;">
                <span class="text-[10px] font-black uppercase opacity-70">Proteínas</span>
                <span class="text-2xl font-black italic"><?php echo round($prote); ?>g</span>
            </div>
            <div class="flex flex-col justify-center items-center text-white" style="width: <?php echo $wCarbs; ?>%; background-color: #fbbf24;">
                <span class="text-[10px] font-black uppercase opacity-70">Carbos</span>
                <span class="text-2xl font-black italic"><?php echo round($carbs); ?>g</span>
            </div>
            <div class="flex flex-col justify-center items-center text-white" style="width: <?php echo $wGras; ?>%; background-color: #f97316;">
                <span class="text-[10px] font-black uppercase opacity-70">Grasas</span>
                <span class="text-2xl font-black italic"><?php echo round($gras); ?>g</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- FORMULARIO DE PERFIL -->
    <form method="POST" id="profileForm" action="perfil.php" class="space-y-8">
        <!-- Campo Oculto: Almacena las restricciones como JSON para procesarlas en el Backend -->
        <input type="hidden" name="restricciones_json" id="restricciones_json" value='<?php echo json_encode($currentRestrictions); ?>'>

        <!-- SECCIÓN 1: Datos Fisiológicos -->
        <div class="bg-white p-8 md:p-10 rounded-[2.5rem] shadow-sm border-2 border-gray-50">
            <h2 class="text-2xl font-bold text-gray-800 mb-8 flex items-center italic tracking-tight">
                <span class="bg-teal-50 p-3 rounded-2xl mr-4">🧬</span> Datos Personales
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-2">
                    <label for="nombre" class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-2">Nombre Completo</label>
                    <input id="nombre" name="nombre" type="text" required class="w-full p-5 rounded-2xl border-2 border-gray-50 bg-gray-50 focus:bg-white focus:border-teal-200 outline-none transition-all font-bold text-gray-700" value="<?php echo htmlspecialchars($profileData['nombre'] ?? ''); ?>">
                </div>
                <div class="space-y-2">
                    <label for="fecha_nacimiento" class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-2">Fecha de Nacimiento</label>
                    <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" required class="w-full p-5 rounded-2xl border-2 border-gray-50 bg-gray-50 focus:bg-white focus:border-teal-200 outline-none transition-all font-bold text-gray-700 uppercase" value="<?php echo htmlspecialchars($profileData['fecha_nacimiento'] ?? ''); ?>">
                </div>
                <!-- Género y Altura con el mismo estilo minimalista -->
                <div class="space-y-2">
                    <label for="genero" class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-2">Género</label>
                    <select id="genero" name="genero" required class="w-full p-5 rounded-2xl border-2 border-gray-50 bg-gray-50 focus:bg-white focus:border-teal-200 outline-none transition-all font-bold text-gray-700">
                        <option value="">Selecciona</option>
                        <option value="M" <?php echo ($profileData['genero'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculino</option>
                        <option value="F" <?php echo ($profileData['genero'] ?? '') === 'F' ? 'selected' : ''; ?>>Femenino</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <label for="altura_cm" class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-2">Altura (cm)</label>
                    <input id="altura_cm" name="altura_cm" type="number" step="1" required class="w-full p-5 rounded-2xl border-2 border-gray-50 bg-gray-50 focus:bg-white focus:border-teal-200 outline-none transition-all font-bold text-gray-700" value="<?php echo htmlspecialchars($profileData['altura_cm'] ?? ''); ?>">
                </div>
                <div class="md:col-span-2 space-y-2">
                    <label for="peso_kg" class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-2">Peso Actual (kg)</label>
                    <input id="peso_kg" name="peso_kg" type="number" step="0.1" required class="w-full p-6 rounded-2xl border-2 border-teal-50 bg-teal-50/30 focus:bg-white focus:border-teal-200 outline-none transition-all font-black text-teal-600 text-3xl text-center" value="<?php echo htmlspecialchars($profileData['peso_kg'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- SECCIÓN 2: Estilo de vida y Metas -->
        <div class="bg-white p-8 md:p-10 rounded-[2.5rem] shadow-sm border-2 border-gray-50">
            <h2 class="text-2xl font-bold text-gray-800 mb-8 flex items-center italic tracking-tight">
                <span class="bg-teal-50 p-3 rounded-2xl mr-4">🎯</span> Metas y Actividad
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-2">
                    <label for="nivel_actividad" class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-2">Nivel de Actividad Física</label>
                    <select id="nivel_actividad" name="nivel_actividad" required class="w-full p-5 rounded-2xl border-2 border-gray-50 bg-gray-50 focus:bg-white focus:border-teal-200 outline-none transition-all font-bold text-gray-700">
                        <?php foreach ($nivelesActividad as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($profileData['nivel_actividad'] ?? '') === $key ? 'selected' : ''; ?>><?php echo $value; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="space-y-2">
                    <label for="objetivo" class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-2">Objetivo Principal</label>
                    <select id="objetivo" name="objetivo" required class="w-full p-5 rounded-2xl border-2 border-gray-50 bg-gray-50 focus:bg-white focus:border-teal-200 outline-none transition-all font-black text-teal-600">
                        <?php foreach ($objetivos as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($profileData['objetivo'] ?? '') === $key ? 'selected' : ''; ?>><?php echo $value; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 3: Restricciones -->
        <div class="bg-gray-900 p-8 md:p-10 rounded-[2.5rem] shadow-xl text-white border-b-8 border-red-500">
            <h2 class="text-2xl font-bold mb-4 flex items-center italic tracking-tight">
                <span class="text-3xl mr-3">🚫</span> Restricciones Alimentarias
            </h2>
            <!-- Agregador de Restricciones Dinámico -->
            <div class="flex flex-col sm:flex-row gap-4 mb-10">
                <div class="flex-grow">
                    <input id="nuevo_ingrediente" type="text" placeholder="Ej: Lactosa..." class="w-full p-5 rounded-2xl bg-white/10 border border-white/10 focus:border-red-400 outline-none text-white font-bold transition-all">
                </div>
                <div class="sm:w-64">
                    <select id="new_tipo" class="w-full p-5 rounded-2xl bg-white/10 border border-white/10 text-white font-bold outline-none">
                        <?php foreach ($tiposRestriccion as $key => $value): ?>
                            <option value="<?php echo $key; ?>" class="text-gray-900 font-bold"><?php echo $value; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" id="agregarRestriccionBtn" class="px-8 py-5 bg-red-600 hover:bg-red-500 text-white font-black rounded-2xl transition-all uppercase tracking-widest text-xs">
                    Añadir
                </button>
            </div>
            <!-- Lista de restricciones añadidas (renderizadas por JS o PHP inicial) -->
            <div class="pt-8 border-t border-white/10">
                <ul id="restrictionsList" class="space-y-3">
                    <?php if (empty($currentRestrictions)): ?>
                        <li class="text-sm text-gray-500 italic text-center py-4" id="emptyMessage">Sin restricciones añadidas.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- BOTÓN DE ACCIÓN FINAL -->
        <div class="pt-4 pb-12">
            <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-black py-8 rounded-[2.5rem] shadow-2xl transition-all transform hover:scale-[1.02] uppercase tracking-[0.2em]">
                GUARDAR PERFIL Y ACTUALIZAR
            </button>
        </div>
    </form>
    <div class="mt-16 text-center">
        <a href="../dashboard.php" class="inline-flex items-center font-black text-xs uppercase tracking-[0.2em] text-gray-400 hover:text-teal-600 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path></svg>
            Volver al Panel
        </a>
    </div>
</div>

<!-- DATA BRIDGE: Pasa los datos de PHP a JavaScript -->
<script>
    window.profileData = {
        tipoMap: <?php echo json_encode($tiposRestriccion); ?>,
        currentRestrictions: <?php echo json_encode($currentRestrictions); ?>
    };
</script>

<?php
// Incluir el pie de página
include_once __DIR__ . '/../templates/footer.php'; 
?>