<?php
/**
 * VISTA: EDITAR RECETA - KaloAI
 * Permite modificar una receta existente. 
 * Implementa una validación crucial: solo el creador original puede editarla.
 */

$pageTitle = "KaloAI | Editar Receta";
session_start();
require_once __DIR__ . '/../../core/utilidades.php';
require_once __DIR__ . '/../../core/configuracion.php';

// 1. CONTROL DE ACCESO
if (!isset($_SESSION['user_id'])) { redirect('auth/login.php'); }

$pdo = connectDB();
$userId = $_SESSION['user_id'];
$recetaId = $_GET['id'] ?? null;

/* ==========================================================================
   2. CARGA DE DATOS Y VALIDACIÓN DE SEGURIDAD
   ========================================================================== */
$receta = null;
if ($recetaId) {
    // Filtramos por id_usuario_creador para que nadie edite lo que no es suyo
    $stmt = $pdo->prepare("SELECT * FROM receta WHERE id_receta = ? AND id_usuario_creador = ?");
    $stmt->execute([$recetaId, $userId]);
    $receta = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$receta) {
    header("Location: mis_recetas.php");
    exit;
}

/* ==========================================================================
   3. RECONSTRUCCIÓN DE INGREDIENTES
   ========================================================================== */
$stmtIng = $pdo->prepare("SELECT nombre_ingrediente FROM detalle_receta WHERE id_receta = ?");
$stmtIng->execute([$recetaId]);
$ingredientesArray = $stmtIng->fetchAll(PDO::FETCH_COLUMN);
$ingredientesText = implode("\n", $ingredientesArray);

include_once __DIR__ . '/../templates/header.php';
?>

<div class="container mx-auto px-6 py-12 max-w-4xl">
    
    <!-- CABECERA -->
    <div class="flex items-center mb-12">
        <a href="detalle_receta.php?id=<?= $recetaId ?>" class="mr-6 bg-white border border-gray-100 p-4 rounded-2xl text-gray-400 hover:text-teal-600 hover:shadow-md transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <h1 class="text-5xl font-black text-gray-900 italic tracking-tighter">
                Editar <span class="text-teal-600">Receta</span>
            </h1>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mt-2 flex items-center gap-2">
                <span class="w-2 h-2 bg-teal-500 rounded-full"></span>
                Modificando: <?= htmlspecialchars($receta['titulo']) ?>
            </p>
        </div>
    </div>

    <!-- FORMULARIO: Envía a guardar_receta.php -->
    <form action="../../core/guardar_receta.php" method="POST" class="space-y-8">
        <!-- ID OCULTO: Vital para que el script haga UPDATE en lugar de INSERT -->
        <input type="hidden" name="id_receta" value="<?= $recetaId ?>">
        
        <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-gray-100">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="md:col-span-2">
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Título</label>
                    <input type="text" name="titulo" value="<?= htmlspecialchars($receta['titulo']) ?>" required
                        class="w-full bg-gray-50 border-none rounded-2xl px-6 py-4 text-lg font-bold italic focus:ring-2 focus:ring-teal-500 transition-all">
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Categoría</label>
                    <select name="tipo_comida" class="w-full bg-gray-50 border-none rounded-2xl px-6 py-4 font-bold text-gray-700 focus:ring-2 focus:ring-teal-500 transition-all">
                        <?php
                        $tipos = ['desayuno' => '☕ Desayuno', 'almuerzo' => '☀️ Almuerzo', 'snack' => '🍏 Snack', 'cena' => '🌙 Cena'];
                        foreach ($tipos as $val => $label): ?>
                            <option value="<?= $val ?>" <?= (strtolower($receta['tipo_comida']) == $val) ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Calorías</label>
                    <input type="number" name="calorias_por_racion" value="<?= $receta['calorias_por_racion'] ?>" step="1"
                        class="w-full bg-gray-50 border-none rounded-2xl px-6 py-4 font-bold focus:ring-2 focus:ring-teal-500 transition-all">
                </div>
            </div>
        </div>

        <!-- MACRONUTRIENTES -->
        <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-gray-100">
            <h2 class="text-xl font-black text-gray-900 italic mb-6">Información Nutricional (g)</h2>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-blue-500 uppercase tracking-tighter mb-2 ml-1">Proteínas</label>
                    <input type="number" name="proteinas_g_por_racion" value="<?= $receta['proteinas_g_por_racion'] ?>" step="1" 
                        class="w-full bg-gray-50 border-none rounded-xl px-4 py-3 font-bold focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-yellow-500 uppercase tracking-tighter mb-2 ml-1">Carbohidratos</label>
                    <input type="number" name="carbohidratos_g_por_racion" value="<?= $receta['carbohidratos_g_por_racion'] ?>" step="1" 
                        class="w-full bg-gray-50 border-none rounded-xl px-4 py-3 font-bold focus:ring-2 focus:ring-yellow-500">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-orange-500 uppercase tracking-tighter mb-2 ml-1">Grasas</label>
                    <input type="number" name="grasas_g_por_racion" value="<?= $receta['grasas_g_por_racion'] ?>" step="1" 
                        class="w-full bg-gray-50 border-none rounded-xl px-4 py-3 font-bold focus:ring-2 focus:ring-orange-500">
                </div>
            </div>
        </div>

        <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-gray-100">
            <div class="space-y-6">
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Ingredientes (uno por línea)</label>
                    <textarea name="ingredientes" rows="5"
                        class="w-full bg-gray-50 border-none rounded-[2rem] px-6 py-4 font-medium focus:ring-2 focus:ring-teal-500 transition-all"><?= htmlspecialchars($ingredientesText) ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Instrucciones</label>
                    <textarea name="instrucciones" rows="5"
                        class="w-full bg-gray-50 border-none rounded-[2rem] px-6 py-4 font-medium focus:ring-2 focus:ring-teal-500 transition-all"><?= htmlspecialchars($receta['descripcion']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="flex gap-4">
            <button type="submit" class="flex-1 bg-teal-600 text-white font-black py-6 rounded-[2rem] shadow-xl shadow-teal-100 hover:bg-teal-700 hover:-translate-y-1 transition-all uppercase text-xs tracking-[0.2em]">
                Actualizar Receta
            </button>
            <a href="detalle_receta.php?id=<?= $recetaId ?>" class="px-10 bg-white border border-gray-100 text-gray-400 font-black py-6 rounded-[2rem] hover:bg-gray-50 transition-all uppercase text-xs tracking-[0.2em]">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php include_once __DIR__ . '/../templates/footer.php'; ?>