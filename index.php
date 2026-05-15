<?php
/**
 * PÁGINA DE INICIO (LANDING PAGE) - KaloAI
 * Esta es la cara pública del proyecto. Utiliza un diseño moderno basado en Tailwind CSS
 * para presentar la propuesta de valor: planificación alimentaria mediante IA.
 */

$pageTitle = "KaloAI | Planificación Alimentaria Inteligente";
$isAuth = false; // Control para no aplicar estilos específicos de login/registro
session_start();

// Carga de utilidades globales 
require_once __DIR__ . '/core/utilidades.php'; 

// Carga del componente de cabecera 
include_once __DIR__ . '/views/templates/header.php'; 
?>

<!-- SECCIÓN HERO: El primer impacto visual del usuario -->
<section class="bg-white min-h-[94vh] cabecera flex items-center justify-center relative">
    <!-- Capa de fondo con gradiente radial sutil para dar profundidad -->
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,_var(--tw-color-teal-50)_20%,_transparent_80%)] opacity-70"></div>

    <div class="container mx-auto px-6 text-center">
        <!-- Título principal con tipografía gigante y contraste de color -->
        <h1 class="text-7xl md:text-9xl font-extrabold text-gray-900 leading-none mb-6">
            <span class="block">Tu Nutrición.</span> 
            <span class="text-teal-600 block">Planificada por IA.</span>
        </h1>

        <!-- Propuesta de valor: Qué hace la App -->
        <p class="text-2xl md:text-3xl text-gray-700 mb-12 max-w-4xl mx-auto font-light">
            KaloAI usa <span class="text-teal-600">Inteligencia Artificial</span> para generar automáticamente menús, recetas y listas de la compra adaptadas a tus objetivos calóricos, preferencias y lo que ya tienes en casa.
        </p>
        
        <!-- CTA (Call to Action) CONDICIONAL: 
             Si no hay sesión, invita a registrarse. Si la hay, lleva al Dashboard. -->
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="<?php echo BASE_URL; ?>views/auth/registro.php" 
                class="inline-block bg-teal-600 text-white font-black py-4 px-12 rounded-full text-xl hover:bg-teal-700 transition duration-300 shadow-2xl shadow-teal-500/50 transform hover:scale-105 hover:shadow-teal-600/60">
                Empieza Gratis Ahora
            </a>
            <p class="text-sm text-gray-500 mt-6 font-medium">
                No requiere tarjeta. Empieza a planificar en 30 segundos.
            </p>
        <?php else: ?>
            <p class="text-xl text-teal-600 font-semibold mb-6">
                ¡Bienvenido de nuevo!
            </p>
            <a href="<?php echo BASE_URL; ?>views/dashboard.php" 
                class="inline-block bg-teal-600 text-white font-black py-4 px-12 rounded-full text-xl hover:bg-teal-700 transition duration-300 shadow-2xl shadow-teal-500/50 transform hover:scale-105 hover:shadow-teal-600/60">
                Ir a mi Dashboard
            </a>
        <?php endif; ?>
    </div>
</section>

