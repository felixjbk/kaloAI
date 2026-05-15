<?php
/**
 * GESTOR DE PLANIFICACIÓN SEMANAL - KaloAI
 * Este módulo actúa como el panel de control donde se visualiza el calendario semanal,
 * se calculan los totales diarios y se comparan con la meta del usuario.
 */
$pageTitle = "KaloAI | Mi Plan Semanal";

session_start();
require_once __DIR__ . '/../../core/configuracion.php';
require_once __DIR__ . '/../../core/utilidades.php'; 

// SEGURIDAD: Validación de estado de sesión para evitar acceso a calendarios ajenos
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../auth/login.php"); 
    exit; 
}

$userId = $_SESSION['user_id'];
$pdo = connectDB();

/**
 * 1. LÓGICA DE ELIMINACIÓN (Mantenimiento del Plan)
 * Permite al usuario retirar recetas específicas de su calendario.
 * Se incluye un JOIN de seguridad para asegurar que el registro pertenece al usuario actual.
 */
if (isset($_GET['eliminar_id'])) {
    $idComida = intval($_GET['eliminar_id']);
    try {
        $sqlDelete = "DELETE cp FROM comida_planificada cp 
                      JOIN plan_semanal ps ON cp.id_plan = ps.id_plan 
                      WHERE cp.id_comida_planificada = ? AND ps.id_usuario = ?";
        $stmtDel = $pdo->prepare($sqlDelete);
        $stmtDel->execute([$idComida, $userId]);
        
        // Redirección limpia para evitar reenvíos de formulario
        header("Location: planificacion_semanal.php?deleted=1");
        exit;
    } catch (Exception $e) { 
        $error = "No se pudo eliminar la comida del plan."; 
    }
}

/**
 * 2. OBTENER OBJETIVO CALÓRICO Y MACROS (Referencia Nutricional)
 * Recupera el TDEE (Gasto Energético Total) calculado en el perfil.
 */
$stmtPerfil = $pdo->prepare("SELECT tdee_calorias FROM perfil WHERE id_usuario = ?");
$stmtPerfil->execute([$userId]);
$perfil = $stmtPerfil->fetch();

// Meta por defecto si el perfil no está completo (2000 kcal como estándar de salud)
$metaKcal = $perfil['tdee_calorias'] ?? 2000;

/**
 * CÁLCULO DE MACROS OBJETIVO:
 * Reparto estándar para mantenimiento: 30% Prot, 40% Carb, 30% Grasas.
 * Nota: El divisor (4 o 9) corresponde a las kcal por gramo de cada macronutriente.
 */
$metaP = ($metaKcal * 0.30) / 4;
$metaC = ($metaKcal * 0.40) / 4;
$metaG = ($metaKcal * 0.30) / 9;

/**
 * 3. OBTENCIÓN Y ESTRUCTURACIÓN DEL PLAN (Motor de Cómputo)
 * Consulta las recetas asignadas a cada día y momento del día (Desayuno, Cena, etc.).
 */
$sql = "SELECT cp.*, r.*, 
               CASE cp.dia_semana 
                    WHEN 0 THEN 'Lunes' WHEN 1 THEN 'Martes' WHEN 2 THEN 'Miércoles' 
                    WHEN 3 THEN 'Jueves' WHEN 4 THEN 'Viernes' WHEN 5 THEN 'Sábado' WHEN 6 THEN 'Domingo' 
               END as dia_nombre
        FROM comida_planificada cp
        JOIN receta r ON cp.id_receta = r.id_receta
        JOIN plan_semanal ps ON cp.id_plan = ps.id_plan
        WHERE ps.id_usuario = ?"; 

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$comidas = $stmt->fetchAll();

// Matrices para organizar la visualización y el sumatorio acumulado
$plan = [];
$totalesDia = [];

foreach ($comidas as $c) {
    $dia = $c['dia_nombre'];
    $momento = trim($c['momento_comida']); 
    
    // Agrupamos por [Día][Momento] para el renderizado del grid en el frontend
    $plan[$dia][$momento] = $c;
    
    /**
     * ACUMULADOR NUTRICIONAL:
     * Suma las calorías y gramos de cada receta para dar un total diario.
     */
    if(!isset($totalesDia[$dia])) {
        $totalesDia[$dia] = ['kcal'=>0, 'p'=>0, 'c'=>0, 'g'=>0];
    }
    $totalesDia[$dia]['kcal'] += $c['calorias_por_racion'];
    $totalesDia[$dia]['p'] += $c['proteinas_g_por_racion'];
    $totalesDia[$dia]['c'] += $c['carbohidratos_g_por_racion'];
    $totalesDia[$dia]['g'] += $c['grasas_g_por_racion'];
}

