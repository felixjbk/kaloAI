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

// 3. Estado visual
// Modo gestión (nutricionista dentro de la cuenta de un paciente). Una sola
// condición para la barra y para el desplazamiento de la cabecera: así la
// cabecera solo baja cuando la barra existe de verdad.
$impersonando = isset($_SESSION['is_impersonating']) && $_SESSION['is_impersonating'] === true;

// Inicial del usuario para el avatar
$inicial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? $appName); ?></title>
    <?php include __DIR__ . '/tema.php'; ?>
    
    <meta name="description" content="Recetas creadas con IA según tus calorías, objetivos e intolerancias, con plan semanal, inventario y lista de la compra. Para usuarios y nutricionistas.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="KaloAI">
    <meta property="og:title" content="KaloAI | Planificación Alimentaria Inteligente">
    <meta property="og:description" content="Recetas creadas con IA según tus calorías, objetivos e intolerancias, con plan semanal, inventario y lista de la compra.">
    <meta property="og:url" content="https://kaloai.infinityfreeapp.com/">
    <meta property="og:image" content="https://kaloai.infinityfreeapp.com/images/og.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">

    <!-- TIPOGRAFÍAS: Inter (texto, la que ya pedía style.css) y Plus Jakarta Sans (titulares) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- RECURSOS EXTERNOS: Frameworks de UI y estilos personalizados -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Solo tipografía: Inter como fuente base y Plus Jakarta Sans como
        // fuente de titulares (clase font-display)
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
                        display: ['"Plus Jakarta Sans"', 'Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                },
            },
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/style.css">
</head>
<body class="bg-gray-50 antialiased">
    <!-- MODO OSCURO: después de los estilos de Tailwind para poder sobrescribirlos -->
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/oscuro.css">

    <!-- BARRA DE IMPERSONACIÓN: Se activa cuando un nutricionista gestiona la cuenta de un paciente.
         Alto fijo de 52px: la cabecera se coloca justo debajo (top-[52px]). -->
    <?php if ($impersonando): ?>
        <div class="sticky top-0 z-50 flex h-[52px] items-center bg-gradient-to-r from-orange-600 to-amber-500 text-white shadow-lg shadow-orange-900/20">
            <div class="container mx-auto flex items-center justify-between gap-4 px-5 sm:px-6">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="relative flex h-2.5 w-2.5 shrink-0" aria-hidden="true">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white/70"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-white"></span>
                    </span>
                    <!-- Muestra el nombre del paciente suplantado -->
                    <span class="truncate text-[11px] font-bold uppercase tracking-[0.16em]">
                        Modo Gestión: <span class="font-black"><?= htmlspecialchars($userName) ?></span>
                    </span>
                </div>
                <!-- Acción para revertir la suplantación y volver al panel de nutricionista -->
                <a href="<?= BASE_URL ?>views/nutri/salir_paciente.php"
                   class="shrink-0 rounded-full bg-white px-4 py-1.5 text-[11px] font-bold uppercase tracking-wider text-orange-600 shadow-sm transition hover:bg-orange-50">
                    Finalizar Gestión
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- NAVEGACIÓN PRINCIPAL: Sticky header con efecto de desenfoque -->
    <header class="sticky <?php echo $impersonando ? 'top-[52px]' : 'top-0'; ?> z-40 border-b border-gray-900/5 bg-white/75 shadow-[0_1px_12px_-6px_rgba(15,118,110,0.15)] backdrop-blur-xl backdrop-saturate-150">
        <nav class="container mx-auto flex items-center justify-between gap-4 px-5 py-3.5 sm:px-6" aria-label="Principal">

            <!-- LOGOTIPO -->
            <a href="<?= BASE_URL ?>index.php" class="shrink-0 rounded-xl transition-opacity hover:opacity-80 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-200">
                <img src="<?= BASE_URL ?>images/logo.png" class="h-9 w-auto sm:h-10" alt="KaloAI">
            </a>

            <div class="flex items-center gap-2 sm:gap-3">
                <!-- TEMA: alterna claro / oscuro y lo recuerda (views/templates/tema.php) -->
                <button type="button" id="kalo-tema"
                        class="grid h-11 w-11 place-items-center rounded-full text-gray-500 transition-colors hover:bg-teal-50 hover:text-teal-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-100"
                        aria-label="Cambiar a modo oscuro" title="Modo oscuro">
                    <svg class="kalo-luna h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                    <svg class="kalo-sol h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    </svg>
                </button>
                <script>
                    (function () {
                        var boton = document.getElementById('kalo-tema');
                        var raiz = document.documentElement;
                        var rotular = function () {
                            var oscuro = raiz.classList.contains('dark');
                            boton.setAttribute('aria-label', oscuro ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
                            boton.title = oscuro ? 'Modo claro' : 'Modo oscuro';
                        };
                        rotular();
                        boton.addEventListener('click', function () {
                            var oscuro = raiz.classList.toggle('dark');
                            try { localStorage.setItem('kalo-tema', oscuro ? 'oscuro' : 'claro'); } catch (e) {}
                            rotular();
                        });
                    })();
                </script>

                <!-- VISTA USUARIO AUTENTICADO: Perfil y Cierre de sesión -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="flex items-center gap-3 rounded-full border border-gray-900/5 bg-white/80 py-1 pl-1 pr-1 shadow-sm md:pr-4">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-teal-500 to-teal-700 text-sm font-bold text-white" aria-hidden="true">
                            <?php echo htmlspecialchars($inicial); ?>
                        </span>
                        <div class="hidden text-left md:block">
                            <p class="mb-0.5 text-[10px] font-semibold uppercase leading-none tracking-[0.14em] text-gray-400">Identificado como</p>
                            <p class="max-w-[12rem] truncate text-sm font-semibold leading-tight text-gray-900"><?php echo htmlspecialchars($userName); ?></p>
                        </div>
                    </div>

                    <a href="<?php echo BASE_URL; ?>views/auth/logout.php"
                       class="grid h-11 w-11 place-items-center rounded-full text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500 focus:outline-none focus-visible:ring-4 focus-visible:ring-red-100"
                       title="Cerrar sesión" aria-label="Cerrar sesión">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                    </a>
                <!-- VISTA INVITADO: Accesos a Auth -->
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>views/auth/login.php"
                       class="rounded-full px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors hover:bg-teal-50 hover:text-teal-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-100">
                        Login
                    </a>
                    <a href="<?php echo BASE_URL; ?>views/auth/registro.php"
                       class="rounded-full bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-teal-600/25 transition hover:-translate-y-px hover:bg-teal-700 hover:shadow-lg hover:shadow-teal-600/30 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-200">
                        Registro
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main class="flex-grow">