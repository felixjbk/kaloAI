<?php
/**
 * PLANTILLA: PIE DE PÁGINA (FOOTER) - KaloAI
 * Este archivo cierra las etiquetas principales del DOM, gestiona la
 * navegación global secundaria y carga los recursos de JavaScript.
 */
?>
</main>
<footer class="relative overflow-hidden bg-gray-950 text-gray-400">
    <!-- Detalle de marca: filo teal arriba y un resplandor muy suave -->
    <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-teal-500/70 to-transparent" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -top-48 left-1/2 h-96 w-[44rem] max-w-[120vw] -translate-x-1/2 rounded-full bg-teal-500/10 blur-3xl" aria-hidden="true"></div>

    <div class="container relative mx-auto px-5 py-14 sm:px-6 md:py-16">
        <div class="flex flex-col items-center gap-10 text-center md:flex-row md:items-center md:justify-between md:text-left">

            <!-- BRANDING Y SLOGAN: Refuerzo de identidad al final de la página -->
            <div class="max-w-sm">
                <a href="<?php echo BASE_URL; ?>index.php" class="inline-block rounded-xl transition-opacity hover:opacity-80 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-500/30">
                    <img src="<?= BASE_URL ?>images/logo.png" class="mx-auto h-12 w-auto brightness-125 md:mx-0" alt="KaloAI">
                </a>
                <p class="mt-4 text-sm leading-relaxed text-gray-400">
                    Planificación alimentaria con IA. Nutrición inteligente, simple y personalizada.
                </p>
            </div>

            <!-- NAVEGACIÓN SECUNDARIA: Enlaces rápidos y control de sesión dinámico -->
            <nav class="flex flex-wrap justify-center gap-x-2 gap-y-1 text-sm font-medium md:justify-end" aria-label="Pie de página">
                <a href="<?php echo BASE_URL; ?>index.php" class="rounded-full px-3 py-2 text-gray-400 transition-colors duration-300 hover:bg-white/5 hover:text-teal-300">Inicio</a>
                <a href="<?php echo BASE_URL; ?>views/dashboard.php" class="rounded-full px-3 py-2 text-gray-400 transition-colors duration-300 hover:bg-white/5 hover:text-teal-300">Dashboard</a>

                <!-- LÓGICA DE SESIÓN: Muestra Registro o Logout según el estado del usuario -->
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo BASE_URL; ?>views/auth/registro.php" class="rounded-full px-3 py-2 text-gray-400 transition-colors duration-300 hover:bg-white/5 hover:text-teal-300">Registro</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>views/auth/logout.php" class="rounded-full px-3 py-2 text-gray-400 transition-colors duration-300 hover:bg-white/5 hover:text-teal-300">Cerrar Sesión</a>
                <?php endif; ?>

                <a href="<?php echo BASE_URL; ?>public/contacto.php" class="rounded-full px-3 py-2 text-gray-400 transition-colors duration-300 hover:bg-white/5 hover:text-teal-300">Contacto</a>
                <a href="<?php echo BASE_URL; ?>public/privacidad.php" class="rounded-full px-3 py-2 text-gray-400 transition-colors duration-300 hover:bg-white/5 hover:text-teal-300">Privacidad</a>
            </nav>
        </div>

        <!-- COPYRIGHT: Año dinámico mediante PHP -->
        <div class="mt-12 border-t border-white/10 pt-6 text-center md:text-left">
            <p class="text-xs tracking-wide text-gray-500">
                &copy; <?php echo date('Y'); ?> KaloAI. Todos los derechos reservados.
            </p>
        </div>
    </div>

    <!-- CARGA DE SCRIPTS: Funcionalidades core y librería de gráficos -->
    <script src="<?php echo BASE_URL; ?>public/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // En modo oscuro, los textos y las líneas de los gráficos en tonos claros
        if (window.Chart && document.documentElement.classList.contains('dark')) {
            Chart.defaults.color = '#aab6b4';
            Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.08)';
        }
    </script>

</footer>
</body>
</html>
