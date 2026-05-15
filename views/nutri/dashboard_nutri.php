<?php
/**
 * VISTA: PANEL DEL NUTRICIONISTA - KaloAI
 * Permite a los profesionales gestionar su cartera de clientes, visualizar 
 * estadísticas rápidas y acceder a la gestión individual de cada paciente.
 */

$pageTitle = "KaloAI | Panel Nutricionista";

session_start();
require_once __DIR__ . '/../../core/configuracion.php';
require_once __DIR__ . '/../../core/utilidades.php';

/* ==========================================================================
   SEGURIDAD Y CONTROL DE ACCESO
   ========================================================================== */
// Solo permitimos el acceso a Nutricionistas (Rol 2) o Administradores (Rol 1).
// Si el rol es mayor a 2 (ej. Usuario estándar), se redirige al dashboard.
if (!isset($_SESSION['user_role_id']) || $_SESSION['user_role_id'] > 2) {
    header("Location: ../dashboard.php");
    exit;
}

$nutriId = $_SESSION['user_id'];
$pdo = connectDB();

/* ==========================================================================
   EXTRACCIÓN DE DATOS
   ========================================================================== */

// 1. Obtener el número total de pacientes vinculados a este nutricionista
$totalPacientes = $pdo->prepare("SELECT COUNT(*) FROM perfil WHERE id_nutricionista = ?");
$totalPacientes->execute([$nutriId]);
$numPacientes = $totalPacientes->fetchColumn();

// 2. Listado detallado de pacientes
// Combinamos 'usuario' (para el correo) y 'perfil' (para datos antropométricos)
$stmt = $pdo->prepare("
    SELECT u.id_usuario, p.nombre, u.correo, p.peso_kg, p.objetivo 
    FROM usuario u
    JOIN perfil p ON u.id_usuario = p.id_usuario
    WHERE p.id_nutricionista = ?
");
$stmt->execute([$nutriId]);
$pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);



// Carga del componente de cabecera 
include_once __DIR__ . '/../templates/header.php';
?>

<!-- MENSAJE DE INVITACIÓN -->
<div class="container mx-auto px-6">
    <?php 
    // Verificamos si existe un mensaje de éxito
    $msj = $_GET['msj'] ?? null; 
    // Verificamos si existe un error
    $err = $_GET['err'] ?? null; 

    if ($msj === 'solicitud_enviada'): ?>
        <div class="mb-8 bg-teal-500 text-white px-8 py-4 rounded-[25px] shadow-lg font-black uppercase text-[12px] tracking-widest flex justify-between items-center animate-fade-in">
            <span>¡Invitación enviada correctamente al paciente!</span>
            <button onclick="this.parentElement.remove()" class="opacity-50 hover:opacity-100">✕</button>
        </div>
    <?php endif; ?>

    <?php if ($err): ?>
        <div class="mb-8 bg-red-500 text-white px-8 py-4 rounded-[25px] shadow-lg font-black uppercase text-[12px] tracking-widest flex justify-between items-center">
            <span>
                <?php 
                    echo ($err === 'usuario_no_encontrado') ? "El correo no existe o no es un usuario estándar." : 
                         (($err === 'ya_solicitado') ? "Ya existe una invitación pendiente para este correo." : "Error al procesar la solicitud.");
                ?>
            </span>
            <button onclick="this.parentElement.remove()" class="opacity-50 hover:opacity-100">✕</button>
        </div>
    <?php endif; ?>
</div>