<!-- SECCIÓN "PASOS": Explicación del funcionamiento del servicio -->
<section class="py-16 md:py-24 bg-gray-50">
    <div class="container mx-auto px-6">
        <h2 class="text-4xl md:text-5xl font-extrabold text-center text-gray-900 mb-16">
            Tu planificación, resumida en <span class="text-teal-600">3 pasos simples</span>
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
            
            <div class="group relative p-10 bg-white rounded-3xl shadow-xl transition duration-500 transform hover:shadow-teal-300/50 hover:shadow-2xl hover:-translate-y-2 border border-gray-100">
                <div class="absolute -top-6 left-1/2 transform -translate-x-1/2">
                    <div class="bg-teal-600 text-white h-12 w-12 flex items-center justify-center rounded-full text-xl font-bold shadow-lg">1</div>
                </div>
                <div class="text-teal-600 mb-6 flex justify-center mt-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 group-hover:text-teal-700 transition duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.192-2.058-.512-3.004z" />
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 text-center mb-3">Establece tu Perfil Nutricional</h3>
                <p class="text-gray-600 text-center">Configura tu objetivo calórico, todas tus preferencias dietéticas y la frecuencia de comidas que deseas.</p>
            </div>

            <div class="group relative p-10 bg-white rounded-3xl shadow-xl transition duration-500 transform hover:shadow-teal-300/50 hover:shadow-2xl hover:-translate-y-2 border border-gray-100">
                 <div class="absolute -top-6 left-1/2 transform -translate-x-1/2">
                    <div class="bg-teal-600 text-white h-12 w-12 flex items-center justify-center rounded-full text-xl font-bold shadow-lg">2</div>
                </div>
                <div class="text-teal-600 mb-6 flex justify-center mt-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 group-hover:text-teal-700 transition duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M11 4a2 2 0 10-4 0v1h4v-1zm4 1h-4v-1a2 2 0 10-4 0v1H7v14h10V5zm-4 7v4" />
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 text-center mb-3">La IA Crea tu Menú en Segundos</h3>
                <p class="text-gray-600 text-center">Nuestro motor inteligente diseña automáticamente un menú semanal completo, equilibrando nutrición y coste.</p>
            </div>

            <div class="group relative p-10 bg-white rounded-3xl shadow-xl transition duration-500 transform hover:shadow-teal-300/50 hover:shadow-2xl hover:-translate-y-2 border border-gray-100">
                <div class="absolute -top-6 left-1/2 transform -translate-x-1/2">
                    <div class="bg-teal-600 text-white h-12 w-12 flex items-center justify-center rounded-full text-xl font-bold shadow-lg">3</div>
                </div>
                <div class="text-teal-600 mb-6 flex justify-center mt-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 group-hover:text-teal-700 transition duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M10 16h.01" />
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 text-center mb-3">Tu Lista de la Compra Lista</h3>
                <p class="text-gray-600 text-center">Obtén la lista exacta que necesitas, organizada por secciones para optimizar tu tiempo en el súper.</p>
            </div>

        </div>
    </div>
</section>

<div class="h-20 bg-gradient-to-b from-gray-50 to-teal-50"></div>

<!-- SECCIÓN DE BENEFICIOS: Cards con bordes superiores destacados -->
<section class="pt-4 pb-32 bg-teal-50">
    <div class="container mx-auto px-6">
        <div class="max-w-4xl mx-auto text-center mb-16">
            <h2 class="text-4xl md:text-6xl font-extrabold text-teal-900 leading-tight mb-4">Ahorra Tiempo, Gana Salud.</h2>
            <p class="text-xl md:text-2xl text-teal-800 font-light max-w-3xl mx-auto">
                KaloAI es tu nutricionista personal y planificador de comidas disponible las 24 horas del día.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-10 max-w-6xl mx-auto">
            <div class="p-8 bg-white rounded-3xl shadow-xl hover:shadow-2xl transition duration-300 border-t-4 border-teal-600">
                <div class="flex items-start space-x-5">
                    <div class="flex-shrink-0 text-teal-600 p-4 bg-teal-50 rounded-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-2xl font-bold text-gray-900 mb-2 mt-1">Cero Estrés de Planificación</h4>
                        <p class="text-gray-600 leading-relaxed">Eliminamos la fatiga de decisión planificando la semana en segundos, adaptando recetas a lo que ya tienes en casa.</p>
                    </div>
                </div>
            </div>

            <div class="p-8 bg-white rounded-3xl shadow-xl hover:shadow-2xl transition duration-300 border-t-4 border-teal-600">
                <div class="flex items-start space-x-5">
                    <div class="flex-shrink-0 text-teal-600 p-4 bg-teal-50 rounded-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.192-2.058-.512-3.004z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-2xl font-bold text-gray-900 mb-2 mt-1">Nutrición de Precisión</h4>
                        <p class="text-gray-600 leading-relaxed">Planes generados con un balance exacto de macros. Tus objetivos se consiguen con base científica y de forma sostenible.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SECCIÓN TESTIMONIOS: Slider/Carousel de prueba social -->
