<?php
/**
 * PÁGINA DE INICIO (LANDING PAGE) - KaloAI
 * Cara pública del proyecto: planificación alimentaria mediante IA.
 * Tailwind (CDN, cargado en header.php) + un pequeño bloque de estilos propio
 * para lo que Tailwind no cubre (tipografía, degradados de texto, animaciones).
 *
 * Compatibilidad con public/js/main.js (carrusel de testimonios):
 *   - #testimony-wrapper  pausa la rotación al pasar el ratón
 *   - #testimony-track    se desplaza con translateX
 *   - sus hijos .flex-shrink-0 son las diapositivas (no usar esa clase dentro)
 *   - #testimony-dots .dot alterna bg-gray-300 / bg-teal-600
 */

$pageTitle = "KaloAI | Planificación Alimentaria Inteligente";
$isAuth = false; // Control para no aplicar estilos específicos de login/registro
session_start();

// Carga de utilidades globales
require_once __DIR__ . '/core/utilidades.php';

// Carga del componente de cabecera
include_once __DIR__ . '/views/templates/header.php';

// Estado de sesión y destinos de los CTA
$logueado     = isset($_SESSION['user_id']);
$urlRegistro  = BASE_URL . 'views/auth/registro.php';
$urlDashboard = BASE_URL . 'views/dashboard.php';
?>

<!-- Tipografías: Plus Jakarta Sans para titulares, Inter para el texto.
     (Lo ideal es mover estas tres líneas a header.php para todo el sitio.) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

