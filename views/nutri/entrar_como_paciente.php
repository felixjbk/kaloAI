<?php
/**
 * LÓGICA: ENTRAR COMO PACIENTE - KaloAI
 * Permite a un nutricionista "suplantar" la sesión de un alumno/paciente
 * para ver su dashboard, editar su dieta o revisar su progreso en tiempo real.
 */

ob_start();
session_start();
require_once __DIR__ . '/../../core/configuracion.php';

/* ==========================================================================
   SEGURIDAD DE ACCESO
   ========================================================================== */
// Solo Nutricionistas (2) o Admins (1) pueden ejecutar esta acción.
if (!isset($_SESSION['user_role_id']) || $_SESSION['user_role_id'] > 2) {
    header("Location: ../../index.php");
    exit;
}

$idPaciente = $_GET['id_paciente'] ?? null;
$nutriId = $_SESSION['user_id'];
$pdo = connectDB();

// Si no hay ID de paciente, volvemos al panel principal
if (!$idPaciente) {
    header("Location: dashboard_nutri.php");
    exit;
}

/* ==========================================================================
   VERIFICACIÓN DE VÍNCULO
   ========================================================================== */
// IMPORTANTE: Verificamos que el paciente solicitado realmente esté asignado
// a este nutricionista para evitar que un nutri vea datos de pacientes ajenos.
$stmt = $pdo->prepare("SELECT id_usuario FROM perfil WHERE id_usuario = ? AND id_nutricionista = ?");
$stmt->execute([$idPaciente, $nutriId]);

if ($stmt->fetch()) {
    
    /* 1. PRESERVAR IDENTIDAD REAL */
    // Guardamos los datos del nutricionista en variables temporales de sesión
    // para poder "volver a ser nosotros" cuando cerremos el modo espejo.
    $_SESSION['real_user_id'] = $nutriId; 
    $_SESSION['real_user_role'] = $_SESSION['user_role_id'];
    
    /* 2. ACTIVAR MODO ESPEJO (SUPLANTACIÓN) */
    // Sobrescribimos las variables de sesión principales con los datos del paciente.
    $_SESSION['user_id'] = (int)$idPaciente;
    $_SESSION['user_role_id'] = 3; // Forzamos rol de Usuario Estándar
    
    // Bandera para mostrar avisos en el Dashboard (ej: "Estás viendo el perfil de...")
    $_SESSION['is_impersonating'] = true;

    // Redirigimos al Dashboard común, que ahora mostrará los datos del paciente
    header("Location: ../dashboard.php");
} else {
    // Intento de acceso no autorizado a un paciente ajeno
    echo "Error de seguridad: No tienes permiso para gestionar a este usuario o el vínculo no existe.";
}

ob_end_flush();