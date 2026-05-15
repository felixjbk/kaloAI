<?php
/**
 * VISTA: DETALLE DE RECETA - KaloAI
 * Muestra toda la información de una receta específica: pasos, macros y metadatos.
 * Incluye un sistema dinámico de estilos por categoría y gestión de borrado seguro.
 */

$pageTitle = "KaloAI | Detalle de Receta";
session_start();
require_once __DIR__ . '/../../core/utilidades.php';
require_once __DIR__ . '/../../core/configuracion.php';

// Control de acceso: Solo usuarios registrados
if (!isset($_SESSION['user_id'])) { redirect('auth/login.php'); }

$pdo = connectDB();
$recetaId = $_GET['id'] ?? null;
$receta = null;

/* ==========================================================================
   1. CARGA DE DATOS DESDE LA BD
   ========================================================================== */
if ($recetaId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM receta WHERE id_receta = ?");
        $stmt->execute([$recetaId]);
        $receta = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Error al cargar detalle de receta: " . $e->getMessage());
    }
}

// Redirección de seguridad si la receta no existe o el ID es inválido
if (!$receta) {
    header("Location: mis_recetas.php");
    exit;
}

/**
 * MOTOR DE ESTILOS DINÁMICOS
 * Devuelve un mapa de clases de Tailwind según el tipo de comida.
 * Esto asegura coherencia visual en toda la aplicación.
 */
function getEstiloReceta($tipo) {
    $tipo = mb_strtolower($tipo);
    switch ($tipo) {
        case 'desayuno': 
            return ['icon' => '☕', 'bg' => 'bg-orange-50', 'bg_intense' => 'bg-orange-100', 'badge' => 'bg-orange-600', 'text' => 'text-orange-600', 'steps' => 'bg-orange-200 text-orange-700', 'step_hover' => 'group-hover:bg-orange-600'];
        case 'almuerzo':
        case 'comida': 
            return ['icon' => '☀️', 'bg' => 'bg-amber-50', 'bg_intense' => 'bg-amber-100', 'badge' => 'bg-amber-600', 'text' => 'text-amber-600', 'steps' => 'bg-amber-200 text-amber-700', 'step_hover' => 'group-hover:bg-amber-600'];
        case 'cena': 
            return ['icon' => '🌙', 'bg' => 'bg-indigo-50', 'bg_intense' => 'bg-indigo-100', 'badge' => 'bg-indigo-600', 'text' => 'text-indigo-600', 'steps' => 'bg-indigo-200 text-indigo-700', 'step_hover' => 'group-hover:bg-indigo-600'];
        case 'snack':
        case 'merienda': 
            return ['icon' => '🍏', 'bg' => 'bg-lime-50', 'bg_intense' => 'bg-lime-100', 'badge' => 'bg-lime-600', 'text' => 'text-lime-600', 'steps' => 'bg-lime-200 text-lime-700', 'step_hover' => 'group-hover:bg-lime-600'];
        default: 
            return ['icon' => '🍳', 'bg' => 'bg-teal-50', 'bg_intense' => 'bg-teal-100', 'badge' => 'bg-teal-600', 'text' => 'text-teal-600', 'steps' => 'bg-teal-200 text-teal-700', 'step_hover' => 'group-hover:bg-teal-600'];
    }
}

$estilo = getEstiloReceta($receta['tipo_comida']);

// Carga del componente de cabecera 
include_once __DIR__ . '/../templates/header.php';
?>

