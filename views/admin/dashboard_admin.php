<?php
/**
 * VISTA: DASHBOARD DE ADMINISTRACIÓN - KaloAI
 * Proporciona una interfaz para la gestión de usuarios, roles, 
 * asignaciones de nutricionistas y visualización de logs de auditoría.
 */
$pageTitle = "KaloAI | Panel Admin";

session_start();
require_once __DIR__ . '/../../core/configuracion.php';
require_once __DIR__ . '/../../core/utilidades.php';

/* ==========================================================================
   SEGURIDAD Y ACCESO
   ========================================================================== */
if (!isset($_SESSION['user_role_id']) || $_SESSION['user_role_id'] != 1) {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = connectDB();

/* ==========================================================================
   CONSULTAS DE DATOS (ESTADÍSTICAS Y LISTADOS)
   ========================================================================== */

$totalUsuarios = $pdo->query("SELECT COUNT(*) FROM usuario")->fetchColumn();
$totalNutris = $pdo->query("SELECT COUNT(*) FROM usuario WHERE id_rol = 2 AND activo = 1")->fetchColumn();

$nutricionistas = $pdo->query("
    SELECT u.id_usuario, p.nombre 
    FROM usuario u 
    JOIN perfil p ON u.id_usuario = p.id_usuario 
    WHERE u.id_rol = 2 AND u.activo = 1
")->fetchAll();

// Listado maestro ampliado con Hash y Fecha para Supervisión de Seguridad
$usuarios = $pdo->query("
    SELECT u.id_usuario, u.correo, u.id_rol, u.activo, u.contrasena_hash, u.fecha_creacion, p.nombre AS nombre_usuario, p.id_nutricionista
    FROM usuario u
    LEFT JOIN perfil p ON u.id_usuario = p.id_usuario
    ORDER BY u.id_rol ASC, u.id_usuario DESC
")->fetchAll();

$logs = $pdo->query("SELECT * FROM logs_actividad ORDER BY fecha DESC LIMIT 10")->fetchAll();

// Carga del componente de cabecera 
include_once __DIR__ . '/../templates/header.php'; 
?>

<main class="container mx-auto mt-32 mb-20 px-6">

    <!-- ALERTAS DE SISTEMA: Feedback tras acciones de borrado -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'borrado_exitoso'): ?>
        <div id="successAlert" class="mb-8 bg-teal-500 text-white px-8 py-4 rounded-[25px] shadow-lg font-black uppercase text-[12px] tracking-widest animate-pulse flex justify-between items-center">
            <span>¡Usuario eliminado del sistema correctamente!</span>
            <button onclick="this.parentElement.remove()" class="opacity-50 hover:opacity-100">✕</button>
        </div>
    <?php endif; ?>

    <!-- INDICADORES (KPIs): Resumen rápido de estadísticas de la plataforma -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        <div class="bg-teal-600 text-white p-8 rounded-[40px] shadow-lg shadow-teal-100 flex flex-col justify-center relative overflow-hidden group">
            <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-32 w-32" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
            <p class="text-[12px] opacity-80 uppercase font-black tracking-[0.2em] mb-1">Total Usuarios</p>
            <h3 class="text-6xl font-black italic tracking-tighter"><?= $totalUsuarios ?></h3>
        </div>

        <div class="bg-white p-8 rounded-[40px] shadow-xl border border-gray-100 flex flex-col justify-center">
            <p class="text-[12px] text-teal-600 uppercase font-black tracking-widest mb-1">Nutricionistas Activos</p>
            <h3 id="nutriCounter" class="text-6xl font-black text-gray-900 italic tracking-tighter"><?= $totalNutris ?></h3>
        </div>

        <a href="../dashboard.php" class="bg-gray-900 p-8 rounded-[40px] shadow-2xl flex flex-col justify-center items-center group transition-all hover:bg-gray-800">
            <p class="text-[12px] text-teal-400 uppercase font-black tracking-widest mb-2">Vista Aplicación</p>
            <h3 class="text-xl font-black text-white flex items-center gap-2 uppercase italic">
                Ver tu dashboard
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-teal-500 group-hover:translate-x-2 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </h3>
        </a>
    </div>

    <div class="bg-white p-8 md:p-12 rounded-[50px] shadow-2xl border border-gray-50">

        <!-- CONTROLES DE FILTRADO: Búsqueda en tiempo real y filtro por rol -->
        <div class="flex flex-col lg:flex-row justify-between items-center mb-12 gap-8">
            <div class="text-center lg:text-left">
                <h2 class="text-6xl font-black text-gray-900 uppercase tracking-tighter italic leading-none">
                    Admin <span class="text-teal-600">Dashboard</span>
                </h2>
                <p class="text-gray-400 text-base font-medium mt-2 italic ml-1">Gestión avanzada de la plataforma KaloAI.</p>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto">
                <select id="roleFilter" class="px-8 py-5 bg-gray-50 border-none rounded-[25px] text-sm font-black uppercase tracking-widest text-gray-500 outline-none focus:ring-4 focus:ring-teal-500/10 transition-all cursor-pointer">
                    <option value="all">Todos los Roles</option>
                    <option value="1">Administradores</option>
                    <option value="2">Nutricionistas</option>
                    <option value="3">Usuarios</option>
                </select>
                
                <div class="relative w-full sm:w-96">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-6">
                        <svg class="h-6 w-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input type="text" id="realTimeSearch" placeholder="Buscar por nombre o email..." 
                           class="w-full bg-gray-50 border-none py-5 pl-16 pr-8 rounded-[25px] text-base font-bold focus:bg-white focus:ring-4 focus:ring-teal-500/10 outline-none transition-all placeholder:text-gray-300 shadow-inner">
                </div>
            </div>
        </div>

        <!-- LISTADO DE USUARIOS: Renderizado dinámico de filas con acciones AJAX -->
        <div class="space-y-6">
            <div class="hidden lg:grid grid-cols-10 px-12 mb-4 text-gray-400 text-[12px] uppercase font-black tracking-[0.2em]">
                <div class="col-span-3">Usuario</div>
                <div class="col-span-2 text-center">Nivel de Acceso</div>
                <div class="col-span-2 text-center">Asignación Nutri</div>
                <div class="col-span-3 text-right">Acciones</div>
            </div>

            <div id="contenedorUsuarios">
                <?php foreach ($usuarios as $u): ?>
                <div class="user-row grid grid-cols-1 lg:grid-cols-10 items-center bg-white border border-gray-100 p-8 md:px-12 rounded-[40px] shadow-sm hover:shadow-2xl hover:-translate-y-1 transition-all duration-300 group mb-6"
                     data-search="<?= strtolower(htmlspecialchars(($u['nombre_usuario'] ?? '') . ' ' . $u['correo'])) ?>" 
                     data-role="<?= $u['id_rol'] ?>">
                    
                     <!-- Perfil -->
                    <div class="col-span-1 lg:col-span-3 mb-6 lg:mb-0">
                        <div class="font-black text-gray-900 text-xl tracking-tight group-hover:text-teal-600 transition-colors italic">
                            <?= htmlspecialchars($u['nombre_usuario'] ?? 'Sin Perfil') ?>
                        </div>
                        <div class="text-sm text-gray-400 font-bold"><?= htmlspecialchars($u['correo']) ?></div>
                        <p class="text-[10px] font-bold text-gray-400 mt-2 uppercase tracking-widest">Registrado: <?= date('d/m/Y', strtotime($u['fecha_creacion'])) ?></p>
                    </div>

                    <!-- Cambio de Rol (Trigger AJAX) -->
                    <div class="col-span-1 lg:col-span-2 flex justify-center mb-6 lg:mb-0">
                        <select onchange="actualizarCampoUsuario(<?= $u['id_usuario'] ?>, 'rol', this.value)" 
                                class="text-[11px] font-black uppercase bg-gray-50 border-none rounded-2xl px-6 py-3.5 outline-none focus:ring-2 focus:ring-teal-500/20 cursor-pointer transition-all">
                            <option value="3" <?= $u['id_rol'] == 3 ? 'selected' : '' ?>>USUARIO</option>
                            <option value="2" <?= $u['id_rol'] == 2 ? 'selected' : '' ?>>NUTRI</option>
                            <option value="1" <?= $u['id_rol'] == 1 ? 'selected' : '' ?>>ADMIN</option>
                        </select>
                    </div>

                    <!-- Asignación Profesional -->
                    <div class="col-span-1 lg:col-span-2 flex justify-center mb-6 lg:mb-0">
                        <?php if ($u['id_rol'] == 3): ?>
                            <select onchange="actualizarCampoUsuario(<?= $u['id_usuario'] ?>, 'nutri', this.value)" 
                                    class="text-[11px] bg-gray-50 border-none rounded-2xl px-6 py-3.5 outline-none w-full max-w-[200px] font-bold text-gray-600 italic cursor-pointer">
                                <option value="">Sin asignar</option>
                                <?php foreach ($nutricionistas as $nutri): ?>
                                    <option value="<?= $nutri['id_usuario'] ?>" <?= $u['id_nutricionista'] == $nutri['id_usuario'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($nutri['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <span class="text-[11px] text-gray-300 uppercase font-black tracking-[0.15em] italic">Staff KaloAI</span>
                        <?php endif; ?>
                    </div>

                    <!-- Botones de Acción: Bloqueo y Borrado -->
                    <div class="col-span-1 lg:col-span-3 flex gap-3 justify-end">
                        <button id="btn-status-<?= $u['id_usuario'] ?>" 
                                onclick="alternarEstadoUsuario(<?= $u['id_usuario'] ?>, <?= $u['id_rol'] ?>, <?= $u['activo'] == 1 ? 0 : 1 ?>)" 
                                class="flex-1 lg:flex-none px-6 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-md active:scale-95 <?= $u['activo'] == 1 ? 'bg-teal-50 text-teal-600 hover:bg-teal-500 hover:text-white' : 'bg-orange-500 text-white hover:bg-orange-600' ?>">
                            <?= $u['activo'] == 1 ? 'ACTIVO' : 'BLOQUEADO' ?>
                        </button>
                        
                        <button onclick="abrirModalEliminarAdmin(<?= $u['id_usuario'] ?>)" 
                                class="px-6 py-4 bg-red-50 text-red-600 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-red-600 hover:text-white transition-all shadow-sm active:scale-95">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- LOGS DE AUDITORÍA: Registro histórico de eventos del sistema -->
        <div class="mt-20 bg-gray-900 p-12 rounded-[50px] shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 p-10 opacity-10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <div class="flex justify-between items-center mb-10">
                <div>
                    <h3 class="text-3xl font-black uppercase tracking-tighter text-white italic">Logs de <span class="text-teal-400">Actividad</span></h3>
                    <p class="text-gray-500 text-sm font-bold uppercase tracking-widest">Auditoría del sistema en tiempo real</p>
                </div>
                <div class="h-4 w-4 rounded-full bg-teal-500 animate-pulse"></div>
            </div>

            <div class="space-y-4">
                <?php foreach($logs as $log): ?>
                <div class="flex justify-between items-center bg-white/5 border border-white/5 p-6 rounded-3xl hover:bg-white/10 transition-all group">
                    <span class="text-base text-gray-200 font-medium"><?= $log['detalle'] ?></span>
                    <span class="text-[11px] text-gray-500 font-black uppercase bg-black/30 px-4 py-2 rounded-xl shrink-0 ml-6">
                        <?= date('H:i | d M', strtotime($log['fecha'])) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<!-- MODAL DE CONFIRMACIÓN: Capa de seguridad para eliminación permanente -->
<div id="deleteConfirmModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div id="modalOverlay" class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300"></div>
    <div id="modalContent" class="relative bg-white rounded-[50px] shadow-2xl max-w-md w-full p-12 transform transition-all scale-95 opacity-0 duration-300">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-red-50 mb-8">
                <svg class="h-12 w-12 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h3 class="text-3xl font-black text-gray-900 uppercase tracking-tighter italic mb-3">¿Eliminar Usuario?</h3>
            <p class="text-gray-500 text-base font-medium mb-10 italic leading-relaxed">Esta acción es irreversible. Se borrarán permanentemente todas las recetas y registros asociados.</p>
            <div class="flex gap-4">
                <button onclick="cerrarModalEliminar()" class="flex-1 px-8 py-5 bg-gray-100 text-gray-400 rounded-2xl text-[12px] font-black uppercase tracking-widest hover:bg-gray-200 transition-all">Cancelar</button>
                <button onclick="ejecutarEliminacion()" class="flex-1 px-8 py-5 bg-red-600 text-white rounded-2xl text-[12px] font-black uppercase tracking-widest hover:bg-red-700 shadow-lg shadow-red-200 transition-all">Confirmar Borrado</button>
            </div>
        </div>
    </div>
</div>


<?php 
// Incluir el pie de página
include_once __DIR__ . '/../templates/footer.php'; 
?>