<?php
/**
 * BIBLIOTECA DE RECETAS - KaloAI
 * Este módulo gestiona la visualización de la colección de recetas del usuario,
 * incluyendo las creadas por él y las recetas base del sistema (NULL).
 */

$pageTitle = "KaloAI | Mis Recetas";
session_start();
require_once __DIR__ . '/../../core/utilidades.php';
require_once __DIR__ . '/../../core/configuracion.php';

// SEGURIDAD: Redirección si no hay sesión activa
if (!isset($_SESSION['user_id'])) { redirect('auth/login.php'); }

$pdo = connectDB();
$userId = $_SESSION['user_id'];

try {
    /**
     * CONSULTA JERÁRQUICA:
     * 1. Trae recetas propias (ID usuario) y recetas globales (NULL).
     * 2. Ordena prioritariamente por tipo de comida (flujo lógico de un día)
     *    y secundariamente por orden alfabético.
     */
    $sql = "SELECT * FROM receta 
            WHERE id_usuario_creador = ? OR id_usuario_creador IS NULL 
            ORDER BY 
                CASE 
                    WHEN LOWER(tipo_comida) = 'desayuno' THEN 1
                    WHEN LOWER(tipo_comida) IN ('almuerzo', 'comida') THEN 2
                    WHEN LOWER(tipo_comida) IN ('snack', 'merienda') THEN 3
                    WHEN LOWER(tipo_comida) = 'cena' THEN 4
                    ELSE 5 
                END ASC, 
                titulo ASC";
                
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $recetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    error_log("Error en Mis Recetas: " . $e->getMessage());
    $recetas = [];
}

/**
 * CONFIGURADOR DE INTERFAZ (UI HELPER)
 * Mapea el tipo de comida con iconos, colores y estilos de Tailwind
 * para mantener la coherencia visual en las "cards".
 */
function getEstiloReceta($tipo) {
    $tipo = mb_strtolower($tipo);
    switch ($tipo) {
        case 'desayuno': return ['icon' => '☕', 'bg' => 'bg-orange-100', 'text' => 'text-orange-600', 'btn' => 'bg-orange-50 text-orange-600 hover:bg-orange-100'];
        case 'almuerzo':
        case 'comida': return ['icon' => '☀️', 'bg' => 'bg-amber-100', 'text' => 'text-amber-600', 'btn' => 'bg-amber-50 text-amber-600 hover:bg-amber-100'];
        case 'cena': return ['icon' => '🌙', 'bg' => 'bg-indigo-100', 'text' => 'text-indigo-600', 'btn' => 'bg-indigo-50 text-indigo-600 hover:bg-indigo-100'];
        case 'snack':
        case 'merienda': return ['icon' => '🍏', 'bg' => 'bg-lime-100', 'text' => 'text-lime-600', 'btn' => 'bg-lime-50 text-lime-600 hover:bg-lime-100'];
        default: return ['icon' => '🍳', 'bg' => 'bg-teal-100', 'text' => 'text-teal-600', 'btn' => 'bg-teal-50 text-teal-600 hover:bg-teal-100'];
    }
}

// Carga del componente de cabecera 
include_once __DIR__ . '/../templates/header.php';
?>

