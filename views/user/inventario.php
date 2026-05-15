<?php
/**
 * GESTOR DE INVENTARIO - KaloAI
 * Permite al usuario llevar un control de sus existencias.
 * Incluye lógica de conversión de unidades y patrón PRG para evitar reenvíos de formulario.
 */

$pageTitle = "KaloAI | Inventario Doméstico";

session_start();
require_once __DIR__ . '/../../core/configuracion.php';
require_once __DIR__ . '/../../core/utilidades.php'; 

// 1. SEGURIDAD: Verificación de sesión activa
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php'); 
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = 'success';
$inventarioItems = [];
$pdo = connectDB();

/* ==========================================================
   CONFIGURACIÓN DE CONVERSIONES
   Se definen las unidades soportadas y se cargan los factores 
   matemáticos desde el core.
   ========================================================== */
$unidades = [
    'g' => 'Gramos',
    'ml' => 'Mililitros',
    'unidad' => 'Unidad(es)',
];
$conversionMap = getDetallesConversion();


/* ==========================================================
   2. PROCESAMIENTO DE ACCIONES (POST)
   Maneja: Añadir (con suma inteligente), Editar y Eliminar.
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $action = limpiarInput($_POST['action'] ?? '');
    $idInventario = filter_var($_POST['id_inventario'] ?? null, FILTER_VALIDATE_INT);

    try {
        if ($action === 'add' || $action === 'edit') {
            // Saneamiento de entradas numéricas y de texto
            $nombreIngrediente = limpiarInput($_POST['nombre_ingrediente'] ?? '');
            $cantidad = str_replace(',', '.', limpiarInput($_POST['cantidad'] ?? '')); 
            $cantidad = filter_var($cantidad, FILTER_VALIDATE_FLOAT);
            $unidad = limpiarInput($_POST['unidad'] ?? '');

            // Validación de integridad de datos
            if (empty($nombreIngrediente) || $cantidad === false || $cantidad <= 0 || !array_key_exists($unidad, $unidades)) {
                $errors[] = "Datos de ingrediente inválidos o incompletos.";
            }

            $newUnitDetails = $conversionMap[$unidad] ?? null;
            if (!$newUnitDetails) {
                 $errors[] = "Unidad de medida no reconocida.";
            }

            if (empty($errors)) {
                $fechaActualizacion = date('Y-m-d H:i:s'); 
                
                /* ------------------------------------------------------
                   LÓGICA DE SUMA INTELIGENTE (Acción 'add')
                   Evita duplicados como "Arroz (500g)" y "Arroz (1kg)"
                   ------------------------------------------------------ */
                if ($action === 'add') {
                    
                    $normalizedNameForSQL = normalizarString($nombreIngrediente);
                    $group = $newUnitDetails['group'];
                    
                    // BUSQUEDA POR SIMILITUD: Busca el ingrediente ignorando tildes y mayúsculas,
                    // asegurando que pertenezca al mismo grupo de medida (ej. no sumar 'huevos' con 'leche').
                    $sqlCheck = "SELECT id_inventario, cantidad, unidad, nombre_ingrediente 
                                 FROM inventario 
                                 WHERE id_usuario = ? 
                                 AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(nombre_ingrediente), 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u') = ?
                                 AND unidad IN (
                                     SELECT unit FROM (
                                         SELECT DISTINCT T1.unidad AS unit, 
                                            CASE 
                                                WHEN T1.unidad IN ('g', 'kg') THEN 'peso'
                                                WHEN T1.unidad IN ('ml', 'L') THEN 'volumen'
                                                ELSE 'conteo'
                                            END AS unit_group
                                         FROM inventario T1
                                     ) AS UnitGroups
                                     WHERE unit_group = ?
                                 )";
                    
                    $stmtCheck = $pdo->prepare($sqlCheck);
                    $stmtCheck->execute([$userId, $normalizedNameForSQL, $group]);
                    $existingItem = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if ($existingItem) {
                        // SI EXISTE: Convertir ambas cantidades a una "Unidad Base" para operar matemáticamente
                        $existingUnit = $existingItem['unidad'];
                        $existingUnitDetails = $conversionMap[$existingUnit];
                        
                        // Cantidad nueva -> Base
                        $addedQuantityInBase = $cantidad * $newUnitDetails['conversion_factor'];
                        
                        // Cantidad vieja -> Base
                        $existingQuantityInBase = $existingItem['cantidad'] * $existingUnitDetails['conversion_factor'];
                        
                        // Suma y reconversión a la unidad que ya estaba guardada en la DB
                        $newQuantityInBase = $existingQuantityInBase + $addedQuantityInBase;
                        $finalNewQuantity = $newQuantityInBase / $existingUnitDetails['conversion_factor'];
                        
                        $sqlUpdate = "UPDATE inventario SET cantidad = ?, fecha_actualizacion = ? 
                                      WHERE id_inventario = ? AND id_usuario = ?";
                        $stmtUpdate = $pdo->prepare($sqlUpdate);
                        $stmtUpdate->execute([$finalNewQuantity, $fechaActualizacion, $existingItem['id_inventario'], $userId]);
                        
                        $message = "Ingrediente {$existingItem['nombre_ingrediente']} actualizado con éxito.";

                    } else {
                        // SI NO EXISTE: Inserción limpia como nuevo registro
                        $sqlInsert = "INSERT INTO inventario (id_usuario, nombre_ingrediente, cantidad, unidad, fecha_actualizacion) 
                                VALUES (?, ?, ?, ?, ?)";
                        $stmtInsert = $pdo->prepare($sqlInsert);
                        $stmtInsert->execute([$userId, $nombreIngrediente, $cantidad, $unidad, $fechaActualizacion]);
                        $message = "Ingrediente '{$nombreIngrediente}' añadido.";
                    }

                } elseif ($action === 'edit' && $idInventario > 0) {
                    // EDICIÓN MANUAL: Reemplazo total de los valores seleccionados
                    $sql = "UPDATE inventario SET nombre_ingrediente = ?, cantidad = ?, unidad = ?, fecha_actualizacion = ? 
                            WHERE id_inventario = ? AND id_usuario = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombreIngrediente, $cantidad, $unidad, $fechaActualizacion, $idInventario, $userId]);
                    
                    if ($stmt->rowCount() > 0) {
                        $message = "Cambios guardados correctamente.";
                    } else {
                        throw new Exception("No se realizaron cambios o no tienes permiso.");
                    }
                }
            }
        } elseif ($action === 'delete' && $idInventario > 0) {
            // ELIMINACIÓN: Borrado físico del registro tras validar propiedad
            $sql = "DELETE FROM inventario WHERE id_inventario = ? AND id_usuario = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idInventario, $userId]);

            if ($stmt->rowCount() > 0) {
                $message = "Ingrediente eliminado.";
            } else {
                throw new Exception("Error al eliminar el ingrediente.");
            }
        }
        
    } catch (\Exception $e) {
        $errors[] = "Error: " . $e->getMessage();
        $messageType = 'error';
    }

    /* ------------------------------------------------------
       PATRÓN PRG (Post-Redirect-Get)
       Evita que si el usuario refresca la página, se 
       vuelva a insertar el ingrediente duplicando la cantidad.
       ------------------------------------------------------ */
    if (empty($errors)) {
        $_SESSION['message'] = $message;
        $_SESSION['messageType'] = $messageType;
        header("Location: inventario.php");
        exit;
    }
}

