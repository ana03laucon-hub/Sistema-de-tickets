<?php
require_once __DIR__ . '/config/config.php';

if (usuarioAutenticado()) {
    redirigir('/modules/tickets/dashboard.php');
}

$error = null;
if (isset($_GET['error']) && $_GET['error'] === 'sesion') {
    $error = 'Debe iniciar sesión para continuar.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $error = 'Sesión de formulario inválida, intente de nuevo.';
    } else {
        $usuario = trim($_POST['usuario'] ?? '');
        $contrasena = $_POST['contrasena'] ?? '';

        if ($usuario === '' || $contrasena === '') {
            $error = 'Ingrese usuario y contraseña.';
        } else {
            $pdo = conectarDB();
            $stmt = $pdo->prepare(
                'SELECT u.*, r.nombre_rol FROM usuarios u
                 JOIN roles r ON r.id_rol = u.id_rol
                 WHERE u.usuario = :usuario AND u.activo = 1'
            );
            $stmt->execute(['usuario' => $usuario]);
            $datosUsuario = $stmt->fetch();

            if ($datosUsuario && password_verify($contrasena, $datosUsuario['contrasena'])) {
                session_regenerate_id(true);
                $_SESSION['id_usuario']      = $datosUsuario['id_usuario'];
                $_SESSION['nombre_completo'] = $datosUsuario['nombre_completo'];
                $_SESSION['id_rol']          = (int)$datosUsuario['id_rol'];
                $_SESSION['nombre_rol']      = $datosUsuario['nombre_rol'];
                $_SESSION['id_departamento'] = $datosUsuario['id_departamento'] ? (int)$datosUsuario['id_depaartamento'] : null;

                $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id')
                    ->execute(['id' => $datosUsuario['id_usuario']]);

                redirigir('/modules/tickets/dashboard.php');
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        }
    }
}

$tituloPagina = 'Iniciar sesión';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($tituloPagina) ?> · <?= h(NOMBRE_SISTEMA) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/estilos.css">
</head>
<body class="login-body">
    <div class="login-card">
        <h1>Sistema de Soporte Técnico</h1>
        <h2>H. Ayuntamiento de Tehuacán</h2>

        <?php if ($error): ?>
            <div class="alerta alerta-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">

            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" required autofocus>

            <label for="contrasena">Contraseña</label>
            <input type="password" id="contrasena" name="contrasena" required>

            <button type="submit" class="btn btn-primario">Ingresar</button>
        </form>
    </div>
</body>
</html>