<main class="container mx-auto mt-32 mb-20 px-6">

    <!-- DASHBOARD SUPERIOR: Resumen de actividad y herramientas de vinculación -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        
        <!-- Contador de Pacientes -->
        <div class="bg-teal-600 text-white p-8 rounded-[40px] shadow-lg shadow-teal-100 flex flex-col justify-center relative overflow-hidden group">
            <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-32 w-32" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <p class="text-[10px] opacity-80 uppercase font-black tracking-[0.2em] mb-1">Pacientes Activos</p>
            <h3 class="text-5xl font-black italic tracking-tighter"><?= $numPacientes ?></h3>
        </div>

        <!-- Acceso al Perfil Propio (Nutricionista) -->
        <a href="../dashboard.php" class="bg-white p-8 rounded-[40px] shadow-xl border-2 border-dashed border-gray-100 flex flex-col justify-center items-center group transition-all hover:border-teal-500 hover:bg-teal-50/30">
            <div class="bg-gray-50 p-4 rounded-full mb-3 group-hover:bg-teal-100 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400 group-hover:text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <p class="text-[10px] text-gray-400 uppercase font-black tracking-widest mb-1">Tu perfil personal</p>
            <h3 class="text-sm font-black text-gray-900 flex items-center gap-2 uppercase italic">Ver mi propio plan</h3>
        </a>

        <!-- Formulario de Vinculación: Permite añadir nuevos pacientes mediante invitación por correo -->
        <div class="bg-gray-900 p-8 rounded-[40px] shadow-2xl flex flex-col justify-center">
            <p class="text-[10px] text-teal-400 uppercase font-black tracking-widest mb-4">Añadir Paciente por Email</p>
            <form method="POST" action="vincula_paciente.php" class="space-y-3">
                <div class="relative">
                    <input type="email" name="email_busqueda" placeholder="ejemplo@correo.com" 
                           class="w-full bg-white/10 border border-white/5 px-5 py-3 rounded-2xl text-xs font-medium text-white outline-none focus:border-teal-500 transition-all placeholder:text-gray-600" required>
                </div>
                <button type="submit" class="w-full bg-teal-500 text-white py-3 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-teal-400 transition-all shadow-lg shadow-teal-900/20">
                    Enviar Invitación
                </button>
            </form>
        </div>
    </div>

    <!-- LISTADO DE CLIENTES: Sección principal con filtrado y tabla de datos -->
    <div class="bg-white p-8 md:p-12 rounded-[50px] shadow-2xl border border-gray-50">
        
        <div class="flex flex-col lg:flex-row justify-between items-center mb-12 gap-8">
            <div class="text-center lg:text-left">
                <h2 class="text-5xl font-black text-gray-900 uppercase tracking-tighter italic leading-none">
                    Mis <span class="text-teal-600">Clientes</span>
                </h2>
                <p class="text-gray-400 text-sm font-medium mt-2 italic">Usa el buscador para filtrar rápidamente por nombre o email.</p>
            </div>

            <!-- Buscador en tiempo real (Frontend) -->
            <div class="relative w-full lg:w-96">
                <span class="absolute inset-y-0 left-0 flex items-center pl-5">
                    <svg class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input type="text" id="buscadorPaciente" placeholder="Escribe un nombre o correo..." 
                       class="w-full bg-gray-50 border-2 border-transparent py-4 pl-14 pr-6 rounded-[25px] text-sm font-bold focus:bg-white focus:border-teal-500/20 focus:ring-4 focus:ring-teal-500/5 outline-none transition-all placeholder:text-gray-300 shadow-inner">
            </div>
        </div>

        <!-- TABLA DE PACIENTES: Visualización de métricas clave (Peso y Objetivos) -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-separate border-spacing-y-4" id="tablaPacientes">
                <thead>
                    <tr class="text-gray-400 text-[10px] uppercase tracking-[0.25em] px-8">
                        <th class="px-8 py-2">Datos del Paciente</th>
                        <th class="px-8 py-2 text-center">Peso Actual</th>
                        <th class="px-8 py-2 text-center">Meta</th>
                        <th class="px-8 py-2 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pacientes as $p): ?>
                    <tr class="fila-paciente bg-white border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300">
                        <td class="px-8 py-6 rounded-l-[30px] border-y border-l">
                            <div class="nombre-paciente font-black text-gray-900 text-xl tracking-tight"><?= htmlspecialchars($p['nombre'] ?? 'Sin Perfil') ?></div>
                            <div class="correo-paciente text-xs text-teal-600 font-bold opacity-60 italic"><?= htmlspecialchars($p['correo']) ?></div>
                        </td>
                        <!-- Identificación -->
                        <td class="px-8 py-6 text-center border-y">
                            <span class="bg-gray-50 text-gray-700 px-4 py-2 rounded-xl text-xs font-black">
                                <?= !empty($p['peso_kg']) ? $p['peso_kg'] . ' KG' : '--' ?>
                            </span>
                        </td>
                        <!-- Métricas Nutricionales -->
                        <td class="px-8 py-6 text-center border-y">
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 bg-gray-50 px-3 py-1 rounded-lg">
                                <?= str_replace('_', ' ', $p['objetivo'] ?? 'Pendiente') ?>
                            </span>
                        </td>
                        <!-- Acción: Login simulado como paciente para gestión directa -->
                        <td class="px-8 py-6 text-right rounded-r-[30px] border-y border-r">
                            <a href="entrar_como_paciente.php?id_paciente=<?= $p['id_usuario'] ?>" 
                               class="bg-gray-900 text-white px-8 py-4 rounded-2xl text-[10px] font-black hover:bg-teal-600 transition-all uppercase tracking-widest inline-block shadow-lg">
                                Gestionar
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Estado vacío para el buscador -->
            <div id="noResultados" class="hidden py-20 text-center">
                <div class="bg-gray-50 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                     <svg class="h-8 w-8 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <p class="text-gray-300 font-black uppercase text-xs tracking-[0.3em]">No hay coincidencias</p>
            </div>
        </div>
    </div>
</main>

<?php 
// Incluir el pie de página
include_once __DIR__ . '/../templates/footer.php'; 
?>