<?php
/**
 * LÓGICA: FINALIZAR MODO ESPEJO - KaloAI
 * Restaura la identidad original del nutricionista y limpia las variables
 * temporales utilizadas durante la suplantación del paciente.
 */

ob_start();
session_start();

/* ==========================================================================
   RESTAURACIÓN DE IDENTIDAD
   ========================================================================== */
// Verificamos si realmente estamos en modo suplantación para evitar
// errores al intentar acceder a índices de $_SESSION que no existen.
if (isset($_SESSION['is_impersonating'])) {
    
    /* 1. RECUPERAR VALORES ORIGINALES */
    // Devolvemos al usuario su ID y su Rol real (Nutricionista o Admin)
    // que habíamos guardado previamente en 'real_user_id'.
    $_SESSION['user_id']      = $_SESSION['real_user_id'];
    $_SESSION['user_role_id'] = $_SESSION['real_user_role'];
    
    /* 2. LIMPIEZA DE HUELLAS (SEGURIDAD) */
    // Es fundamental eliminar estas variables para que el sistema no crea 
    // que seguimos suplantando a alguien. 
    // Usamos unset() para borrarlas completamente del servidor.
    unset($_SESSION['real_user_id']);
    unset($_SESSION['real_user_role']);
    unset($_SESSION['is_impersonating']);
    
    // Nota: No usamos session_destroy() porque queremos mantener la sesión 
    // del nutricionista abierta, solo queremos "cambiarnos de ropa".
}

/* ==========================================================================
   REDIRECCIÓN DE RETORNO
   ========================================================================== */
// Una vez recuperada la identidad, enviamos al profesional de vuelta 
// a su centro de mando (Dashboard de Nutricionista).
header("Location: dashboard_nutri.php");

ob_end_flush();