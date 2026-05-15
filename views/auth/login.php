<?php
/**
 * VISTA: ACCESO AL SISTEMA (LOGIN) - KaloAI
 * Gestiona la autenticación de usuarios, validación de credenciales
 * y redirección estratégica según el rol del usuario.
 */

session_start();
require_once __DIR__ . '/../../core/configuracion.php';
require_once __DIR__ . '/../../core/utilidades.php';

/* ==========================================================================
   CONTROL DE SESIÓN ACTIVA
   ========================================================================== */
// Si el usuario ya está logueado, lo enviamos directamente a su panel 
// para evitar que vea el formulario de login innecesariamente.
if (isset($_SESSION['user_id'])) {
    redirigirPorRol($_SESSION['user_role_id']);
}

$error = '';

/* ==========================================================================
   PROCESAMIENTO DEL FORMULARIO (POST)
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validación básica de campos vacíos
    if (!empty($email) && !empty($password)) {
        try {
            $pdo = connectDB();
            
            /* 1. BÚSQUEDA DEL USUARIO */
            // Obtenemos el hash de la contraseña y el estado de la cuenta.
            $stmt = $pdo->prepare("SELECT id_usuario, contrasena_hash, id_rol, activo FROM usuario WHERE correo = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            /* 2. VERIFICACIÓN DE CREDENCIALES */
            // password_verify compara el texto plano con el hash seguro de la BD.
            if ($user && password_verify($password, $user['contrasena_hash'])) {
            
                /* 3. VERIFICACIÓN DE ESTADO (SEGURIDAD) */
                // Si el administrador ha marcado 'activo = 0', impedimos el acceso.
                if ($user['activo'] == 0) {
                    $error = "🚫 Esta cuenta ha sido suspendida. Contacta con soporte.";
                } else {
                    /* 4. LOGIN EXITOSO */
                    // Regeneramos el ID de sesión para prevenir ataques de fijación de sesión.
                    session_regenerate_id(true);

                    // Almacenamos datos clave en la superglobal $_SESSION
                    $_SESSION['user_id'] = $user['id_usuario'];
                    $_SESSION['user_role_id'] = $user['id_rol'];

                    // Redirigir según el rol (Admin -> Panel Admin, Usuario -> Dashboard, etc.)
                    redirigirPorRol($user['id_rol']);
                }
            } else {
                // Error genérico por seguridad (no indicar si el email existe o no)
                $error = "Correo o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            $error = "Error técnico: No se pudo conectar con el servicio de autenticación.";
        }
    } else {
        $error = "Por favor, rellena todos los campos.";
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - KaloAI</title>
    <!-- USO DE TAILWIND -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-6">
    <!-- BOTÓN VOLVER: Enlace para regresar al index -->
    <div class="absolute top-8 left-8">
        <a href="../../index.php" class="flex items-center gap-2 text-gray-400 hover:text-teal-600 transition-all font-bold text-sm uppercase tracking-widest group">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 transition-transform group-hover:-translate-x-1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
            Volver
        </a>
    </div>
    <!-- CONTENEDOR PRINCIPAL: Tarjeta de acceso con diseño minimalista -->
    <div class="max-w-md w-full bg-white p-10 rounded-[40px] shadow-2xl border border-gray-100">
        
        <!-- BRANDING: Identidad visual en el formulario -->
        <div class="text-center mb-10">
            <h1 class="text-4xl font-black text-gray-900 tracking-tighter italic">Kalo<span class="text-teal-600">AI</span></h1>
            <p class="text-gray-400 text-sm mt-2 font-semibold uppercase tracking-widest">Acceso a la plataforma</p>
        </div>

        <!-- GESTIÓN DE ERRORES: Feedback visual ante fallos de autenticación -->
        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-2xl mb-6 text-sm font-bold border border-red-100 animate-pulse text-center">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- FORMULARIO DE ACCESO: Captura de credenciales -->
        <form method="POST" action="" class="space-y-6">
            <div>
                <label class="block text-xs font-black uppercase text-gray-400 mb-2 ml-1">Correo Electrónico</label>
                <input type="email" name="email" required 
                       class="w-full px-5 py-4 bg-gray-50 border border-gray-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500 transition-all text-gray-700"
                       placeholder="ejemplo@kalo.com">
            </div>

            <div>
                <label class="block text-xs font-black uppercase text-gray-400 mb-2 ml-1">Contraseña</label>
                <input type="password" name="password" required 
                       class="w-full px-5 py-4 bg-gray-50 border border-gray-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500 transition-all text-gray-700"
                       placeholder="••••••••">
            </div>

            <!-- ACCIÓN PRINCIPAL: Botón de envío -->
            <button type="submit" 
                    class="w-full bg-teal-600 hover:bg-teal-700 text-white font-black py-4 rounded-2xl shadow-xl shadow-teal-200 transition-all transform hover:scale-[1.02] active:scale-95 uppercase tracking-widest text-sm">
                Entrar ahora
            </button>
        </form>

        <!-- NAVEGACIÓN SECUNDARIA: Enlace alternativo para nuevos usuarios -->
        <div class="mt-10 text-center">
            <p class="text-gray-400 text-sm font-semibold">
                ¿No tienes cuenta? 
                <a href="registro.php" class="text-teal-600 hover:text-teal-800 transition-colors">Regístrate gratis</a>
            </p>
        </div>
    </div>

</body>
</html>