<!-- CONTENEDOR PRINCIPAL: Gestión de biblioteca de recetas -->
<div class="container mx-auto px-4 py-8">
    
    <!-- SISTEMA DE FEEDBACK: Notificaciones con temporizador automático (JS) -->
    <?php if (isset($_SESSION['mensaje'])): ?>
        <div id="notification" class="mb-8 flex items-center p-6 bg-teal-50 border border-teal-100 rounded-[2rem] animate-fade-in-up shadow-sm">
            <div class="flex-shrink-0 w-12 h-12 bg-white rounded-2xl flex items-center justify-center text-xl shadow-sm mr-4">
                ✅
            </div>
            <div class="flex-1">
                <p class="text-sm font-black text-teal-800 italic uppercase tracking-wider leading-none">¡Hecho!</p>
                <p class="text-xs font-medium text-teal-600 mt-1"><?php echo $_SESSION['mensaje']; ?></p>
            </div>
            <!-- Botón de cierre manual -->
            <button onclick="document.getElementById('notification').remove()" class="text-teal-300 hover:text-teal-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <!-- Botón de cierre manual -->
        <script>
            setTimeout(() => {
                const notif = document.getElementById('notification');
                if(notif) {
                    notif.style.transition = 'all 0.5s ease';
                    notif.style.opacity = '0';
                    notif.style.transform = 'translateY(-20px)';
                    setTimeout(() => notif.remove(), 500);
                }
            }, 5000);
        </script>
        <?php unset($_SESSION['mensaje']); ?>
    <?php endif; ?>

    <!-- CABECERA: Título y botón para disparar el modal de creación -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-10 gap-6">
        <div>
            <div class="flex items-center group">
                <a href="../dashboard.php" class="mr-4 bg-teal-50 p-3 rounded-2xl text-teal-600 hover:bg-teal-600 hover:text-white transition-all shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h1 class="text-5xl font-black text-gray-900 italic tracking-tight">
                    Mis <span class="text-teal-600">Recetas</span>
                </h1>
            </div>
            <p class="text-gray-500 font-medium mt-4 ml-16">Gestiona tu alimentación diaria de forma inteligente.</p>
        </div>
        
        <!-- CALL TO ACTION: Disparador del modal de creación -->
        <button onclick="abrirModalReceta()" class="bg-teal-600 hover:bg-teal-700 text-white px-8 py-4 rounded-2xl font-bold transition-all shadow-lg shadow-teal-100 flex items-center justify-center transform hover:scale-105">
            <span class="text-xl mr-2">+</span> Nueva Receta
        </button>
    </div>

    <!-- BUSCADOR Y FILTROS JS: Operan sobre el DOM para una experiencia rápida sin recarga -->
    <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border-2 border-gray-50 mb-12">
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Input de búsqueda por texto -->
            <div class="flex-1 relative">
                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-xl">🔍</span>
                <input type="text" id="recipeSearch" placeholder="Buscar receta..." 
                       class="w-full pl-14 pr-6 py-5 rounded-3xl border-2 border-gray-50 bg-gray-50 focus:bg-white focus:border-teal-200 focus:outline-none transition-all text-lg font-medium">
            </div>
            
            <!-- Botonera de filtros por categoría (Tipo de Comida) -->
            <div class="flex flex-wrap gap-2" id="filterButtons">
                <button onclick="filtrarPor('todos')" class="filter-btn active bg-teal-600 text-white px-6 py-4 rounded-2xl font-bold transition-all shadow-md">Todos</button>
                <button onclick="filtrarPor('desayuno')" class="filter-btn bg-white border-2 border-gray-50 text-gray-400 hover:border-orange-200 hover:text-orange-600 px-6 py-4 rounded-2xl font-bold transition-all">☕ Desayuno</button>
                <button onclick="filtrarPor('almuerzo')" class="filter-btn bg-white border-2 border-gray-50 text-gray-400 hover:border-amber-200 hover:text-amber-600 px-6 py-4 rounded-2xl font-bold transition-all">☀️ Almuerzo</button>
                <button onclick="filtrarPor('snack')" class="filter-btn bg-white border-2 border-gray-50 text-gray-400 hover:border-lime-200 hover:text-lime-600 px-6 py-4 rounded-2xl font-bold transition-all">🍏 Snacks</button>
                <button onclick="filtrarPor('cena')" class="filter-btn bg-white border-2 border-gray-50 text-gray-400 hover:border-indigo-200 hover:text-indigo-600 px-6 py-4 rounded-2xl font-bold transition-all">🌙 Cena</button>
            </div>
        </div>
    </div>

    <!-- GRID DE RECETAS: Renderizado dinámico desde PHP -->
    <?php if (empty($recetas)): ?>
        <!-- EMPTY STATE: Se muestra si el usuario aún no tiene recetas en BD -->
        <div class="text-center py-24 bg-white rounded-[2.5rem] border-2 border-dashed border-gray-200 shadow-sm animate-fade-in">
            <div class="text-7xl mb-6">🍳</div>
            <p class="text-gray-400 font-black uppercase tracking-[0.2em] text-sm italic">Tu biblioteca está vacía</p>
            <p class="text-gray-400 mt-2 max-w-md mx-auto font-medium">Añade tus recetas favoritas para empezar a organizar tu alimentación inteligente.</p>
            
            <button onclick="abrirModalReceta()" class="inline-block mt-8 px-10 py-4 bg-teal-600 text-white font-black rounded-2xl hover:bg-teal-700 transition-all shadow-xl shadow-teal-100 uppercase text-xs tracking-widest active:scale-95">
                + Crear mi primera receta
            </button>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-10" id="recipesGrid">
            <?php foreach ($recetas as $receta): 
            // Función auxiliar que devuelve colores e iconos según el tipo de comida
                $estilo = getEstiloReceta($receta['tipo_comida']);
                $tipoData = mb_strtolower($receta['tipo_comida']);

                // Normalización de tipos para el motor de filtrado JS
                if($tipoData == 'comida') $tipoData = 'almuerzo';
                if($tipoData == 'merienda') $tipoData = 'snack';
            ?>
                <!-- TARJETA DE RECETA: Contiene meta-información y macros calculados -->
                <div class="recipe-card group bg-white rounded-[2.5rem] border-2 border-gray-50 overflow-hidden shadow-sm hover:shadow-2xl hover:-translate-y-3 transition-all duration-500" 
                    data-tipo="<?php echo $tipoData; ?>" 
                    data-titulo="<?php echo mb_strtolower($receta['titulo']); ?>">
                    
                    <!-- Header de la tarjeta: Icono central y etiqueta de Calorías -->
                    <div class="relative h-56 <?php echo $estilo['bg']; ?> flex items-center justify-center">
                        <span class="text-7xl group-hover:scale-125 transition-transform duration-700"><?php echo $estilo['icon']; ?></span>
                        <div class="absolute bottom-4 right-6 bg-white/90 backdrop-blur-md px-4 py-2 rounded-2xl text-sm font-black <?php echo $estilo['text']; ?> shadow-sm">
                            <?php echo round($receta['calorias_por_racion']); ?> <span class="text-[10px] uppercase">kcal</span>
                        </div>
                    </div>

                    <div class="p-8">
                        <div class="text-[10px] font-black <?php echo $estilo['text']; ?> uppercase tracking-[0.2em] mb-2"><?php echo $receta['tipo_comida']; ?></div>
                        <h3 class="recipe-title text-2xl font-bold text-gray-900 mb-3 italic tracking-tight leading-tight"><?php echo htmlspecialchars($receta['titulo']); ?></h3>
                        
                        <!-- TABLA DE MACROS: Desglose nutricional por ración -->
                        <div class="flex justify-between items-center bg-gray-50/50 p-4 rounded-2xl mb-6 border border-gray-50">
                            <div class="text-center"><p class="text-[10px] text-gray-400 font-bold uppercase">Prot</p><p class="font-bold text-gray-700"><?php echo round($receta['proteinas_g_por_racion']); ?>g</p></div>
                            <div class="text-center"><p class="text-[10px] text-gray-400 font-bold uppercase">Carb</p><p class="font-bold text-gray-700"><?php echo round($receta['carbohidratos_g_por_racion']); ?>g</p></div>
                            <div class="text-center"><p class="text-[10px] text-gray-400 font-bold uppercase">Gras</p><p class="font-bold text-gray-700"><?php echo round($receta['grasas_g_por_racion']); ?>g</p></div>
                        </div>

                        <!-- Enlace al detalle completo -->
                        <a href="detalle_receta.php?id=<?php echo $receta['id_receta']; ?>" 
                        class="block text-center font-black uppercase text-xs tracking-widest py-4 rounded-2xl transition-all <?php echo $estilo['btn']; ?>">
                            Ver Detalles
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- NAVEGACIÓN INFERIOR -->
    <div class="mt-16 text-center">
        <a href="../dashboard.php" class="inline-flex items-center font-black text-xs uppercase tracking-[0.2em] text-gray-400 hover:text-teal-600 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path></svg>
            Volver al Panel
        </a>
    </div>

    <!-- FEEDBACK DE BÚSQUEDA: Se muestra vía JS si el usuario filtra y no hay coincidencias -->
    <div id="noResults" class="hidden text-center py-24 bg-gray-50 rounded-[3rem] border-2 border-dashed border-gray-200">
        <span class="text-6xl mb-4 block">🔍</span>
        <h3 class="text-2xl font-bold text-gray-800 italic">No hemos encontrado esa receta</h3>
        <p class="text-gray-500 mt-2">Prueba con otro nombre o añade una nueva.</p>
    </div>
