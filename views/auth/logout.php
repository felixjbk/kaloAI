<?php
/**
 * LÓGICA: CIERRE DE SESIÓN (LOGOUT) - KaloAI
 * Finaliza de forma segura la sesión del usuario actual, limpia las variables
 * de entorno y redirige al formulario de acceso.
 */

// 1. Reanudamos la sesión actual para poder manipularla
session_start();

// 2. Limpiamos todas las variables de sesión almacenadas en memoria ($_SESSION)
// Esto borra el 'user_id', 'user_role_id', etc., pero la sesión sigue existiendo en el servidor.
$_SESSION = [];

// 3. Destruimos físicamente la sesión en el servidor y el ID de sesión (Cookie)
// Tras este paso, el usuario es oficialmente un "invitado" para el sistema.
session_destroy();

/**
 * REDIRECCIÓN DE SALIDA
 * Usamos una ruta relativa explícita. Al cerrar sesión, lo más lógico
 * es enviar al usuario de vuelta a la pantalla de Login.
 */
header("Location: ./login.php");

// Finalizamos la ejecución del script para evitar que se procese cualquier código extra
exit;
?>