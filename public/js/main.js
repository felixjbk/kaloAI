/**
 * KaloAI - Scripts Principales de Gestión
 * ---------------------------------------
 * Este archivo centraliza las funciones interactivas: administración,
 * planificación nutricional, gestión de inventario y recetas.
 */

/* ==========================================================================
   1. VARIABLES GLOBALES Y ESTADO
   ========================================================================== */
let recetaParaEliminar = null;
let usuarioParaEliminar = null;
window.recetaParaEliminar = null; // Mantenido por compatibilidad
window.filtroRecetaActual = 'todos';

/* ==========================================================================
   2. MÓDULO DE ADMINISTRACIÓN (Usuarios)
   ========================================================================== */

/**
 * Envía una petición AJAX a api_admin.php para actualizar rol o nutricionista.
 */
function actualizarCampoUsuario(idUsuario, tipo, valor) {
    const datosFormulario = new FormData();
    datosFormulario.append('user_id', idUsuario);
    datosFormulario.append('action', tipo);
    datosFormulario.append('value', valor);

    fetch('api_admin.php', { method: 'POST', body: datosFormulario })
    .then(respuesta => respuesta.json())
    .then(datos => {
        if(datos.success) window.location.reload();
    });
}

/**
 * Envía una petición AJAX para activar o bloquear a un usuario.
 */
function alternarEstadoUsuario(idUsuario, rolUsuario, nuevoValor) {
    const datosFormulario = new FormData();
    datosFormulario.append('user_id', idUsuario);
    datosFormulario.append('action', 'toggle_status');
    datosFormulario.append('value', nuevoValor);

    fetch('api_admin.php', { method: 'POST', body: datosFormulario })
    .then(respuesta => respuesta.json())
    .then(datos => { 
        if(datos.success) window.location.reload();
    });
}

/**
 * Gestiona la apertura del modal de eliminación de administración mediante Data Attributes.
 */
window.abrirModalEliminarAdmin = function(id) {
    const idUsuario = parseInt(id);
    
    if (isNaN(idUsuario)) {
        console.error("Error: El ID recibido no es un número válido:", id);
        return;
    }

    const modal = document.getElementById('deleteConfirmModal');
    if (modal) {
        modal.setAttribute('data-id-a-eliminar', idUsuario);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        setTimeout(() => {
            document.getElementById('modalOverlay')?.classList.replace('opacity-0', 'opacity-100');
            document.getElementById('modalContent')?.classList.replace('opacity-0', 'opacity-100');
            document.getElementById('modalContent')?.classList.replace('scale-95', 'scale-100');
        }, 10);
    }
};

/**
 * Ejecuta la redirección final para eliminar un usuario desde administración.
 */
window.ejecutarEliminacion = function() {
    const modal = document.getElementById('deleteConfirmModal');
    const id = modal ? modal.getAttribute('data-id-a-eliminar') : null;

    if (id && id !== "null") {
        window.location.href = `borrar_usuario.php?id=${id}`;
    } else {
        alert("Error crítico: El ID no se pudo recuperar del modal.");
    }
};

/**
 * Cierra el modal de eliminación de administración con transiciones.
 */
window.cerrarModalEliminar = function() {
    const modal = document.getElementById('deleteConfirmModal');
    if (!modal) return;

    document.getElementById('modalOverlay')?.classList.replace('opacity-100', 'opacity-0');
    document.getElementById('modalContent')?.classList.replace('opacity-100', 'opacity-0');
    document.getElementById('modalContent')?.classList.replace('scale-100', 'scale-95');

    setTimeout(() => {
        modal.classList.replace('flex', 'hidden');
        modal.removeAttribute('data-id-a-eliminar');
    }, 300);
};

/* ==========================================================================
   3. MÓDULO DE PLANIFICACIÓN NUTRICIONAL
   ========================================================================== */

/**
 * Muestra el detalle de una receta en el modal de planificación.
 */
