<?php
/**
 * VISTA: CREAR NUEVA RECETA - KaloAI
 * Proporciona un formulario detallado para que el usuario añada sus propias 
 * creaciones culinarias a su biblioteca personal, incluyendo macros y pasos.
 */

$pageTitle = "KaloAI | Crear Nueva Receta";
session_start();
require_once __DIR__ . '/../../core/utilidades.php';
require_once __DIR__ . '/../../core/configuracion.php';

// Seguridad: Si no hay sesión, al login.
if (!isset($_SESSION['user_id'])) { 
    redirect('auth/login.php'); 
}

// Carga del componente de cabecera 
include_once __DIR__ . '/../templates/header.php';
?>

<!-- CONTENEDOR DE FORMULARIO: Diseño centrado y limitado para facilitar la lectura -->
<div class="container mx-auto px-6 py-12 max-w-4xl">
    
    <!-- CABECERA DE ACCIÓN: Navegación de retorno y título de sección -->
    <div class="flex items-center mb-12">
        <a href="mis_recetas.php" class="mr-6 bg-white border border-gray-100 p-4 rounded-2xl text-gray-400 hover:text-teal-600 hover:shadow-md transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <h1 class="text-5xl font-black text-gray-900 italic tracking-tighter">
                Nueva <span class="text-teal-600">Receta</span>
            </h1>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mt-2 flex items-center gap-2">
                <span class="w-2 h-2 bg-teal-500 rounded-full"></span>
                Añadir manualmente a tu biblioteca
            </p>
        </div>
    </div>

    <!-- FORMULARIO DE RECEPTA: Envío de datos al núcleo de procesamiento -->
    <form action="../../core/guardar_receta.php" method="POST" class="space-y-8">
        
        <!-- BLOQUE 1: Datos básicos y clasificación de la comida -->
        <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-gray-100">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="md:col-span-2">
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Título de la receta</label>
                    <input type="text" name="titulo" placeholder="Ej: Ensalada César Kalo" required
                        class="w-full bg-gray-50 border-none rounded-2xl px-6 py-4 text-lg font-bold italic focus:ring-2 focus:ring-teal-500 transition-all">
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Tipo de Comida</label>
                    <select name="tipo_comida" class="w-full bg-gray-50 border-none rounded-2xl px-6 py-4 font-bold text-gray-700 focus:ring-2 focus:ring-teal-500 transition-all">
                        <option value="desayuno">☕ Desayuno</option>
                        <option value="almuerzo">☀️ Almuerzo / Comida</option>
                        <option value="snack">🍏 Snack / Merienda</option>
                        <option value="cena">🌙 Cena</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Calorías (por ración)</label>
                    <input type="number" name="calorias" placeholder="0" min="0" max="5000" required
                    class="w-full bg-gray-50 border-none rounded-2xl px-6 py-4 font-bold focus:ring-2 focus:ring-teal-500 transition-all">               </div>
            </div>
        </div>

        <!-- BLOQUE 2: Macros Nutricionales con codificación de colores para identificación rápida -->
        <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-gray-100">
            <h2 class="text-xl font-black text-gray-900 italic mb-6">Información Nutricional (g)</h2>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-blue-500 uppercase tracking-tighter mb-2 ml-1">Proteínas</label>
                    <input type="number" name="proteinas" placeholder="0g" min="0" max="500" step="1" required class="w-full bg-gray-50 border-none rounded-xl px-4 py-3 font-bold focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-yellow-500 uppercase tracking-tighter mb-2 ml-1">Carbohidratos</label>
                    <input type="number" name="carbohidratos" placeholder="0g" min="0" max="500" step="1" required class="w-full bg-gray-50 border-none rounded-xl px-4 py-3 font-bold focus:ring-2 focus:ring-yellow-500">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-orange-500 uppercase tracking-tighter mb-2 ml-1">Grasas</label>
                    <input type="number" name="grasas" placeholder="0g" min="0" max="500" step="1" required class="w-full bg-gray-50 border-none rounded-xl px-4 py-3 font-bold focus:ring-2 focus:ring-orange-500">
                </div>
            </div>
        </div>

        <!-- BLOQUE 3: Elaboración (Ingredientes e Instrucciones) -->
        <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-gray-100">
            <div class="space-y-6">
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Ingredientes (uno por línea)</label>
                    <textarea name="ingredientes" rows="5" placeholder="100g de Pollo&#10;1 ración de Lechuga..."
                        class="w-full bg-gray-50 border-none rounded-[2rem] px-6 py-4 font-medium focus:ring-2 focus:ring-teal-500 transition-all"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-3 ml-2">Instrucciones / Pasos</label>
                    <textarea name="instrucciones" rows="5" placeholder="1. Cocinar el pollo a la plancha...&#10;2. Mezclar con la lechuga..."
                        class="w-full bg-gray-50 border-none rounded-[2rem] px-6 py-4 font-medium focus:ring-2 focus:ring-teal-500 transition-all"></textarea>
                </div>
            </div>
        </div>

        <!-- ACCIONES: Botón de guardado con énfasis visual y opción de cancelación -->
        <div class="flex gap-4">
            <button type="submit" class="flex-1 bg-teal-600 text-white font-black py-6 rounded-[2rem] shadow-xl shadow-teal-100 hover:bg-teal-700 hover:-translate-y-1 transition-all uppercase text-xs tracking-[0.2em]">
                Guardar Receta
            </button>
            <a href="mis_recetas.php" class="px-10 bg-white border border-gray-100 text-gray-400 font-black py-6 rounded-[2rem] hover:bg-gray-50 transition-all uppercase text-xs tracking-[0.2em]">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php 
// Incluir el pie de página
include_once __DIR__ . '/../templates/footer.php';
?>