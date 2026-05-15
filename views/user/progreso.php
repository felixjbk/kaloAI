<?php
/**
 * GESTIÓN DE PROGRESO Y SEGUIMIENTO ANTROPOMÉTRICO - KaloAI
 * Este script gestiona el historial de peso y medidas, asegurando la sincronización
 * automática con el perfil metabólico para mantener los cálculos de TDEE actualizados.
 */

$pageTitle = "KaloAI | Mi Progreso y Seguimiento";
session_start();

require_once __DIR__ . '/../../core/utilidades.php';
require_once __DIR__ . '/../../core/configuracion.php'; 

// CONTROL DE ACCESO: Verificación de sesión activa
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php');
}

$userId = $_SESSION['user_id'];
$pdo = connectDB();
$progresoData = [];
$message = null;
$messageType = 'success';

/**
 * ==========================================================
 * ACCIÓN: ELIMINAR REGISTRO Y RE-SINCRONIZAR
 * Al borrar un dato, el sistema busca la medición anterior más reciente
 * para no dejar el perfil con datos obsoletos.
 * ==========================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_fecha'])) {

    $fechaBorrar = limpiarInput($_POST['delete_fecha']);

    try {
        $pdo->beginTransaction(); // Inicio de operación atómica

        // 1. Eliminación del registro puntual en el historial
        $sqlDelete = "DELETE FROM progreso WHERE id_usuario = ? AND fecha = ?";
        $stmtDelete = $pdo->prepare($sqlDelete);
        $stmtDelete->execute([$userId, $fechaBorrar]);

        // 2. RE-CÁLCULO: Localizar el nuevo "último peso" tras la eliminación
        $sqlNuevoUltimo = "SELECT peso_kg FROM progreso 
                           WHERE id_usuario = ? 
                           ORDER BY fecha DESC LIMIT 1";
        $stmtNuevo = $pdo->prepare($sqlNuevoUltimo);
        $stmtNuevo->execute([$userId]);
        $nuevoPesoData = $stmtNuevo->fetch(PDO::FETCH_ASSOC);

        // 3. SINCRONIZACIÓN: Actualizar la tabla 'perfil' con el peso remanente
        $nuevoPeso = $nuevoPesoData ? $nuevoPesoData['peso_kg'] : 0;
        
        $sqlPerfilUpdate = "UPDATE perfil SET peso_kg = ?, fecha_actualizacion = NOW() WHERE id_usuario = ?";
        $stmtUpdatePerfil = $pdo->prepare($sqlPerfilUpdate);
        $stmtUpdatePerfil->execute([$nuevoPeso, $userId]);

        $pdo->commit(); // Consolidación de cambios

        $f_mostrar = function_exists('formatearFecha') ? formatearFecha($fechaBorrar) : $fechaBorrar;
        $message = "Registro del " . $f_mostrar . " eliminado. Perfil sincronizado con el historial.";

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error al eliminar y sincronizar: " . $e->getMessage());
        $message = "No se pudo eliminar el registro o actualizar el perfil.";
        $messageType = 'error';
    }
}

/**
 * ==========================================================
 * ACCIÓN: REGISTRAR NUEVO PROGRESO
 * Valida límites físicos y actualiza el estado actual del usuario.
 * ==========================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_progreso'])) {

    // Sanitización y tipado de datos médicos
    $fecha = limpiarInput($_POST['fecha']);
    $peso_kg_input = filter_var($_POST['peso_kg'], FILTER_VALIDATE_FLOAT);
    $cintura_cm = filter_var($_POST['cintura_cm'], FILTER_VALIDATE_FLOAT);
    $nota = mb_substr(limpiarInput($_POST['nota']), 0, 100); 
    $hoy = date('Y-m-d');
    
    $errors = [];

    // VALIDACIONES DE INTEGRIDAD NUTRICIONAL
    if (!$fecha || $fecha > $hoy) {
        $errors[] = "La fecha no puede ser futura.";
    }

    if (!$peso_kg_input || $peso_kg_input < 30 || $peso_kg_input > 350) {
        $errors[] = "Por favor, introduce un peso realista (30 - 350 kg).";
    }

    if ($_POST['cintura_cm'] !== '' && ($cintura_cm < 40 || $cintura_cm > 250)) {
        $errors[] = "La medida de cintura debe estar entre 40 y 250 cm.";
    }

    if (empty($errors)) {
        try {
            // Evitar duplicidad de registros en una misma fecha
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM progreso WHERE id_usuario = ? AND fecha = ?");
            $checkStmt->execute([$userId, $fecha]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $f_error = function_exists('formatearFecha') ? formatearFecha($fecha) : $fecha;
                $message = "Ya existe un registro para el " . $f_error . ". Borra el anterior para actualizar.";
                $messageType = 'error';
            } else {
                $pdo->beginTransaction();

                $cintura_val = ($cintura_cm > 0) ? $cintura_cm : null;

                // A. Persistencia en historial de progreso
                $sqlProgreso = "INSERT INTO progreso (id_usuario, fecha, peso_kg, cintura_cm, nota) 
                                VALUES (?, ?, ?, ?, ?)";
                $stmtP = $pdo->prepare($sqlProgreso);
                $stmtP->execute([$userId, $fecha, $peso_kg_input, $cintura_val, $nota]);

                // B. ACTUALIZACIÓN DINÁMICA: Si es la medición más reciente, actualiza el perfil principal
                $sqlUltima = "SELECT MAX(fecha) FROM progreso WHERE id_usuario = ?";
                $stmtUltima = $pdo->prepare($sqlUltima);
                $stmtUltima->execute([$userId]);
                $fechaMasReciente = $stmtUltima->fetchColumn();

                if ($fecha >= $fechaMasReciente) {
                    $sqlPerfil = "UPDATE perfil SET peso_kg = ?, fecha_actualizacion = NOW() WHERE id_usuario = ?";
                    $stmtU = $pdo->prepare($sqlPerfil);
                    $stmtU->execute([$peso_kg_input, $userId]);
                }

                $pdo->commit();
                $message = "¡Progreso guardado! Tu peso actual se ha actualizado en el perfil.";
                $messageType = 'success';
            }
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Error en tabla perfil o progreso: " . $e->getMessage());
            $message = "Error en la base de datos al sincronizar el perfil.";
            $messageType = 'error';
        }
    } else {
        $message = $errors[0];
        $messageType = 'error';
    }
}

/**
 * ==========================================================
 * EXTRACCIÓN DE DATOS PARA ANÁLISIS VISUAL
 * Prepara los datos para ser consumidos por librerías de gráficos (como Chart.js).
 * ==========================================================
 */
