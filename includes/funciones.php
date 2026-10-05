<?php
/**
 * Funciones auxiliares generales del sistema.
 */

/** Escapa texto para salida segura en HTML (previene XSS). */
function h(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/* ============================================================
 * VALIDACIÓN DE DATOS DE ENTRADA
 * Requerimiento no funcional: "El sistema deberá validar los
 * datos introducidos por los usuarios antes de almacenarlos".
 * ============================================================ */

/** Valida formato de correo electrónico. */
function validarCorreo(string $correo): bool
{
    return filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;
}

/** Valida que un texto no esté vacío y no exceda una longitud máxima. */
function validarTexto(string $texto, int $min = 1, int $max = 255): bool
{
    $largo = largoTexto(trim($texto));
    return $largo >= $min && $largo <= $max;
}

/**
 * Longitud de un texto en caracteres. Usa mb_strlen si la extensión
 * mbstring está disponible; si el servidor no la tiene habilitada
 * (algunos hostings compartidos la omiten), recurre a strlen normal
 * para que el sistema siga funcionando sin interrumpirse.
 */
function largoTexto(string $texto): int
{
    return function_exists('mb_strlen') ? mb_strlen($texto) : strlen($texto);
}

/** Recorta un texto a un máximo de caracteres, con la misma tolerancia a falta de mbstring. */
function recortarTexto(string $texto, int $max): string
{
    return function_exists('mb_substr') ? mb_substr($texto, 0, $max) : substr($texto, 0, $max);
}

/** Valida que un valor exista como llave primaria activa en una tabla catálogo. */
function existeEnCatalogo(PDO $pdo, string $tabla, string $columnaId, int $valor): bool
{
    $tablasPermitidas = ['categorias', 'prioridades', 'estados', 'roles', 'departamentos', 'usuarios'];
    if (!in_array($tabla, $tablasPermitidas, true)) {
        return false; // Lista blanca: nunca interpolar un nombre de tabla no controlado.
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM `$tabla` WHERE `$columnaId` = :valor");
    $stmt->execute(['valor' => $valor]);
    return (int)$stmt->fetch()['total'] > 0;
}

/** Sanitiza un texto simple recortando espacios y controlando su longitud máxima. */
function limpiarTexto(string $texto, int $max = 255): string
{
    return recortarTexto(trim($texto), $max);
}

/** Redirige a otra URL dentro del sistema y detiene la ejecución. */
function redirigir(string $ruta): void
{
    header('Location: ' . BASE_URL . $ruta);
    exit;
}

/** Verifica si hay una sesión de usuario activa. */
function usuarioAutenticado(): bool
{
    return isset($_SESSION['id_usuario']);
}

/** Obtiene el id del rol del usuario en sesión (1=Admin, 2=Técnico, 3=Usuario). */
function rolActual(): ?int
{
    return $_SESSION['id_rol'] ?? null;
}

/** Obliga a que exista sesión activa; si no, redirige al login. */
function requerirLogin(): void
{
    if (!usuarioAutenticado()) {
        redirigir('/index.php?error=sesion');
    }
}

/** Obliga a que el usuario en sesión tenga uno de los roles permitidos. */
function requerirRol(array $rolesPermitidos): void
{
    requerirLogin();
    if (!in_array(rolActual(), $rolesPermitidos, true)) {
        http_response_code(403);
        die('No tiene permisos para acceder a esta sección.');
    }
}

/** Genera y guarda un token CSRF en sesión. */
function generarTokenCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Valida el token CSRF recibido de un formulario. */
function validarTokenCSRF(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && $token !== null
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Genera un folio único con formato TCK-AAAA-000001. */
function generarFolio(PDO $pdo): string
{
    $anio = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM tickets WHERE YEAR(fecha_creacion) = $anio");
    $total = (int)$stmt->fetch()['total'] + 1;
    return sprintf('TCK-%s-%06d', $anio, $total);
}

/** Genera un folio único para un equipo ingresado a soporte. */
function generarFolioEquipo(PDO $pdo): string
{
    $anio = date('Y');
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM equipos_soporte WHERE YEAR(fecha_ingreso) = :anio');
    $stmt->execute(['anio' => $anio]);
    $total = (int)$stmt->fetch()['total'] + 1;
    return sprintf('EQ-%s-%06d', $anio, $total);
}

/** Devuelve una etiqueta visual para el estado de un equipo. */
function badgeEstadoEquipo(string $estado): string
{
    $clases = [
        'Recibido' => 'equipo-estado-recibido',
        'En reparación' => 'equipo-estado-proceso',
        'Listo para entrega' => 'equipo-estado-listo',
        'Entregado' => 'equipo-estado-entregado',
    ];
    $clase = $clases[$estado] ?? 'equipo-estado-recibido';
    return '<span class="badge-equipo ' . h($clase) . '">' . h($estado) . '</span>';
}

/** Da formato legible a una fecha (dd/mm/aaaa hh:mm). */
function formatoFecha(?string $fecha): string
{
    if (!$fecha) {
        return '—';
    }
    $dt = new DateTime($fecha);
    return $dt->format('d/m/Y H:i');
}

/** Registra un movimiento de auditoría de cambio de estado. */
function registrarHistorialEstado(PDO $pdo, int $idTicket, ?int $estadoAnterior, int $estadoNuevo, int $idUsuario): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO historial_estados (id_ticket, id_estado_anterior, id_estado_nuevo, id_usuario)
         VALUES (:ticket, :anterior, :nuevo, :usuario)'
    );
    $stmt->execute([
        'ticket'   => $idTicket,
        'anterior' => $estadoAnterior,
        'nuevo'    => $estadoNuevo,
        'usuario'  => $idUsuario,
    ]);
}

/** Crea una notificación interna para un usuario. */
function crearNotificacion(PDO $pdo, int $idUsuario, string $mensaje, ?int $idTicket = null, ?int $idEquipo = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notificaciones (id_usuario, id_ticket, id_equipo, mensaje) 
        VALUES (:usuario, :ticket, :equipo, :mensaje)'
    );
    $stmt->execute([
        'usuario' => $idUsuario, 
        'ticket' => $idTicket,
        'equipo' => $idEquipo, 
        'mensaje' => $mensaje]);
}

