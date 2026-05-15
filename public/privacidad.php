<?php
/**
 * VISTA: PRIVACIDAD - KaloAI
 * Documento legal sobre el tratamiento de datos personales.
 */

$pageTitle = "KaloAI | Política de Privacidad";
session_start();

require_once __DIR__ . '/../core/utilidades.php'; 

// Carga del componente de cabecera 
include_once __DIR__ . '/../views/templates/header.php'; 
?>

<!-- HERO SECTION: Cabecera temática con énfasis en el concepto de Privacidad -->
<section class="bg-teal-50 py-24">
    <div class="container mx-auto px-6 text-center">
        <h1 class="text-5xl md:text-7xl font-extrabold text-gray-900 mb-6">Privacidad de <span class="text-teal-600">Datos.</span></h1>
    </div>
</section>

<section class="py-20 bg-white">
    <div class="container mx-auto px-6 max-w-4xl">
        <div class="prose prose-teal prose-xl max-w-none space-y-16">
            
            <!-- BLOQUE INFORMATIVO: Introducción y compromiso de la marca -->
            <div class="bg-gray-50 p-10 rounded-[3rem] border border-gray-100">
                <h2 class="text-3xl font-black text-gray-900 mb-6">1. Tu privacidad es prioridad</h2>
                <p class="text-gray-600 leading-relaxed">
                    En <strong>KaloAI</strong>, entendemos que tu información nutricional y de salud es extremadamente personal. Esta política explica cómo recopilamos, usamos y protegemos tus datos cuando utilizas nuestra plataforma de planificación alimentaria.
                </p>
            </div>

            <!-- GRID DE CATEGORÍAS: Clasificación de los datos capturados por el sistema -->
            <div>
                <h2 class="text-3xl font-black text-gray-900 mb-8 flex items-center gap-4">
                    <span class="bg-teal-600 text-white h-10 w-10 flex items-center justify-center rounded-full text-lg font-bold">2</span>
                    Datos que Recopilamos
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="p-8 border border-teal-100 rounded-3xl bg-white">
                        <h4 class="font-bold text-teal-900 mb-2">Información de Cuenta</h4>
                        <p class="text-sm text-gray-600">Nombre, correo electrónico y contraseña encriptada para el acceso seguro.</p>
                    </div>
                    <div class="p-8 border border-teal-100 rounded-3xl bg-white">
                        <h4 class="font-bold text-teal-900 mb-2">Perfil Nutricional</h4>
                        <p class="text-sm text-gray-600">Objetivos calóricos, preferencias dietéticas e intolerancias necesarias para la IA.</p>
                    </div>
                </div>
            </div>

            <!-- DESTACADO IA: Explicación del procesamiento algorítmico y ética de datos -->
            <div class="bg-teal-900 p-12 rounded-[3rem] text-white shadow-2xl">
                <h2 class="text-3xl font-black mb-6">3. Uso de Inteligencia Artificial</h2>
                <p class="text-teal-100 leading-relaxed mb-6">
                    Tus datos son procesados por nuestros algoritmos de IA únicamente para generar tus planes personalizados. <strong>No vendemos tus datos</strong> a empresas de marketing ni a terceros.
                </p>
                <div class="flex items-center gap-3 text-teal-400 font-bold italic">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                    </svg>
                    Seguridad Punto a Punto
                </div>
            </div>

            <!-- MARCO LEGAL: Derechos del usuario (RGPD) y acciones disponibles -->
            <div>
                <h2 class="text-3xl font-black text-gray-900 mb-6">4. Tus Derechos</h2>
                <p class="text-gray-600 leading-relaxed mb-8">
                    De acuerdo con el RGPD, tienes derecho a acceder, rectificar o eliminar tus datos en cualquier momento desde tu panel de configuración o contactándonos directamente.
                </p>
                <a href="contacto.php" class="text-teal-600 font-black uppercase tracking-widest text-xs hover:underline">
                    Solicitar eliminación de datos &rarr;
                </a>
            </div>

        </div>
    </div>
</section>

<?php 
// Incluir el pie de página
include_once __DIR__ . '/../views/templates/footer.php'; 
?>