try {
    // Orden ASCENDENTE: Requisito técnico para graficar series temporales correctamente
    $sql = "SELECT fecha, peso_kg, cintura_cm, nota 
             FROM progreso 
             WHERE id_usuario = ? 
             ORDER BY fecha ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $progresoData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // HISTORIAL VISUAL: Invertimos el orden para mostrar lo más reciente primero en la tabla
    $progresoHistorial = array_reverse($progresoData);

} catch (\PDOException $e) {
    error_log("Error al cargar historial de progreso: " . $e->getMessage());
    $message = "No se pudo cargar el historial de progreso.";
    $messageType = 'error';
}

// Inyección de datos al contexto de JavaScript para renderizado de gráficas
$jsProgresoData = json_encode($progresoData);

// Carga del componente de cabecera 
include_once __DIR__ . '/../templates/header.php';
?>

<!-- CONTENEDOR DE PROGRESO: Enfoque en visualización de datos y analítica personal -->
<div class="container mx-auto px-4 py-8">

    <div class="flex flex-col md:flex-row md:items-center justify-between mb-10 gap-6">
        <div>
            <div class="flex items-center group">
                <a href="../dashboard.php" class="mr-4 bg-teal-50 p-3 rounded-2xl text-teal-600 hover:bg-teal-600 hover:text-white transition-all shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h1 class="text-5xl font-black text-gray-900 italic tracking-tight">
                    Mi <span class="text-teal-600">Progreso</span>
                </h1>
            </div>
            <p class="text-gray-500 font-medium mt-4 ml-16">Visualiza tu evolución y mantén el control de tus objetivos.</p>
        </div>
    </div>

    <!-- SISTEMA DE FEEDBACK: Alertas de éxito o error al guardar mediciones -->
    <?php if ($message): ?>
        <div class="mb-8 p-5 rounded-3xl font-bold text-center border-2 <?php echo $messageType === 'success' ? 'bg-teal-50 border-teal-100 text-teal-700' : 'bg-red-50 border-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- DASHBOARD DE GRÁFICAS: Se oculta por defecto hasta que JS procesa los datos -->
    <div id="chartContainer" class="mb-10" style="display: none;">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Gráfica Principal (Sparkline/Evolución) -->
            <div class="lg:col-span-3 bg-white p-8 rounded-[2.5rem] shadow-sm border-2 border-gray-50">
                <div class="h-72 relative">
                    <!-- Canvas para Chart.js -->
                    <canvas id="sparklineChart"></canvas>
                </div>
                <!-- Indicador de Tendencia: Calculado por JS para mostrar si el peso sube/baja -->
                <div class="mt-6 pt-6 border-t border-gray-50 flex items-center justify-between">
                    <div class="flex items-center">
                        <span id="trendIcon" class="text-2xl mr-3"></span>
                        <div>
                            <p class="text-xs font-black text-gray-400 uppercase tracking-widest">Tendencia actual</p>
                            <p id="trendText" class="font-bold text-gray-700"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARDS DE ESTADÍSTICAS RÁPIDAS: Comparativa automática -->
            <div class="flex flex-col gap-4">
                <div class="bg-white p-6 rounded-[2rem] border-2 border-gray-50 flex-1 flex flex-col justify-center">
                    <p class="text-xs font-black text-gray-400 uppercase tracking-widest">Peso Inicial</p>
                    <p id="statInicial" class="text-3xl font-black text-gray-900 mt-1"></p>
                </div>
                <div class="bg-white p-6 rounded-[2rem] border-2 border-teal-100 bg-teal-50/30 flex-1 flex flex-col justify-center">
                    <p class="text-xs font-black text-teal-600 uppercase tracking-widest">Peso Actual</p>
                    <p id="statActual" class="text-4xl font-black text-teal-600 mt-1"></p>
                </div>
                <div class="bg-white p-6 rounded-[2rem] border-2 border-gray-50 flex-1 flex flex-col justify-center">
                    <p class="text-xs font-black text-gray-400 uppercase tracking-widest">Cambio Total</p>
                    <p id="statCambio" class="text-3xl font-black mt-1"></p>
                </div>
                <div class="bg-white p-6 rounded-[2rem] border-2 border-gray-50 flex-1 flex flex-col justify-center">
                    <p class="text-xs font-black text-gray-400 uppercase tracking-widest">Cintura</p>
                    <p id="statCinturaCambio" class="text-3xl font-black mt-1"></p>
                </div>
            </div>
        </div>
    </div>

    <?php if (count($progresoData) < 2): ?>
        <div class="bg-orange-50 p-8 rounded-[2.5rem] border-2 border-orange-100 text-center mb-10">
            <p class="text-orange-600 font-bold text-lg">Registra al menos 2 días para activar tu gráfica de evolución.</p>
        </div>
    <?php endif; ?>

    <!-- FORMULARIO DE NUEVA MEDICIÓN: Diseño horizontal de 5 columnas -->
    <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border-2 border-gray-50 mb-12">
        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center italic">
            <span class="bg-teal-600 text-white w-8 h-8 rounded-lg flex items-center justify-center mr-3 not-italic text-sm">+</span>
            Nueva Medición
        </h2>
        <form method="POST" action="progreso.php" class="grid grid-cols-1 md:grid-cols-5 gap-6 items-end">
            <input type="hidden" name="register_progreso" value="1">
            
            <div class="md:col-span-1">
                <label class="block text-xs font-black text-gray-400 mb-2 ml-2 uppercase tracking-widest">Fecha</label>
                <input type="date" name="fecha" required value="<?php echo date('Y-m-d'); ?>" class="w-full px-5 py-4 rounded-2xl border-2 border-gray-50 bg-gray-50/50 focus:bg-white focus:border-teal-500 transition-all outline-none font-bold text-gray-700">
            </div>
            
            <div class="md:col-span-1">
                <label class="block text-xs font-black text-gray-400 mb-2 ml-2 uppercase tracking-widest">Peso (kg)</label>
                <input type="number" step="0.1" name="peso_kg" placeholder="00.0" required class="w-full px-5 py-4 rounded-2xl border-2 border-gray-50 bg-gray-50/50 focus:bg-white focus:border-teal-500 transition-all outline-none font-bold text-gray-700">
            </div>
            
            <div class="md:col-span-1">
                <label class="block text-xs font-black text-gray-400 mb-2 ml-2 uppercase tracking-widest">Cintura (cm)</label>
                <input type="number" step="0.1" name="cintura_cm" placeholder="Opcional" class="w-full px-5 py-4 rounded-2xl border-2 border-gray-50 bg-gray-50/50 focus:bg-white focus:border-teal-500 transition-all outline-none font-bold text-gray-700">
            </div>

            <div class="md:col-span-1">
                <label class="block text-xs font-black text-gray-400 mb-2 ml-2 uppercase tracking-widest">Nota</label>
                <input type="text" name="nota" placeholder="¿Cómo te sientes?" class="w-full px-5 py-4 rounded-2xl border-2 border-gray-50 bg-gray-50/50 focus:bg-white focus:border-teal-500 transition-all outline-none font-bold text-gray-700">
            </div>
            
            <button type="submit" class="bg-gray-900 hover:bg-teal-600 text-white px-6 py-4 rounded-2xl font-black uppercase tracking-widest text-xs transition-all shadow-lg transform hover:scale-105 active:scale-95">
                Guardar
            </button>
        </form>
    </div>

    <!-- HISTORIAL: Listado detallado con opción de borrado -->
    <h2 class="text-3xl font-black text-gray-900 mb-8 italic">Historial <span class="text-teal-600">Completo</span></h2>
    
    <?php if (empty($progresoHistorial)): ?>
        <div class="text-center py-12 bg-gray-50 rounded-[2.5rem] border-2 border-dashed border-gray-200">
            <p class="text-gray-400 font-bold">No hay registros aún. ¡Comienza hoy!</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($progresoHistorial as $data): 
                $f_date = function_exists('formatearFecha') ? formatearFecha($data['fecha']) : $data['fecha'];
            ?>
            <div class="bg-white p-6 rounded-[2rem] border-2 border-gray-50 flex justify-between items-center hover:border-teal-100 transition-all group shadow-sm">
                <!-- Información de la entrada -->
                <div class="flex items-center gap-5">
                    <div class="bg-gray-50 p-3 rounded-2xl group-hover:bg-teal-50 transition-colors">
                        <p class="text-[10px] font-black text-gray-400 uppercase leading-none mb-1">Día</p>
                        <p class="font-bold text-gray-700"><?php echo htmlspecialchars($f_date); ?></p>
                    </div>
                    <div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-black text-gray-900"><?php echo number_format($data['peso_kg'], 1); ?></span>
                            <span class="text-xs font-bold text-gray-400 uppercase">kg</span>
                        </div>
                        <?php if($data['cintura_cm']): ?>
                            <p class="text-sm font-medium text-gray-400"><?php echo number_format($data['cintura_cm'], 1); ?> cm cintura</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <?php if($data['nota']): ?>
                        <span class="hidden md:block text-xs bg-gray-50 text-gray-500 px-3 py-1 rounded-full font-bold max-w-[100px] truncate">
                            <?php echo htmlspecialchars($data['nota']); ?>
                        </span>
                    <?php endif; ?>
                    <!-- Acción de borrar: Usa data-attributes para pasar info al modal JS -->
                    <button type="button" 
                            class="btn-delete-progreso p-3 rounded-xl text-gray-300 hover:text-red-500 hover:bg-red-50 transition-all"
                            data-fecha="<?php echo htmlspecialchars($data['fecha']); ?>"
                            data-display-fecha="<?php echo htmlspecialchars($f_date); ?>">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3m3 0h3"/>
                        </svg>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="mt-16 text-center">
        <a href="../dashboard.php" class="inline-flex items-center font-black text-xs uppercase tracking-[0.2em] text-gray-400 hover:text-teal-600 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path></svg>
            Volver al Panel
        </a>
    </div>
