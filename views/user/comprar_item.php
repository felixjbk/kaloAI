<?php
/**
 * LÓGICA: ACTUALIZACIÓN DE INVENTARIO - KaloAI
 * Permite al usuario añadir ingredientes a su despensa virtual. 
 * Si el ingrediente ya existe con la misma unidad, incrementa el stock actual.
 */

session_start();
require_once __DIR__ . '/../../core/configuracion.php';
require_once __DIR__ . '/../../core/utilidades.php';

/* ==========================================================================
   PROCESAMIENTO DE INVENTARIO (POST)
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    
    $userId = $_SESSION['user_id'];
    $pdo = connectDB();

    // 1. CAPTURA Y LIMPIEZA DE DATOS
    // Usamos limpiarInput (o sanitize) para evitar XSS y floatval para asegurar
    // que la cantidad sea un número válido antes de realizar operaciones matemáticas.
    $nombre   = limpiarInput($_POST['nombre'] ?? '');
    $cantidad = floatval($_POST['cantidad'] ?? 0);
    $unidad   = limpiarInput($_POST['unidad'] ?? '');

    try {
        /* 2. COMPROBACIÓN DE EXISTENCIA (Lógica de Suma) */
        // Buscamos si el usuario ya tiene ese ingrediente EXACTO (nombre y unidad).
        // Es importante validar la unidad (ej: no es lo mismo 1kg de huevos que 1 unidad).
        $stmt = $pdo->prepare("
            SELECT id_inventario, cantidad 
            FROM inventario 
            WHERE id_usuario = ? 
            AND nombre_ingrediente = ? 
            AND unidad = ?
        ");
        $stmt->execute([$userId, $nombre, $unidad]);
        $existente = $stmt->fetch();

        if ($existente) {
            /* 3. CASO A: EL INGREDIENTE YA EXISTE */
            // Calculamos la nueva cantidad sumando la actual con la entrante.
            $nuevaCantidad = $existente['cantidad'] + $cantidad;
            
            $update = $pdo->prepare("UPDATE inventario SET cantidad = ? WHERE id_inventario = ?");
            $update->execute([$nuevaCantidad, $existente['id_inventario']]);
        } else {
            /* 4. CASO B: NUEO INGREDIENTE */
            // Insertamos una fila completamente nueva en la tabla.
            $insert = $pdo->prepare("
                INSERT INTO inventario (id_usuario, nombre_ingrediente, cantidad, unidad) 
                VALUES (?, ?, ?, ?)
            ");
            $insert->execute([$userId, $nombre, $cantidad, $unidad]);
        }

        // Redirección con éxito
        header("Location: lista_compra.php?success=1");

    } catch (Exception $e) {
        // Registro de error silencioso para el usuario, detallado para el admin
        error_log("Error actualizando inventario: " . $e->getMessage());
        header("Location: lista_compra.php?error=tecnico");
    }
    exit;
}