<?php
/**
 * GENERADOR DE LISTA DE COMPRA INTELIGENTE - KaloAI
 * Este script realiza un cruce de datos (JOIN) entre la planificación semanal
 * y el inventario actual para calcular cantidades netas a comprar.
 */

$pageTitle = "KaloAI | Mi Lista de la Compra";
session_start();

require_once __DIR__ . '/../../core/configuracion.php';
require_once __DIR__ . '/../../core/utilidades.php';

// 1. SEGURIDAD: Garantizar que solo usuarios identificados accedan a sus datos
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php');
}

$userId = $_SESSION['user_id'];
$pdo = connectDB();

/**
 * ==========================================================================
 * LÓGICA DE CÁLCULO OPTIMIZADA Y NORMALIZADA
 * El objetivo es que "Leche" y "leche" (o "Arroz" y "Arroz cocido") 
 * se traten como el mismo producto para sumarse correctamente.
 * ==========================================================================
 */

// 1. OBTENCIÓN DE DEMANDA: Extraer ingredientes de todas las recetas en el plan actual
$sqlNecesario = "SELECT dr.nombre_ingrediente, dr.cantidad, cp.raciones, dr.unidad 
                 FROM comida_planificada cp
                 JOIN detalle_receta dr ON cp.id_receta = dr.id_receta
                 JOIN plan_semanal ps ON cp.id_plan = ps.id_plan
                 WHERE ps.id_usuario = ?";

$stmtNec = $pdo->prepare($sqlNecesario);
$stmtNec->execute([$userId]);
$necesariosRaw = $stmtNec->fetchAll();

// 2. PROCESAMIENTO Y LIMPIEZA DE DATOS (Normalización)
$necesariosProcesados = [];

foreach ($necesariosRaw as $nec) {
    // --- LIMPIEZA LINGÜÍSTICA ---
    // Convertimos a minúsculas y eliminamos tildes para evitar duplicidad (ej: "Tomate" vs "tomate")
    $nombreOriginal = trim($nec['nombre_ingrediente']);
    $nombreNorm = mb_strtolower($nombreOriginal, 'UTF-8');
    $nombreNorm = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $nombreNorm);
    
    // FILTRADO DE ADJETIVOS: Eliminamos palabras que no definen el producto base
    // Esto permite que "Arroz cocido" se sume con "Arroz"
    $palabras_sobrantes = [
        'cocido', 'en seco', 'peso en seco', 'en hojuelas', 'en copos', 
        'integrales', 'blanco', '(peso )', '(peso)', 'frescas', 'semidesnatada'
    ];
    $nombreNorm = str_replace($palabras_sobrantes, '', $nombreNorm);
    $nombreNorm = trim($nombreNorm);

    // --- UNIFICACIÓN DE UNIDADES REBELDES ---
    $unidad = $nec['unidad'];
    
    // Homogeneización forzada para líquidos comunes (Aceite/Leche) 
    // Asegura que si una receta pide 'g' y otra 'ml', se agrupen bajo la misma métrica
    if (strpos($nombreNorm, 'aceite') !== false || strpos($nombreNorm, 'leche') !== false) {
        $unidad = 'ml';
    }

    // Cálculo de volumen total según raciones planificadas
    $cantidadTotal = $nec['cantidad'] * $nec['raciones'];

    // Creación de Clave Única (Nombre + Unidad) para agrupar ingredientes idénticos
    $key = $nombreNorm . "_" . $unidad;

    if (!isset($necesariosProcesados[$key])) {
        $necesariosProcesados[$key] = [
            'nombre' => ucfirst($nombreNorm), // Capitalizamos para la vista final
            'cantidad' => 0,
            'unidad' => $unidad
        ];
    }
    $necesariosProcesados[$key]['cantidad'] += $cantidadTotal;
}

/* ==========================================================================
   3. OBTENCIÓN DE SUMINISTROS (Inventario)
   Normalizamos el inventario con el mismo criterio para que el "match" sea exacto
   ========================================================================== */