/* ==========================================================
   3. CARGA DE DATOS PARA LA VISTA
   Se ejecuta siempre al cargar la página (GET)
   ========================================================== */
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'];
    unset($_SESSION['message'], $_SESSION['messageType']);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM inventario WHERE id_usuario = ? ORDER BY nombre_ingrediente ASC");
    $stmt->execute([$userId]);
    $inventarioItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $message = "Error al conectar con el inventario.";
    $messageType = 'error';
}

// Carga del componente de cabecera 
include_once __DIR__ . '/../templates/header.php'; 
?>

<!-- CONTENEDOR PRINCIPAL -->
<div class="container mx-auto px-4 py-8">
    
    <!-- CONTENEDOR PRINCIPAL -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-10 gap-6">
        <div>
            <div class="flex items-center group">
                <!-- Botón de retroceso -->
                <a href="../dashboard.php" class="mr-4 bg-teal-50 p-3 rounded-2xl text-teal-600 hover:bg-teal-600 hover:text-white transition-all shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <!-- Título principal -->
                <h1 class="text-5xl font-black text-gray-900 italic tracking-tight">
                    Mi <span class="text-teal-600">Inventario</span>
                </h1>
            </div>
            <p class="text-gray-500 font-medium mt-4 ml-16 italic">Gestiona los ingredientes que ya tienes en casa.</p>
        </div>
        
        <!-- TRIGGER MODAL: Llama a la función JS para añadir ingrediente -->
        <button onclick="abrirModalInventario('add')"
            class="bg-teal-600 hover:bg-teal-700 text-white font-black py-4 px-8 rounded-2xl transition-all shadow-lg shadow-teal-200 flex items-center gap-3 text-xs uppercase tracking-widest active:scale-95">
            <i class="fas fa-plus-circle text-lg"></i>
            <span class="text-xl mr-2">+</span> Nuevo Ingrediente
        </button>
    </div>
    
    <!-- ALERTAS DE FEEDBACK: Éxito o Error tras operaciones POST -->
    <?php if ($message): ?>
        <div class="p-5 mb-8 rounded-2xl font-bold transition duration-300 flex items-center shadow-sm
            <?php echo $messageType === 'success' ? 'bg-green-50 text-green-700 border-l-4 border-green-500' : 'bg-red-50 text-red-700 border-l-4 border-red-500'; ?>" 
            role="alert">
            <i class="mr-3 text-xl <?php echo $messageType === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- ESTADO VACÍO: UX mejorada para cuando no hay datos en la BD -->
    <?php if (empty($inventarioItems)): ?>
        <div class="text-center py-24 bg-white rounded-[2.5rem] border-2 border-dashed border-gray-200 shadow-sm">
            <div class="text-7xl mb-6">🍎</div>
            <p class="text-gray-400 font-black uppercase tracking-widest text-sm">Tu despensa está vacía</p>
            <p class="text-gray-400 mt-2 max-w-md mx-auto">Añade tus primeros ingredientes para empezar a generar recetas inteligentes.</p>
            <button onclick="abrirModalInventario('add')" class="mt-8 px-10 py-4 bg-teal-600 text-white font-black rounded-2xl hover:bg-teal-700 transition-all shadow-xl shadow-teal-100 uppercase text-xs tracking-widest">
                Añadir Ahora
            </button>
        </div>
    <?php else: ?>
        <!-- TABLA DE INVENTARIO: Contenedor con scroll horizontal para móviles -->
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-50 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th scope="col" class="px-8 py-5 text-left text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Ingrediente</th>
                            <th scope="col" class="px-8 py-5 text-left text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Cantidad</th>
                            <th scope="col" class="px-8 py-5 text-left text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Unidad</th>
                            <th scope="col" class="px-8 py-5 text-left text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Actualización</th>
                            <th scope="col" class="px-8 py-5 text-right text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($inventarioItems as $index => $item): ?>
                            <tr class="hover:bg-teal-50/30 transition-colors group">
                                <!-- Nombre: Formateado con MB_STRLOWER para consistencia visual -->
                                <td class="px-8 py-5 whitespace-nowrap text-lg font-black text-gray-900 capitalize">
                                    <?php echo htmlspecialchars(mb_strtolower($item['nombre_ingrediente'], 'UTF-8')); ?>
                                </td>
                                <!-- Cantidad: Lógica PHP para evitar decimales innecesarios (ej: 5.00 -> 5) -->
                                <td class="px-8 py-5 whitespace-nowrap text-lg font-bold text-teal-600 tabular-nums">
                                    <?php
                                        $cantidad = $item['cantidad'];
                                        echo (floor($cantidad) == $cantidad) ? number_format($cantidad, 0, ',', '.') : number_format($cantidad, 2, ',', '.');
                                    ?>
                                </td>
                                <!-- Unidad: Mapeo mediante array $unidades -->
                                <td class="px-8 py-5 whitespace-nowrap text-sm font-bold text-gray-500 uppercase tracking-wider">
                                    <?php echo htmlspecialchars($unidades[$item['unidad']] ?? $item['unidad']); ?>
                                </td>
                                <td class="px-8 py-5 whitespace-nowrap text-xs text-gray-400 font-medium">
                                    <?php echo date('d/m/Y', strtotime($item['fecha_actualizacion'])); ?>
                                </td>
                                <!-- BOTONES DE ACCIÓN: Envío de datos JSON al modal para edición rápida -->
                                <td class="px-8 py-5 whitespace-nowrap text-right">
                                    <div class="flex justify-end gap-2">
                                        <button onclick="abrirModalInventario('edit', <?php echo htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)" 
                                            class="p-3 bg-amber-50 text-amber-600 rounded-xl hover:bg-amber-500 hover:text-white transition-all active:scale-90 shadow-sm shadow-amber-100/50">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zm-3.83 3.83a.999.999 0 00-.083.187l-.022.046-.01.03-.004.008-6.195 6.195a1 1 0 00-.232.453l-.579 2.052a1 1 0 00.147.935.996.996 0 00.439.236l2.052-.579a1 1 0 00.453-.232l6.195-6.195a1 1 0 00.187-.083.998.998 0 00.187-.083l.035-.022.046-.01.03-.004.008-.002.002-.002z"/>
                                            </svg>
                                        </button>
                                        <button onclick="abrirModalEliminarInventario(<?php echo (int) $item['id_inventario']; ?>, '<?php echo htmlspecialchars(addslashes($item['nombre_ingrediente'])); ?>')" 
                                            class="p-3 bg-red-50 text-red-600 rounded-xl hover:bg-red-500 hover:text-white transition-all active:scale-90 shadow-sm shadow-red-100/50">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm4 0a1 1 0 10-2 0v6a1 1 0 102 0V8z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CONSEJO DINÁMICO: Mejora la retención del usuario explicando la integración con otras funciones -->
        <div class="mt-12 p-8 bg-amber-50/50 rounded-[2.5rem] border-2 border-amber-100/50 flex flex-col md:flex-row gap-6 items-center">
            <div class="bg-amber-100 w-16 h-16 rounded-2xl flex items-center justify-center text-3xl shadow-inner">💡</div>
            <div>
                <p class="text-amber-900 font-black italic text-lg mb-1">Consejo KaloAI</p>
                <p class="text-amber-800/80 font-medium leading-relaxed">
                    Mantén tu inventario actualizado para que la <b>Lista de la Compra</b> solo te muestre lo que realmente te falta. Al añadir alimentos aquí, desaparecen automáticamente de tu lista de pendientes.
                </p>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- NAVEGACIÓN INFERIOR -->
    <div class="mt-16 text-center">
        <a href="../dashboard.php" class="inline-flex items-center font-black text-xs uppercase tracking-[0.2em] text-gray-400 hover:text-teal-600 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path></svg>
            Volver al Panel
        </a>
    </div>
