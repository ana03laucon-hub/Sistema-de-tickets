<?php
require_once __DIR__ . '/../../config/config.php';
requerirLogin();

$tituloPagina = 'Guía rápida';
$subtituloPagina = 'Antes de registrar una solicitud, revisa si tu problema es de los que se resuelven en un par de minutos.';
require_once __DIR__ . '/../../includes/header.php';
?>

<section class="pasos-guia">
    <div class="paso-guia">
        <span class="paso-numero">1</span>
        <h3>¿Tienes un problema?</h3>
        <p>Puede ser tu equipo, la impresora, el internet o algún programa que dejó de funcionar correctamente.</p>
    </div>
    <div class="paso-guia">
        <span class="paso-numero">2</span>
        <h3>Registra tu solicitud</h3>
        <p>Si no lo resuelves con los pasos de abajo, da clic en <strong>"Nueva solicitud"</strong> y describe el problema con el mayor detalle posible.</p>
    </div>
    <div class="paso-guia">
        <span class="paso-numero">3</span>
        <h3>Un técnico te atenderá</h3>
        <p>La Coordinación de Gobierno Electrónico asignará tu solicitud a un técnico, quien le dará seguimiento hasta resolverla.</p>
    </div>
</section>

<?php if (rolActual() === 3): ?>
<p style="text-align:center; margin: 1.5rem 0;">
    <a href="<?= BASE_URL ?>/modules/tickets/crear.php" class="btn btn-primario">Registrar nueva solicitud</a>
</p>
<?php endif; ?>

<h2 style="margin-top:2.5rem;">Problemas comunes, soluciones rápidas</h2>
<p class="texto-suave">Si tu equipo presenta alguno de estos problemas, intenta primero estos pasos antes de levantar un ticket.</p>

<div class="lista-guias">

    <details class="guia-item">
        <summary>Mi equipo no enciende</summary>
        <ol>
            <li>Verifica que el cable de corriente esté bien conectado tanto al equipo como al contacto de pared.</li>
            <li>Prueba el contacto con otro aparato (lámpara, cargador) para descartar que sea falla eléctrica del lugar.</li>
            <li>Revisa que el botón del regulador o "no-break" esté encendido, si tu equipo usa uno.</li>
            <li>Mantén presionado el botón de encendido 10 segundos, suéltalo y vuelve a intentar.</li>
        </ol>
        <p class="texto-suave">¿Sigue sin encender? Registra una solicitud de categoría <strong>Hardware</strong>.</p>
    </details>

    <details class="guia-item">
        <summary>No tengo conexión a internet</summary>
        <ol>
            <li>Revisa que el cable de red esté bien conectado en tu computadora y no esté dañado.</li>
            <li>Verifica si tus compañeros del mismo departamento también se quedaron sin internet (indica una falla general, no solo de tu equipo).</li>
            <li>Reinicia tu computadora.</li>
            <li>Si usas WiFi, verifica que esté activado y trata de reconectarte a la red del Ayuntamiento.</li>
        </ol>
        <p class="texto-suave">¿Sigue sin funcionar? Registra una solicitud de categoría <strong>Red e Internet</strong>, indicando si afecta solo a tu equipo o a todo el departamento.</p>
    </details>

    <details class="guia-item">
        <summary>El monitor no muestra imagen</summary>
        <ol>
            <li>Verifica que el monitor esté encendido (el led indicador debe estar iluminado).</li>
            <li>Revisa que el cable de video esté bien conectado tanto al monitor como a la computadora.</li>
            <li>Prueba mover el mouse o presionar una tecla, ya que el equipo pudo entrar en modo de ahorro de energía.</li>
            <li>Verifica que el monitor esté configurado en la entrada de video correcta (HDMI, VGA, etc.).</li>
        </ol>
        <p class="texto-suave">¿Sigue sin mostrar imagen? Registra una solicitud de categoría <strong>Hardware</strong>.</p>
    </details>

    <details class="guia-item">
        <summary>No puedo imprimir o escanear</summary>
        <ol>
            <li>Verifica que la impresora esté encendida y con papel suficiente.</li>
            <li>Revisa que no haya ningún mensaje de error en la pantalla de la impresora.</li>
            <li>Intenta imprimir un documento distinto para descartar que el problema sea del archivo original.</li>
            <li>Reinicia la impresora apagándola, esperando 10 segundos y encendiéndola de nuevo.</li>
        </ol>
        <p class="texto-suave">¿Sigue sin funcionar? Registra una solicitud de categoría <strong>Hardware</strong>, indicando el modelo de la impresora si lo conoces.</p>
    </details>

    <details class="guia-item">
        <summary>Olvidé mi contraseña o no puedo entrar al sistema</summary>
        <ol>
            <li>Verifica que estés escribiendo tu usuario y contraseña correctamente (revisa mayúsculas y el bloqueo de mayúsculas del teclado).</li>
            <li>Confirma con tu jefe de área cuál es el usuario asignado a tu departamento.</li>
        </ol>
        <p class="texto-suave">Por seguridad, solo el Administrador puede restablecer contraseñas. Solicítalo directamente a la Coordinación de Gobierno Electrónico.</p>
    </details>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