function abrirReceta(r) {
    const modal = document.getElementById('modalReceta');
    if (!modal) return;

    document.getElementById('mTit').innerText = r.titulo;
    document.getElementById('mMom').innerText = r.momento_comida;
    document.getElementById('mDesc').innerText = r.descripcion || 'Sin descripción disponible.';
    document.getElementById('mMacros').innerHTML = `
        <div class="bg-gray-50 p-3 rounded-2xl text-center"><p class="text-[9px] text-gray-400 font-bold uppercase">Kcal</p><p class="font-black text-gray-800">${Math.round(r.calorias_por_racion)}</p></div>
        <div class="bg-blue-50 p-3 rounded-2xl text-center"><p class="text-[9px] text-blue-400 font-bold uppercase">Prot</p><p class="font-black text-blue-700">${Math.round(r.proteinas_g_por_racion)}g</p></div>
        <div class="bg-orange-50 p-3 rounded-2xl text-center"><p class="text-[9px] text-orange-400 font-bold uppercase">Carb</p><p class="font-black text-orange-700">${Math.round(r.carbohidratos_g_por_racion)}g</p></div>
        <div class="bg-red-50 p-3 rounded-2xl text-center"><p class="text-[9px] text-red-400 font-bold uppercase">Gras</p><p class="font-black text-red-700">${Math.round(r.grasas_g_por_racion)}g</p></div>
    `;
    modal.classList.remove('hidden');
}

/**
 * Muestra el resumen nutricional del día comparado con objetivos.
 */
function abrirResumenDia(nombre, totales) {
    const modal = document.getElementById('modalDia');
    if (!modal || !window.planData) return;

    const meta = window.planData.meta;
    document.getElementById('mDiaTit').innerText = "Resumen del " + nombre;
    
    const kcalPct = Math.min((totales.kcal / meta.kcal) * 100, 100);
    document.getElementById('mDiaKcalText').innerText = `${Math.round(totales.kcal)} / ${Math.round(meta.kcal)} kcal`;
    document.getElementById('mDiaKcalBar').style.width = kcalPct + "%";
    document.getElementById('mDiaKcalBar').className = "h-full transition-all duration-700 " + (totales.kcal > meta.kcal * 1.1 ? "bg-red-500" : "bg-teal-500");

    const macros = [
        { label: 'Proteínas', actual: totales.p, obj: meta.p, color: 'blue' },
        { label: 'Carbohidratos', actual: totales.c, obj: meta.c, color: 'orange' },
        { label: 'Grasas', actual: totales.g, obj: meta.g, color: 'red' }
    ];

    let html = '';
    macros.forEach(m => {
        const pct = Math.min((m.actual / m.obj) * 100, 100);
        html += `
            <div>
                <div class="flex justify-between text-[11px] font-bold mb-1 uppercase tracking-wider">
                    <span class="text-gray-400">${m.label}</span>
                    <span class="text-${m.color}-600">${Math.round(m.actual)}g / ${Math.round(m.obj)}g</span>
                </div>
                <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                    <div class="h-full bg-${m.color}-500" style="width: ${pct}%"></div>
                </div>
            </div>
        `;
    });
    
    document.getElementById('mDiaMacrosList').innerHTML = html;
    modal.classList.remove('hidden');
}

/**
 * Modal de confirmación para eliminar una comida asignada al plan.
 */
function confirmarEliminarComida(id) {
    const modal = document.getElementById('modalEliminar');
    const btn = document.getElementById('btnConfirmarEliminar');
    if (modal && btn) {
        btn.href = `?eliminar_id=${id}`;
        modal.classList.remove('hidden');
    }
}

function cerrarModalPlan(id) { 
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('hidden'); 
}

/* ==========================================================================
   4. MÓDULO DE INVENTARIO
   ========================================================================== */

/**
 * Abre el modal de inventario para adición o edición de ingredientes.
 */