/** Sube un archivo adjunto validando extensión y tamaño. Devuelve el nombre guardado o null. */
function subirArchivo(array $archivo): ?string
{
    if (empty($archivo['name']) || $archivo['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if ($archivo['size'] > TAMANO_MAXIMO_ARCHIVO) {
        throw new RuntimeException('El archivo excede el tamaño máximo permitido (5 MB).');
    }

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, EXTENSIONES_PERMITIDAS, true)) {
        throw new RuntimeException('Tipo de archivo no permitido.');
    }

    if (!is_dir(RUTA_UPLOADS)) {
        mkdir(RUTA_UPLOADS, 0755, true);
    }

    $nombreFinal = uniqid('adj_', true) . '.' . $extension;
    $destino = RUTA_UPLOADS . $nombreFinal;

    if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
        throw new RuntimeException('No fue posible guardar el archivo adjunto.');
    }

    return $nombreFinal;
}

/**
 * Verifica si el usuario en sesión tiene permiso para ver un ticket dado
 * (requerimiento no funcional: la información solo debe estar disponible
 * para usuarios autorizados). Administradores ven todo; el técnico solo
 * los que tiene asignados; el usuario solo los que él mismo creó.
 */
function usuarioPuedeVerTicket(PDO $pdo, int $idTicket, int $idUsuario, int $rol): bool
{
    if ($rol === 1) {
        return true;
    }
    $stmt = $pdo->prepare('SELECT id_usuario_creador, id_tecnico_asignado FROM tickets WHERE id_ticket = :id');
    $stmt->execute(['id' => $idTicket]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        return false;
    }
    if ($rol === 3) {
        return (int)$ticket['id_usuario_creador'] === $idUsuario;
    }
    if ($rol === 2) {
        return (int)$ticket['id_tecnico_asignado'] === $idUsuario;
    }
    return false;
}

/** Devuelve una etiqueta HTML tipo "badge" con el color del estado. */
function badgeEstado(string $nombre, string $color): string
{
    return sprintf(
        '<span class="badge" style="color:%s">%s</span>',
        h($color),
        h($nombre)
    );
}

