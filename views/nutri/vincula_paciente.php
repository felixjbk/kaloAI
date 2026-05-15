<?php
/**
 * LÓGICA: VINCULACIÓN DE PACIENTES - KaloAI
 * Permite a un nutricionista buscar a un usuario por su correo electrónico 
 * y enviarle una solicitud de seguimiento.
 */

session_start();
require_once __DIR__ . '/../../core/configuracion.php';

/* ==========================================================================
   SEGURIDAD: CONTROL DE ACCESO
   ========================================================================== */
// Solo permitimos que Nutricionistas (2) o Administradores (1) realicen búsquedas.
if (!isset($_SESSION['user_role_id']) || $_SESSION['user_role_id'] > 2) {
    header("Location: ../../index.php");
    exit;
}

/* ==========================================================================
   PROCESAMIENTO DE LA BÚSQUEDA (POST)
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email_busqueda'])) {
    $email = trim($_POST['email_busqueda']);
    $nutriId = $_SESSION['user_id'];
    $pdo = connectDB();

    /* 1. BUSCAR AL CANDIDATO */
    // Solo buscamos usuarios que tengan el Rol 3 (Usuario Estándar).
    // No permitimos que un Nutri invite a otro Nutri o a un Admin.
    $stmt = $pdo->prepare("SELECT id_usuario FROM usuario WHERE correo = ? AND id_rol = 3 LIMIT 1");
    $stmt->execute([$email]);
    $paciente = $stmt->fetch();

    if ($paciente) {
        $idPaciente = $paciente['id_usuario'];
        
        /* 2. VERIFICAR DUPLICADOS O PENDIENTES */
        // Evitamos enviar múltiples solicitudes a la misma persona si ya hay una 'pendiente'.
        $check = $pdo->prepare("
            SELECT id_solicitud 
            FROM solicitudes_vinculacion 
            WHERE id_nutricionista = ? 
            AND id_paciente = ? 
            AND estado = 'pendiente'
        ");
        $check->execute([$nutriId, $idPaciente]);
        
        if ($check->fetch()) {
            // El usuario ya tiene una invitación en su buzón.
            header("Location: dashboard_nutri.php?err=ya_solicitado");
        } else {
            /* 3. CREAR LA INVITACIÓN */
            // Insertamos el registro en la tabla de solicitudes. 
            // El paciente deberá aceptarla desde su propio Dashboard para que el vínculo sea oficial.
            $ins = $pdo->prepare("INSERT INTO solicitudes_vinculacion (id_nutricionista, id_paciente, fecha_solicitud) VALUES (?, ?, NOW())");
            $ins->execute([$nutriId, $idPaciente]);
            
            header("Location: dashboard_nutri.php?msj=solicitud_enviada");
        }
    } else {
        /* 4. USUARIO NO EXISTENTE */
        // Si el correo no existe en la BD o no es un rol de "Paciente".
        header("Location: dashboard_nutri.php?err=usuario_no_encontrado");
    }
    exit;
}