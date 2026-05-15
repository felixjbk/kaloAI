<?php
/**
 * LÓGICA DE CONTROL - CHEF IA (KaloAI)
 * Gestiona la generación inteligente de recetas, validación de límites calóricos 
 * y la asignación de platos al plan semanal.
 */

session_start();

// Carga de dependencias centrales
require_once __DIR__ . '/../../core/configuracion.php';
require_once __DIR__ . '/../../core/generar_recetas_ia.php'; // Motor de IA (OpenAI/Gemini)
require_once __DIR__ . '/../../core/utilidades.php';

// 1. SEGURIDAD Y CONTROL DE ACCESO
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$pdo = connectDB(); 

$receta = null;
$errorIA = null;

// 2. CAPTURA DE CONTEXTO (Proveniente del calendario o navegación directa)
// Se identifica para qué día y momento (Desayuno, Almuerzo...) se está trabajando

$diaUrl = $_GET['dia'] ?? $_POST['dia_contexto'] ?? null;
$momentoUrl = $_GET['momento'] ?? $_POST['momento_contexto'] ?? null;

$momentoParaIA = $momentoUrl ?: 'Almuerzo';

// Mapeo para traducir nombres de días a índices de base de datos (0-6)
$diasMapeo = [
    'Lunes' => 0, 'Martes' => 1, 'Miércoles' => 2, 
    'Jueves' => 3, 'Viernes' => 4, 'Sábado' => 5, 'Domingo' => 6
];

/* ==========================================================================
   3. VALIDACIÓN DE LÍMITE CALÓRICO
   Evita que el usuario añada más comida si ya superó su meta diaria (Margen 10%)
   ========================================================================== */