// Estructuras de control para la generación de la tabla/interfaz
$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$todasComidas = ['Desayuno', 'Media Mañana', 'Almuerzo', 'Merienda', 'Cena'];

// Carga del componente de cabecera 
include_once __DIR__ . '/../templates/header.php';
?>

<div class="container mx-auto px-4 sm:px-6 py-12 max-w-[1600px]">
    
    <!-- HEADER DEL PLAN: Resumen de objetivos semanales -->
    <div class="flex flex-col lg:flex-row lg:items-end justify-between mb-12 gap-8 bg-white p-10 rounded-[2.5rem] shadow-sm border border-gray-50">
        <div>
            <div class="flex items-center group mb-4">
                <a href="../dashboard.php" class="mr-5 bg-teal-50 p-3 rounded-2xl text-teal-600 hover:bg-teal-600 hover:text-white transition-all shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h1 class="text-5xl font-black text-gray-900 italic tracking-tight">
                    Plan <span class="text-teal-600">Semanal</span>
                </h1>
            </div>
            <p class="text-gray-400 font-bold ml-16 uppercase text-xs tracking-[0.2em]">Tu estrategia nutricional para los próximos 7 días</p>
        </div>

        <!-- INDICADORES DE META: Los valores objetivo calculados en el perfil -->
        <div class="flex flex-wrap gap-3 items-center">
            <!-- Kcal Objetivo -->
            <div class="bg-gray-50 px-6 py-4 rounded-3xl border border-gray-100 text-center min-w-[100px]">
                <p class="text-[10px] font-black text-teal-500 uppercase tracking-widest mb-1">Meta Kcal</p>
                <p class="text-2xl font-black text-gray-800 italic"><?= round($metaKcal) ?></p>
            </div>
            <div class="h-10 w-[1px] bg-gray-100 mx-2 hidden xl:block"></div>
            <!-- Kcal Objetivo -->
            <div class="flex gap-2">
                <div class="bg-blue-50/50 px-5 py-3 rounded-2xl border border-blue-100/50 text-center">
                    <p class="text-[9px] font-black text-blue-400 uppercase mb-1">Proteína</p>
                    <p class="font-bold text-blue-700"><?= round($metaP) ?>g</p>
                </div>
                <div class="bg-orange-50/50 px-5 py-3 rounded-2xl border border-orange-100/50 text-center">
                    <p class="text-[9px] font-black text-orange-400 uppercase mb-1">Carbos</p>
                    <p class="font-bold text-orange-700"><?= round($metaC) ?>g</p>
                </div>
                <div class="bg-red-50/50 px-5 py-3 rounded-2xl border border-red-100/50 text-center">
                    <p class="text-[9px] font-black text-red-400 uppercase mb-1">Grasas</p>
                    <p class="font-bold text-red-700"><?= round($metaG) ?>g</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Kcal Objetivo -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-5">
        <?php foreach ($dias as $dia): 
            $t = $totalesDia[$dia] ?? ['kcal'=>0,'p'=>0,'c'=>0,'g'=>0];
            $pct = ($t['kcal'] / $metaKcal) * 100;
            $excesoCritico = $pct > 110; 
        ?>
            <div class="bg-white rounded-[2rem] shadow-sm border transition-all duration-300 <?= $excesoCritico ? 'border-red-100 ring-4 ring-red-50/50' : 'border-gray-50 hover:border-teal-100' ?> flex flex-col min-h-[550px] overflow-hidden">
                
                <!-- CABECERA DE DÍA: Barra de progreso de calorías -->
                <div class="p-5 border-b border-gray-50 <?= $excesoCritico ? 'bg-red-50/50' : 'bg-gray-50/30' ?> cursor-pointer hover:bg-white transition-colors" 
                     onclick='abrirResumenDia("<?= $dia ?>", <?= json_encode($t) ?>)'>
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-black text-gray-900 italic text-lg capitalize"><?= $dia ?></h3>
                        <div class="h-6 w-6 rounded-full bg-white flex items-center justify-center shadow-sm">
                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
                        </div>
                    </div>
                    
                    <!-- Barra de progreso -->
                    <div class="w-full bg-gray-200/50 h-2 rounded-full overflow-hidden">
                        <div class="<?= $excesoCritico ? 'bg-red-500' : 'bg-teal-500' ?> h-full transition-all duration-1000" style="width: <?= min($pct, 100) ?>%"></div>
                    </div>
                    <div class="flex justify-between mt-2">
                        <span class="text-[10px] font-black <?= $excesoCritico ? 'text-red-600' : 'text-teal-600' ?>"><?= round($pct) ?>%</span>
                        <span class="text-[10px] font-bold text-gray-400 tracking-tighter italic"><?= round($t['kcal']) ?> KCAL</span>
                    </div>
                </div>

                <!-- CONTENEDOR DE COMIDAS: Muestra Desayuno, Almuerzo, etc. -->
                <div class="p-4 space-y-4 flex-1">
                    <?php foreach ($todasComidas as $m): ?>
                        <?php if (isset($plan[$dia][$m])): $c = $plan[$dia][$m]; ?>
                            <!-- TARJETA DE RECETA: Si la comida ya está planificada -->
                            <div onclick='abrirReceta(<?= json_encode($c) ?>)' 
                                 class="p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md hover:border-teal-100 transition-all cursor-pointer group relative overflow-hidden">
                                <div class="absolute left-0 top-0 w-1 h-full bg-teal-500 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-[8px] font-black text-teal-400 uppercase tracking-widest"><?= $m ?></span>
                                    <!-- Botón Eliminar: event.stopPropagation() evita que se abra el modal de receta al querer borrar -->
                                    <button type="button" 
                                            onclick="event.stopPropagation(); confirmarEliminarComida(<?= $c['id_comida_planificada'] ?>)" 
                                            class="text-gray-300 hover:text-red-500 transition-colors p-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                                <p class="text-sm font-black text-gray-800 leading-tight mb-2 group-hover:text-teal-700 transition-colors"><?= $c['titulo'] ?></p>
                                <div class="flex items-center text-[10px] font-bold text-gray-400 italic">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    <?= round($c['calorias_por_racion']) ?> kcal
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- BOTÓN PARA AÑADIR: Si el hueco está vacío -->
                            <?php if ($excesoCritico): ?>
                                <!-- Bloqueo por exceso: No permite añadir más comida si superas el límite -->
                                <div class="p-5 border-2 border-dashed border-gray-50 rounded-2xl flex items-center justify-center bg-gray-50/20">
                                    <span class="text-[10px] font-black text-gray-300 uppercase tracking-widest italic">Límite kcal</span>
                                </div>
                            <?php else: ?>
                                <a href="generar_receta.php?dia=<?= $dia ?>&momento=<?= $m ?>" 
                                   class="p-5 border-2 border-dashed border-gray-100 rounded-2xl flex flex-col items-center justify-center hover:bg-teal-50/50 hover:border-teal-200 transition-all group border-spacing-4">
                                    <span class="text-[9px] font-black text-gray-400 group-hover:text-teal-600 uppercase tracking-widest mb-1"><?= $m ?></span>
                                    <div class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-300 group-hover:bg-teal-600 group-hover:text-white transition-all shadow-sm">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                                    </div>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-20 text-center pb-10">
        <a href="../dashboard.php" class="inline-flex items-center font-black text-xs uppercase tracking-[0.2em] text-gray-400 hover:text-teal-600 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path></svg>
            Volver al Panel
        </a>
    </div>