</div>

<!-- MODAL: AÑADIR / EDITAR -->
 <!-- Utiliza un campo oculto 'action' para diferenciar entre INSERT y UPDATE en el backend -->
<div id="itemModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        
        <div class="fixed inset-0 bg-gray-900 bg-opacity-70 transition-opacity" aria-hidden="true" onclick="cerrarModalInventario()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border-t-4 border-teal-600">
            <form id="itemForm" method="POST" action="inventario.php" class="p-6 sm:p-8">
                <h3 class="text-2xl font-extrabold text-gray-900 mb-6 border-b pb-3" id="modal-title">
                    Añadir Nuevo Ingrediente
                </h3>

                <input type="hidden" name="action" id="actionField" value="add">
                <input type="hidden" name="id_inventario" id="idInventarioField" value="">

                <div class="space-y-4">
                    <div>
                        <label for="nombre_ingrediente" class="block text-sm font-medium text-gray-700 mb-1">Nombre del Ingrediente</label>
                        <input type="text" name="nombre_ingrediente" id="nombre_ingrediente" required
                               class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-teal-500 focus:border-teal-500 sm:text-base"
                               placeholder="Ej: Harina de trigo, Leche">
                    </div>

                    <div class="flex space-x-4">
                        <div class="w-1/2">
                            <label for="cantidad" class="block text-sm font-medium text-gray-700 mb-1">Cantidad</label>
                            <input type="number" name="cantidad" id="cantidad" step="1" min="1" required
                                   class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-teal-500 focus:border-teal-500 sm:text-base"
                                   placeholder="Ej: 500, 2.5">
                        </div>
                        
                        <div class="w-1/2">
                            <label for="unidad" class="block text-sm font-medium text-gray-700 mb-1">Unidad</label>
                            <select id="unidad" name="unidad" required
                                    class="mt-1 block w-full px-4 py-3 border border-gray-300 bg-white rounded-xl shadow-sm focus:outline-none focus:ring-teal-500 focus:border-teal-500 sm:text-base">
                                <option value="">Selecciona Unidad</option>
                                <?php foreach ($unidades as $key => $value): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-8 pt-4 border-t border-gray-100 sm:flex sm:flex-row-reverse">
                    <button type="submit" id="submitButton"
                        class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-3 bg-teal-600 text-base font-medium text-white hover:bg-teal-700 focus:outline-none transition duration-150 sm:ml-3 sm:w-auto sm:text-base transform hover:scale-[1.03] active:scale-[0.98] shadow-xl shadow-teal-300/50">
                        <i class="fas fa-save mr-2"></i> Añadir Ingrediente
                    </button>
                    <button type="button" onclick="cerrarModalInventario()" 
                        class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-4 py-3 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none transition duration-150 sm:mt-0 sm:w-auto sm:text-base transform hover:scale-[1.03] active:scale-[0.98]">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: CONFIRMACIÓN DE ELIMINACIÓN -->
 <!-- Un modal separado para evitar borrados accidentales, reforzado con color rojo -->
