<?php
/**
 * LÓGICA: GENERACIÓN DE PDF DE COMPRA - KaloAI
 * Este script calcula la diferencia entre los ingredientes necesarios para el 
 * plan semanal y el inventario actual del usuario, generando un PDF descargable.
 */

session_start();
require_once __DIR__ . '/../../core/configuracion.php';
require_once __DIR__ . '/../../core/utilidades.php';

// Carga de Dompdf vía Composer (Asegúrate de haber ejecutado 'composer require dompdf/dompdf')
require_once __DIR__ . '/../../vendor/autoload.php'; 

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id'])) {
    die("Error: No has iniciado sesión.");
}

$userId = $_SESSION['user_id'];
$pdo = connectDB();

/* ==========================================================================
   1. CÁLCULO DE NECESIDADES VS. INVENTARIO
   ========================================================================== */

// A. Obtener ingredientes necesarios según el plan semanal
$sqlNecesario = "SELECT dr.nombre_ingrediente, dr.cantidad, cp.raciones, dr.unidad 
                 FROM comida_planificada cp
                 JOIN detalle_receta dr ON cp.id_receta = dr.id_receta
                 JOIN plan_semanal ps ON cp.id_plan = ps.id_plan
                 WHERE ps.id_usuario = ?";

$stmtNec = $pdo->prepare($sqlNecesario);
$stmtNec->execute([$userId]);
$necesariosRaw = $stmtNec->fetchAll();

$necesariosProcesados = [];

// B. Normalización y Unificación (Evita duplicados por tildes o mayúsculas)
foreach ($necesariosRaw as $nec) {
    $nombreNorm = mb_strtolower(trim($nec['nombre_ingrediente']), 'UTF-8');
    $nombreNorm = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $nombreNorm);
    
    // Limpieza de palabras que ensucian la comparación
    $palabras_sobrantes = ['cocido', 'en seco', 'integrales', 'blanco', 'frescas'];
    $nombreNorm = str_replace($palabras_sobrantes, '', $nombreNorm);
    $nombreNorm = trim($nombreNorm);

    $unidad = $nec['unidad'];
    $cantidadTotal = $nec['cantidad'] * $nec['raciones'];
    $key = $nombreNorm . "_" . $unidad;

    if (!isset($necesariosProcesados[$key])) {
        $necesariosProcesados[$key] = [
            'nombre' => ucfirst($nombreNorm),
            'cantidad' => 0,
            'unidad' => $unidad
        ];
    }
    $necesariosProcesados[$key]['cantidad'] += $cantidadTotal;
}

// C. Obtener Inventario actual del usuario para restar
$stmtInv = $pdo->prepare("SELECT nombre_ingrediente, cantidad, unidad FROM inventario WHERE id_usuario = ?");
$stmtInv->execute([$userId]);
$inventarioRaw = $stmtInv->fetchAll();

$inventarioProcesado = [];
foreach ($inventarioRaw as $inv) {
    $nInv = mb_strtolower(trim($inv['nombre_ingrediente']), 'UTF-8');
    $nInv = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $nInv);
    $uInv = $inv['unidad'];
    $inventarioProcesado[$nInv . "_" . $uInv] = $inv['cantidad'];
}

// D. Cruce de datos: ¿Qué falta realmente?
$listaCompra = [];
foreach ($necesariosProcesados as $key => $data) {
    $tengo = $inventarioProcesado[$key] ?? 0;
    $falta = $data['cantidad'] - $tengo;

    if ($falta > 0) {
        $listaCompra[] = [
            'nombre' => $data['nombre'],
            'comprar' => $falta,
            'unidad' => $data['unidad']
        ];
    }
}

/* ==========================================================================
   2. CONSTRUCCIÓN DEL HTML PARA EL PDF
   ========================================================================== */

if (empty($listaCompra)) {
    die("¡Genial! Tienes todo lo necesario en tu despensa.");
}

$html = '
<html>
<head>
<style>
    body { font-family: "Helvetica", sans-serif; color: #1a1a1a; padding: 30px; }
    .header { text-align: center; border-bottom: 3px solid #0d9488; padding-bottom: 15px; margin-bottom: 25px; }
    .header h1 { color: #0d9488; text-transform: uppercase; letter-spacing: 2px; margin: 0; }
    .item { border-bottom: 1px solid #e5e7eb; padding: 12px 0; clear: both; }
    .qty { float: right; font-weight: bold; background: #f3f4f6; padding: 4px 10px; border-radius: 5px; }
    .cb { display: inline-block; width: 16px; height: 16px; border: 2px solid #0d9488; margin-right: 12px; vertical-align: middle; border-radius: 3px; }
    .name { vertical-align: middle; font-weight: 600; font-size: 14px; }
</style>
</head>
<body>
    <div class="header">
        <h1>Compra KaloAI</h1>
        <p>Generada el ' . date('d/m/Y') . '</p>
    </div>';

foreach ($listaCompra as $item) {
    $html .= '
    <div class="item">
        <span class="cb"></span>
        <span class="name">' . htmlspecialchars($item['nombre']) . '</span>
        <span class="qty">' . (float)round($item['comprar'], 2) . ' ' . $item['unidad'] . '</span>
    </div>';
}

$html .= '</body></html>';

/* ==========================================================================
   3. RENDERIZADO Y STREAMING DEL PDF
   ========================================================================== */
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A5', 'portrait'); // Formato A5: ideal para llevar en el móvil o imprimir pequeño
$dompdf->render();

// Forzamos la descarga del archivo
$dompdf->stream("Lista_KaloAI_" . date('d_m_Y') . ".pdf", ["Attachment" => true]);