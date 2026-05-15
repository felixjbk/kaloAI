<?php
/**
 * PANEL DE CONTROL PRINCIPAL (DASHBOARD) - KaloAI
 * Este es el punto de entrada tras el login. Orquesta la carga de datos 
 * de múltiples módulos para ofrecer una visión 360° del estado del usuario.
 */

$pageTitle = "KaloAI | Panel de Control";

// 1. INICIALIZACIÓN Y SEGURIDAD
session_start();
require_once __DIR__ . '/../core/configuracion.php'; 
require_once __DIR__ . '/../core/utilidades.php';

// Validar que la sesión esté activa; de lo contrario, proteger la ruta
if (!isset($_SESSION['user_id'])) {
    redirect('auth/login.php');
}

// Mapeo de identidad y permisos
$userEmail = $_SESSION['user_email'] ?? 'Usuario KaloAI';
$userId = $_SESSION['user_id'] ?? 'N/A';
$userRole = $_SESSION['user_role_id'] ?? 3; 

$roleMap = [
    1 => 'Administrador',
    2 => 'Nutricionista',
    3 => 'Usuario Estándar'
];
$roleName = $roleMap[$userRole] ?? 'Desconocido';

// Inicialización de contenedores de datos
$profileData = null;
$latestProgressData = null;
$progresoData = []; 
$jsProgresoData = '[]'; 
$pdo = connectDB();

/**
 * ==========================================================
 * ACCIÓN: CIERRE DE SESIÓN
 * Limpieza de buffers y destrucción de tokens de acceso.
 * ==========================================================
 */
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    session_destroy();
    redirect('auth/login.php?loggedout=true');
}

/**
 * ==========================================================
 * CARGA DE PERFIL METABÓLICO
 * Extrae los objetivos nutricionales (Kcal/Macros) para mostrarlos en el resumen.
 * ==========================================================
 */
try {
    $stmt = $pdo->prepare("SELECT 
        nombre, 
        tdee_calorias, 
        proteinas_objetivo_g, 
        carbohidratos_objetivo_g, 
        grasas_objetivo_g, 
        calorias_netas_objetivo 
    FROM perfil 
    WHERE id_usuario = ?");
    $stmt->execute([$userId]);
    $profileData = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    error_log("Error al cargar datos del perfil en dashboard: " . $e->getMessage());
}

/**
 * ==========================================================
 * CARGA DE PROGRESO ANTROPOMÉTRICO
 * Recupera la serie temporal para gráficos y el registro más reciente.
 * ==========================================================
 */
try {
    $sql_all = "SELECT fecha, peso_kg, cintura_cm, nota 
                FROM progreso 
                WHERE id_usuario = ? 
                ORDER BY fecha ASC"; 
    $stmt_all = $pdo->prepare($sql_all);
    $stmt_all->execute([$userId]);
    $progresoData = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($progresoData)) {
        $latestProgressData = end($progresoData); 
    }

    // Transformación a formato consumible por Chart.js
    $jsProgresoData = json_encode($progresoData);

} catch (\PDOException $e) {
    error_log("Error al cargar datos de progreso en dashboard: " . $e->getMessage());
}

/**
 * ==========================================================
 * LÓGICA DE ALIMENTACIÓN DIARIA (V1)
 * Determina el día de la semana y normaliza los momentos de comida.
 * ==========================================================
 */
$diasEsp = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$diaHoyNombre = $diasEsp[date('w')]; // Obtiene el nombre del día según el índice de la función date()

$momentosDefinidos = ['Desayuno', 'Media Mañana', 'Almuerzo', 'Merienda', 'Cena'];
$comidasHoy = [];

// Consulta SQL con normalización LOWER para evitar errores de case-sensitivity en los días
$sqlComidas = "SELECT cp.*, r.titulo, r.calorias_por_racion, r.id_receta 
                FROM comida_planificada cp
                JOIN receta r ON cp.id_receta = r.id_receta
                JOIN plan_semanal ps ON cp.id_plan = ps.id_plan
                WHERE ps.id_usuario = ? 
                AND LOWER(cp.dia_semana) = LOWER(?)"; 

$stmtComidas = $pdo->prepare($sqlComidas);
$stmtComidas->execute([$userId, $diaHoyNombre]); 
$resultadosComidas = $stmtComidas->fetchAll(PDO::FETCH_ASSOC);

