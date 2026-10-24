<?php
/**
 * PLANTILLA: TEMA CLARO / OSCURO - KaloAI
 * Va dentro de <head>, antes de pintar nada, para que no haya destello.
 * Orden: ?tema=oscuro|claro en la URL (se recuerda y se quita de la barra),
 * si no lo que se eligió antes con el botón, si no el tema del sistema.
 * El modo oscuro es la clase .dark en <html>; los colores están en
 * public/css/oscuro.css.
 */
?>
<meta name="color-scheme" content="light dark">
<script>
    (function () {
        try {
            var url = new URL(location.href);
            var pedido = url.searchParams.get('tema');
            if (pedido === 'oscuro' || pedido === 'claro') {
                localStorage.setItem('kalo-tema', pedido);
                url.searchParams.delete('tema');
                history.replaceState(null, '', url.pathname + url.search + url.hash);
            }
            var guardado = localStorage.getItem('kalo-tema');
            var oscuro = guardado ? guardado === 'oscuro' : matchMedia('(prefers-color-scheme: dark)').matches;
            if (oscuro) document.documentElement.classList.add('dark');
        } catch (e) {}
    })();
</script>
