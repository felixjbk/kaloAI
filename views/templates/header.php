<?php
/**
 * PLANTILLA: CABECERA (HEADER) - KaloAI
 * Este archivo gestiona los metadatos, la navegación global y la lógica visual
 */

// 1. Asegurar que la sesión esté iniciada para acceder a $_SESSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Variables de configuración de rutas
$appName = "KaloAI";
// Definimos BASE_URL: Vital para que las imágenes y enlaces no se rompan al navegar por subcarpetas
if (!defined('BASE_URL')) {
    define('BASE_URL', '/kaloai/'); 
}

$userName = 'Usuario'; 

/* ==========================================================================
   LÓGICA DE USUARIO LOGUEADO
   ========================================================================== */
if (isset($_SESSION['user_id'])) {
    try {
        // Usamos __DIR__ para que PHP encuentre el archivo sin importar desde dónde se incluya este header
        require_once __DIR__ . '/../../core/configuracion.php';
        $pdo = connectDB();
        
        // Buscamos el nombre real en la tabla perfil
        $stmt = $pdo->prepare("SELECT nombre FROM perfil WHERE id_usuario = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $perfil = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($perfil && !empty($perfil['nombre'])) {
            $userName = $perfil['nombre'];
        }
    } catch (\Exception $e) {
        error_log("Error en header: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? $appName); ?></title>
    
    <!-- RECURSOS EXTERNOS: Frameworks de UI y estilos personalizados -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/style.css">
</head>
<body class="bg-gray-50 antialiased">

    <!-- BARRA DE IMPERSONACIÓN: Se activa cuando un nutricionista gestiona la cuenta de un paciente -->
    <?php if (isset($_SESSION['is_impersonating']) && $_SESSION['is_impersonating'] === true): ?>
        <div class="bg-orange-600 text-white py-3 px-6 shadow-xl sticky top-0 z-50 animate-pulse-slow">
            <div class="container mx-auto flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <!-- Muestra el nombre del paciente suplantado -->
                    <span class="font-black text-[10px] uppercase tracking-[0.2em]">Modo Gestión: <?= htmlspecialchars($userName) ?></span>
                </div>
                <!-- Acción para revertir la suplantación y volver al panel de nutricionista -->
                <a href="<?= BASE_URL ?>views/nutri/salir_paciente.php" class="bg-white text-orange-600 px-4 py-1.5 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-gray-100 transition-all shadow-md">
                    Finalizar Gestión
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- NAVEGACIÓN PRINCIPAL: Sticky header con efecto de desenfoque -->
    <header class="bg-white/80 backdrop-blur-md border-b border-gray-100 sticky <?php echo isset($_SESSION['is_impersonating']) ? 'top-[52px]' : 'top-0'; ?> z-40">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            
            <!-- LOGOTIPO -->
            <a href="<?= BASE_URL ?>index.php" class="hover:opacity-80 transition-opacity">
                <img src="<?= BASE_URL ?>images/logo.png" style="height: 40px;" alt="KaloAI">
            </a>

            <div class="flex items-center space-x-6">
                <!-- VISTA USUARIO AUTENTICADO: Perfil y Cierre de sesión -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="hidden md:block text-right">
                        <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest leading-none mb-1">Identificado como</p>
                        <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($userName); ?></p>
                    </div>

                    <a href="<?php echo BASE_URL; ?>views/auth/logout.php" 
                       class="bg-gray-50 text-gray-400 hover:text-red-500 p-2 rounded-xl transition-colors"
                       title="Cerrar sesión">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </a>
                <!-- VISTA INVITADO: Accesos a Auth -->
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>views/auth/login.php" class="text-xs font-black uppercase tracking-widest text-gray-400 hover:text-teal-600 transition-colors">Login</a>
                    <a href="<?php echo BASE_URL; ?>views/auth/registro.php" class="bg-teal-600 text-white px-5 py-2.5 rounded-2xl text-xs font-black uppercase tracking-widest shadow-lg shadow-teal-100 hover:scale-105 transition-all">Registro</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    
    <main class="flex-grow">