function abrirModalInventario(modo, datos = {}) {
    const modal = document.getElementById('itemModal');
    const titulo = document.getElementById('modal-title');
    const campoAccion = document.getElementById('actionField');
    const campoIdInventario = document.getElementById('idInventarioField');
    const entradaNombre = document.getElementById('nombre_ingrediente');
    const entradaCantidad = document.getElementById('cantidad');
    const selectorUnidad = document.getElementById('unidad');
    const botonEnviar = document.getElementById('submitButton');

    if (!modal) return;

    if (modo === 'add') {
        titulo.textContent = 'Añadir Nuevo Ingrediente';
        campoAccion.value = 'add';
        campoIdInventario.value = '';
        entradaNombre.value = '';
        entradaCantidad.value = '';
        selectorUnidad.value = '';
        botonEnviar.innerHTML = '<i class="fas fa-save mr-2"></i> Añadir Ingrediente';
        botonEnviar.classList.replace('bg-blue-600', 'bg-teal-600');
    } else if (modo === 'edit') {
        titulo.textContent = `Editar: ${datos.nombre_ingrediente}`;
        campoAccion.value = 'edit';
        campoIdInventario.value = datos.id_inventario;
        entradaNombre.value = datos.nombre_ingrediente;
        entradaCantidad.value = parseFloat(datos.cantidad); 
        selectorUnidad.value = datos.unidad;
        botonEnviar.innerHTML = '<i class="fas fa-save mr-2"></i> Actualizar Ingrediente';
        botonEnviar.classList.replace('bg-teal-600', 'bg-blue-600'); 
    }
    
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function cerrarModalInventario() {
    const modal = document.getElementById('itemModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
}

/**
 * Gestiona el modal de confirmación de borrado de ítems de inventario.
 */
function abrirModalEliminarInventario(id, nombre) {
    const modalEliminar = document.getElementById('deleteConfirmModal');
    const visorNombreItem = document.getElementById('deleteItemName');
    const fondoModal = modalEliminar?.querySelector('.fixed.inset-0.bg-gray-900');
    const panelModal = modalEliminar?.querySelector('.inline-block.align-middle');

    if (!modalEliminar) return;
    if (visorNombreItem) visorNombreItem.textContent = nombre;
    
    // Evitar duplicidad de eventos clonando el botón
    const botonConfirmarAntiguo = document.getElementById('confirmDeleteButton');
    const botonConfirmarNuevo = botonConfirmarAntiguo.cloneNode(true);
    botonConfirmarAntiguo.replaceWith(botonConfirmarNuevo);
    
    botonConfirmarNuevo.addEventListener('click', () => {
        eliminarItemInventario(id);
    }, { once: true }); 

    modalEliminar.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');

    setTimeout(() => {
        if (fondoModal) fondoModal.classList.replace('opacity-0', 'opacity-80');
        if (panelModal) panelModal.classList.remove('scale-95', 'opacity-0');
        if (panelModal) panelModal.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function cerrarModalEliminarInventario() {
    const modalEliminar = document.getElementById('deleteConfirmModal');
    const fondoModal = modalEliminar?.querySelector('.fixed.inset-0.bg-gray-900');
    const panelModal = modalEliminar?.querySelector('.inline-block.align-middle');

    if (!modalEliminar) return;

    if (fondoModal) fondoModal.classList.remove('opacity-80');
    if (panelModal) panelModal.classList.replace('scale-100', 'scale-95');
    if (panelModal) panelModal.classList.replace('opacity-100', 'opacity-0');

    setTimeout(() => {
        modalEliminar.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }, 300);
}

/**
 * Procesa la eliminación del inventario mediante un formulario dinámico POST.
 */
function eliminarItemInventario(id) {
    cerrarModalEliminarInventario(); 
    const formularioBorrado = document.createElement('form');
    formularioBorrado.method = 'POST';
    formularioBorrado.action = 'inventario.php';
    
    const inputAction = (name, val) => {
        const el = document.createElement('input');
        el.type = 'hidden'; el.name = name; el.value = val;
        return el;
    };
    
    formularioBorrado.appendChild(inputAction('action', 'delete'));
    formularioBorrado.appendChild(inputAction('id_inventario', id));
    document.body.appendChild(formularioBorrado);
    formularioBorrado.submit();
}

/* ==========================================================================
   5. MÓDULO DE RECETAS (Filtros y Modal de Creación)
   ========================================================================== */

function abrirModalReceta() {
    const modal = document.getElementById('recipeChoiceModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; 
    }
}

function cerrarModalReceta() {
    const modal = document.getElementById('recipeChoiceModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

/**
 * Filtra las recetas por tipo (IA, Manual, Todos) y actualiza la UI.
 */
window.filtrarPor = function(tipo) {
    window.filtroRecetaActual = tipo;
    
    const botones = document.querySelectorAll('.filter-btn');
    botones.forEach(btn => {
        btn.classList.remove('bg-teal-600', 'text-white', 'active', 'shadow-md');
        btn.classList.add('bg-white', 'text-gray-400');
        
        if (btn.getAttribute('onclick').includes(`'${tipo}'`)) {
            btn.classList.add('bg-teal-600', 'text-white', 'active', 'shadow-md');
            btn.classList.remove('bg-white', 'text-gray-400');
        }
    });

    aplicarFiltrosRecetas();
};

/**
 * Aplica lógica combinada de búsqueda por texto y filtro por tipo.
 */
window.aplicarFiltrosRecetas = function() {
    const entradaBusqueda = document.getElementById('recipeSearch');
    const tarjetasReceta = document.querySelectorAll('.recipe-card');
    const sinResultados = document.getElementById('noResults');
    
    if (!tarjetasReceta.length) return;

    const terminoBusqueda = entradaBusqueda ? entradaBusqueda.value.toLowerCase() : "";
    let contadorVisibles = 0;

    tarjetasReceta.forEach(tarjeta => {
        const tipoTarjeta = tarjeta.getAttribute('data-tipo').toLowerCase();
        const tituloTarjeta = tarjeta.getAttribute('data-titulo').toLowerCase();

        const coincideTipo = (window.filtroRecetaActual === 'todos' || tipoTarjeta === window.filtroRecetaActual);
        const coincideBusqueda = tituloTarjeta.includes(terminoBusqueda);

        if (coincideTipo && coincideBusqueda) {
            tarjeta.classList.remove('hidden');
            tarjeta.style.display = 'block';
            contadorVisibles++;
        } else {
            tarjeta.classList.add('hidden');
            tarjeta.style.display = 'none';
        }
    });

    if (sinResultados) {
        sinResultados.style.display = (contadorVisibles === 0) ? 'block' : 'none';
    }
};

/**
 * Gestión específica para la eliminación de recetas del usuario.
 */
window.abrirModalEliminar = function(id) {
    window.recetaParaEliminar = id;
    const modal = document.getElementById('deleteConfirmModal');
    const overlay = document.getElementById('modalOverlay');
    const content = document.getElementById('modalContent');

    if (modal) {
        modal.classList.remove('hidden');
        setTimeout(() => {
            if (overlay) overlay.classList.add('opacity-100');
            if (content) content.classList.add('opacity-100', 'scale-100');
        }, 10);
    }
};

window.cerrarModalEliminarReceta = function() { // Renombrado internamente para claridad
    const modal = document.getElementById('deleteConfirmModal');
    const overlay = document.getElementById('modalOverlay');
    const content = document.getElementById('modalContent');

    if (overlay) overlay.classList.remove('opacity-100');
    if (content) content.classList.remove('opacity-100', 'scale-100');

    setTimeout(() => {
        if (modal) modal.classList.add('hidden');
        window.recetaParaEliminar = null;
    }, 300);
};

/* ==========================================================================
   6. INICIALIZACIÓN DE EVENTOS (DOMContentLoaded)
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function() {
    // Buscador de recetas
    const inputSearch = document.getElementById('recipeSearch');
    if (inputSearch) {
        inputSearch.addEventListener('input', aplicarFiltrosRecetas);
    }

    // Confirmación de eliminación de recetas
    const btnConfirmarReceta = document.getElementById('confirmDeleteButton');
    if (btnConfirmarReceta) {
        btnConfirmarReceta.onclick = function() {
            if (window.recetaParaEliminar) {
                window.location.href = '../../core/borrar_receta.php?id=' + window.recetaParaEliminar;
            }
        };
    }
});

/* ==========================================================================
   LÓGICA AL CARGAR EL DOM
   ========================================================================== */
document.addEventListener('DOMContentLoaded', () => {
    

    /* ==========================================================================
       1. CARRUSEL DE TESTIMONIOS (Landing Page)
       ========================================================================== */
    const carrilTestimonios = document.getElementById('testimony-track');
    
    // Verificamos si estamos en la Landing Page (donde existe el carrusel)
    if (carrilTestimonios) {
        const contenedorTestimonios = document.getElementById('testimony-wrapper');
        const diapositivas = carrilTestimonios.querySelectorAll('.flex-shrink-0');
        const puntosIndicadores = document.querySelectorAll('#testimony-dots .dot');
        
        let indiceActual = 0;
        const totalDiapositivas = diapositivas.length;
        const duracionDiapositiva = 7000; // 7 segundos

        function actualizarCarrusel() {
            const desplazamiento = -indiceActual * 100;
            carrilTestimonios.style.transform = `translateX(${desplazamiento}%)`;

            // Actualizar puntos indicadores
            puntosIndicadores.forEach((punto, indice) => {
                if (indice === indiceActual) {
                    punto.classList.replace('bg-gray-300', 'bg-teal-600');
                } else {
                    punto.classList.replace('bg-teal-600', 'bg-gray-300');
                }
            });
        }

        function irASiguiente() {
            indiceActual = (indiceActual + 1) % totalDiapositivas;
            actualizarCarrusel();
        }

        // Auto-rotación
        let idIntervalo = setInterval(irASiguiente, duracionDiapositiva);
        
        // Pausa al pasar el ratón
        if (contenedorTestimonios) {
            contenedorTestimonios.addEventListener('mouseenter', () => clearInterval(idIntervalo));
            contenedorTestimonios.addEventListener('mouseleave', () => {
                idIntervalo = setInterval(irASiguiente, duracionDiapositiva);
            });
        }

        actualizarCarrusel();
        console.log("Carrusel inicializado correctamente.");
    }


    /* ==========================================================================
       2. FILTRADO DE USUARIOS (Panel Admin)
       ========================================================================== */

    /**
     * Filtra los usuarios en pantalla basándose en el buscador y el selector de roles.
     */
    function filtrarUsuarios() {
        const entradaBusqueda = document.getElementById('realTimeSearch');
        const selectorRol = document.getElementById('roleFilter');
        
        if (!entradaBusqueda || !selectorRol) return;

        const terminoBusqueda = entradaBusqueda.value.toLowerCase().trim();
        const valorRol = selectorRol.value;
        const filas = document.querySelectorAll('.user-row');
        
        filas.forEach(fila => {
            const coincideTexto = (fila.getAttribute('data-search') || "").includes(terminoBusqueda);
            const coincideRol = (valorRol === 'all' || fila.getAttribute('data-role') === valorRol);
            
            // Se mantiene "grid" o "none" para no romper el diseño CSS de Tailwind/Flex
            fila.style.display = (coincideTexto && coincideRol) ? "grid" : "none";
        });
    }

    // Escuchadores de eventos para filtrado reactivo (Solo si existen los elementos)
    const buscadorTiempoReal = document.getElementById('realTimeSearch');
    const filtroRol = document.getElementById('roleFilter');

    if (buscadorTiempoReal) buscadorTiempoReal.addEventListener('input', filtrarUsuarios);
    if (filtroRol) filtroRol.addEventListener('change', filtrarUsuarios);


    /* ==========================================================================
       3. FILTRADO DE PACIENTES (Panel Nutricionista)
       ========================================================================== */
    const entradaBuscadorPaciente = document.getElementById('buscadorPaciente');
    if (entradaBuscadorPaciente) {
        entradaBuscadorPaciente.addEventListener('input', function(evento) {
            const terminoBusqueda = evento.target.value.toLowerCase().trim();
            const filasPacientes = document.querySelectorAll('.fila-paciente');
            const mensajeNoResultados = document.getElementById('noResultados');
            let totalEncontrados = 0;

            filasPacientes.forEach(fila => {
                const elementoNombre = fila.querySelector('.nombre-paciente');
                const elementoCorreo = fila.querySelector('.correo-paciente');
                
                const nombre = elementoNombre ? elementoNombre.textContent.toLowerCase() : "";
                const correo = elementoCorreo ? elementoCorreo.textContent.toLowerCase() : "";
                
                if (nombre.includes(terminoBusqueda) || correo.includes(terminoBusqueda)) {
                    fila.style.display = '';
                    totalEncontrados++;
                } else {
                    fila.style.display = 'none';
                }
            });

            if (mensajeNoResultados) {
                // Oculta el mensaje si hay resultados o si la búsqueda está vacía
                mensajeNoResultados.classList.toggle('hidden', totalEncontrados > 0 || terminoBusqueda === "");
            }
        });
    }


    /* ==========================================================================
       4. GESTIÓN DE RECETAS (Borrado)
       ========================================================================== */
    const botonConfirmarBorrado = document.getElementById('confirmDeleteButton');
    if (botonConfirmarBorrado) {
        botonConfirmarBorrado.addEventListener('click', function() {
            // Utilizamos la variable global "recetaParaEliminar" que definimos antes
            if (recetaParaEliminar) {
                window.location.href = '../../core/borrar_receta.php?id=' + recetaParaEliminar;
            }
        });
    }


    /* ==========================================================================
       5. GENERADOR DE RECETAS CON IA (Chef IA)
       ========================================================================== */
    const formularioIA = document.getElementById('formGeneradorIA');
    const capaCarga = document.getElementById('loadingOverlay');
    const elementoMensajeCarga = document.getElementById('loadingMessage');
    
    // Frases que se mostrarán cíclicamente durante la espera de la API
    const frasesDeEspera = [
        "Consultando tu inventario...",
        "Calculando proteínas...",
        "Buscando el sabor perfecto...",
        "Ajustando macros...",
        "Casi listo..."
    ];

    if (formularioIA) {
        formularioIA.addEventListener('submit', function() {
            // Mostramos la capa de carga (overlay)
            if (capaCarga) capaCarga.classList.remove('hidden');
            
            let indiceFrase = 0;
            // Intervalo para cambiar el mensaje cada 2 segundos
            setInterval(() => {
                if (elementoMensajeCarga) {
                    elementoMensajeCarga.innerText = frasesDeEspera[indiceFrase % frasesDeEspera.length];
                    indiceFrase++;
                }
            }, 2000);
        });
    }


    /* ==========================================================================
       6. PERFIL DE USUARIO (Gestión Dinámica de Restricciones)
       ========================================================================== */

    // Variable persistente para la sesión actual del DOM
    let listaRestricciones = [];

    /**
     * Renderiza la lista completa de restricciones en el DOM.
     */
    function renderizarRestricciones() {
        const contenedorLista = document.getElementById('restrictionsList');
        const entradaJsonOculta = document.getElementById('restricciones_json');
        
        // Si no estamos en la página de perfil, salimos
        if (!contenedorLista || !entradaJsonOculta) return;

        // Recuperamos el mapa de tipos desde el puente de datos
        const mapaTipos = window.profileData ? window.profileData.tipoMap : {};
        
        contenedorLista.innerHTML = '';
        
        if (listaRestricciones.length === 0) {
            contenedorLista.innerHTML = `<li class="text-sm text-gray-500 italic" id="emptyMessage">No hay restricciones añadidas.</li>`;
            entradaJsonOculta.value = '[]';
            return;
        }

        listaRestricciones.forEach((restriccion, indice) => {
            const itemLista = document.createElement('li');
            itemLista.className = 'flex justify-between items-center bg-white p-3 rounded-lg shadow-sm border border-red-100';
            
            // Determinar clase según el tipo
            const claseTipo = restriccion.tipo === 'alergia' ? 'bg-red-200 text-red-800' : 
                               restriccion.tipo === 'intolerancia' ? 'bg-orange-200 text-orange-800' : 
                               'bg-gray-200 text-gray-800';

            // Contenido del item
            itemLista.innerHTML = `
                <span>
                    <span class="font-medium text-gray-900">${restriccion.ingrediente.toUpperCase()}</span> 
                    <span class="ml-2 px-2 py-1 text-xs font-semibold rounded-full ${claseTipo}">
                        ${mapaTipos[restriccion.tipo] || restriccion.tipo}
                    </span>
                </span>
            `;
            
            // Botón de Eliminar
            const botonEliminar = document.createElement('button');
            botonEliminar.type = 'button';
            botonEliminar.innerHTML = `
                <svg class="w-5 h-5 text-red-500 hover:text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            `;
            botonEliminar.onclick = () => eliminarRestriccion(indice);
            
            itemLista.appendChild(botonEliminar);
            contenedorLista.appendChild(itemLista);
        });

        // Actualizar el campo oculto con el JSON para el envío del formulario PHP
        entradaJsonOculta.value = JSON.stringify(listaRestricciones);
    }

    /**
     * Añade una nueva restricción a la lista local.
     */
    function agregarRestriccion() {
        const entradaNuevoIngrediente = document.getElementById('nuevo_ingrediente');
        const selectorNuevoTipo = document.getElementById('new_tipo');
        
        if (!entradaNuevoIngrediente) return;

        const nombreIngrediente = entradaNuevoIngrediente.value.trim();
        const tipoSeleccionado = selectorNuevoTipo.value;

        if (nombreIngrediente === '') {
            Swal.fire('Error', 'Por favor, ingresa el nombre del ingrediente.', 'error');
            return;
        }

        // Evitar duplicados
        const esDuplicado = listaRestricciones.some(r => r.ingrediente.toLowerCase() === nombreIngrediente.toLowerCase());
        if (esDuplicado) {
            Swal.fire('Aviso', `El ingrediente "${nombreIngrediente}" ya está en la lista.`, 'info');
            entradaNuevoIngrediente.value = '';
            return;
        }
        
        const ingredienteFormateado = nombreIngrediente.charAt(0).toUpperCase() + nombreIngrediente.slice(1).toLowerCase();

        listaRestricciones.push({
            ingrediente: ingredienteFormateado,
            tipo: tipoSeleccionado
        });

        entradaNuevoIngrediente.value = '';
        renderizarRestricciones();
    }

    /**
     * Elimina una restricción por su índice en el array.
     */
    function eliminarRestriccion(indice) {
        listaRestricciones.splice(indice, 1);
        renderizarRestricciones();
    }

    // Inicialización de listeners de Perfil (Dentro del DOMContentLoaded general)
    const botonAñadirRestriccion = document.getElementById('agregarRestriccionBtn');
    const entradaTextoIngrediente = document.getElementById('nuevo_ingrediente');

    if (botonAñadirRestriccion) {
        // Cargar datos iniciales desde el puente PHP
        if (window.profileData && window.profileData.currentRestrictions) {
            listaRestricciones = window.profileData.currentRestrictions;
        }
        
        botonAñadirRestriccion.addEventListener('click', agregarRestriccion);
        renderizarRestricciones();
    }

    if (entradaTextoIngrediente) {
        entradaTextoIngrediente.addEventListener('keydown', function(evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                agregarRestriccion();
            }
        });
    }

    /* ==========================================================================
    7. PLANIFICACIÓN SEMANAL (Gestión de Comidas y Resúmenes Diarios)
    ========================================================================== */

    /**
     * Listener global para cerrar modales al hacer clic en el overlay (fondo oscuro).
     */
    window.addEventListener('click', function(e) {
        // Detectamos si el clic fue en el fondo oscuro de cualquier modal de planificación
        if (e.target.classList.contains('bg-black/60') || e.target.id === 'modalReceta' || e.target.id === 'modalDia' || e.target.id === 'modalEliminar') {
            const modales = ['modalReceta', 'modalDia', 'modalEliminar'];
            modales.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            });
        }
    });

    /* ==========================================================================
       8. SEGUIMIENTO DE PROGRESO (Gráficas y Estadísticas)
       ========================================================================== */

    /**
     * Inicializa y renderiza la gráfica de peso utilizando Chart.js
     */
    function inicializarGraficaProgreso() {
        const datosProgreso = window.progresoData;
        const contextoLienzo = document.getElementById("sparklineChart")?.getContext("2d");
        
        if (!contextoLienzo || !datosProgreso || datosProgreso.length < 2) return;

        const etiquetasFechas = datosProgreso.map(entrada => entrada.fecha);
        const listaPesos = datosProgreso.map(entrada => parseFloat(entrada.peso_kg));
        const datosCintura = datosProgreso.filter(entrada => entrada.cintura_cm > 0).map(entrada => parseFloat(entrada.cintura_cm));

        // --- Cálculo de Métricas en el DOM ---
        const pesoInicial = listaPesos[0];
        const pesoActual = listaPesos[listaPesos.length - 1];
        const diferenciaPeso = (pesoActual - pesoInicial).toFixed(1);

        if(document.getElementById("statInicial")) document.getElementById("statInicial").textContent = pesoInicial + " kg";
        if(document.getElementById("statActual")) document.getElementById("statActual").textContent = pesoActual + " kg";

        const elementoCambio = document.getElementById("statCambio");
        if (elementoCambio) {
            elementoCambio.textContent = diferenciaPeso + " kg";
            // Color según si subió o bajó de peso
            elementoCambio.style.color = diferenciaPeso < 0 ? "#059669" : (diferenciaPeso > 0 ? "#dc2626" : "#374151");
        }

        // Métricas de Cintura
        const estadisticaCinturaCambio = document.getElementById("statCinturaCambio");
        if (estadisticaCinturaCambio) {
            if (datosCintura.length >= 2) {
                const cinturaInicial = datosCintura[0];
                const cinturaActual = datosCintura[datosCintura.length - 1];
                const diferenciaCintura = (cinturaActual - cinturaInicial).toFixed(1);
                estadisticaCinturaCambio.textContent = diferenciaCintura + " cm";
                estadisticaCinturaCambio.style.color = diferenciaCintura < 0 ? "#059669" : (diferenciaCintura > 0 ? "#dc2626" : "#374151");
            } else {
                estadisticaCinturaCambio.textContent = "- N/A -";
            }
        }

        // Texto de tendencia
        const textoTendencia = document.getElementById("trendText");
        const iconoTendencia = document.getElementById("trendIcon");
        if (textoTendencia && iconoTendencia) {
            const tendencia = pesoActual - pesoInicial;
            textoTendencia.textContent = tendencia < 0 ? "Descenso saludable" : (tendencia > 0 ? "Aumento de peso" : "Estable");
            iconoTendencia.textContent = tendencia < 0 ? "📉" : (tendencia > 0 ? "📈" : "➖");
        }

        // --- Renderizado de Chart.js ---
        const degradado = contextoLienzo.createLinearGradient(0, 0, 0, 250);
        degradado.addColorStop(0, "rgba(13, 148, 136, 0.4)");
        degradado.addColorStop(1, "rgba(13, 148, 136, 0)");

        new Chart(contextoLienzo, {
            type: "line",
            data: { 
                labels: etiquetasFechas, 
                datasets: [{ 
                    data: listaPesos, 
                    borderColor: "#0d9488", 
                    borderWidth: 3, 
                    backgroundColor: degradado, 
                    pointRadius: 0, 
                    tension: 0.4, 
                    fill: true 
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { display: false }, 
                    tooltip: { enabled: true, callbacks: { label: contexto => contexto.raw + " kg" } } 
                },
                scales: { 
                    x: { display: false }, 
                    y: { display: false, suggestedMin: Math.min(...listaPesos) - 1 } 
                }
            }
        });
    }

    /**
     * Gestión del Modal de Eliminación de registros de progreso
     */
    function mostrarModalEliminarProgreso(fecha, fechaLegible) {
        const modal = document.getElementById('deleteModal');
        if (!modal) return;
        document.getElementById('modalFechaDisplay').textContent = fechaLegible;
        document.getElementById('modalFechaInput').value = fecha;
        modal.classList.remove('opacity-0', 'pointer-events-none');
        modal.querySelector('.max-w-sm')?.classList.add('scale-100');
    }

    function ocultarModalEliminarProgreso() {
        const modal = document.getElementById('deleteModal');
        if (!modal) return;
        modal.classList.add('opacity-0', 'pointer-events-none');
        modal.querySelector('.max-w-sm')?.classList.remove('scale-100');
    }

    // Inicialización de Progreso
    const contenedorGrafica = document.getElementById('chartContainer');
    if (contenedorGrafica && window.progresoData) {
        if (window.progresoData.length >= 2) {
            contenedorGrafica.style.display = 'block';
            inicializarGraficaProgreso();
        } else {
            contenedorGrafica.style.display = 'none';
        }
    }

    // Escuchadores para botones de eliminar progreso
    document.querySelectorAll('.btn-delete-progreso').forEach(boton => {
        boton.addEventListener('click', () => {
            mostrarModalEliminarProgreso(boton.dataset.fecha, boton.dataset.displayFecha);
        });
    });

    const cancelarEliminarProgreso = document.getElementById('cancelDeleteBtn');
    if (cancelarEliminarProgreso) {
        cancelarEliminarProgreso.addEventListener('click', ocultarModalEliminarProgreso);
    }

    /* ==========================================================================
       9. DASHBOARD: GRÁFICOS DE MACROS Y PROGRESO
       ========================================================================== */

    // 1. Gráfico de Macronutrientes (Doughnut/Dona)
    const lienzoMacros = document.getElementById('macroChart');
    if (lienzoMacros && window.dashboardData && window.dashboardData.hasProfile) {
        new Chart(lienzoMacros, {
            type: 'doughnut',
            data: {
                labels: ['Proteínas', 'Carbohidratos', 'Grasas'],
                datasets: [{
                    data: window.dashboardData.macros,
                    backgroundColor: ['#4287E2', '#FBBF24', '#F97316'],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(contexto) {
                                let etiqueta = contexto.label || '';
                                if (etiqueta) etiqueta += ': ';
                                if (contexto.parsed !== null) etiqueta += contexto.parsed + 'g';
                                return etiqueta;
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Gráfico de Progreso (Línea)
    const datosProgresoDashboard = window.dashboardData ? window.dashboardData.progreso : [];
    const lienzoProgreso = document.getElementById("progressChart");

    if (lienzoProgreso && datosProgresoDashboard.length >= 2) {
        const etiquetasFechas = datosProgresoDashboard.map(e => e.fecha);
        const listaPesos = datosProgresoDashboard.map(e => parseFloat(e.peso_kg));
        const contextoProgreso = lienzoProgreso.getContext("2d");
        
        const degradadoProgreso = contextoProgreso.createLinearGradient(0, 0, 0, 250);
        degradadoProgreso.addColorStop(0, "rgba(59, 130, 246, 0.4)");
        degradadoProgreso.addColorStop(1, "rgba(59, 130, 246, 0)");

        new Chart(contextoProgreso, {
            type: "line",
            data: { 
                labels: etiquetasFechas, 
                datasets: [{ 
                    data: listaPesos, 
                    borderColor: "#3b82f6", 
                    borderWidth: 3, 
                    backgroundColor: degradadoProgreso, 
                    pointRadius: 3, 
                    pointBackgroundColor: "#3b82f6",
                    tension: 0.3, 
                    fill: true 
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                animation: { duration: 1200, easing: "easeOutQuart" }, 
                plugins: { 
                    legend: { display: false }, 
                    tooltip: { 
                        backgroundColor: "#1e3a8a", 
                        padding: 10, 
                        displayColors: false, 
                        callbacks: { 
                            title: ctx => (new Date(ctx[0].label)).toLocaleDateString('es-ES', { day: '2-digit', month: 'short' }),
                            label: ctx => 'Peso: ' + ctx.raw + " kg" 
                        } 
                    } 
                }, 
                scales: {
                    x: { display: false }, 
                    y: { 
                        display: false, 
                        suggestMin: Math.min(...listaPesos) - 0.5, 
                        suggestMax: Math.max(...listaPesos) + 0.5  
                    } 
                } 
            }
        });
    }
});