foreach ($resultadosComidas as $row) {
    /**
     * NORMALIZACIÓN DE CLAVES:
     * Asegura que "almuerzo" se convierta en "Almuerzo" para que la UI 
     * encuentre siempre la receta en el array $comidasHoy.
     */
    $momentoKey = ucfirst(strtolower($row['momento_comida']));
    $comidasHoy[$momentoKey] = $row;
}

/**
 * ==========================================================
 * LÓGICA DE ALIMENTACIÓN DIARIA (V2 - ADAPTADA A 5 MOMENTOS)
 * Versión optimizada con traducción de índices de base de datos a nombres.
 * ==========================================================
 */
$diasEsp = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$diaHoyNombre = $diasEsp[date('w')]; 

$momentosDefinidos = ['Desayuno', 'Media Mañana', 'Almuerzo', 'Merienda', 'Cena'];
$comidasHoy = [];

try {
    /**
     * USO DE CASE EN SQL:
     * Transforma el ID numérico guardado en 'dia_semana' (0-6) en el nombre 
     * legible para comparar con $diaHoyNombre mediante la cláusula HAVING.
     */
    $sqlComidas = "SELECT cp.*, r.titulo, r.calorias_por_racion, r.id_receta,
                    CASE cp.dia_semana 
                        WHEN 0 THEN 'Lunes' WHEN 1 THEN 'Martes' WHEN 2 THEN 'Miércoles' 
                        WHEN 3 THEN 'Jueves' WHEN 4 THEN 'Viernes' WHEN 5 THEN 'Sábado' WHEN 6 THEN 'Domingo' 
                    END as dia_nombre
                FROM comida_planificada cp
                JOIN receta r ON cp.id_receta = r.id_receta
                JOIN plan_semanal ps ON cp.id_plan = ps.id_plan
                WHERE ps.id_usuario = ? 
                HAVING dia_nombre = ?"; 

    $stmtComidas = $pdo->prepare($sqlComidas);
    $stmtComidas->execute([$userId, $diaHoyNombre]); 
    $rows = $stmtComidas->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        // Trim para evitar que espacios en blanco en la DB rompan las claves del array
        $momentoKey = trim($row['momento_comida']); 
        $comidasHoy[$momentoKey] = $row;
    }
} catch (\PDOException $e) { 
    error_log("Error Comidas Hoy: " . $e->getMessage()); 
}

/**
 * ==========================================================
 * SISTEMA DE VINCULACIÓN PROFESIONAL
 * Detecta si un Nutricionista ha solicitado acceso a los datos del paciente.
 * ==========================================================
 */