<div id="deleteConfirmModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="delete-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4 sm:p-0">
        
        <div class="fixed inset-0 bg-gray-900 bg-opacity-80 transition-opacity duration-200 opacity-0" aria-hidden="true" onclick="cerrarModalEliminarInventario()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-middle bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all ease-out duration-300 scale-95 opacity-0 sm:my-8 sm:max-w-md sm:w-full">
            
            <div class="p-8 text-center">
                
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100">
                    <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                
                <h3 class="mt-5 text-2xl font-extrabold text-gray-900" id="delete-modal-title">
                    Eliminar Elemento
                </h3>
                
                <div class="mt-4">
                    <p class="text-base text-gray-600">
                        Esta acción eliminará el siguiente ingrediente de tu inventario:
                    </p>
                    <p id="deleteItemName" class="text-lg font-bold text-red-600 mt-3 p-3 bg-red-50 rounded-lg break-words shadow-inner">
                        [Nombre del Ingrediente]
                    </p>
                    <p class="text-sm font-medium text-red-700 mt-2">
                        Esta acción es irreversible.
                    </p>
                </div>
            </div>
            
            <div class="bg-gray-50 px-8 py-5 sm:flex sm:flex-row-reverse rounded-b-2xl border-t border-gray-100">
                <button type="button" id="confirmDeleteButton" 
                    class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-md px-5 py-3 bg-red-600 text-base font-semibold text-white hover:bg-red-700 focus:outline-none transition duration-150 sm:ml-4 sm:w-auto transform hover:scale-[1.03] active:scale-[0.98]">
                    Sí, Eliminar
                </button>
                <button type="button" onclick="cerrarModalEliminarInventario()" 
                    class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-5 py-3 bg-white text-base font-semibold text-gray-700 hover:bg-gray-100 focus:outline-none transition duration-150 sm:mt-0 sm:w-auto transform hover:scale-[1.03] active:scale-[0.98]">
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