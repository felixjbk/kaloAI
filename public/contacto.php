<?php
/**
 * VISTA: CONTACTO - KaloAI
 * Proporciona un canal directo de comunicación con soporte.
 */

$pageTitle = "KaloAI | Contacto";
session_start();

require_once __DIR__ . '/../core/utilidades.php'; 

// Carga del componente de cabecera 
include_once __DIR__ . '/../views/templates/header.php'; 
?>

<!-- HERO SECTION: Cabecera de impacto para la página de soporte -->
<section class="bg-teal-50 min-h-[50vh] flex items-center justify-center relative py-20">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,var(--tw-color-teal-50)_20%,transparent_80%)] opacity-70"></div>
    
    <div class="container mx-auto px-6 text-center relative z-10">
        <h1 class="text-6xl md:text-8xl font-extrabold text-gray-900 leading-none mb-6">
            Estamos para <span class="text-teal-600">Ayudarte.</span>
        </h1>
        <p class="text-xl md:text-2xl text-gray-600 max-w-2xl mx-auto font-light">
            ¿Tienes dudas sobre tu plan nutricional o necesitas soporte técnico? Nuestro equipo responde en menos de 24 horas.
        </p>
    </div>
</section>

<!-- CONTENIDO PRINCIPAL: Grid de contacto y formulario -->
<section class="py-20 bg-gray-50">
    <div class="container mx-auto px-6 max-w-6xl">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-start">
            
            <!-- INFORMACIÓN LATERAL: Canales de comunicación directa -->
            <div class="space-y-12">
                <div>
                    <h2 class="text-4xl font-black text-gray-900 italic mb-6">Información de <span class="text-teal-600">Contacto</span></h2>
                    <p class="text-gray-600 text-lg">Si prefieres no usar el formulario, puedes contactarnos a través de nuestros canales oficiales.</p>
                </div>

                <div class="space-y-6">
                    <!-- Card de Email Soporte -->
                    <div class="flex items-center space-x-5 p-6 bg-white rounded-3xl shadow-sm border border-gray-100">
                        <div class="bg-teal-100 p-4 rounded-2xl text-teal-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-black text-gray-400 uppercase tracking-widest">Email</p>
                            <p class="text-xl font-bold text-gray-800">soporte@kaloai.com</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FORMULARIO DE CONTACTO: Captura de leads y consultas -->
            <div class="bg-white p-10 md:p-12 rounded-[3rem] shadow-2xl shadow-teal-900/5 border border-gray-100">
                <form action="#" method="POST" class="space-y-6">
                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Nombre Completo</label>
                        <input type="text" name="nombre" required
                            class="w-full bg-gray-50 border-none rounded-2xl px-6 py-4 text-lg font-bold focus:ring-2 focus:ring-teal-500 transition-all" placeholder="Ej. Juan Pérez">
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Correo Electrónico</label>
                        <input type="email" name="email" required
                            class="w-full bg-gray-50 border-none rounded-2xl px-6 py-4 text-lg font-bold focus:ring-2 focus:ring-teal-500 transition-all" placeholder="juan@email.com">
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Tu Mensaje</label>
                        <textarea name="mensaje" rows="5" required
                            class="w-full bg-gray-50 border-none rounded-[2rem] px-6 py-4 font-medium focus:ring-2 focus:ring-teal-500 transition-all" placeholder="¿En qué podemos ayudarte?"></textarea>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="w-full bg-teal-600 text-white font-black py-6 rounded-[2rem] shadow-xl shadow-teal-100 hover:bg-teal-700 hover:-translate-y-1 transition-all uppercase text-xs tracking-[0.2em]">
                        Enviar Mensaje
                    </button>
                </form>
            </div>

        </div>
    </div>
</section>


<?php 
// Incluir el pie de página
include_once __DIR__ . '/../views/templates/footer.php'; 
?>