$stmtInv = $pdo->prepare("SELECT nombre_ingrediente, cantidad, unidad FROM inventario WHERE id_usuario = ?");
$stmtInv->execute([$userId]);
$inventarioRaw = $stmtInv->fetchAll();

$inventarioProcesado = [];
foreach ($inventarioRaw as $inv) {
    $nInv = mb_strtolower(trim($inv['nombre_ingrediente']), 'UTF-8');
    $nInv = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $nInv);
    $uInv = $inv['unidad'];
    
    // Almacenamos lo que ya existe en despensa usando la clave única
    $inventarioProcesado[$nInv . "_" . $uInv] = $inv['cantidad'];
}

/* ==========================================================================
   4. CRUCE DE DATOS Y CÁLCULO DE FALTANTES
   Comparamos: [Lo que necesito para la semana] - [Lo que tengo en casa]
   ========================================================================== */
$listaCompra = [];
foreach ($necesariosProcesados as $key => $data) {
    $tengo = $inventarioProcesado[$key] ?? 0;
    $falta = $data['cantidad'] - $tengo;

    // Solo se añade a la lista si la cantidad necesaria supera lo disponible en inventario
    if ($falta > 0) {
        $listaCompra[] = [
            'nombre' => $data['nombre'],
            'total_recetas' => $data['cantidad'], // Demanda bruta
            'en_despensa' => $tengo,              // Stock actual
            'comprar' => $falta,                  // Cantidad neta a adquirir
            'unidad' => $data['unidad']
        ];
    }
}

// Carga del componente de cabecera 
include_once __DIR__ . '/../templates/header.php';
?>

