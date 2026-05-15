<?php
/**
 * VISTA: REGISTRO DE USUARIOS - KaloAI
 * Gestiona la creación de nuevas cuentas, validando duplicados y 
 * asegurando la integridad de los datos en múltiples tablas (Transacciones).
 */

// Configuración de la vista y metadatos
$isAuth = true; 
$pageTitle = "KaloAI | Registro de Usuario";
$message = ''; 
$messageType = 'success'; 

session_start();
require_once __DIR__ . '/../../core/configuracion.php';
require_once __DIR__ . '/../../core/utilidades.php';

/* ==========================================================================
   CONTROL DE ACCESO
   ========================================================================== */
// Si el usuario ya tiene una sesión iniciada, no debería registrarse de nuevo.
if (isset($_SESSION['user_id'])) {
    redirect('../dashboard.php');
}

/* ==========================================================================
   PROCESAMIENTO DEL REGISTRO (POST)
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    /* 1. SANITIZACIÓN Y CAPTURA */
    // Limpiamos los inputs para evitar ataques XSS o inyecciones básicas.
    $email = limpiarInput($_POST['email'] ?? '');
    $nombre = limpiarInput($_POST['nombre'] ?? ''); 
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    /* 2. VALIDACIÓN DE REGLAS DE NEGOCIO */
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "El formato del correo electrónico no es válido.";
    }
    if (empty($nombre)) {
        $errors[] = "El nombre es obligatorio.";
    }
    if (strlen($password) < 8) {
        $errors[] = "La contraseña debe tener al menos 8 caracteres.";
    }
    if ($password !== $passwordConfirm) {
        $errors[] = "Las contraseñas no coinciden.";
    }

    if (empty($errors)) {
        try {
            $pdo = connectDB();
            
            /* 3. VERIFICAR DUPLICADOS */
            // Comprobamos si el email ya existe para evitar errores de clave única en la BD.
            $stmt = $pdo->prepare("SELECT id_usuario FROM usuario WHERE correo = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $errors[] = "Este correo electrónico ya está registrado. Intenta iniciar sesión.";
            } else {
                /* 4. SEGURIDAD DE CONTRASEÑA */
                // Nunca guardamos contraseñas en texto plano. Usamos BCRYPT mediante hashContrasena().
                $hashedPassword = hashContrasena($password);
                
                /* 5. PROCESO DE ALTA (TRANSACCIÓN) */
                // Usamos transacciones porque si falla la creación del perfil, 
                // debemos deshacer la creación del usuario para no dejar datos huérfanos.
                $pdo->beginTransaction();

                // A) Insertar en la tabla 'usuario' (Por defecto Rol 3: Usuario)
                $stmt = $pdo->prepare("INSERT INTO usuario (correo, contrasena_hash, id_rol, fecha_creacion) VALUES (?, ?, 3, NOW())");
                $stmt->execute([$email, $hashedPassword]);
                
                // Obtenemos el ID generado para vincularlo al perfil
                $newUserId = $pdo->lastInsertId();

                // B) Crear el perfil vinculado con el nombre del usuario
                $stmtPerfil = $pdo->prepare("INSERT INTO perfil (id_usuario, nombre) VALUES (?, ?)");
                $stmtPerfil->execute([$newUserId, $nombre]);

                // Si todo ha ido bien, confirmamos los cambios permanentemente
                $pdo->commit();
                
                $message = "¡Registro exitoso! Ya puedes iniciar sesión.";
                $messageType = 'success';
                
                // Limpiamos variables para que no se muestren en el formulario tras el éxito
                $email = $nombre = "";
            }
        } catch (Exception $e) {
            /* 6. GESTIÓN DE FALLOS */
            // Si algo falla, el rollback asegura que la base de datos vuelva a su estado original.
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error crítico en registro: " . $e->getMessage());
            $errors[] = "Hubo un problema técnico. Por favor, inténtalo más tarde.";
        }
    }
    
    // Consolidación de errores para mostrar en la UI
    if (!empty($errors)) {
        $message = "<ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
        $messageType = 'error';
    }
}

// Carga del componente de cabecera 
include_once __DIR__ . '/../templates/header.php'; 
?>

<!-- CONTENEDOR DE REGISTRO: Centrado total para enfoque en la conversión -->
<div class="container mx-auto px-6 py-12 flex justify-center items-center min-h-[80vh]">
    <div class="w-full max-w-md bg-white p-10 rounded-[2.5rem] shadow-xl shadow-gray-100 border border-gray-50">
        
        <!-- ENCABEZADO: Título aspiracional y subtítulo de marca -->
        <div class="text-center mb-10">
            <h2 class="text-4xl font-black text-gray-900 italic tracking-tighter">Únete a <span class="text-teal-600">KaloAI</span></h2>
            <p class="text-gray-400 text-xs font-bold uppercase tracking-widest mt-2">Tu nutrición inteligente comienza aquí</p>
        </div>
        
        <!-- FEEDBACK DINÁMICO: Alertas de éxito o error (ej. email duplicado o contraseñas no coincidentes) -->
        <?php if ($message): ?>
            <div class="p-4 mb-6 rounded-2xl text-sm font-medium animate-fade-in <?php echo $messageType === 'success' ? 'bg-teal-50 text-teal-700 border border-teal-100' : 'bg-red-50 text-red-700 border border-red-100'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- FORMULARIO DE ALTA: Captura de nuevos perfiles -->
        <form method="POST" action="registro.php" class="space-y-5">
            <div>
                <label for="nombre" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-2">Nombre Completo</label>
                <input id="nombre" name="nombre" type="text" required 
                       class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-medium focus:ring-2 focus:ring-teal-500 transition-all"
                       placeholder="Ej. Juan Pérez"
                       value="<?php echo htmlspecialchars($nombre ?? ''); ?>">
            </div>
            
            <div>
                <label for="email" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-2">Email</label>
                <input id="email" name="email" type="email" required 
                       class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-medium focus:ring-2 focus:ring-teal-500 transition-all"
                       placeholder="correo@ejemplo.com"
                       value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>
            
            <!-- SEGURIDAD: Doble validación de contraseña en grid responsivo -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-2">Contraseña</label>
                    <input id="password" name="password" type="password" required 
                           class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-medium focus:ring-2 focus:ring-teal-500 transition-all"
                           placeholder="••••••••">
                </div>
                <div>
                    <label for="password_confirm" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-2">Confirmar</label>
                    <input id="password_confirm" name="password_confirm" type="password" required 
                           class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-medium focus:ring-2 focus:ring-teal-500 transition-all"
                           placeholder="••••••••">
                </div>
            </div>

            <!-- SEGURIDAD: Doble validación de contraseña en grid responsivo -->
            <div class="pt-4">
                <button type="submit"
                        class="w-full bg-teal-600 text-white font-black py-5 rounded-2xl shadow-lg shadow-teal-100 hover:bg-teal-700 hover:-translate-y-1 transition-all uppercase text-xs tracking-widest">
                    Crear mi cuenta
                </button>
            </div>
        </form>

        <!-- FOOTER DEL FORMULARIO: Redirect para usuarios ya existentes -->
        <div class="mt-10 text-center border-t border-gray-50 pt-6">
            <p class="text-sm text-gray-500 font-medium">
                ¿Ya eres miembro? 
                <a href="login.php" class="text-teal-600 font-bold hover:underline ml-1">Entrar ahora</a>
            </p>
        </div>
    </div>
</div>

<?php 
// Incluir el pie de página
include_once __DIR__ . '/../templates/footer.php'; 
?>