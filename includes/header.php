<?php
/**
 * Header común con menú lateral izquierdo y barra de título superior.
 * Debe incluirse después de config/config.php y después de
 * requerirLogin()/requerirRol() cuando aplique.
 *
 * Variables opcionales:
 *   $tituloPagina     - título grande de la página (obligatorio de facto)
 *   $subtituloPagina  - línea gris debajo del título
 */
$tituloPagina = $tituloPagina ?? 'Inicio';
$subtituloPagina = $subtituloPagina ?? null;

$totalNoLeidas = 0;
if (usuarioAutenticado()) {
    $pdo = conectarDB();
    $stmtNotif = $pdo->prepare('SELECT COUNT(*) AS total FROM notificaciones WHERE id_usuario = :id AND leida = 0');
    $stmtNotif->execute(['id' => $_SESSION['id_usuario']]);
    $totalNoLeidas = (int)$stmtNotif->fetch()['total'];
}

// Para resaltar el enlace activo del menú según el archivo actual.
$paginaActual = basename($_SERVER['SCRIPT_NAME']);
function claseActiva(string $archivo, string $actual): string
{
    return $archivo === $actual ? 'activo' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($tituloPagina) ?> · <?= h(NOMBRE_SISTEMA) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/estilos.css">
</head>
<body>
<div class="layout">
    <?php if (usuarioAutenticado()): ?>
    <input type="checkbox" id="menu-toggle" class="menu-toggle-checkbox">
    <label for="menu-toggle" class="menu-toggle-btn" aria-label="Abrir menú">☰</label>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="sidebar-title">SISTEMA DE TICKETS</span>
            <span class="sidebar-subtitle">AYUNTAMIENTO<br>DE TEHUACÁN</span>
        </div>

        <nav class="sidebar-nav">
            <a href="<?= BASE_URL ?>/modules/tickets/dashboard.php" class="<?= claseActiva('dashboard.php', $paginaActual) ?>">Panel</a>
            <a href="<?= BASE_URL ?>/modules/tickets/listar.php" class="<?= claseActiva('listar.php', $paginaActual) ?>">Tickets</a>
            <a href="<?= BASE_URL ?>/modules/tickets/recepcion.php" class="<?= claseActiva('recercion.php', $paginaActual) ?>">Recepción de equipo</a>
            <?php if (rolActual() === 3): ?>
                <a href="<?= BASE_URL ?>/modules/tickets/crear.php" class="<?= claseActiva('crear.php', $paginaActual) ?>">Nueva solicitud</a>
                <a href="<?= BASE_URL ?>/modules/tickets/ayuda.php" class="<?= claseActiva('ayuda.php', $paginaActual) ?>">Guía rápida</a>
            <?php endif; ?>
            <?php if (rolActual() === 1): ?>
                <a href="<?= BASE_URL ?>/modules/admin/usuarios.php" class="<?= claseActiva('usuarios.php', $paginaActual) ?>">Usuarios</a>
                <a href="<?= BASE_URL ?>/modules/admin/catalogos.php" class="<?= claseActiva('catalogos.php', $paginaActual) ?>">Catálogos</a>
                <a href="<?= BASE_URL ?>/modules/admin/reportes.php" class="<?= claseActiva('reportes.php', $paginaActual) ?>">Reportes</a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/modules/tickets/notificaciones.php" class="notif-link <?= claseActiva('notificaciones.php', $paginaActual) ?>">
                Notificaciones
                <?php if ($totalNoLeidas > 0): ?>
                    <span class="notif-badge"><?= $totalNoLeidas ?></span>
                <?php endif; ?>
            </a>
        </nav>

        <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="sidebar-logout">Cerrar sesión</a>
    </aside>
    <?php endif; ?>

    <main class="contenido">
        <?php if (usuarioAutenticado()): ?>
        <div class="header-pagina">
            <div>
                <h1><?= h($tituloPagina) ?></h1>
                <?php if ($subtituloPagina): ?><p class="header-subtitulo"><?= h($subtituloPagina) ?></p><?php endif; ?>
            </div>
            <a href="<?= BASE_URL ?>/modules/auth/perfil.php" class="header-usuario">
                <span class="avatar-circulo"></span>
                <span class="header-usuario-info">
                    <strong><?= h($_SESSION['nombre_completo']) ?></strong>
                    <?= h($_SESSION['nombre_rol']) ?>
                </span>
            </a>
        </div>
        <?php endif; ?>
        <div class="contenedor">