</div>

<!-- MODAL: DETALLE DE RECETA -->
<!-- Se activa al hacer clic en una comida ya planificada. -->
<div id="modalReceta" class="fixed inset-0 bg-gray-900/60 backdrop-blur-md z-50 hidden flex items-center justify-center p-4 transition-all duration-300">
    <div class="bg-white w-full max-w-lg rounded-[2.5rem] overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.2)] animate-fade-in border border-white/20">
        
        <div class="p-8 text-white bg-gradient-to-br from-teal-500 to-teal-700 flex justify-between items-start relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
            
            <div class="relative z-10">
                <p id="mMom" class="text-[10px] uppercase font-black tracking-[0.2em] opacity-80 mb-2"></p>
                <h2 id="mTit" class="text-3xl font-black italic leading-tight tracking-tight"></h2>
            </div>
            <button onclick="cerrarModalPlan('modalReceta')" class="relative z-10 bg-black/10 hover:bg-black/20 w-10 h-10 rounded-full flex items-center justify-center transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div class="p-8 space-y-8">
            <!-- Grid para Macros: Se rellena dinámicamente mediante JS (Kcal, P, C, G) -->
            <div id="mMacros" class="grid grid-cols-4 gap-3">
                </div>

            <div class="space-y-4">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    </div>
                    <p class="font-black text-sm uppercase tracking-widest text-gray-800">Preparación</p>
                </div>
                
                <!-- Área de texto de preparación con scroll personalizado -->
                <div class="bg-gray-50/50 rounded-3xl p-6 border border-gray-100">
                    <div class="max-h-60 overflow-y-auto custom-scrollbar pr-2">
                        <p id="mDesc" class="text-gray-600 text-sm whitespace-pre-line leading-relaxed italic"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: RESUMEN DIARIO -->
