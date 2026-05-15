<?php
/**
 * LÓGICA: GESTIÓN DE SOLICITUD DE VINCULACIÓN - KaloAI
 * Este script procesa la decisión del paciente (Aceptar/Rechazar) sobre
 * una invitación enviada por un nutricionista.
 */

session_start();

// Importamos la configuración global y conexión a BD
require_once __DIR__ . '/../../core/configuracion.php';

/* ==========================================================================
   1. SEGURIDAD Y CAPTURA DE DATOS
   ========================================================================== */
// Solo usuarios autenticados pueden interactuar con sus solicitudes.
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$idSol = $_GET['id'] ?? null;
$accion = $_GET['accion'] ?? null;
$idPaciente = $_SESSION['user_id'];
$pdo = connectDB();

/* ==========================================================================
   2. PROCESAMIENTO DE LA DECISIÓN
   ========================================================================== */
if ($idSol && $accion) {
    try {
        // VERIFICACIÓN DE PROPIEDAD:
        // Nos aseguramos de que la solicitud exista, pertenezca a este paciente
        // y que aún esté en estado 'pendiente' para evitar procesamientos duplicados.
        $stmt = $pdo->prepare("
            SELECT id_nutricionista 
            FROM solicitudes_vinculacion 
            WHERE id_solicitud = ? 
            AND id_paciente = ? 
            AND estado = 'pendiente'
        ");
        $stmt->execute([$idSol, $idPaciente]);
        $solicitud = $stmt->fetch();

        if ($solicitud) {
            $idNutri = $solicitud['id_nutricionista'];

            if ($accion === 'aceptar') {
                /* --- INICIO DE TRANSACCIÓN --- */
                // Usamos una transacción porque requerimos que DOS tablas se actualicen con éxito.
                $pdo->beginTransaction();

                // A. Actualizar el estado de la solicitud a 'aceptada'
                $upd1 = $pdo->prepare("UPDATE solicitudes_vinculacion SET estado = 'aceptada' WHERE id_solicitud = ?");
                $upd1->execute([$idSol]);

                // B. Vincular oficialmente al nutricionista en el perfil del paciente
                // A partir de este momento, el nutri tendrá acceso al "Modo Gestión".
                $upd2 = $pdo->prepare("UPDATE perfil SET id_nutricionista = ? WHERE id_usuario = ?");
                $upd2->execute([$idNutri, $idPaciente]);

                // Confirmamos ambos cambios
                $pdo->commit();
                
            } elseif ($accion === 'rechazar') {
                // C. Si rechaza, solo marcamos la solicitud como 'rechazada'
                // No tocamos la tabla 'perfil', manteniendo al usuario independiente.
                $upd3 = $pdo->prepare("UPDATE solicitudes_vinculacion SET estado = 'rechazada' WHERE id_solicitud = ?");
                $upd3->execute([$idSol]);
            }
        }
    } catch (Exception $e) {
        // Si algo falla en el proceso de 'aceptar', revertimos cualquier cambio parcial.
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error crítico en aceptar_nutri.php: " . $e->getMessage());
    }
}

/* ==========================================================================
   3. RETORNO AL DASHBOARD
   ========================================================================== */
// Redirigimos al usuario a su panel principal. 
// Las notificaciones de éxito/error se pueden manejar mediante parámetros GET si se desea.
header("Location: ../dashboard.php");
exit;