</div>

<!-- MODAL DE SELECCIÓN: IA O MANUAL -->
<!-- Este modal bifurca la experiencia del usuario entre la automatización (IA) o el control total (Manual) -->
<div id="recipeChoiceModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        
        <div class="fixed inset-0 bg-gray-900 bg-opacity-70 transition-opacity" aria-hidden="true" onclick="cerrarModalReceta()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white rounded-[3rem] text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full p-8 border-t-8 border-teal-600">
            
            <div class="text-center mb-10">
                <h3 class="text-4xl font-black text-gray-900 italic tracking-tight mb-2">Nueva Receta</h3>
                <p class="text-gray-500 font-medium">¿Cómo quieres añadir tu próxima creación?</p>
            </div>

            <!-- Opciones de creación -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- OPCIÓN IA: Conecta con el motor de generación KaloAI -->
                <a href="generar_receta.php" class="group bg-teal-50 hover:bg-teal-600 p-8 rounded-[2.5rem] transition-all duration-500 text-center flex flex-col items-center shadow-sm hover:shadow-xl hover:-translate-y-2">
                    <div class="w-20 h-20 bg-white rounded-3xl flex items-center justify-center text-4xl mb-6 shadow-sm group-hover:scale-110 transition-transform">
                        ✨
                    </div>
                    <h4 class="text-xl font-black text-teal-800 group-hover:text-white uppercase tracking-widest mb-3">KaloAI</h4>
                    <p class="text-teal-600/70 group-hover:text-teal-50 text-sm font-medium leading-relaxed">Genera una receta inteligente perfecta para ti.</p>
                </a>

                <!-- OPCIÓN MANUAL: Formulario tradicional paso a paso -->
                <a href="crear_receta.php" class="group bg-gray-50 hover:bg-gray-900 p-8 rounded-[2.5rem] transition-all duration-500 text-center flex flex-col items-center shadow-sm hover:shadow-xl hover:-translate-y-2">
                    <div class="w-20 h-20 bg-white rounded-3xl flex items-center justify-center text-4xl mb-6 shadow-sm group-hover:scale-110 transition-transform">
                        ✍️
                    </div>
                    <h4 class="text-xl font-black text-gray-800 group-hover:text-white uppercase tracking-widest mb-3">Manual</h4>
                    <p class="text-gray-500 group-hover:text-gray-400 text-sm font-medium leading-relaxed">Escribe tu receta paso a paso de forma tradicional.</p>
                </a>
            </div>

            <button onclick="cerrarModalReceta()" class="mt-10 w-full py-4 text-gray-400 font-bold hover:text-gray-600 transition-colors uppercase text-xs tracking-[0.3em]">
                Cerrar Ventana
            </button>
        </div>
    </div>
</div>

<?php 
// Incluir el pie de página
include_once __DIR__ . '/../templates/footer.php';
?>