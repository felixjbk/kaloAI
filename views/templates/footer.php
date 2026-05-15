<?php
/**
 * PLANTILLA: PIE DE PÁGINA (FOOTER) - KaloAI
 * Este archivo cierra las etiquetas principales del DOM, gestiona la 
 * navegación global secundaria y carga los recursos de JavaScript.
 */
?>
</main> 
<footer class="bg-gray-900 text-gray-300 py-14">
    <div class="container mx-auto px-6 text-center space-y-8">

        <!-- NAVEGACIÓN SECUNDARIA: Enlaces rápidos y control de sesión dinámico -->
        <nav class="flex flex-wrap justify-center gap-x-8 gap-y-3 text-sm font-medium">
            <a href="<?php echo BASE_URL; ?>index.php" class="text-gray-400 hover:text-teal-400 transition duration-300 tracking-wide">Inicio</a>
            <a href="<?php echo BASE_URL; ?>views/dashboard.php" class="text-gray-400 hover:text-teal-400 transition duration-300 tracking-wide">Dashboard</a>
            
            <!-- LÓGICA DE SESIÓN: Muestra Registro o Logout según el estado del usuario -->
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="<?php echo BASE_URL; ?>views/auth/registro.php" class="text-gray-400 hover:text-teal-400 transition duration-300 tracking-wide">Registro</a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>views/auth/logout.php" class="text-gray-400 hover:text-teal-400 transition duration-300 tracking-wide">Cerrar Sesión</a>
            <?php endif; ?>
            
            <a href="<?php echo BASE_URL; ?>public/contacto.php" class="text-gray-400 hover:text-teal-400 transition duration-300 tracking-wide">Contacto</a>
            <a href="<?php echo BASE_URL; ?>public/privacidad.php" class="text-gray-400 hover:text-teal-400 transition duration-300 tracking-wide">Privacidad</a>
        </nav>
        
        <div class="max-w-md mx-auto border-t border-gray-700 mt-6 mb-6"></div>

        <!-- BRANDING Y SLOGAN: Refuerzo de identidad al final de la página -->
        <div>
            <img src="<?= BASE_URL ?>images/logo.png" class="logo block mx-auto mb-6 h-20" alt="KaloAI">
            <p class="text-md text-gray-500 max-w-lg mx-auto">
                Planificación alimentaria con IA. Nutrición inteligente, simple y personalizada.
            </p>
        </div>

        <!-- COPYRIGHT: Año dinámico mediante PHP -->
        <div class="pt-4">
            <p class="text-xs text-teal-600 tracking-wider">
                &copy; <?php echo date('Y'); ?> KaloAI. Todos los derechos reservados.
            </p>
        </div>

    </div>

    <!-- CARGA DE SCRIPTS: Funcionalidades core y librería de gráficos -->
    <script src="<?php echo BASE_URL; ?>public/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
</footer>
</body>
</html>