<!-- Se activa al hacer clic en la cabecera de un día. Muestra el progreso total frente a la meta diaria. -->
<div id="modalDia" class="fixed inset-0 bg-gray-900/60 backdrop-blur-md z-50 hidden flex items-center justify-center p-4 transition-all duration-300">
    <div class="bg-white w-full max-w-md rounded-[2.5rem] p-10 shadow-[0_20px_50px_rgba(0,0,0,0.2)] relative border border-gray-50">
        
        <button onclick="cerrarModalPlan('modalDia')" class="absolute top-8 right-8 text-gray-400 hover:text-gray-600 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div class="mb-10">
            <p class="text-[10px] font-black text-teal-500 uppercase tracking-[0.3em] mb-1 text-center">Resumen Nutricional</p>
            <h2 id="mDiaTit" class="text-4xl font-black text-gray-900 text-center italic capitalize tracking-tight"></h2>
        </div>
        
        <div class="space-y-10">
            <!-- Barra de progreso calórico detallada -->
            <div class="bg-gray-50 rounded-[2rem] p-6 border border-gray-100">
                <div class="flex justify-between items-end mb-4 px-2">
                    <span class="text-xs font-black text-gray-400 uppercase tracking-widest">Calorías Totales</span>
                    <span id="mDiaKcalText" class="text-xl font-black text-gray-800 italic"></span>
                </div>
                <div class="w-full bg-gray-200/50 h-4 rounded-full overflow-hidden p-1">
                    <div id="mDiaKcalBar" class="h-full bg-teal-500 rounded-full shadow-[0_0_15px_rgba(20,184,166,0.4)] transition-all duration-1000"></div>
                </div>
            </div>

            <!-- Lista de Macros: Se generan barras de progreso individuales para P, C y G aquí -->
            <div class="space-y-2 px-2">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Distribución de Macronutrientes</p>
                <div id="mDiaMacrosList" class="grid grid-cols-1 gap-4">
                    </div>
            </div>
        </div>

        <div class="mt-10 pt-6 border-t border-gray-50 text-center">
            <p class="text-[10px] text-gray-400 font-bold italic">Basado en tus objetivos diarios configurados</p>
        </div>
    </div>
</div>

<!-- MODAL: CONFIRMAR ELIMINACIÓN -->
<!-- Capa extra de seguridad para evitar borrados accidentales.-->
<div id="modalEliminar" class="fixed inset-0 bg-gray-900/60 backdrop-blur-md z-[60] hidden flex items-center justify-center p-4 transition-all duration-300">
    <div class="bg-white w-full max-w-sm rounded-[2.5rem] p-10 shadow-[0_20px_50px_rgba(0,0,0,0.3)] border border-gray-50 animate-fade-in text-center">
        <!-- Icono de advertencia -->
        <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
        </div>

        <h3 class="text-2xl font-black text-gray-900 italic mb-2 tracking-tight">¿Quitar receta?</h3>
        <p class="text-sm text-gray-400 font-bold mb-10 uppercase tracking-widest leading-relaxed">Esta acción liberará el espacio en tu plan para una nueva opción.</p>

        <!-- El 'href' de este botón se cambia dinámicamente vía JS según la ID de la comida -->
        <div class="flex flex-col gap-3">
            <a id="btnConfirmarEliminar" href="#" class="w-full py-4 bg-red-500 hover:bg-red-600 text-white rounded-2xl font-black uppercase text-xs tracking-[0.2em] transition-all shadow-lg shadow-red-200">
                Confirmar y Quitar
            </a>
            <button onclick="cerrarModalPlan('modalEliminar')" class="w-full py-4 bg-gray-50 hover:bg-gray-100 text-gray-400 rounded-2xl font-black uppercase text-xs tracking-[0.2em] transition-all">
                Cancelar
            </button>
        </div>
    </div>
</div>

<!-- DATA BRIDGE: Pasa los datos de PHP a JavaScript -->
<script>
    window.planData = {
        meta: {
            kcal: <?= (float)$metaKcal ?>,
            p: <?= (float)$metaP ?>,
            c: <?= (float)$metaC ?>,
            g: <?= (float)$metaG ?>
        }
    };
</script>

<?php 
// Incluir el pie de página
include_once __DIR__ . '/../templates/footer.php'; 
?>