<div class="container mx-auto px-4 py-8">

    <!-- NAVEGACIÓN: Botón de retorno con efecto hover interactivo -->
    <a href="mis_recetas.php" class="inline-flex items-center text-teal-600 font-bold mb-8 hover:text-teal-800 transition-colors group">
        <svg class="w-5 h-5 mr-2 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Volver a Mis Recetas
    </a>

    <div class="bg-white rounded-[3rem] shadow-xl overflow-hidden border border-gray-100">
        <div class="grid grid-cols-1 lg:grid-cols-2">
            
            <!-- COLUMNA IZQUIERDA: Presentación y Pasos de Preparación -->
            <div class="<?php echo $estilo['bg']; ?> p-12 flex flex-col justify-center items-center">
                <span class="text-9xl mb-6 drop-shadow-sm"><?php echo $estilo['icon']; ?></span>
                
                <span class="<?php echo $estilo['badge']; ?> text-white px-6 py-2 rounded-2xl font-black italic tracking-tighter uppercase text-sm mb-6 shadow-md">
                    <?php echo htmlspecialchars($receta['tipo_comida']); ?>
                </span>
                
                <h1 class="text-4xl md:text-5xl font-black text-gray-900 mb-8 italic tracking-tight leading-tight text-center">
                    <?php echo htmlspecialchars($receta['titulo']); ?>
                </h1>

                <!-- LÓGICA DE PREPARACIÓN: Procesa el texto plano separándolo por puntos para crear una lista numerada -->
                <div class="max-w-md w-full text-left bg-white/40 backdrop-blur-sm p-8 rounded-[2rem] border border-white/50">
                    <h3 class="<?php echo $estilo['text']; ?> font-black italic uppercase tracking-widest text-sm mb-6 flex items-center">
                        <span class="mr-2">📝</span> Preparación paso a paso:
                    </h3>
                    <ul class="space-y-6">
                        <?php 
                        $pasos = explode('.', $receta['descripcion']); 
                        $count = 1;
                        foreach ($pasos as $paso): 
                            $pasoLimpio = trim($paso);
                            if (!empty($pasoLimpio)): ?>
                                <li class="flex items-start group">
                                    <span class="flex-shrink-0 w-8 h-8 <?php echo $estilo['steps']; ?> rounded-full flex items-center justify-center font-bold text-sm mr-4 <?php echo $estilo['step_hover']; ?> group-hover:text-white transition-all shadow-sm">
                                        <?php echo $count++; ?>
                                    </span>
                                    <p class="text-gray-700 text-md leading-relaxed">
                                        <?php echo htmlspecialchars($pasoLimpio); ?>.
                                    </p>
                                </li>
                            <?php endif; 
                        endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- COLUMNA DERECHA: Información Nutricional y Gestión -->
            <div class="p-8 md:p-12 flex flex-col justify-center">
                <h2 class="text-2xl font-bold text-gray-800 mb-8 flex items-center">
                    <span class="bg-gray-100 p-2 rounded-lg mr-3">📊</span> 
                    Valores Nutricionales <span class="text-sm text-gray-400 ml-2 font-normal">(por ración)</span>
                </h2>

                <!-- GRID DE MACRONUTRIENTES: Visualización de Kcal, Proteínas, CH y Grasas -->
                <div class="grid grid-cols-2 gap-6 mb-10">
                    <div class="<?php echo $estilo['bg_intense']; ?> p-6 rounded-3xl border-2 border-white shadow-sm">
                        <p class="<?php echo $estilo['text']; ?> text-xs font-bold uppercase tracking-widest mb-1 opacity-70">Calorías</p>
                        <p class="text-4xl font-black <?php echo $estilo['text']; ?> italic">
                            <?php echo round($receta['calorias_por_racion']); ?> 
                            <span class="text-sm italic">kcal</span>
                        </p>
                    </div>
                    <div class="bg-blue-50 p-6 rounded-3xl border-2 border-white shadow-sm">
                        <p class="text-blue-400 text-xs font-bold uppercase tracking-widest mb-1">Proteínas</p>
                        <p class="text-4xl font-black text-blue-600 italic">
                            <?php echo round($receta['proteinas_g_por_racion']); ?> 
                            <span class="text-sm italic">g</span>
                        </p>
                    </div>
                    <div class="bg-yellow-50 p-6 rounded-3xl border-2 border-white shadow-sm">
                        <p class="text-yellow-600 text-xs font-bold uppercase tracking-widest mb-1 opacity-70">Carbohidratos</p>
                        <p class="text-4xl font-black text-yellow-600 italic">
                            <?php echo round($receta['carbohidratos_g_por_racion']); ?> 
                            <span class="text-sm italic">g</span>
                        </p>
                    </div>
                    <div class="bg-orange-50 p-6 rounded-3xl border-2 border-white shadow-sm">
                        <p class="text-orange-500 text-xs font-bold uppercase tracking-widest mb-1 opacity-70">Grasas</p>
                        <p class="text-4xl font-black text-orange-600 italic">
                            <?php echo round($receta['grasas_g_por_racion']); ?> 
                            <span class="text-sm italic">g</span>
                        </p>
                    </div>
                </div>

                <!-- METADATOS Y ACCIONES DE USUARIO -->
                <div class="border-t border-gray-100 pt-8 mt-4">
                    <h3 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-4">Información del Sistema</h3>
                    <div class="flex flex-wrap gap-3 mb-8">
                        <div class="flex items-center text-gray-500 bg-gray-50 px-4 py-2 rounded-xl text-xs font-bold border border-gray-100">
                            📅 REGISTRADA: <?php echo date('d/m/Y', strtotime($receta['fecha_creacion'])); ?>
                        </div>
                        <!-- Indicador de optimización mediante Inteligencia Artificial -->
                        <?php if($receta['generado_ia']): ?>
                        <div class="flex items-center text-purple-600 bg-purple-50 px-4 py-2 rounded-xl text-xs font-black border border-purple-100 uppercase tracking-tighter">
                            ✨ IA OPTIMIZADA
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- CONTROL DE PERMISOS: Solo el creador puede editar o eliminar -->
                    <?php if ($receta['id_usuario_creador'] == $_SESSION['user_id']): ?>
                    <div class="flex gap-4 mb-12">
                        <a href="editar_receta.php?id=<?= $receta['id_receta'] ?>" 
                        class="flex-1 bg-teal-600 text-white font-black py-4 rounded-2xl shadow-lg shadow-teal-100 hover:bg-teal-700 hover:-translate-y-1 transition-all uppercase text-xs tracking-widest text-center">
                            Editar Receta
                        </a>
                        
                        <button onclick="abrirModalEliminar(<?= $receta['id_receta'] ?>)" 
                                class="px-8 bg-white border border-red-100 text-red-500 font-black py-4 rounded-2xl hover:bg-red-50 transition-all uppercase text-xs tracking-widest">
                            Eliminar
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE CONFIRMACIÓN: Interfaz de seguridad para evitar eliminaciones accidentales -->
<div id="deleteConfirmModal" class="fixed inset-0 z-50 hidden" aria-labelledby="delete-modal-title" role="dialog" aria-modal="true">
    <!-- Overlay: Aseguramos que cubra todo con flex y centrado -->
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div id="modalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-80 transition-opacity duration-300 opacity-0" aria-hidden="true" onclick="cerrarModalEliminar()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <!-- Contenido del Modal: Cambiamos inline-block por relative inline-block y aseguramos align-middle -->
        <div id="modalContent" class="relative inline-block align-middle bg-white rounded-[2.5rem] text-left overflow-hidden shadow-2xl transform transition-all ease-out duration-300 scale-95 opacity-0 sm:my-8 sm:max-w-md sm:w-full">
            <div class="p-10 text-center">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-3xl bg-red-50 mb-6">
                    <svg class="h-10 w-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                
                <h3 class="text-3xl font-black text-gray-900 italic tracking-tight" id="delete-modal-title">
                    ¿Eliminar <span class="text-red-600">Receta</span>?
                </h3>
                
                <div class="mt-6">
                    <p class="text-sm font-medium text-gray-500 leading-relaxed">
                        Estás a punto de borrar permanentemente esta receta de tu biblioteca:
                    </p>
                    <p id="deleteItemName" class="text-lg font-black text-red-600 mt-4 p-4 bg-red-50 rounded-2xl break-words italic border border-red-100">
                        <?php echo htmlspecialchars($receta['titulo']); ?>
                    </p>
                    <p class="text-[10px] font-black text-red-400 uppercase tracking-[0.2em] mt-6">
                        ⚠️ Esta acción no se puede deshacer
                    </p>
                </div>
            </div>
            
            <!-- Botonera del Modal -->
            <div class="bg-gray-50 px-10 py-8 sm:flex sm:flex-row-reverse gap-3">
                <button type="button" id="confirmDeleteButton" 
                    class="w-full sm:w-auto px-8 py-4 bg-red-600 text-white font-black rounded-2xl shadow-lg shadow-red-100 hover:bg-red-700 transition-all uppercase text-xs tracking-widest transform hover:scale-105 active:scale-95">
                    Sí, Eliminar
                </button>
                <button type="button" onclick="cerrarModalEliminar()" 
                    class="mt-3 sm:mt-0 w-full sm:w-auto px-8 py-4 bg-white text-gray-400 font-bold rounded-2xl border border-gray-200 hover:bg-gray-100 transition-all uppercase text-xs tracking-widest">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<?php 
// Incluir el pie de página
include_once __DIR__ . '/../templates/footer.php';
?>