<style>
    .kalo-home { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; }
    .kalo-home .font-display { font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif; }

    /* Texto con degradado teal (el color de marca, con más profundidad) */
    .kalo-home .text-gradient {
        background: linear-gradient(100deg, #0f766e 0%, #0d9488 35%, #14b8a6 70%, #0d9488 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }

    /* Retícula muy suave que se desvanece hacia los bordes */
    .kalo-home .bg-grid {
        background-image:
            linear-gradient(to right, rgb(15 118 110 / 0.07) 1px, transparent 1px),
            linear-gradient(to bottom, rgb(15 118 110 / 0.07) 1px, transparent 1px);
        background-size: 44px 44px;
        -webkit-mask-image: radial-gradient(ellipse 70% 60% at 50% 35%, #000 20%, transparent 75%);
        mask-image: radial-gradient(ellipse 70% 60% at 50% 35%, #000 20%, transparent 75%);
    }

    .kalo-home .bg-grid-light {
        background-image:
            linear-gradient(to right, rgb(255 255 255 / 0.06) 1px, transparent 1px),
            linear-gradient(to bottom, rgb(255 255 255 / 0.06) 1px, transparent 1px);
        background-size: 44px 44px;
        -webkit-mask-image: radial-gradient(ellipse 80% 70% at 50% 0%, #000 30%, transparent 80%);
        mask-image: radial-gradient(ellipse 80% 70% at 50% 0%, #000 30%, transparent 80%);
    }

    /* Entradas y flotación (desactivadas con movimiento reducido) */
    @keyframes kalo-rise {
        from { opacity: 0; transform: translateY(18px); }
        to   { opacity: 1; transform: none; }
    }
    @keyframes kalo-float {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-8px); }
    }
    .kalo-home .kalo-rise  { animation: kalo-rise 0.9s cubic-bezier(0.22, 1, 0.36, 1) both; }
    .kalo-home .kalo-float { animation: kalo-float 6s ease-in-out infinite; }
    .kalo-home .delay-1 { animation-delay: 0.12s; }
    .kalo-home .delay-2 { animation-delay: 0.24s; }
    .kalo-home .delay-3 { animation-delay: 1.2s; }

    @media (prefers-reduced-motion: reduce) {
        .kalo-home .kalo-rise,
        .kalo-home .kalo-float { animation: none; }
    }

    /* Puntos del carrusel: main.js alterna bg-gray-300 / bg-teal-600.
       Sobre el fondo teal oscuro, el activo es una píldora blanca. */
    #testimony-dots .dot {
        height: 0.5rem;
        width: 0.5rem;
        border-radius: 999px;
        background: rgb(255 255 255 / 0.3);
        transition: width 0.4s ease, background-color 0.4s ease;
    }
    #testimony-dots .dot.bg-teal-600 {
        width: 1.75rem;
        background: #ffffff;
    }
</style>

<main class="kalo-home overflow-x-clip">

    <!-- ==========================================================
         HERO: propuesta de valor + vista previa del producto
         ========================================================== -->
    <section class="relative isolate overflow-hidden bg-white">
        <!-- Fondo: retícula y dos manchas de luz teal -->
        <div class="bg-grid absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="absolute -top-40 -left-32 -z-10 h-[30rem] w-[30rem] rounded-full bg-teal-200/50 blur-3xl" aria-hidden="true"></div>
        <div class="absolute top-1/3 -right-40 -z-10 h-[26rem] w-[26rem] rounded-full bg-emerald-100/80 blur-3xl" aria-hidden="true"></div>

        <div class="container mx-auto grid items-center gap-14 px-5 pt-12 pb-28 sm:px-6 md:pt-16 grid-cols-1 lg:min-h-[calc(94vh-73px)] lg:grid-cols-[minmax(0,1.08fr)_minmax(0,0.92fr)] lg:gap-10 lg:py-20">

            <!-- Texto -->
            <div class="text-center lg:text-left">
                <span class="kalo-rise inline-flex items-center gap-2 rounded-full border border-teal-100 bg-teal-50/90 px-4 py-1.5 text-xs font-semibold text-teal-700 sm:text-sm">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                    </svg>
                    Planificación alimentaria con IA
                </span>

                <!-- Titular: el tamaño se adapta al ancho (en móvil ya no se sale) -->
                <h1 class="kalo-rise delay-1 font-display mt-6 text-[clamp(2.6rem,11.5vw,4.25rem)] font-extrabold leading-[0.98] tracking-[-0.035em] text-gray-900 lg:text-[clamp(4rem,6.1vw,5.75rem)]">
                    <span class="block">Tu Nutrición.</span>
                    <span class="text-gradient block pb-2">Planificada por&nbsp;IA.</span>
                </h1>

                <p class="kalo-rise delay-2 mx-auto mt-6 max-w-xl text-lg leading-relaxed text-gray-600 sm:text-xl lg:mx-0 lg:text-[1.375rem]">
                    KaloAI usa <span class="font-semibold text-teal-700">Inteligencia Artificial</span> para generar automáticamente menús, recetas y listas de la compra adaptadas a tus objetivos calóricos, preferencias y lo que ya tienes en casa.
                </p>

                <div class="kalo-rise delay-2 mt-10">
                    <?php if (!$logueado): ?>
                        <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start">
                            <a href="<?php echo $urlRegistro; ?>"
                               class="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-teal-600 px-8 py-4 text-lg font-bold text-white shadow-xl shadow-teal-600/30 transition duration-300 hover:-translate-y-0.5 hover:bg-teal-700 hover:shadow-2xl hover:shadow-teal-600/40 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-300 sm:w-auto">
                                Empieza Gratis Ahora
                                <svg class="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                            <a href="#como-funciona"
                               class="inline-flex w-full items-center justify-center gap-2 rounded-full px-6 py-4 text-base font-semibold text-gray-700 transition hover:bg-teal-50 hover:text-teal-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-200 sm:w-auto">
                                Cómo funciona
                            </a>
                        </div>
                        <p class="text-balance mt-5 text-sm font-medium text-gray-500">
                            <svg class="-mt-0.5 mr-1.5 inline h-4 w-4 text-teal-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            No requiere tarjeta. Empieza a planificar en 30 segundos.
                        </p>
                    <?php else: ?>
                        <p class="mb-5 text-xl font-semibold text-teal-700">¡Bienvenido de nuevo!</p>
                        <a href="<?php echo $urlDashboard; ?>"
                           class="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-teal-600 px-8 py-4 text-lg font-bold text-white shadow-xl shadow-teal-600/30 transition duration-300 hover:-translate-y-0.5 hover:bg-teal-700 hover:shadow-2xl hover:shadow-teal-600/40 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-300 sm:w-auto">
                            Ir a mi Dashboard
                            <svg class="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vista previa del producto (ilustrativa, decorativa) -->
            <div class="kalo-rise delay-2 relative mx-auto w-full max-w-[25rem] lg:max-w-[28rem]" aria-hidden="true">
                <div class="absolute inset-8 -z-10 rounded-[3rem] bg-gradient-to-br from-teal-400/40 to-emerald-300/30 blur-3xl"></div>

                <div class="rounded-[2rem] bg-white/90 p-5 shadow-2xl shadow-teal-900/15 ring-1 ring-gray-900/5 backdrop-blur-xl sm:p-6">
                    <!-- Cabecera del menú -->
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Menú de hoy</p>
                            <p class="font-display mt-1 text-xl font-bold text-gray-900">Lunes</p>
                        </div>
                        <!-- Anillo de calorías -->
                        <div class="relative grid h-16 w-16 place-items-center rounded-full" style="background: conic-gradient(#14b8a6 0 80%, var(--kalo-ring-rest, #ccfbf1) 80% 100%);">
                            <div class="grid h-12 w-12 place-items-center rounded-full bg-white text-center">
                                <span class="text-[0.7rem] font-bold leading-none text-gray-900">80%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Macros -->
                    <div class="mt-5 grid grid-cols-3 gap-3">
                        <div class="rounded-2xl bg-gray-50 p-3">
                            <p class="text-[0.7rem] font-medium text-gray-500">Proteína</p>
                            <p class="mt-0.5 text-sm font-bold text-gray-900">118 g</p>
                            <div class="mt-2 h-1.5 rounded-full bg-gray-200"><div class="h-full w-[85%] rounded-full bg-teal-500"></div></div>
                        </div>
                        <div class="rounded-2xl bg-gray-50 p-3">
                            <p class="text-[0.7rem] font-medium text-gray-500">Carbos</p>
                            <p class="mt-0.5 text-sm font-bold text-gray-900">196 g</p>
                            <div class="mt-2 h-1.5 rounded-full bg-gray-200"><div class="h-full w-[72%] rounded-full bg-emerald-400"></div></div>
                        </div>
                        <div class="rounded-2xl bg-gray-50 p-3">
                            <p class="text-[0.7rem] font-medium text-gray-500">Grasas</p>
                            <p class="mt-0.5 text-sm font-bold text-gray-900">58 g</p>
                            <div class="mt-2 h-1.5 rounded-full bg-gray-200"><div class="h-full w-[64%] rounded-full bg-amber-400"></div></div>
                        </div>
                    </div>

                    <!-- Comidas -->
                    <ul class="mt-5 space-y-2.5">
                        <li class="flex items-center gap-3 rounded-2xl border border-gray-100 p-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-amber-50 text-xl">🥑</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[0.7rem] font-semibold uppercase tracking-wider text-gray-400">Desayuno</p>
                                <p class="truncate text-sm font-semibold text-gray-900">Tostada integral con aguacate y huevo</p>
                            </div>
                            <span class="text-xs font-semibold text-gray-500">420 kcal</span>
                        </li>
                        <li class="flex items-center gap-3 rounded-2xl border border-teal-100 bg-teal-50/60 p-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white text-xl">🥗</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[0.7rem] font-semibold uppercase tracking-wider text-teal-600">Comida</p>
                                <p class="truncate text-sm font-semibold text-gray-900">Bowl de quinoa, pollo y verduras</p>
                            </div>
                            <span class="text-xs font-semibold text-teal-700">680 kcal</span>
                        </li>
                        <li class="flex items-center gap-3 rounded-2xl border border-gray-100 p-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-sky-50 text-xl">🐟</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[0.7rem] font-semibold uppercase tracking-wider text-gray-400">Cena</p>
                                <p class="truncate text-sm font-semibold text-gray-900">Salmón al horno con boniato</p>
                            </div>
                            <span class="text-xs font-semibold text-gray-500">590 kcal</span>
                        </li>
                    </ul>
                </div>

                <!-- Tarjeta flotante: lista de la compra -->
                <div class="kalo-float delay-3 absolute -bottom-24 left-2 w-52 rounded-2xl bg-white p-4 shadow-xl shadow-teal-900/10 ring-1 ring-gray-900/5 sm:-left-10">
                    <p class="flex items-center gap-2 text-xs font-bold text-gray-900">
                        <span class="grid h-6 w-6 place-items-center rounded-lg bg-teal-600 text-white">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                            </svg>
                        </span>
                        Lista de la compra
                    </p>
                    <ul class="mt-3 space-y-1.5 text-xs text-gray-600">
                        <li class="flex items-center gap-2"><span class="h-3.5 w-3.5 rounded bg-teal-500"></span><span class="line-through decoration-gray-300">Aguacates ×2</span></li>
                        <li class="flex items-center gap-2"><span class="h-3.5 w-3.5 rounded bg-teal-500"></span><span class="line-through decoration-gray-300">Quinoa 500 g</span></li>
                        <li class="flex items-center gap-2"><span class="h-3.5 w-3.5 rounded border border-gray-300"></span>Salmón · 2 lomos</li>
                    </ul>
                </div>

                <!-- Etiqueta flotante: menú semanal -->
                <div class="kalo-float absolute -top-5 right-3 flex items-center gap-2 rounded-full bg-gray-900 px-4 py-2 text-xs font-semibold text-white shadow-lg sm:-right-6">
                    <svg class="h-4 w-4 text-teal-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                    </svg>
                    Menú semanal generado
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================================
         PASOS: cómo funciona
         ========================================================== -->
    <section id="como-funciona" class="scroll-mt-20 bg-gray-50 py-20 md:py-28" aria-labelledby="pasos-titulo">
        <div class="container mx-auto px-5 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-teal-600 sm:text-sm">Cómo funciona</p>
                <h2 id="pasos-titulo" class="text-balance font-display mt-4 text-[clamp(2rem,6.5vw,3.25rem)] font-extrabold leading-[1.08] tracking-[-0.025em] text-gray-900">
                    Tu planificación, resumida en <span class="text-gradient">3 pasos simples</span>
                </h2>
            </div>

            <div class="relative mt-14 md:mt-20">
                <!-- Línea que une los pasos (escritorio) -->
                <div class="absolute left-[16%] right-[16%] top-[3.25rem] hidden h-px bg-gradient-to-r from-transparent via-teal-300 to-transparent md:block" aria-hidden="true"></div>

                <ol class="relative grid gap-5 md:grid-cols-3 md:gap-8">
                    <li class="group rounded-3xl border border-gray-100 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-teal-900/5 sm:p-8">
                        <div class="flex items-center justify-between">
                            <span class="grid h-14 w-14 place-items-center rounded-2xl bg-teal-50 text-teal-600 ring-1 ring-teal-100 transition duration-300 group-hover:bg-teal-600 group-hover:text-white">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                </svg>
                            </span>
                            <span class="font-display text-5xl font-extrabold text-gray-100 transition duration-300 group-hover:text-teal-100" aria-hidden="true">01</span>
                        </div>
                        <h3 class="font-display mt-6 text-xl font-bold text-gray-900 sm:text-2xl">Establece tu Perfil Nutricional</h3>
                        <p class="mt-3 leading-relaxed text-gray-600">Configura tu objetivo calórico, todas tus preferencias dietéticas y la frecuencia de comidas que deseas.</p>
                    </li>

                    <li class="group rounded-3xl border border-gray-100 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-teal-900/5 sm:p-8">
                        <div class="flex items-center justify-between">
                            <span class="grid h-14 w-14 place-items-center rounded-2xl bg-teal-50 text-teal-600 ring-1 ring-teal-100 transition duration-300 group-hover:bg-teal-600 group-hover:text-white">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                                </svg>
                            </span>
                            <span class="font-display text-5xl font-extrabold text-gray-100 transition duration-300 group-hover:text-teal-100" aria-hidden="true">02</span>
                        </div>
                        <h3 class="font-display mt-6 text-xl font-bold text-gray-900 sm:text-2xl">La IA Crea tu Menú en Segundos</h3>
                        <p class="mt-3 leading-relaxed text-gray-600">Nuestro motor inteligente diseña automáticamente un menú semanal completo, equilibrando nutrición y coste.</p>
                    </li>

                    <li class="group rounded-3xl border border-gray-100 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-teal-900/5 sm:p-8">
                        <div class="flex items-center justify-between">
                            <span class="grid h-14 w-14 place-items-center rounded-2xl bg-teal-50 text-teal-600 ring-1 ring-teal-100 transition duration-300 group-hover:bg-teal-600 group-hover:text-white">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                </svg>
                            </span>
                            <span class="font-display text-5xl font-extrabold text-gray-100 transition duration-300 group-hover:text-teal-100" aria-hidden="true">03</span>
                        </div>
                        <h3 class="font-display mt-6 text-xl font-bold text-gray-900 sm:text-2xl">Tu Lista de la Compra Lista</h3>
                        <p class="mt-3 leading-relaxed text-gray-600">Obtén la lista exacta que necesitas, organizada por secciones para optimizar tu tiempo en el súper.</p>
                    </li>
                </ol>
            </div>
        </div>
    </section>

    <!-- ==========================================================
         BENEFICIOS
         ========================================================== -->
    <section class="bg-gradient-to-b from-gray-50 via-teal-50 to-teal-50 pt-8 pb-24 md:pb-32" aria-labelledby="beneficios-titulo">
        <div class="container mx-auto px-5 sm:px-6">
            <div class="mx-auto mb-12 max-w-3xl text-center md:mb-16">
                <h2 id="beneficios-titulo" class="text-balance font-display text-[clamp(2.1rem,7vw,3.75rem)] font-extrabold leading-[1.05] tracking-[-0.03em] text-teal-900">
                    Ahorra Tiempo, Gana Salud.
                </h2>
                <p class="text-balance mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-teal-800/90 sm:text-xl">
                    KaloAI es tu nutricionista personal y planificador de comidas disponible las 24 horas del día.
                </p>
            </div>

            <div class="mx-auto grid max-w-6xl gap-5 md:grid-cols-2 md:gap-8">
                <!-- Cero estrés -->
                <article class="relative overflow-hidden rounded-3xl bg-white p-7 shadow-xl shadow-teal-900/5 ring-1 ring-teal-900/5 sm:p-9">
                    <div class="flex items-start gap-4 sm:gap-5">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-teal-600 text-white shadow-lg shadow-teal-600/30">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </span>
                        <div>
                            <h3 class="font-display text-xl font-bold text-gray-900 sm:text-2xl">Cero Estrés de Planificación</h3>
                            <p class="mt-2 leading-relaxed text-gray-600">Eliminamos la fatiga de decisión planificando la semana en segundos, adaptando recetas a lo que ya tienes en casa.</p>
                        </div>
                    </div>
                    <!-- La semana, planificada -->
                    <div class="mt-8 grid grid-cols-7 gap-1.5 sm:gap-2" aria-hidden="true">
                        <?php foreach (['L', 'M', 'X', 'J', 'V', 'S', 'D'] as $dia): ?>
                            <div class="rounded-xl bg-teal-50 py-2.5 text-center">
                                <p class="text-[0.7rem] font-bold text-teal-700"><?php echo $dia; ?></p>
                                <div class="mx-auto mt-2 flex w-fit flex-col gap-1">
                                    <span class="h-1.5 w-5 rounded-full bg-teal-500"></span>
                                    <span class="h-1.5 w-5 rounded-full bg-teal-400"></span>
                                    <span class="h-1.5 w-5 rounded-full bg-teal-300"></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <!-- Precisión -->
                <article class="relative overflow-hidden rounded-3xl bg-white p-7 shadow-xl shadow-teal-900/5 ring-1 ring-teal-900/5 sm:p-9">
                    <div class="flex items-start gap-4 sm:gap-5">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-teal-600 text-white shadow-lg shadow-teal-600/30">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                            </svg>
                        </span>
                        <div>
                            <h3 class="font-display text-xl font-bold text-gray-900 sm:text-2xl">Nutrición de Precisión</h3>
                            <p class="mt-2 leading-relaxed text-gray-600">Planes generados con un balance exacto de macros. Tus objetivos se consiguen con base científica y de forma sostenible.</p>
                        </div>
                    </div>
                    <!-- Reparto de macros -->
                    <div class="mt-8" aria-hidden="true">
                        <div class="flex h-3 overflow-hidden rounded-full">
                            <span class="w-[30%] bg-teal-500"></span>
                            <span class="w-[45%] bg-emerald-400"></span>
                            <span class="w-[25%] bg-amber-400"></span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs font-medium text-gray-500">
                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-teal-500"></span>Proteína</span>
                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-400"></span>Carbohidratos</span>
                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-400"></span>Grasas</span>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- ==========================================================
         TESTIMONIOS + CTA FINAL
         ========================================================== -->
    <section class="relative isolate overflow-hidden bg-gradient-to-br from-teal-700 via-teal-800 to-teal-950 py-20 md:py-32" aria-labelledby="cta-titulo">
        <div class="bg-grid-light absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="absolute -top-40 left-1/2 -z-10 h-[28rem] w-[40rem] -translate-x-1/2 rounded-full bg-teal-400/25 blur-3xl" aria-hidden="true"></div>

        <div class="container mx-auto px-5 text-center sm:px-6">
            <h2 id="cta-titulo" class="text-balance font-display mx-auto max-w-3xl text-[clamp(2rem,6.5vw,3.5rem)] font-extrabold leading-[1.08] tracking-[-0.025em] text-white">
                ¡Deja de calcular y empieza a comer bien!
            </h2>
            <p class="text-balance mx-auto mt-5 max-w-xl text-lg text-teal-100/90 sm:text-xl">
                Únete a KaloAI y transforma tu manera de relacionarte con la comida.
            </p>

            <!-- Carrusel (lo mueve public/js/main.js) -->
            <div class="mx-auto mt-14 max-w-3xl md:mt-20">
                <div id="testimony-wrapper" class="relative overflow-hidden rounded-3xl shadow-2xl shadow-black/20">
                    <div id="testimony-track" class="flex transition-transform duration-700 ease-in-out">

                        <div class="flex-shrink-0 w-full bg-white p-7 text-left sm:p-10 md:p-12">
                            <svg class="h-9 w-9 text-teal-100" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M9.3 26c-2.6 0-4.6-.9-6-2.6C1.9 21.7 1.2 19.6 1.2 17c0-3 .9-5.8 2.6-8.4C5.6 6 8.2 3.9 11.6 2.4l1.3 2.3c-2.2 1-3.9 2.3-5.1 3.9-1.2 1.5-1.8 3-1.8 4.6 0 .5.1.9.4 1.2.5-.3 1.2-.5 2-.5 1.8 0 3.2.6 4.3 1.7 1.1 1.1 1.6 2.5 1.6 4.2 0 1.8-.6 3.3-1.8 4.4-1.1 1.2-2.6 1.8-4.4 1.8Zm16.4 0c-2.6 0-4.6-.9-6-2.6-1.4-1.7-2.1-3.8-2.1-6.4 0-3 .9-5.8 2.6-8.4 1.8-2.6 4.4-4.7 7.8-6.2l1.3 2.3c-2.2 1-3.9 2.3-5.1 3.9-1.2 1.5-1.8 3-1.8 4.6 0 .5.1.9.4 1.2.5-.3 1.2-.5 2-.5 1.8 0 3.2.6 4.3 1.7 1.1 1.1 1.6 2.5 1.6 4.2 0 1.8-.6 3.3-1.8 4.4-1.1 1.2-2.6 1.8-4.4 1.8Z"/></svg>
                            <blockquote class="mt-4 text-lg font-medium leading-relaxed text-gray-800 sm:text-xl md:text-2xl">
                                Desde que uso KaloAI, no solo logré mis objetivos de peso en 3 meses, sino que el ahorro en la lista de la compra es tangible. Es planificación inteligente y económica.
                            </blockquote>
                            <div class="mt-7 flex items-center gap-3 border-t border-gray-100 pt-5">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-teal-600 text-sm font-bold text-white">LM</span>
                                <div>
                                    <p class="font-bold text-gray-900">Laura M.</p>
                                    <p class="text-sm text-gray-500">Usuaria Avanzada, 35 años</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex-shrink-0 w-full bg-white p-7 text-left sm:p-10 md:p-12">
                            <svg class="h-9 w-9 text-teal-100" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M9.3 26c-2.6 0-4.6-.9-6-2.6C1.9 21.7 1.2 19.6 1.2 17c0-3 .9-5.8 2.6-8.4C5.6 6 8.2 3.9 11.6 2.4l1.3 2.3c-2.2 1-3.9 2.3-5.1 3.9-1.2 1.5-1.8 3-1.8 4.6 0 .5.1.9.4 1.2.5-.3 1.2-.5 2-.5 1.8 0 3.2.6 4.3 1.7 1.1 1.1 1.6 2.5 1.6 4.2 0 1.8-.6 3.3-1.8 4.4-1.1 1.2-2.6 1.8-4.4 1.8Zm16.4 0c-2.6 0-4.6-.9-6-2.6-1.4-1.7-2.1-3.8-2.1-6.4 0-3 .9-5.8 2.6-8.4 1.8-2.6 4.4-4.7 7.8-6.2l1.3 2.3c-2.2 1-3.9 2.3-5.1 3.9-1.2 1.5-1.8 3-1.8 4.6 0 .5.1.9.4 1.2.5-.3 1.2-.5 2-.5 1.8 0 3.2.6 4.3 1.7 1.1 1.1 1.6 2.5 1.6 4.2 0 1.8-.6 3.3-1.8 4.4-1.1 1.2-2.6 1.8-4.4 1.8Z"/></svg>
                            <blockquote class="mt-4 text-lg font-medium leading-relaxed text-gray-800 sm:text-xl md:text-2xl">
                                Antes odiaba pensar en qué cocinar. KaloAI me ahorra más de 4 horas de planificación semanal. Simplemente presiono un botón y tengo mi menú listo.
                            </blockquote>
                            <div class="mt-7 flex items-center gap-3 border-t border-gray-100 pt-5">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-teal-600 text-sm font-bold text-white">JR</span>
                                <div>
                                    <p class="font-bold text-gray-900">Javier R.</p>
                                    <p class="text-sm text-gray-500">Padre de familia, 42 años</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex-shrink-0 w-full bg-white p-7 text-left sm:p-10 md:p-12">
                            <svg class="h-9 w-9 text-teal-100" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M9.3 26c-2.6 0-4.6-.9-6-2.6C1.9 21.7 1.2 19.6 1.2 17c0-3 .9-5.8 2.6-8.4C5.6 6 8.2 3.9 11.6 2.4l1.3 2.3c-2.2 1-3.9 2.3-5.1 3.9-1.2 1.5-1.8 3-1.8 4.6 0 .5.1.9.4 1.2.5-.3 1.2-.5 2-.5 1.8 0 3.2.6 4.3 1.7 1.1 1.1 1.6 2.5 1.6 4.2 0 1.8-.6 3.3-1.8 4.4-1.1 1.2-2.6 1.8-4.4 1.8Zm16.4 0c-2.6 0-4.6-.9-6-2.6-1.4-1.7-2.1-3.8-2.1-6.4 0-3 .9-5.8 2.6-8.4 1.8-2.6 4.4-4.7 7.8-6.2l1.3 2.3c-2.2 1-3.9 2.3-5.1 3.9-1.2 1.5-1.8 3-1.8 4.6 0 .5.1.9.4 1.2.5-.3 1.2-.5 2-.5 1.8 0 3.2.6 4.3 1.7 1.1 1.1 1.6 2.5 1.6 4.2 0 1.8-.6 3.3-1.8 4.4-1.1 1.2-2.6 1.8-4.4 1.8Z"/></svg>
                            <blockquote class="mt-4 text-lg font-medium leading-relaxed text-gray-800 sm:text-xl md:text-2xl">
                                He mejorado mi alimentación sin esfuerzo y sin sentir que estoy "a dieta". Lo mejor es que los menús se adaptan a mis gustos exactos y objetivos deportivos.
                            </blockquote>
                            <div class="mt-7 flex items-center gap-3 border-t border-gray-100 pt-5">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-teal-600 text-sm font-bold text-white">MG</span>
                                <div>
                                    <p class="font-bold text-gray-900">Marta G.</p>
                                    <p class="text-sm text-gray-500">Usuaria Fit, 29 años</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex-shrink-0 w-full bg-white p-7 text-left sm:p-10 md:p-12">
                            <svg class="h-9 w-9 text-teal-100" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M9.3 26c-2.6 0-4.6-.9-6-2.6C1.9 21.7 1.2 19.6 1.2 17c0-3 .9-5.8 2.6-8.4C5.6 6 8.2 3.9 11.6 2.4l1.3 2.3c-2.2 1-3.9 2.3-5.1 3.9-1.2 1.5-1.8 3-1.8 4.6 0 .5.1.9.4 1.2.5-.3 1.2-.5 2-.5 1.8 0 3.2.6 4.3 1.7 1.1 1.1 1.6 2.5 1.6 4.2 0 1.8-.6 3.3-1.8 4.4-1.1 1.2-2.6 1.8-4.4 1.8Zm16.4 0c-2.6 0-4.6-.9-6-2.6-1.4-1.7-2.1-3.8-2.1-6.4 0-3 .9-5.8 2.6-8.4 1.8-2.6 4.4-4.7 7.8-6.2l1.3 2.3c-2.2 1-3.9 2.3-5.1 3.9-1.2 1.5-1.8 3-1.8 4.6 0 .5.1.9.4 1.2.5-.3 1.2-.5 2-.5 1.8 0 3.2.6 4.3 1.7 1.1 1.1 1.6 2.5 1.6 4.2 0 1.8-.6 3.3-1.8 4.4-1.1 1.2-2.6 1.8-4.4 1.8Z"/></svg>
                            <blockquote class="mt-4 text-lg font-medium leading-relaxed text-gray-800 sm:text-xl md:text-2xl">
                                Como entrenador personal, valoro la precisión. KaloAI ofrece un balance de macros profesional y elimina la necesidad de contar calorías manualmente.
                            </blockquote>
                            <div class="mt-7 flex items-center gap-3 border-t border-gray-100 pt-5">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-teal-600 text-sm font-bold text-white">AL</span>
                                <div>
                                    <p class="font-bold text-gray-900">Andrés L.</p>
                                    <p class="text-sm text-gray-500">Entrenador y Nutricionista</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Indicadores del carrusel (los actualiza main.js) -->
                <div id="testimony-dots" class="mt-7 flex justify-center gap-2" aria-hidden="true">
                    <span class="dot h-2 w-2 rounded-full bg-teal-600 transition duration-300"></span>
                    <span class="dot h-2 w-2 rounded-full bg-gray-300 transition duration-300"></span>
                    <span class="dot h-2 w-2 rounded-full bg-gray-300 transition duration-300"></span>
                    <span class="dot h-2 w-2 rounded-full bg-gray-300 transition duration-300"></span>
                </div>
            </div>

            <!-- Conversión: registro o dashboard según la sesión -->
            <div class="mt-12">
                <?php if (!$logueado): ?>
                    <a href="<?php echo $urlRegistro; ?>"
                       class="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-white px-5 py-4 text-[0.9375rem] font-bold text-teal-800 sm:px-9 sm:text-lg shadow-2xl shadow-black/20 transition duration-300 hover:-translate-y-0.5 hover:bg-teal-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-300 sm:w-auto">
                        Regístrate y comienza a planificar
                        <svg class="hidden h-5 w-5 sm:block transition-transform duration-300 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                <?php else: ?>
                    <a href="<?php echo $urlDashboard; ?>"
                       class="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-white px-5 py-4 text-[0.9375rem] font-bold text-teal-800 sm:px-9 sm:text-lg shadow-2xl shadow-black/20 transition duration-300 hover:-translate-y-0.5 hover:bg-teal-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-300 sm:w-auto">
                        Ir al Dashboard
                        <svg class="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

</main>

<?php
// Incluir el pie de página
include_once __DIR__ . '/views/templates/footer.php';
?>