</div>

<!-- MODAL DE ELIMINACIÓN -->
<div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm transition-opacity duration-300 opacity-0 pointer-events-none">
    <div class="bg-white rounded-[2.5rem] p-8 w-full max-w-sm transform scale-95 transition-transform duration-300 border-2 border-gray-50 shadow-2xl">
        <div class="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center mb-6 mx-auto">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.39 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        <h3 class="text-xl font-black text-gray-900 mb-2 text-center">¿Eliminar registro?</h3>
        <p class="text-gray-500 text-center mb-8 font-medium">Vas a borrar los datos del <span id="modalFechaDisplay" class="text-gray-900 font-bold"></span>.</p>
        
        <form method="POST" action="progreso.php" id="deleteFormModal" class="flex flex-col gap-3">
            <input type="hidden" name="delete_fecha" id="modalFechaInput">
            <button type="submit" class="w-full py-4 bg-red-500 text-white font-black uppercase tracking-widest text-xs rounded-2xl hover:bg-red-600 transition-all shadow-lg shadow-red-100">Sí, eliminar</button>
            <button type="button" id="cancelDeleteBtn" class="w-full py-4 bg-gray-50 text-gray-400 font-black uppercase tracking-widest text-xs rounded-2xl hover:bg-gray-100 transition-all">Cancelar</button>
        </form>
    </div>
</div>

<!-- DATA BRIDGE: Pasa los datos de PHP a JavaScript -->
<script>
    window.progresoData = <?php echo $jsProgresoData; ?>;
</script>

<?php 
// Incluir el pie de página
include_once __DIR__ . '/../templates/footer.php';
?>