<section class="py-16 md:py-40 bg-teal-700">
    <div class="container mx-auto px-6 text-center">
        <h2 class="text-3xl md:text-5xl font-extrabold text-white mb-4">
            ¡Deja de calcular y empieza a comer bien!
        </h2>
        <p class="text-xl text-teal-100 mb-10">
            Únete a KaloAI y transforma tu manera de relacionarte con la comida.
        </p>

        <div class="mt-20 mb-10 max-w-4xl mx-auto px-6">
            <div id="testimony-wrapper" class="relative overflow-hidden rounded-2xl shadow-2xl">
                <div id="testimony-track" class="flex transition-transform duration-700 ease-in-out">
                    
                    <div class="flex-shrink-0 w-full p-10 bg-white">
                        <blockquote class="text-xl italic text-gray-800 leading-snug">
                            <span class="text-teal-600 text-xl font-serif">“</span>
                            Desde que uso KaloAI, no solo logré mis objetivos de peso en 3 meses, sino que el ahorro en la lista de la compra es tangible. Es planificación inteligente y económica.
                            <span class="text-teal-600 text-xl font-serif">”</span>
                        </blockquote>
                        <div class="mt-6 flex items-center justify-start space-x-3 border-t pt-4 border-gray-100">
                            <span class="text-teal-700 font-extrabold text-lg">— Laura M.</span>
                            <span class="text-base text-gray-500"> | Usuaria Avanzada, 35 años</span>
                        </div>
                    </div>

                    <div class="flex-shrink-0 w-full p-10 bg-white">
                        <blockquote class="text-xl italic text-gray-800 leading-snug">
                            <span class="text-teal-600 text-xl font-serif">“</span>
                            Antes odiaba pensar en qué cocinar. KaloAI me ahorra más de 4 horas de planificación semanal. Simplemente presiono un botón y tengo mi menú listo.
                            <span class="text-teal-600 text-xl font-serif">”</span>
                        </blockquote>
                        <div class="mt-6 flex items-center justify-start space-x-3 border-t pt-4 border-gray-100">
                            <span class="text-teal-700 font-extrabold text-lg">— Javier R.</span>
                            <span class="text-base text-gray-500"> | Padre de familia, 42 años</span>
                        </div>
                    </div>

                    <div class="flex-shrink-0 w-full p-10 bg-white">
                        <blockquote class="text-xl italic text-gray-800 leading-snug">
                            <span class="text-teal-600 text-xl font-serif">“</span>
                            He mejorado mi alimentación sin esfuerzo y sin sentir que estoy "a dieta". Lo mejor es que los menús se adaptan a mis gustos exactos y objetivos deportivos.
                            <span class="text-teal-600 text-xl font-serif">”</span>
                        </blockquote>
                        <div class="mt-6 flex items-center justify-start space-x-3 border-t pt-4 border-gray-100">
                            <span class="text-teal-700 font-extrabold text-lg">— Marta G.</span>
                            <span class="text-base text-gray-500"> | Usuaria Fit, 29 años</span>
                        </div>
                    </div>

                    <div class="flex-shrink-0 w-full p-10 bg-white">
                        <blockquote class="text-xl italic text-gray-800 leading-snug">
                            <span class="text-teal-600 text-xl font-serif">“</span>
                            Como entrenador personal, valoro la precisión. KaloAI ofrece un balance de macros profesional y elimina la necesidad de contar calorías manualmente.
                            <span class="text-teal-600 text-xl font-serif">”</span>
                        </blockquote>
                        <div class="mt-6 flex items-center justify-start space-x-3 border-t pt-4 border-gray-100">
                            <span class="text-teal-700 font-extrabold text-lg">— Andrés L.</span>
                            <span class="text-base text-gray-500"> | Entrenador y Nutricionista</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Indicadores de navegación del Slider -->
            <div id="testimony-dots" class="flex justify-center space-x-2 mt-8">
                <span class="dot h-2 w-2 bg-teal-600 rounded-full transition duration-300"></span>
                <span class="dot h-2 w-2 bg-gray-300 rounded-full transition duration-300"></span>
                <span class="dot h-2 w-2 bg-gray-300 rounded-full transition duration-300"></span>
                <span class="dot h-2 w-2 bg-gray-300 rounded-full transition duration-300"></span>
            </div>
        </div>
        
        <!-- BLOQUE DE CONVERSIÓN DINÁMICO -->
        <?php if (!isset($_SESSION['user_id'])): ?>
            <!-- CASO 1: Usuario No Autenticado (Prospecto) -->
            <a href="<?php echo BASE_URL; ?>views/auth/registro.php" 
                class="inline-block bg-white text-teal-700 font-bold py-4 px-10 rounded-full text-xl hover:bg-gray-100 transition duration-300 shadow-2xl transform hover:scale-105">
                Regístrate y comienza a planificar
            </a>
        <?php else: ?>
            <!-- CASO 2: Usuario Autenticado (Cliente recurrente) -->
            <a href="<?php echo BASE_URL; ?>views/dashboard.php" 
                class="inline-block bg-white text-teal-700 font-bold py-4 px-10 rounded-full text-xl hover:bg-gray-100 transition duration-300 shadow-2xl transform hover:scale-105">
                Ir al Dashboard
            </a>
        <?php endif; ?>
    </div>
</section>

<?php 
// Incluir el pie de página
include_once __DIR__ . '/views/templates/footer.php'; 
?>