if ($diaUrl) {
    $diaNum = $diasMapeo[$diaUrl] ?? 0;

    // Sumar calorías ya planificadas para el día seleccionado
    $stmtCheck = $pdo->prepare("SELECT SUM(r.calorias_por_racion) as total 
                                FROM comida_planificada cp 
                                JOIN receta r ON cp.id_receta = r.id_receta 
                                JOIN plan_semanal ps ON cp.id_plan = ps.id_plan
                                WHERE ps.id_usuario = ? AND cp.dia_semana = ?");
    $stmtCheck->execute([$userId, $diaNum]);
    $actual = $stmtCheck->fetch();
    $caloriasActuales = $actual['total'] ?? 0;

    // Obtener la meta TDEE (Gasto calórico total) del perfil
    $stmtMeta = $pdo->prepare("SELECT tdee_calorias FROM perfil WHERE id_usuario = ?");
    $stmtMeta->execute([$userId]);
    $perfil = $stmtMeta->fetch();
    $meta = $perfil['tdee_calorias'] ?? 2000;

    // Si excede el 110% de la meta, bloqueamos la generación para proteger el objetivo
    if ($caloriasActuales >= ($meta * 1.1)) {
        header("Location: planificacion_semanal.php?error=limite_superado");
        exit;
    }
}

/* ==========================================================================
   4. PROCESAR SOLICITUD IA
   Llamada al motor de IA con los parámetros de personalización
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar'])) {
    // Modo estricto: Solo ingredientes en la despensa del usuario
    $soloInventario = isset($_POST['modo_generacion']) && $_POST['modo_generacion'] === 'estricto';
    
    // Texto libre del usuario (ej: "Sin lactosa", "Muy picante")
    $sugerencia = $_POST['sugerencia_ia'] ?? ""; 
    
    // Determinar el tipo de comida final
    $momentoFinal = $momentoUrl ?: ($_POST['tipo_comida_manual'] ?? 'Almuerzo');
    
    // Ejecución de la IA
    $resultado = generarRecetaInteligente($userId, $soloInventario, $diaUrl, $momentoFinal, $sugerencia);
    
    if (isset($resultado['error'])) {
        $errorIA = $resultado['error'];
    } else {
        $receta = $resultado; // Array con titulo, ingredientes, instrucciones y macros
    }
}

/* ==========================================================================
   5. BIBLIOTECA DE RECETAS PROPIAS
   Filtra las recetas guardadas del usuario que coinciden con el tipo buscado
   ========================================================================== */
$tipoBuscado = 'Almuerzo';
$momentoParaFiltrar = $momentoUrl ?: ($_POST['tipo_comida_manual'] ?? 'Almuerzo');
$momentoNormalizado = mb_strtolower($momentoParaFiltrar, 'UTF-8'); // Uso de mb_strtolower para tildes

// Normalización estricta: Todo lo relacionado a entre horas es 'Snack'
if (in_array($momentoNormalizado, ['media mañana', 'merienda', 'snack', 'tentempié'])) {
    $tipoBuscado = 'Snack';
} elseif ($momentoNormalizado === 'desayuno') {
    $tipoBuscado = 'Desayuno';
} elseif ($momentoNormalizado === 'cena') {
    $tipoBuscado = 'Cena';
} else {
    $tipoBuscado = 'Almuerzo';
}

$stmtRecetas = $pdo->prepare("SELECT * FROM receta WHERE id_usuario_creador = ? AND tipo_comida = ? ORDER BY titulo ASC");
$stmtRecetas->execute([$userId, $tipoBuscado]);
$misRecetas = $stmtRecetas->fetchAll();

/* ==========================================================================
   6. ASIGNACIÓN DE RECETA EXISTENTE
   Gestiona la transacción para guardar una receta de la biblioteca en el plan
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seleccionar_existente'])) {
    $idRecetaSel = $_POST['id_receta_seleccionada'];
    $diaNombre = $_POST['dia_contexto']; 
    $momentoContexto = $_POST['momento_contexto'];
    $diaNumero = $diasMapeo[$diaNombre] ?? 0; 

    try {
        $pdo->beginTransaction();
        
        // Obtener el plan activo del usuario (o crear uno si no existe)
        $stmtPlan = $pdo->prepare("SELECT id_plan FROM plan_semanal WHERE id_usuario = ? LIMIT 1");
        $stmtPlan->execute([$userId]);
        $plan = $stmtPlan->fetch();
        $idPlan = $plan ? $plan['id_plan'] : null;
        
        if (!$idPlan) {
            $stmtNewPlan = $pdo->prepare("INSERT INTO plan_semanal (id_usuario, nombre_plan) VALUES (?, 'Mi Plan Principal')");
            $stmtNewPlan->execute([$userId]);
            $idPlan = $pdo->lastInsertId();
        }

        if (!empty($idRecetaSel)) {
            // Eliminar cualquier comida previa en ese mismo horario para evitar solapamientos
            $pdo->prepare("DELETE FROM comida_planificada WHERE id_plan = ? AND dia_semana = ? AND momento_comida = ?")
                ->execute([$idPlan, $diaNumero, $momentoContexto]);

            // Insertar la nueva selección
            $pdo->prepare("INSERT INTO comida_planificada (id_plan, id_receta, dia_semana, momento_comida, raciones) VALUES (?, ?, ?, ?, 1)")
                ->execute([$idPlan, $idRecetaSel, $diaNumero, $momentoContexto]);
            
            $pdo->commit();
            header("Location: planificacion_semanal.php?success=assigned");
            exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errorIA = "Error al asignar: " . $e->getMessage();
    }
}

// INICIO DE LA VISTA (FRONTEND)
$pageTitle = "KaloAI | Generador de Recetas Inteligente";

// Carga del componente de cabecera 
include_once __DIR__ . '/../templates/header.php';
?>

<!-- CONTENEDOR PRINCIPAL: Ajustado para la interfaz del generador de recetas -->
<div class="container mx-auto px-4 py-8">
    
    <!-- CABECERA: Navegación contextual (vuelve a la planificación o a la biblioteca) -->
    <div class="flex items-center group">
        <a href="<?= $diaUrl ? 'planificacion_semanal.php' : 'mis_recetas.php' ?>" class="mr-4 bg-teal-50 p-3 rounded-2xl text-teal-600 hover:bg-teal-600 hover:text-white transition-all shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <h1 class="text-5xl font-black text-gray-900 italic tracking-tight">
            Chef <span class="text-teal-600">IA</span>
        </h1>
    </div>
    
    <!-- SUBTÍTULO DINÁMICO: Indica si estamos planificando un día específico del calendario -->
    <?php if($diaUrl && $momentoUrl): ?>
        <p class="text-gray-500 font-medium mt-4 ml-16 flex items-center gap-2 pb-8" >
            <span class="w-2 h-2 bg-teal-500 rounded-full animate-pulse"></span>
            Planificando <span class="text-teal-600 font-bold"><?= htmlspecialchars($momentoUrl) ?></span> para el <span class="font-bold text-gray-800"><?= htmlspecialchars($diaUrl) ?></span>
        </p>
    <?php else: ?>
        <p class="text-gray-500 font-medium mt-4 ml-16 pb-8">Crea recetas inteligentes basadas en tus metas.</p>
    <?php endif; ?>

    <!-- GESTIÓN DE ERRORES: Feedback visual si la API de IA falla -->
    <?php if ($errorIA): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-8 rounded-r-2xl animate-fade-in">
            <p class="text-red-700 text-xs font-bold uppercase tracking-wide">⚠️ <?= htmlspecialchars($errorIA) ?></p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 gap-10">
        
        <!-- SECCIÓN 1: Formulario de Configuración para la IA -->
        <section class="bg-white p-8 md:p-10 rounded-[2.5rem] shadow-sm border border-gray-100 relative">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 bg-teal-50 rounded-2xl flex items-center justify-center text-2xl shadow-inner">✨</div>
                <div>
                    <h2 class="text-xl font-black text-gray-800 italic">Configura tu receta</h2>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">KaloAI optimizará la receta según tus metas</p>
                </div>
            </div>

            <form id="formGeneradorIA" method="POST" class="space-y-8">
                <!-- Inputs ocultos para mantener el contexto del calendario si existe -->
                <input type="hidden" name="dia_contexto" value="<?= htmlspecialchars($diaUrl) ?>">
                <input type="hidden" name="momento_contexto" value="<?= htmlspecialchars($momentoUrl) ?>">

                <!-- SELECTOR DE MOMENTO: Solo aparece si el usuario entró libremente (no desde el calendario) -->
                <?php if (!$momentoUrl): ?>
                    <div class="animate-fade-in">
                        <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4 ml-2">¿Qué quieres cocinar?</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <?php 
                            $opciones = ['Desayuno' => '🍳', 'Almuerzo' => '🍱', 'Snack' => '🥪', 'Cena' => '🌙'];
                            foreach ($opciones as $nombre => $emoji): ?>
                                <label class="cursor-pointer group">
                                    <input type="radio" name="tipo_comida_manual" value="<?= $nombre ?>" class="hidden peer" <?= ($momentoParaFiltrar === $nombre) ? 'checked' : '' ?>>
                                    <div class="p-4 bg-gray-50 border-2 border-transparent rounded-2xl text-center transition-all peer-checked:bg-teal-50 peer-checked:border-teal-500 group-hover:bg-gray-100">
                                        <span class="block text-xl mb-1"><?= $emoji ?></span>
                                        <span class="block text-[10px] font-black uppercase text-gray-500 peer-checked:text-teal-700"><?= $nombre ?></span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="h-px bg-gray-100 w-full my-6"></div>
                <?php endif; ?>

                <!-- CAMPO DE TEXTO: Sugerencia abierta para la IA (Prompt Engineering) -->
                <div class="animate-fade-in">
                    <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4 ml-2">¿Algún antojo o idea especial? (Opcional)</h3>
                    <div class="relative group">
                        <textarea 
                            name="sugerencia_ia" 
                            placeholder="Ej: 'Quiero algo con pollo y muy picante' o 'Hazme una lasaña saludable'..."
                            class="w-full bg-gray-50 border-2 border-gray-100 rounded-3xl p-5 text-sm font-bold text-gray-700 focus:bg-white focus:border-teal-500 focus:ring-0 transition-all resize-none outline-none group-hover:border-gray-200"
                            rows="3"
                        ></textarea>
                        <div class="absolute right-4 bottom-4 text-xs font-black text-teal-600/30 group-focus-within:text-teal-500 transition-colors uppercase italic tracking-tighter">
                            Opcional
                        </div>
                    </div>
                </div>

                <!-- MODOS DE GENERACIÓN: Controla si la IA usa solo el stock actual o es libre -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="relative cursor-pointer group">
                        <input type="radio" name="modo_generacion" value="estricto" class="hidden peer">
                        <div class="p-6 bg-gray-50 border-2 border-transparent rounded-[2rem] transition-all peer-checked:bg-white peer-checked:border-teal-500 peer-checked:shadow-lg">
                            <div class="flex items-center gap-3 mb-1">
                                <span class="text-lg">🏠</span>
                                <span class="font-black text-gray-900 italic text-sm tracking-tight">Solo mi Inventario</span>
                            </div>
                            <span class="block text-[10px] text-gray-400 font-bold uppercase ml-7">Usa lo que ya tienes</span>
                        </div>
                    </label>

                    <label class="relative cursor-pointer group">
                        <input type="radio" name="modo_generacion" value="flexible" class="hidden peer" checked>
                        <div class="p-6 bg-gray-50 border-2 border-transparent rounded-[2rem] transition-all peer-checked:bg-white peer-checked:border-teal-500 peer-checked:shadow-lg">
                            <div class="flex items-center gap-3 mb-1">
                                <span class="text-lg">🛒</span>
                                <span class="font-black text-gray-900 italic text-sm tracking-tight">Lo que quieras</span>
                            </div>
                            <span class="block text-[10px] text-gray-400 font-bold uppercase ml-7">Sugerir ingredientes externos</span>
                        </div>
                    </label>
                </div>

                <button type="submit" name="generar" class="w-full bg-teal-600 text-white font-black py-5 rounded-3xl shadow-xl shadow-teal-100 hover:bg-teal-700 hover:-translate-y-1 transition-all flex items-center justify-center gap-4 text-sm uppercase tracking-[0.2em]">
                    <span>✨</span> Generar Receta con IA
                </button>
            </form>
        </section>

        <!-- MODOS DE GENERACIÓN: Controla si la IA usa solo el stock actual o es libre -->
        <?php if ($receta): ?>
            <!-- Bloque de visualización de la receta generada -->
            <section class="animate-fade-in bg-white rounded-[2.5rem] shadow-xl border-2 border-teal-500/20 overflow-hidden">
                <div class="bg-teal-600 p-8 text-white relative">
                    <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.3em] opacity-80 mb-2">Sugerencia Inteligente</p>
                            <h2 class="text-4xl font-black italic tracking-tighter"><?= htmlspecialchars($receta['titulo']) ?></h2>
                        </div>
                        <!-- Macros calculados por la IA -->
                        <div class="flex gap-4">
                            <div class="bg-white/10 backdrop-blur-md px-5 py-3 rounded-2xl border border-white/10 text-center">
                                <span class="block text-[9px] font-black uppercase opacity-60">Kcal</span>
                                <span class="text-xl font-black"><?= $receta['calorias'] ?></span>
                            </div>
                            <div class="bg-white/10 backdrop-blur-md px-5 py-3 rounded-2xl border border-white/10 text-center">
                                <span class="block text-[9px] font-black uppercase opacity-60">Proteína</span>
                                <span class="text-xl font-black"><?= $receta['macros']['proteinas'] ?>g</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detalle de la Receta -->
                <div class="p-8 md:p-12 grid grid-cols-1 md:grid-cols-2 gap-12">
                    <!-- Columna: Ingredientes -->
                    <div>
                        <h3 class="text-xs font-black text-gray-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                            <span class="w-2 h-2 bg-teal-500 rounded-full"></span> Ingredientes
                        </h3>
                        <div class="mb-4 p-3 bg-blue-500/10 border border-blue-500/20 rounded-xl">
                            <p class="text-xs text-blue-800">
                                💡 <b>Nota de KaloAI:</b> Esta receta está calculada para <b>1 ración</b> para cumplir tus macros. 
                                Si cocinas para más personas, multiplica las cantidades.
                            </p>
                        </div>
                        <div class="space-y-2">
                            <?php foreach ($receta['ingredientes_usados'] as $ing): ?>
                                <div class="p-4 bg-gray-50 rounded-2xl text-sm border border-gray-100 flex justify-between">
                                    <span class="text-gray-500 font-bold italic"><?= htmlspecialchars($ing['nombre']) ?></span>
                                    <span class="font-black text-gray-800 tracking-tighter"><?= $ing['cantidad'] ?> <?= $ing['unidad'] ?? '' ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Columna: Pasos de Preparación -->
                    <div>
                        <h3 class="text-xs font-black text-gray-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                            <span class="w-2 h-2 bg-orange-400 rounded-full"></span> Preparación
                        </h3>
                        <div class="space-y-6">
                            <?php foreach ($receta['instrucciones'] as $i => $paso): ?>
                                <div class="flex gap-4 group">
                                    <span class="flex-shrink-0 w-8 h-8 bg-teal-50 text-teal-600 rounded-full flex items-center justify-center text-xs font-black shadow-sm group-hover:bg-teal-600 group-hover:text-white transition-all"><?= $i+1 ?></span>
                                    <p class="text-sm text-gray-600 leading-relaxed font-bold italic pt-1"><?= htmlspecialchars($paso) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Formulario de Guardado: Envía el JSON de la IA a la base de datos -->
                <div class="p-8 bg-gray-50 border-t border-gray-100">
                    <form method="POST" action="guardar_receta_ia.php">
                        <?php 
                        $json_seguro = json_encode($receta);
                        $json_seguro = str_replace(["\r", "\n"], '\n', $json_seguro);
                        
                        // CAPTURA LITERAL: Tomamos lo que viene de la URL o del manual sin filtros
                        $momentoParaGuardar = $momentoUrl ?: ($_POST['tipo_comida_manual'] ?? 'Almuerzo');
                        ?>

                        <input type="hidden" name="receta_data" value='<?= htmlspecialchars($json_seguro, ENT_QUOTES, "UTF-8") ?>'>
                        <input type="hidden" name="dia_contexto" value="<?= htmlspecialchars($diaUrl ?? '') ?>">
                        
                        <!-- Enviamos el valor literal (ej: "Media Mañana") para que coincida con tu URL -->
                        <input type="hidden" name="momento_contexto" value="<?= htmlspecialchars($momentoParaGuardar) ?>">
                        
                        <button type="submit" name="save_recipe" class="w-full bg-gray-900 text-white font-black py-5 rounded-2xl shadow-xl hover:bg-black transition-all flex items-center justify-center gap-3 uppercase text-xs tracking-widest">
                            💾 <?= ($diaUrl) ? 'Guardar y añadir al Plan' : 'Guardar en Mis Recetas' ?>
                        </button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <!-- SECCIÓN 3: Biblioteca Rápida (Permite reusar recetas existentes en lugar de generar) -->
        <section class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100">
            <h2 class="text-xs font-black text-gray-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-2">
                <span class="w-2 h-2 bg-gray-200 rounded-full"></span>
                Tus <?= $tipoBuscado ?>s Guardados
            </h2>
            
            <?php if (empty($misRecetas)): ?>
                <div class="text-center py-12 bg-gray-50 rounded-[2rem] border border-dashed border-gray-200">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">No hay recetas de este tipo guardadas</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                    <?php foreach ($misRecetas as $r): ?>
                        <div class="p-5 bg-white border border-gray-100 rounded-[1.5rem] hover:border-teal-500 hover:shadow-lg hover:shadow-teal-50 transition-all flex justify-between items-center group">
                            <div>
                                <h4 class="font-black text-gray-800 text-sm italic mb-1 tracking-tight"><?= htmlspecialchars($r['titulo']) ?></h4>
                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">
                                    <span class="text-teal-600 font-black italic">🔥 <?= $r['calorias_por_racion'] ?> kcal</span> • 💪 <?= $r['proteinas_g_por_racion'] ?>g Proteína
                                </p>
                            </div>
                            <?php if($diaUrl): ?>
                                <form method="POST">
                                    <input type="hidden" name="id_receta_seleccionada" value="<?= $r['id_receta'] ?>">
                                    <input type="hidden" name="dia_contexto" value="<?= htmlspecialchars($diaUrl) ?>">
                                    <input type="hidden" name="momento_contexto" value="<?= htmlspecialchars($momentoUrl) ?>">
                                    <button type="submit" name="seleccionar_existente" class="bg-gray-50 text-gray-400 group-hover:bg-teal-600 group-hover:text-white p-3 rounded-xl transition-all shadow-sm">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="mt-16 text-center">
            <a href="../dashboard.php" class="inline-flex items-center font-black text-xs uppercase tracking-[0.2em] text-gray-400 hover:text-teal-600 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path></svg>
                Volver al Panel
            </a>
        </div>
    </div>
</div>


<!-- OVERLAY DE CARGA: Feedback visual mientras la IA procesa el Prompt (Llamada a la API) -->
<div id="loadingOverlay" class="fixed inset-0 bg-white/90 backdrop-blur-md z-[100] hidden flex flex-col items-center justify-center">
    <div class="relative w-20 h-20 mb-8">
        <div class="absolute inset-0 border-4 border-teal-50 rounded-full"></div>
        <div class="absolute inset-0 border-4 border-teal-500 rounded-full border-t-transparent animate-spin"></div>
    </div>
    <h3 class="text-sm font-black text-gray-900 uppercase tracking-[0.3em] animate-pulse">KaloAI está pensando...</h3>
    <p id="loadingMessage" class="mt-4 text-[9px] font-bold text-teal-600 uppercase tracking-widest opacity-60"></p>
</div>

<?php 
// Incluir el pie de página
include_once __DIR__ . '/../templates/footer.php'; 
?>