$stmtSol = $pdo->prepare("
    SELECT s.id_solicitud, p.nombre AS nombre_nutri 
    FROM solicitudes_vinculacion s
    JOIN perfil p ON s.id_nutricionista = p.id_usuario
    WHERE s.id_paciente = ? AND s.estado = 'pendiente'
");
$stmtSol->execute([$_SESSION['user_id']]);
$solicitudes = $stmtSol->fetchAll();

/**
 * UI: RENDERIZADO DE ALERTAS DE VINCULACIÓN
 * Muestra un modal disruptivo (pero estético) para cada solicitud pendiente.
 */
foreach ($solicitudes as $sol): 
?>
<div id="modal-nutri-<?= $sol['id_solicitud'] ?>" class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-gray-900/60 backdrop-blur-md animate-fade-in">
        
        <!-- Contenedor del Modal con diseño Premium (bordes amplios y sombras suaves) -->
        <div class="bg-white w-full max-w-md rounded-[40px] p-10 shadow-2xl transform animate-pop-in text-center border border-gray-100">
            
            <!-- Icono de Usuario con estilo minimalista -->
            <div class="bg-teal-50 w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>

            <h2 class="text-2xl font-black text-gray-900 uppercase tracking-tighter italic mb-2">
                ¡Nueva Solicitud!
            </h2>
            
            <p class="text-gray-500 text-sm font-medium mb-8 leading-relaxed">
                El nutricionista <span class="text-teal-600 font-bold"><?= htmlspecialchars($sol['nombre_nutri']) ?></span> quiere vincularse a tu cuenta para gestionar y optimizar tu plan nutricional.
            </p>

            <!-- Acciones de Decisión -->
            <div class="flex flex-col gap-3">
                <!-- Botón de Aceptación: Ejecuta el script de vinculación positiva -->
                <a href="user/aceptar_nutri.php?id=<?= $sol['id_solicitud'] ?>&accion=aceptar" 
                   class="w-full bg-gray-900 text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-teal-600 transition-all shadow-xl active:scale-95">
                    Permitir Acceso
                </a>
                
                <!-- Botón de Rechazo: Ejecuta la limpieza de la solicitud -->
                <a href="aceptar_nutri.php?id=<?= $sol['id_solicitud'] ?>&accion=rechazar" 
                   class="w-full bg-white text-gray-400 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:text-red-500 transition-all">
                    Ahora no, gracias
                </a>
            </div>

            <p class="mt-6 text-[10px] text-gray-300 font-bold uppercase tracking-widest">
                Seguridad KaloAI • Acceso Privado
            </p>
        </div>
    </div>
<?php endforeach;

// Carga del componente de cabecera 
include_once __DIR__ . '/templates/header.php';
?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="p-8 bg-white rounded-3xl shadow-sm border border-gray-100">

        <!-- BIENVENIDA Y ACCESO A PANELES DE ROL (Admin/Nutri) -->
        <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <h1 class="text-4xl md:text-5xl font-black text-gray-900 leading-tight">
                    ¡Hola, <span class="text-teal-600"><?php echo getPrimerNombre($profileData['nombre'] ?? $userEmail); ?></span>!
                </h1>
            </div>

            <!-- Lógica de Roles: Muestra botones de administración según el nivel de usuario -->
            <?php if ($userRole == 1): ?>
                <a href="admin/dashboard_admin.php" class="inline-flex items-center gap-3 px-5 py-3 bg-gray-900 border-2 border-gray-900 rounded-2xl transition-all hover:bg-white group shadow-lg hover:shadow-xl">
                    <div class="w-8 h-8 rounded-lg bg-teal-500 flex items-center justify-center transition-colors group-hover:bg-gray-100">
                        <svg class="w-5 h-5 text-white group-hover:text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </div>
                    <span class="text-xs font-black text-white uppercase tracking-widest group-hover:text-gray-900 transition-colors">
                        Panel Admin
                    </span>
                </a>

            <?php elseif ($userRole == 2): ?>
                <a href="nutri/dashboard_nutri.php" class="inline-flex items-center gap-3 px-5 py-3 bg-teal-600 border-2 border-teal-600 rounded-2xl transition-all hover:bg-white group shadow-lg hover:shadow-xl">
                    <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center transition-colors group-hover:bg-teal-500">
                        <svg class="w-5 h-5 text-teal-600 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <span class="text-xs font-black text-white uppercase tracking-widest group-hover:text-teal-600 transition-colors">
                        Panel Nutri
                    </span>
                </a>
            <?php endif; ?>
        </div>
    
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-10">

            <!-- SECCIÓN 1: META NUTRICIONAL (Cálculo de Macronutrientes) -->
            <?php if ($profileData && ($profileData['calorias_netas_objetivo'] ?? 0) > 0): ?>
                <div class="p-6 rounded-xl border-2 border-teal-500 bg-teal-50 shadow-lg">
                    <h2 class="text-2xl font-bold text-teal-800 mb-4 flex items-center">
                        <svg class="w-6 h-8 mr-2 text-teal-600" fill="none" stroke="currentColor" viewBox="-2 -2 24 24" xmlns="http://www.w3.org/2000/svg"> <circle cx="12" cy="8" r="4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <a href="<?php echo BASE_URL; ?>views/user/perfil.php">Tu Meta Nutricional Diaria</a>
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                        <!-- Gráfico de Donut (Chart.js) para Kcal -->
                        <div class="relative h-48 w-48 mx-auto">
                            <canvas id="macroChart"></canvas>
                            <div class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-gray-700">
                                <span class="text-xl text-teal-800 font-extrabold"><?php echo htmlspecialchars(round($profileData['calorias_netas_objetivo'])); ?></span> kcal
                            </div>
                        </div>

                        <!-- Barras de Progreso de TDEE y Desglose de Macros -->
                        <div>
                            <div class="mb-4">
                                <p class="text-xs font-bold text-teal-800 mb-1">Calorías Netas Objetivo</p>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <?php
                                        $caloriasNetas = round($profileData['calorias_netas_objetivo']);
                                        $tdee = round($profileData['tdee_calorias']);
                                        $progressPercentage = ($tdee > 0) ? min(100, ($caloriasNetas / $tdee) * 100) : 0;
                                        $isDeficit = $caloriasNetas < $tdee;
                                        $bgColor = $isDeficit ? 'bg-teal-500' : 'bg-teal-500'; 
                                    ?>
                                    <div class="h-3 rounded-full <?php echo $bgColor; ?>" style="width: <?php echo $progressPercentage; ?>%;"></div>
                                </div>
                                <p class="text-sm text-gray-700 mt-1">
                                    <?php echo htmlspecialchars($caloriasNetas); ?> kcal de <span class="font-semibold"><?php echo htmlspecialchars($tdee); ?> kcal (TDEE)</span>
                                </p>
                            </div>

                            <!-- Barras de Progreso de TDEE y Desglose de Macros -->
                            <ul class="space-y-2">
                                <li class="flex justify-between items-center text-gray-700">
                                    <span class="flex items-center"><span class="w-3 h-3 rounded-full bg-blue-500 mr-2"></span>Proteínas:</span>
                                    <span class="font-semibold"><?php echo htmlspecialchars(round($profileData['proteinas_objetivo_g'])); ?>g</span>
                                </li>
                                <li class="flex justify-between items-center text-gray-700">
                                    <span class="flex items-center"><span class="w-3 h-3 rounded-full bg-yellow-500 mr-2"></span>Carbohidratos:</span>
                                    <span class="font-semibold"><?php echo htmlspecialchars(round($profileData['carbohidratos_objetivo_g'])); ?>g</span>
                                </li>
                                <li class="flex justify-between items-center text-gray-700">
                                    <span class="flex items-center"><span class="w-3 h-3 rounded-full bg-orange-500 mr-2"></span>Grasas:</span>
                                    <span class="font-semibold"><?php echo htmlspecialchars(round($profileData['grasas_objetivo_g'])); ?>g</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- ESTADO VACÍO: Perfil incompleto -->
                <div class="p-6 rounded-xl border-2 border-yellow-500 bg-yellow-50 shadow-lg flex flex-col justify-center">
                    <h2 class="text-2xl font-bold text-yellow-800 mb-3">¡Perfil Incompleto!</h2>
                    <p class="text-gray-700 mb-4">Para ver tu meta diaria y la distribución de macronutrientes, completa tu perfil.</p>
                    <a href="<?php echo BASE_URL; ?>views/user/perfil.php" class="inline-block text-yellow-700 bg-yellow-200 hover:bg-yellow-300 px-4 py-2 rounded-lg font-semibold transition duration-150">
                        Completar Perfil &rarr;
                    </a>
                </div>
            <?php endif; ?>

            <!-- SECCIÓN 2: RESUMEN DE PROGRESO FÍSICO -->
            <div class="p-6 rounded-xl border-2 border-blue-500 bg-blue-50 shadow-lg">
                <?php if ($latestProgressData): ?>
                    <h2 class="text-2xl font-bold text-blue-800 mb-4 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 20V10M10 20V4M16 20v-6M22 20v-4"/>
                        </svg>
                        <a href="<?php echo BASE_URL; ?>views/user/progreso.php">Tu Progreso</a>
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Mini-gráfica de evolución de peso -->
                        <div class="md:col-span-1 bg-white p-4 rounded-lg shadow-inner h-51 flex flex-col justify-center items-center">
                            <?php if (count($progresoData) >= 2): ?>
                                <div class="relative w-full h-full">
                                    <canvas id="progressChart" class="absolute inset-0"></canvas>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-gray-500">
                                    <p class="text-sm mb-2">Añade 2 o más registros para ver tu evolución.</p>
                                    <a href="<?php echo BASE_URL; ?>views/user/progreso.php" class="text-blue-600 font-semibold hover:text-blue-800">Registrar ahora &rarr;</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="md:col-span-1">
                            <p class="text-sm text-gray-600 mb-2 border-b pb-2 block">
                                Último registro: 
                                <span class="font-bold text-blue-700"><?php echo formatearFecha($latestProgressData['fecha']); ?></span>
                            </p>

                            <!-- Datos Rápidos: Peso inicial vs Actual y Diferencia -->
                            <div class="grid grid-cols-2 gap-4 mt-2">
                                <div class="bg-blue-200 p-3 rounded-lg shadow-md">
                                    <p class="text-xs font-medium text-blue-800">Peso Inicial</p>
                                    <p class="text-2xl font-extrabold text-blue-900 mt-1">
                                        <?php 
                                        $pesoInicial = $progresoData[0]['peso_kg'] ?? 0;
                                        echo htmlspecialchars(number_format($pesoInicial, 1)) . ' <span class="text-base font-semibold">kg</span>';
                                        ?>
                                    </p>
                                </div>

                                <div class="bg-blue-200 p-3 rounded-lg shadow-md">
                                    <p class="text-xs font-medium text-blue-800">Peso Actual</p>
                                    <p class="text-2xl font-extrabold text-blue-900 mt-1">
                                        <?php 
                                        $pesoActual = end($progresoData)['peso_kg'] ?? 0;
                                        echo htmlspecialchars(number_format($pesoActual, 1)) . ' <span class="text-base font-semibold">kg</span>';
                                        ?>
                                    </p>
                                </div>

                                <div class="bg-blue-200 p-3 rounded-lg shadow-md">
                                    <p class="text-xs font-medium text-blue-800">Diferencia</p>
                                    <p class="text-2xl font-extrabold text-blue-900 mt-1">
                                        <?php 
                                        $diferencia = $pesoActual - $pesoInicial;
                                        echo htmlspecialchars(number_format($diferencia, 1)) . ' <span class="text-base font-semibold">kg</span>'; 
                                        ?>
                                    </p>
                                </div>

                                <div class="bg-blue-200 p-3 rounded-lg shadow-md">
                                    <p class="text-xs font-medium text-blue-800">Cintura Última</p>
                                    <p class="text-2xl font-extrabold text-blue-900 mt-1">
                                        <?php 
                                        $cintura = end($progresoData)['cintura_cm'] ?? null;
                                        echo $cintura ? htmlspecialchars(number_format($cintura, 1)) . ' <span class="text-base font-semibold">cm</span>' : '<span class="text-sm font-medium text-blue-700">- N/A -</span>'; 
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col justify-center py-4">
                        <h2 class="text-2xl font-bold text-blue-800 mb-3">¡Progreso Incompleto!</h2>
                        <p class="text-gray-700 mb-4 font-medium">Aún no has registrado tu evolución. Empieza hoy para trackear tus cambios de peso y medidas.</p>
                        <a href="<?php echo BASE_URL; ?>views/user/progreso.php" class="inline-block text-blue-700 bg-blue-200 hover:bg-blue-300 px-4 py-2 rounded-lg font-semibold transition duration-150 w-fit">
                            Registrar Primer Peso &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SECCIÓN 3: PLAN DE COMIDAS DEL DÍA (5 Momentos: Desayuno, Almuerzo, etc.) -->
        <div class="mb-10 bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
                <div>
                    <h2 class="text-3xl font-black text-gray-900 italic tracking-tight">
                        Plan para <span class="text-teal-600 capitalize"><?php echo $diaHoyNombre; ?></span>
                    </h2>
                    <p class="text-gray-400 text-sm font-medium italic">Tu estrategia nutricional de hoy</p>
                </div>
                <span class="px-5 py-2 bg-teal-50 rounded-full text-xs font-black text-teal-600 border border-teal-100 uppercase tracking-widest">
                    <?php echo date('d M, Y'); ?>
                </span>
            </div>

            <!-- Grid de Comidas: Se itera sobre Desayuno, Media Mañana, Comida, Merienda y Cena -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                <?php foreach ($momentosDefinidos as $momento): ?>
                    <?php $tieneComida = isset($comidasHoy[$momento]); ?>
                    <div class="group relative p-5 rounded-[2rem] transition-all duration-300 border-2 
                        <?= $tieneComida ? 'bg-white border-teal-50 shadow-sm hover:border-teal-200' : 'bg-gray-50 border-dashed border-gray-200 hover:bg-white hover:border-teal-100'; ?>">
                        
                        <span class="text-[9px] font-black uppercase tracking-[0.2em] mb-3 block <?= $tieneComida ? 'text-teal-500' : 'text-gray-400'; ?>">
                            <?= $momento ?>
                        </span>

                        <?php if ($tieneComida): ?>
                            <!-- COMIDA ASIGNADA: Título y enlace a receta -->
                            <h4 class="font-bold text-gray-800 text-sm leading-tight mb-4 italic line-clamp-2 min-h-[2.5rem]">
                                <?= htmlspecialchars($comidasHoy[$momento]['titulo']) ?>
                            </h4>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs font-black text-gray-600">
                                    <?= round($comidasHoy[$momento]['calorias_por_racion']) ?> <small class="text-gray-400">kcal</small>
                                </span>
                                <a href="user/detalle_receta.php?id=<?= $comidasHoy[$momento]['id_receta'] ?>" class="p-1.5 bg-teal-50 text-teal-600 rounded-lg hover:bg-teal-600 hover:text-white transition-all">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                            </div>
                        <?php else: ?>
                            <!-- COMIDA VACÍA: Enlace dinámico para generar receta con IA -->
                            <div class="text-center py-2">
                                <p class="text-[10px] text-gray-400 italic mb-3">Sin asignar</p>
                                <a href="user/generar_receta.php?dia=<?= urlencode($diaHoyNombre) ?>&momento=<?= urlencode($momento) ?>" 
                                class="inline-block w-full py-1.5 bg-white border border-gray-200 rounded-xl text-[8px] font-black text-gray-400 uppercase tracking-widest hover:border-teal-400 hover:text-teal-600 transition-all">
                                    + Planear <?= $momento ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SECCIÓN 4: ACCESOS DIRECTOS (Grilla de Navegación) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <!-- Cada tarjeta usa un color temático -->
            <a href="<?php echo BASE_URL; ?>views/user/planificacion_semanal.php" class="group relative overflow-hidden bg-white border-2 border-purple-100 p-8 rounded-3xl shadow-sm transition-all duration-300 hover:border-purple-200 hover:shadow-lg hover:-translate-y-1">
                <div class="relative z-10 flex justify-between items-center text-gray-800">
                    <div>
                        <h3 class="text-2xl font-bold italic tracking-tight text-purple-600">Plan Semanal</h3>
                        <p class="text-gray-500">Consulta tus menús y recetas diarias</p>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-2xl text-3xl group-hover:scale-110 transition-transform">🗓️</div>
                </div>
            </a>

            <a href="user/lista_compra.php" class="group relative overflow-hidden bg-white border-2 border-teal-100 p-8 rounded-3xl shadow-sm transition-all duration-300 hover:border-teal-200 hover:shadow-lg hover:-translate-y-1">
                <div class="relative z-10 flex justify-between items-center text-gray-800">
                    <div>
                        <h3 class="text-2xl font-bold italic tracking-tight text-teal-600">Lista de la Compra</h3>
                        <p class="text-gray-500">Lo que necesitas para tu semana</p>
                    </div>
                    <div class="bg-teal-50 p-4 rounded-2xl text-3xl group-hover:scale-110 transition-transform">🛒</div>
                </div>
            </a>

            <a href="<?php echo BASE_URL; ?>views/user/mis_recetas.php" class="group relative overflow-hidden bg-white border-2 border-pink-100 p-8 rounded-3xl shadow-sm transition-all duration-300 hover:border-pink-200 hover:shadow-lg hover:-translate-y-1">
                <div class="relative z-10 flex justify-between items-center text-gray-800">
                    <div>
                        <h3 class="text-2xl font-bold italic tracking-tight text-pink-600">Mis Recetas</h3>
                        <p class="text-gray-500">Explora y gestiona tus platos favoritos</p>
                    </div>
                    <div class="bg-pink-50 p-4 rounded-2xl text-3xl group-hover:scale-110 transition-transform">🍳</div>
                </div>
            </a>

            <a href="user/inventario.php" class="group relative overflow-hidden bg-white border-2 border-orange-100 p-8 rounded-3xl shadow-sm transition-all duration-300 hover:border-orange-200 hover:shadow-lg hover:-translate-y-1">
                <div class="relative z-10 flex justify-between items-center text-gray-800">
                    <div>
                        <h3 class="text-2xl font-bold italic tracking-tight text-orange-600">Tu Inventario</h3>
                        <p class="text-gray-500">Gestiona los alimentos de tu despensa</p>
                    </div>
                    <div class="bg-orange-50 p-4 rounded-2xl text-3xl group-hover:scale-110 transition-transform">📦</div>
                </div>
            </a>

        </div>
    </div>
</div>

<!-- DATA BRIDGE: Pasa los datos de PHP a JavaScript -->
<script>
    window.dashboardData = {
        hasProfile: <?php echo ($profileData && ($profileData['calorias_netas_objetivo'] ?? 0) > 0) ? 'true' : 'false'; ?>,
        macros: [
            <?php echo round($profileData['proteinas_objetivo_g'] ?? 0); ?>,
            <?php echo round($profileData['carbohidratos_objetivo_g'] ?? 0); ?>,
            <?php echo round($profileData['grasas_objetivo_g'] ?? 0); ?>
        ],
        progreso: <?php echo $jsProgresoData; ?>
    };
</script>

<?php 
// Incluir el pie de página
include_once __DIR__ . '/templates/footer.php';
?>