<!-- CONTENEDOR DE LA LISTA: Fondo gris claro para resaltar las tarjetas de ingredientes -->
<div class="min-h-[62vh] bg-gray-50 pt-20 pb-12">
    <div class="container mx-auto px-4 py-8">
        
        <!-- CABECERA: Título con branding y opción de exportación a PDF -->
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-10 gap-6">
            <div>
                <div class="flex items-center group">
                    <a href="../dashboard.php" class="mr-4 bg-teal-50 p-3 rounded-2xl text-teal-600 hover:bg-teal-600 hover:text-white transition-all shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <h1 class="text-5xl font-black text-gray-900 italic tracking-tight">
                        Lista de <span class="text-teal-600">Compra</span>
                    </h1>
                </div>
                <p class="text-gray-500 font-medium mt-4 ml-16 italic">Basado en tu planificación semanal e inventario actual.</p>
            </div>
            
            <!-- EXPORTAR: Enlace al script PHP que genera el PDF (usando Dompdf) -->
            <a href="descargar_lista.php" class="bg-white border-2 border-gray-100 hover:border-teal-500 text-gray-700 font-black py-4 px-8 rounded-2xl transition-all flex items-center gap-3 shadow-sm text-xs uppercase tracking-widest">
                <span>📄</span> Descargar PDF
            </a>
        </div>

        <!-- ESTADO VACÍO (UX): Mensaje de éxito si no hay faltantes -->
        <?php if (empty($listaCompra)): ?>
            <div class="text-center py-20 bg-white rounded-[2.5rem] border-2 border-dashed border-gray-200">
                <div class="text-6xl mb-4">✅</div>
                <p class="text-gray-400 font-black uppercase tracking-widest text-sm">¡Estás al día!</p>
                <p class="text-gray-400 mt-2">Tienes todos los ingredientes necesarios en tu inventario.</p>
                <a href="inventario.php" class="inline-block mt-6 px-8 py-3 bg-teal-600 text-white font-bold rounded-2xl hover:bg-teal-700 transition-all">Ver mi inventario</a>
            </div>
        <?php else: ?>
            
            <!-- GRID DE ITEMS: Cada tarjeta representa un ingrediente faltante -->
            <div class="grid gap-4">
                <?php foreach ($listaCompra as $item): ?>
                    <div class="bg-white rounded-[2rem] p-6 shadow-sm border border-gray-100 flex flex-col md:flex-row md:items-center justify-between hover:shadow-md transition-all group">
                        
                        <!-- Columna Izquierda: Identificación del Ingrediente -->
                        <div class="flex items-center gap-5">
                            <div class="w-14 h-14 bg-teal-50 text-teal-600 rounded-2xl flex items-center justify-center text-2xl shadow-inner  group-hover:text-white transition-all">
                                🛒
                            </div>
                            <div>
                                <h3 class="font-black text-gray-900 text-lg tracking-tight uppercase">
                                    <?= htmlspecialchars($item['nombre']) ?>
                                </h3>
                                <!-- Desglose de Cantidades: Muestra el requerimiento total vs lo disponible -->
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-xs font-bold rounded-md uppercase">
                                        Necesitas: <?= (float)$item['total_recetas'] ?> <?= $item['unidad'] . ($item['unidad'] == 'unidad' && $item['total_recetas'] > 1 ? 'es' : '') ?>
                                    </span>
                                    
                                    <?php if($item['en_despensa'] > 0): ?>
                                        <span class="px-2 py-0.5 bg-amber-50 text-amber-600 text-xs font-bold rounded-md uppercase">
                                            Tienes: <?= (float)$item['en_despensa'] ?> <?= $item['unidad'] . ($item['unidad'] == 'unidad' && $item['total_recetas'] > 1 ? 'es' : '') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Columna Derecha: Acción de "Comprado" -->
                        <div class="flex items-center justify-between md:justify-end gap-8 mt-4 md:mt-0 border-t md:border-t-0 pt-4 md:pt-0">
                            
                            <!-- Columna Derecha: Acción de "Comprado" -->
                            <div class="text-left md:text-right">
                                <span class="block text-[10px] uppercase tracking-widest font-black text-gray-400">Faltante</span>
                                <div class="text-3xl font-black text-teal-600 leading-none">
                                    +<?= round($item['comprar'], 2) ?>
                                    <span class="text-sm font-bold text-teal-400"><?= $item['unidad'] . ($item['unidad'] == 'unidad' && $item['total_recetas'] > 1 ? 'es' : '') ?></span>
                                </div>
                            </div>

                            <!-- Botón de Acción: Al pulsar, el item suele moverse al Inventario automáticamente -->
                            <form action="comprar_item.php" method="POST" class="m-0">
                                <input type="hidden" name="nombre" value="<?= htmlspecialchars($item['nombre']) ?>">
                                <input type="hidden" name="cantidad" value="<?= $item['comprar'] ?>">
                                <input type="hidden" name="unidad" value="<?= $item['unidad'] ?>">
                                
                                <button type="submit" class="flex items-center gap-2 bg-gray-900 hover:bg-green-600 text-white px-5 py-3 rounded-2xl font-black text-[16px] tracking-widest uppercase transition-all active:scale-95 shadow-lg shadow-gray-200 hover:shadow-green-100">
                                    <span>✅</span>
                                    <span class="hidden sm:inline">COMPRADO</span>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- BLOQUE DE CONSEJO: Educando al usuario sobre la sincronización -->
            <div class="mt-12 p-8 bg-amber-50/50 rounded-[2.5rem] border-2 border-amber-100/50 flex flex-col md:flex-row gap-6 items-center">
                <div class="bg-amber-100 w-16 h-16 rounded-2xl flex items-center justify-center text-3xl shadow-inner">💡</div>
                <div>
                    <p class="text-amber-900 font-black italic text-lg mb-1">Consejo KaloAI</p>
                    <p class="text-amber-800/80 font-medium leading-relaxed">
                        Esta lista se actualiza automáticamente. Si compras algo, añádelo a tu <b>Inventario</b> para que el sistema lo descuente de tu próxima compra.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- RETORNO AL DASHBOARD -->
        <div class="mt-16 text-center">
            <a href="../dashboard.php" class="inline-flex items-center font-black text-xs uppercase tracking-[0.2em] text-gray-400 hover:text-teal-600 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path></svg>
                Volver al Panel
            </a>
        </div>
    </div>
</div>

<?php 
// Incluir el pie de página
include_once __DIR__ . '/../templates/footer.php'; 
?>