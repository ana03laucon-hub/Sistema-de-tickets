<?php
require_once __DIR__ . '/../../config/config.php';
requerirLogin();

$pdo = conectarDB();
$rol = rolActual();
$idUsuario = (int)$_SESSION['id_usuario'];
$esPersonalSoporte = in_array($rol, [1, 2], true);

$error = null;
$exito = null;
$idEquipoRegistrado = null;

// El formulario de ingreso solo está disponible para Administrador y Técnico.
$usuarios = $esPersonalSoporte
    ? $pdo->query(
        "SELECT u.id_usuario, u.nombre_completo, u.id_departamento,
                d.nombre AS departamento_nombre
         FROM usuarios u
         LEFT JOIN departamentos d
            ON d.id_departamento = u.id_departamento
         WHERE u.id_rol = 3
           AND u.activo = 1
         ORDER BY u.nombre_completo"
      )->fetchAll()
    : [];

$departamentos = $pdo->query(
    'SELECT id_departamento, nombre
     FROM departamentos
     WHERE activo = 1
     ORDER BY nombre'
)->fetchAll();

$tiposEquipo = [
    'Solo CPU',
    'CPU y Monitor',
    'Impresora',
    'Otro'
];

$condicionesCables = [
    'Con cable',
    'Sin cable',
    'Con cables',
    'Sin cables',
    'Cables USB',
    'Cable de alimentación'
];

$estadosPermitidos = [
    'Recibido',
    'En reparación',
    'Listo para entrega',
    'Entregado'
];


/*
|--------------------------------------------------------------------------
| PROCESAMIENTO DE FORMULARIOS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {

        $error = 'Sesión de formulario inválida, intente de nuevo.';

    } else {

        $accion = $_POST['accion'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | REGISTRAR INGRESO DE EQUIPO
        |--------------------------------------------------------------------------
        */

        if ($accion === 'registrar_ingreso' && $esPersonalSoporte) {

            $idUsuarioEntrega = (int)($_POST['id_usuario_entrega'] ?? 0);

            $nombrePersonaEntrega = trim(
                $_POST['nombre_persona_entrega'] ?? ''
            );

            $idDepartamento = (int)(
                $_POST['id_departamento'] ?? 0
            ) ?: null;

            $tipoEquipo = trim(
                $_POST['tipo_equipo'] ?? ''
            );

            $condicionCables = trim(
                $_POST['condicion_cables'] ?? ''
            );

            $otroEquipo = trim(
                $_POST['otro_equipo'] ?? ''
            );

            $descripcion = trim(
                $_POST['descripcion'] ?? ''
            );

            $folioTicket = trim(
                $_POST['folio_ticket'] ?? ''
            );

            /*
            |--------------------------------------------------------------------------
            | VALIDAR USUARIO
            |--------------------------------------------------------------------------
            */

            $stmtUsuarioValido = $pdo->prepare(
                'SELECT COUNT(*) AS total
                 FROM usuarios
                 WHERE id_usuario = :id
                   AND id_rol = 3
                   AND activo = 1'
            );

            $stmtUsuarioValido->execute([
                'id' => $idUsuarioEntrega
            ]);

            $usuarioValido =
                (int)$stmtUsuarioValido->fetch()['total'] > 0;


            /*
            |--------------------------------------------------------------------------
            | VALIDACIONES
            |--------------------------------------------------------------------------
            */

            if (!$usuarioValido) {

                $error =
                    'El usuario que entrega el equipo no es válido.';

            } elseif (!validarTexto(
                $nombrePersonaEntrega,
                3,
                100
            )) {

                $error =
                    'El nombre de la persona que entrega debe tener entre 3 y 100 caracteres.';

            } elseif (
                $idDepartamento !== null &&
                !existeEnCatalogo(
                    $pdo,
                    'departamentos',
                    'id_departamento',
                    $idDepartamento
                )
            ) {

                $error =
                    'El área o departamento seleccionado no es válido.';

            } elseif (
                !in_array(
                    $tipoEquipo,
                    $tiposEquipo,
                    true
                )
            ) {

                $error =
                    'Seleccione un tipo de equipo válido.';

            } elseif (
                $tipoEquipo === 'Otro' &&
                !validarTexto(
                    $otroEquipo,
                    2,
                    150
                )
            ) {

                $error =
                    'Especifique el tipo de equipo en el campo "Otro".';

            } elseif (
                !validarTexto(
                    $descripcion,
                    5,
                    2000
                )
            ) {

                $error =
                    'La descripción debe tener entre 5 y 2000 caracteres.';

            } elseif (
                $condicionCables !== '' &&
                !in_array(
                    $condicionCables,
                    $condicionesCables,
                    true
                )
            ) {

                $error =
                    'La condición de cables seleccionada no es válida.';
            }


            /*
            |--------------------------------------------------------------------------
            | PROCESAR EVIDENCIA DE RECEPCIÓN
            |--------------------------------------------------------------------------
            */

            if (!$error) {

                try {

                    $nombreArchivo = null;

                    /*
                     * La evidencia es opcional.
                     * Se permiten imágenes y PDF.
                     */

                    if (
                        isset($_FILES['archivo_adjunto']) &&
                        $_FILES['archivo_adjunto']['error'] !== UPLOAD_ERR_NO_FILE
                    ) {

                        $archivo = $_FILES['archivo_adjunto'];

                        if (
                            $archivo['error'] !== UPLOAD_ERR_OK
                        ) {

                            throw new RuntimeException(
                                'No fue posible cargar la evidencia de recepción.'
                            );
                        }

                        // Máximo 5 MB
                        $tamanoMaximo = 5 * 1024 * 1024;

                        if ($archivo['size'] > $tamanoMaximo) {

                            throw new RuntimeException(
                                'La evidencia no puede superar los 5 MB.'
                            );
                        }

                        /*
                         * Extensiones permitidas.
                         */

                        $extensionesPermitidas = [
                            'jpg',
                            'jpeg',
                            'png',
                            'pdf'
                        ];

                        $extension = strtolower(
                            pathinfo(
                                $archivo['name'],
                                PATHINFO_EXTENSION
                            )
                        );

                        if (
                            !in_array(
                                $extension,
                                $extensionesPermitidas,
                                true
                            )
                        ) {

                            throw new RuntimeException(
                                'Formato de evidencia no permitido. Utilice JPG, JPEG, PNG o PDF.'
                            );
                        }

                        /*
                         * Crear carpeta de evidencias.
                         */

                        $carpetaEvidencias =
                            __DIR__ . '/../../uploads/evidencias_equipos/';

                        if (!is_dir($carpetaEvidencias)) {

                            if (
                                !mkdir(
                                    $carpetaEvidencias,
                                    0755,
                                    true
                                )
                            ) {

                                throw new RuntimeException(
                                    'No fue posible crear la carpeta para guardar la evidencia.'
                                );
                            }
                        }

                        /*
                         * Generar nombre único.
                         */

                        $nombreArchivo =
                            'evidencia_' .
                            date('Ymd_His') .
                            '_' .
                            bin2hex(random_bytes(5)) .
                            '.' .
                            $extension;

                        $rutaDestino =
                            $carpetaEvidencias .
                            $nombreArchivo;

                        /*
                         * Guardar archivo.
                         */

                        if (
                            !move_uploaded_file(
                                $archivo['tmp_name'],
                                $rutaDestino
                            )
                        ) {

                            throw new RuntimeException(
                                'No fue posible guardar la evidencia de recepción.'
                            );
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | OBTENER DEPARTAMENTO DEL USUARIO
                    |--------------------------------------------------------------------------
                    */

                    if ($idDepartamento === null) {

                        $stmtDepto = $pdo->prepare(
                            'SELECT id_departamento
                             FROM usuarios
                             WHERE id_usuario = :id'
                        );

                        $stmtDepto->execute([
                            'id' => $idUsuarioEntrega
                        ]);

                        $idDepartamento =
                            $stmtDepto->fetch()['id_departamento']
                            ?? null;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | TICKET RELACIONADO
                    |--------------------------------------------------------------------------
                    */

                    $idTicket = null;

                    if ($folioTicket !== '') {

                        $stmtTicket = $pdo->prepare(
                            'SELECT id_ticket
                             FROM tickets
                             WHERE folio = :folio'
                        );

                        $stmtTicket->execute([
                            'folio' => $folioTicket
                        ]);

                        $ticket = $stmtTicket->fetch();

                        if (!$ticket) {

                            throw new RuntimeException(
                                'El folio de ticket indicado no existe. Déjelo vacío si el equipo no está relacionado con un ticket.'
                            );
                        }

                        $idTicket =
                            (int)$ticket['id_ticket'];
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | TRANSACCIÓN
                    |--------------------------------------------------------------------------
                    */

                    $pdo->beginTransaction();

                    $folio =
                        generarFolioEquipo($pdo);


                    /*
                    |--------------------------------------------------------------------------
                    | INSERTAR EQUIPO
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare(
                        'INSERT INTO equipos_soporte
                            (
                                folio,
                                id_usuario_entrega,
                                nombre_persona_entrega,
                                id_departamento,
                                tipo_equipo,
                                condicion_cables,
                                otro_equipo,
                                descripcion,
                                evidencia_recepcion,
                                id_tecnico_recepciona,
                                estado,
                                id_ticket
                            )
                         VALUES
                            (
                                :folio,
                                :usuario,
                                :nombre_persona,
                                :departamento,
                                :tipo,
                                :cables,
                                :otro,
                                :descripcion,
                                :evidencia,
                                :tecnico,
                                \'Recibido\',
                                :ticket
                            )'
                    );


                    $stmt->execute([

                        'folio' =>
                            $folio,

                        'usuario' =>
                            $idUsuarioEntrega,

                        'nombre_persona' =>
                            limpiarTexto(
                                $nombrePersonaEntrega,
                                100
                            ),

                        'departamento' =>
                            $idDepartamento,

                        'tipo' =>
                            $tipoEquipo,

                        'cables' =>
                            $condicionCables !== ''
                                ? $condicionCables
                                : null,

                        'otro' =>
                            $tipoEquipo === 'Otro'
                                ? limpiarTexto(
                                    $otroEquipo,
                                    150
                                )
                                : null,

                        'descripcion' =>
                            limpiarTexto(
                                $descripcion,
                                2000
                            ),

                        'evidencia' =>
                            $nombreArchivo,

                        'tecnico' =>
                            $idUsuario,

                        'ticket' =>
                            $idTicket
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | OBTENER ID
                    |--------------------------------------------------------------------------
                    */

                    $idEquipo =
                        (int)$pdo->lastInsertId();

                    $idEquipoRegistrado =
                        $idEquipo;


                    /*
                    |--------------------------------------------------------------------------
                    | NOTIFICACIÓN
                    |--------------------------------------------------------------------------
                    */

                    crearNotificacion(
                        $pdo,
                        $idUsuarioEntrega,
                        "Se registró el ingreso de tu equipo $folio para soporte técnico.",
                        null,
                        $idEquipo
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | CONFIRMAR TRANSACCIÓN
                    |--------------------------------------------------------------------------
                    */

                    $pdo->commit();

                    $exito =
                        "Equipo registrado correctamente con folio $folio.";

                } catch (RuntimeException $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $error =
                        $e->getMessage();

                } catch (PDOException $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $error =
                        'Ocurrió un error al registrar el equipo.';

                    error_log(
                        'Área de entrega - registro: ' .
                        $e->getMessage()
                    );
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | CAMBIAR ESTADO
        |--------------------------------------------------------------------------
        */

        elseif (
            $accion === 'cambiar_estado' &&
            $esPersonalSoporte
        ) {

            $idEquipo =
                (int)($_POST['id_equipo'] ?? 0);

            $nuevoEstado =
                trim($_POST['nuevo_estado'] ?? '');


            $stmt = $pdo->prepare(
                'SELECT *
                 FROM equipos_soporte
                 WHERE id_equipo = :id'
            );

            $stmt->execute([
                'id' => $idEquipo
            ]);

            $equipo =
                $stmt->fetch();


            $transiciones = [

                'Recibido' =>
                    'En reparación',

                'En reparación' =>
                    'Listo para entrega',

                'Listo para entrega' =>
                    'Entregado'
            ];


            if (!$equipo) {

                $error =
                    'El equipo no existe.';

            } elseif (
                !isset($transiciones[$equipo['estado']]) ||
                $transiciones[$equipo['estado']] !== $nuevoEstado
            ) {

                $error =
                    'El cambio de estado solicitado no es válido.';

            } else {

                try {

                    $pdo->beginTransaction();


                    if (
                        $nuevoEstado ===
                        'Listo para entrega'
                    ) {

                        $pdo->prepare(
                            "UPDATE equipos_soporte
                             SET estado = 'Listo para entrega',
                                 fecha_lista_entrega = NOW()
                             WHERE id_equipo = :id"
                        )->execute([
                            'id' => $idEquipo
                        ]);


                        crearNotificacion(
                            $pdo,
                            (int)$equipo['id_usuario_entrega'],
                            "Tu equipo {$equipo['folio']} está listo para entrega.",
                            null,
                            $idEquipo
                        );


                    } elseif (
                        $nuevoEstado ===
                        'Entregado'
                    ) {

                        $pdo->prepare(
                            "UPDATE equipos_soporte
                             SET estado = 'Entregado',
                                 fecha_entrega = NOW(),
                                 id_usuario_recibe = id_usuario_entrega,
                                 id_tecnico_entrega = :tecnico
                             WHERE id_equipo = :id"
                        )->execute([
                            'id' =>
                                $idEquipo,

                            'tecnico' =>
                                $idUsuario
                        ]);


                        crearNotificacion(
                            $pdo,
                            (int)$equipo['id_usuario_entrega'],
                            "Se registró la entrega de tu equipo {$equipo['folio']}.",
                            null,
                            $idEquipo
                        );


                    } else {

                        $pdo->prepare(
                            "UPDATE equipos_soporte
                             SET estado = 'En reparación'
                             WHERE id_equipo = :id"
                        )->execute([
                            'id' =>
                                $idEquipo
                        ]);
                    }


                    $pdo->commit();

                    $exito =
                        "El equipo {$equipo['folio']} cambió a estado: $nuevoEstado.";

                } catch (PDOException $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $error =
                        'No fue posible actualizar el estado del equipo.';

                    error_log(
                        'Área de entrega - estado: ' .
                        $e->getMessage()
                    );
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| CONSULTA DE EQUIPOS
|--------------------------------------------------------------------------
*/

if ($esPersonalSoporte) {

    $stmt = $pdo->query(
        "SELECT e.*,
                u.nombre_completo AS usuario_nombre,
                d.nombre AS departamento_nombre,
                tr.nombre_completo AS tecnico_recepciona_nombre,
                te.nombre_completo AS tecnico_entrega_nombre,
                t.folio AS ticket_folio
         FROM equipos_soporte e
         JOIN usuarios u
            ON u.id_usuario = e.id_usuario_entrega
         LEFT JOIN departamentos d
            ON d.id_departamento = e.id_departamento
         JOIN usuarios tr
            ON tr.id_usuario = e.id_tecnico_recepciona
         LEFT JOIN usuarios te
            ON te.id_usuario = e.id_tecnico_entrega
         LEFT JOIN tickets t
            ON t.id_ticket = e.id_ticket
         ORDER BY e.fecha_ingreso DESC"
    );

    $equipos =
        $stmt->fetchAll();

} else {

    $stmt = $pdo->prepare(
        "SELECT e.*,
                d.nombre AS departamento_nombre,
                tr.nombre_completo AS tecnico_recepciona_nombre,
                te.nombre_completo AS tecnico_entrega_nombre,
                t.folio AS ticket_folio
         FROM equipos_soporte e
         LEFT JOIN departamentos d
            ON d.id_departamento = e.id_departamento
         JOIN usuarios tr
            ON tr.id_usuario = e.id_tecnico_recepciona
         LEFT JOIN usuarios te
            ON te.id_usuario = e.id_tecnico_entrega
         LEFT JOIN tickets t
            ON t.id_ticket = e.id_ticket
         WHERE e.id_usuario_entrega = :id_usuario
         ORDER BY
            (e.estado = 'Listo para entrega') DESC,
            e.fecha_ingreso DESC"
    );

    $stmt->execute([
        'id_usuario' =>
            $idUsuario
    ]);

    $equipos =
        $stmt->fetchAll();
}


$pendientes =
    array_values(
        array_filter(
            $equipos,
            static fn($e) =>
                $e['estado'] === 'Listo para entrega'
        )
    );


$tituloPagina =
    'Área de entrega de equipos';

$subtituloPagina =
    $esPersonalSoporte
        ? 'Registro de ingreso, reparación y entrega de equipos.'
        : 'Consulta el estado de los equipos que has dejado para soporte.';

require_once __DIR__ . '/../../includes/header.php';
?>


<?php if ($error): ?>

    <div class="alerta alerta-error">
        <?= h($error) ?>
    </div>

<?php endif; ?>


<?php if ($exito): ?>

    <div class="alerta alerta-exito">

        <?= h($exito) ?>

        <?php if ($idEquipoRegistrado): ?>

            <a
                href="<?= BASE_URL ?>/modules/tickets/generarComprobanteIngreso.php?id=<?= (int)$idEquipoRegistrado ?>"
                class="btn btn-secundario"
                target="_blank"
                rel="noopener"
                style="margin-left:10px"
            >
                Generar comprobante de ingreso
            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>


<?php if (!$esPersonalSoporte): ?>


    <section class="tarjetas-stats">

        <div class="tarjeta <?= !empty($pendientes) ? 'tarjeta-alerta' : '' ?>">

            <span class="tarjeta-num">
                <?= count($pendientes) ?>
            </span>

            <span>
                Equipos pendientes de entrega
            </span>

        </div>


        <div class="tarjeta">

            <span class="tarjeta-num">
                <?= count($equipos) ?>
            </span>

            <span>
                Total de equipos registrados
            </span>

        </div>

    </section>


    <?php if (!empty($pendientes)): ?>

        <section class="panel panel-equipo-pendiente">

            <h3>
                Equipos listos para recoger
            </h3>

            <p>
                Estos equipos ya fueron marcados por soporte como
                <strong>Listos para entrega</strong>.
            </p>


            <table class="tabla">

                <thead>

                    <tr>
                        <th>Folio</th>
                        <th>Equipo</th>
                        <th>Descripción</th>
                        <th>Fecha de ingreso</th>
                        <th>Listo desde</th>
                        <th>Estado</th>
                        <th>Comprobante</th>
                    </tr>

                </thead>


                <tbody>

                <?php foreach ($pendientes as $e): ?>

                    <tr>

                        <td>
                            <?= h($e['folio']) ?>
                        </td>

                        <td>
                            <?= h(
                                $e['tipo_equipo'] === 'Otro'
                                    ? $e['otro_equipo']
                                    : $e['tipo_equipo']
                            ) ?>
                        </td>

                        <td>
                            <?= h($e['descripcion']) ?>
                        </td>

                        <td>
                            <?= formatoFecha($e['fecha_ingreso']) ?>
                        </td>

                        <td>
                            <?= formatoFecha($e['fecha_lista_entrega']) ?>
                        </td>

                        <td>
                            <?= badgeEstadoEquipo($e['estado']) ?>
                        </td>

                        <td>

                            <a
                                href="<?= BASE_URL ?>/modules/tickets/generarComprobanteIngreso.php?id=<?= (int)$e['id_equipo'] ?>"
                                class="btn btn-secundario"
                                target="_blank"
                                rel="noopener"
                            >
                                Generar PDF
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </section>

    <?php endif; ?>


    <section class="panel">

        <h3>
            Mis equipos
        </h3>


        <table class="tabla">

            <thead>

                <tr>
                    <th>Folio</th>
                    <th>Equipo</th>
                    <th>Departamento</th>
                    <th>Ingreso</th>
                    <th>Estado</th>
                    <th>Ticket</th>
                    <th>Comprobante</th>
                </tr>

            </thead>


            <tbody>

            <?php foreach ($equipos as $e): ?>

                <tr>

                    <td>
                        <?= h($e['folio']) ?>
                    </td>

                    <td>
                        <?= h(
                            $e['tipo_equipo'] === 'Otro'
                                ? $e['otro_equipo']
                                : $e['tipo_equipo']
                        ) ?>
                    </td>

                    <td>
                        <?= h(
                            $e['departamento_nombre'] ?? '—'
                        ) ?>
                    </td>

                    <td>
                        <?= formatoFecha($e['fecha_ingreso']) ?>
                    </td>

                    <td>
                        <?= badgeEstadoEquipo($e['estado']) ?>
                    </td>

                    <td>
                        <?= h(
                            $e['ticket_folio'] ?? '—'
                        ) ?>
                    </td>

                    <td>

                        <a
                            href="<?= BASE_URL ?>/modules/tickets/generarComprobanteIngreso.php?id=<?= (int)$e['id_equipo'] ?>"
                            class="btn btn-secundario"
                            target="_blank"
                            rel="noopener"
                        >
                            Generar PDF
                        </a>

                    </td>

                </tr>

            <?php endforeach; ?>


            <?php if (empty($equipos)): ?>

                <tr>
                    <td colspan="7">
                        No tienes equipos registrados para soporte.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </section>


<?php else: ?>


    <!-- ============================================================
         FORMULARIO DE INGRESO
         ============================================================ -->

    <section class="panel">

        <h3>
            Registrar ingreso de equipo
        </h3>

        <p class="texto-suave">

            Registra aquí los equipos que un usuario entrega físicamente
            a soporte para reparación o mantenimiento.

            El sistema asigna un folio, registra al técnico que recibe
            y guarda la fecha y hora de ingreso.

        </p>


        <form
            method="POST"
            class="formulario formulario-equipo"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h(generarTokenCSRF()) ?>"
            >

            <input
                type="hidden"
                name="accion"
                value="registrar_ingreso"
            >


            <!-- DATOS DE LA PERSONA QUE ENTREGA -->

            <div class="datos-entrega">

                <div>

                    <label for="id_usuario_entrega">
                        Usuario que entrega *
                    </label>

                    <select
                        id="id_usuario_entrega"
                        name="id_usuario_entrega"
                        required
                    >

                        <option value="">
                            Seleccione...
                        </option>

                        <?php foreach ($usuarios as $u): ?>

                            <option
                                value="<?= (int)$u['id_usuario'] ?>"
                                data-departamento="<?= (int)($u['id_departamento'] ?? 0) ?>"
                            >

                                <?= h($u['nombre_completo']) ?>

                                <?= $u['departamento_nombre']
                                    ? ' — ' . h($u['departamento_nombre'])
                                    : ''
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div>

                    <label for="nombre_persona_entrega">
                        Persona que entrega el equipo *
                    </label>

                    <input
                        type="text"
                        id="nombre_persona_entrega"
                        name="nombre_persona_entrega"
                        placeholder="Nombre completo de la persona que entrega"
                        maxlength="100"
                        required
                    >

                </div>


                <div>

                    <label for="id_departamento">
                        Área / Departamento *
                    </label>

                    <select
                        id="id_departamento"
                        name="id_departamento"
                        required
                    >

                        <option value="">
                            Seleccione...
                        </option>

                        <?php foreach ($departamentos as $d): ?>

                            <option
                                value="<?= (int)$d['id_departamento'] ?>"
                            >
                                <?= h($d['nombre']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <!-- TIPO DE EQUIPO -->

            <fieldset class="grupo-equipo">

                <legend>
                    ¿Qué equipo deja? *
                </legend>

                <div class="opciones-radio">

                    <?php foreach ($tiposEquipo as $tipo): ?>

                        <label class="opcion-radio">

                            <input
                                type="radio"
                                name="tipo_equipo"
                                value="<?= h($tipo) ?>"
                                required
                            >

                            <?= h($tipo) ?>

                        </label>

                    <?php endforeach; ?>

                </div>

            </fieldset>


            <!-- OTRO EQUIPO -->

            <div
                id="campo-otro-equipo"
                class="campo-oculto"
            >

                <label for="otro_equipo">
                    Otro (especifique)
                </label>

                <input
                    type="text"
                    id="otro_equipo"
                    name="otro_equipo"
                    maxlength="150"
                    placeholder="Ej. Laptop, escáner, proyector..."
                >

            </div>


            <!-- ACCESORIOS -->

            <fieldset class="grupo-equipo">

                <legend>
                    Accesorios / cables
                </legend>

                <div class="opciones-radio">

                    <label class="opcion-radio">
                        <input
                            type="radio"
                            name="condicion_cables"
                            value="Con cables"
                        >
                        Con cables
                    </label>

                    <label class="opcion-radio">
                        <input
                            type="radio"
                            name="condicion_cables"
                            value="Sin cables"
                        >
                        Sin cables
                    </label>

                    <label class="opcion-radio">
                        <input
                            type="radio"
                            name="condicion_cables"
                            value="Cables USB"
                        >
                        Cables USB
                    </label>

                    <label class="opcion-radio">
                        <input
                            type="radio"
                            name="condicion_cables"
                            value="Cable de alimentación"
                        >
                        Cable de alimentación
                    </label>

                </div>


                <p class="texto-suave">

                    Selecciona la opción que describa mejor los accesorios
                    que se reciben junto con el equipo.

                </p>

            </fieldset>


            <!-- DESCRIPCIÓN -->

            <label for="descripcion">
                Descripción / estado físico *
            </label>

            <textarea
                id="descripcion"
                name="descripcion"
                rows="5"
                maxlength="2000"
                required
                placeholder="Describe el equipo, accesorios, estado físico, fallas visibles, número de serie u otras observaciones."
            ></textarea>


            <!-- EVIDENCIA DE RECEPCIÓN -->

            <div class="campo-evidencia">

                <label for="archivo_adjunto">
                    Evidencia de recepción del equipo
                </label>

                <input
                    type="file"
                    id="archivo_adjunto"
                    name="archivo_adjunto"
                    accept=".jpg,.jpeg,.png,.pdf"
                >

                <small class="texto-suave">
                    Puedes adjuntar una fotografía del equipo o un documento
                    como evidencia de recepción.
                    Formatos permitidos: JPG, JPEG, PNG y PDF.
                    Tamaño máximo: 5 MB.
                </small>

            </div>


            <!-- TICKET -->

            <label for="folio_ticket">
                Folio de ticket relacionado (opcional)
            </label>

            <input
                type="text"
                id="folio_ticket"
                name="folio_ticket"
                maxlength="20"
                placeholder="Ej. TCK-2026-000001"
            >


            <!-- RESPONSABLES -->

            <div class="equipo-responsables">

                <div>
                    <strong>Recepciona:</strong>
                    <?= h($_SESSION['nombre_completo']) ?>
                </div>

                <div>
                    <strong>Fecha y hora:</strong>
                    <?= date('d/m/Y H:i') ?>
                </div>

            </div>


            <button
                type="submit"
                class="btn btn-primario"
            >
                Registrar ingreso
            </button>

        </form>

    </section>


    <!-- ============================================================
         TABLA DE EQUIPOS
         ============================================================ -->

    <section class="panel">

        <h3>
            Equipos registrados
        </h3>


        <table class="tabla">

            <thead>

                <tr>

                    <th>Folio</th>
                    <th>Usuario</th>
                    <th>Persona que entrega</th>
                    <th>Departamento</th>
                    <th>Equipo</th>
                    <th>Ingreso</th>
                    <th>Estado</th>
                    <th>Ticket</th>
                    <th>Evidencia</th>
                    <th>Acción</th>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($equipos as $e): ?>

                <tr>

                    <td>
                        <?= h($e['folio']) ?>
                    </td>


                    <td>
                        <?= h($e['usuario_nombre']) ?>
                    </td>


                    <td>
                        <?= h(
                            $e['nombre_persona_entrega']
                            ?? '—'
                        ) ?>
                    </td>


                    <td>
                        <?= h(
                            $e['departamento_nombre']
                            ?? '—'
                        ) ?>
                    </td>


                    <td>
                        <?= h(
                            $e['tipo_equipo'] === 'Otro'
                                ? $e['otro_equipo']
                                : $e['tipo_equipo']
                        ) ?>
                    </td>


                    <td>
                        <?= formatoFecha(
                            $e['fecha_ingreso']
                        ) ?>
                    </td>


                    <td>
                        <?= badgeEstadoEquipo(
                            $e['estado']
                        ) ?>
                    </td>


                    <td>
                        <?= h(
                            $e['ticket_folio']
                            ?? '—'
                        ) ?>
                    </td>


                    <!-- EVIDENCIA -->

                    <td>

                        <?php if (
                            !empty(
                                $e['evidencia_recepcion']
                            )
                        ): ?>

                            <a
                                href="<?= BASE_URL ?>/uploads/evidencias_equipos/<?= rawurlencode($e['evidencia_recepcion']) ?>"
                                class="btn btn-secundario"
                                target="_blank"
                                rel="noopener"
                            >
                                Ver evidencia
                            </a>

                        <?php else: ?>

                            <span class="texto-suave">
                                Sin evidencia
                            </span>

                        <?php endif; ?>

                    </td>


                    <!-- ACCIONES -->

                    <td>

                        <a
                            href="<?= BASE_URL ?>/modules/tickets/generarComprobanteIngreso.php?id=<?= (int)$e['id_equipo'] ?>"
                            class="btn btn-secundario"
                            target="_blank"
                            rel="noopener"
                        >
                            Generar PDF
                        </a>


                        <?php if (
                            $e['estado'] === 'Recibido'
                        ): ?>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= h(generarTokenCSRF()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="accion"
                                    value="cambiar_estado"
                                >

                                <input
                                    type="hidden"
                                    name="id_equipo"
                                    value="<?= (int)$e['id_equipo'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="nuevo_estado"
                                    value="En reparación"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-secundario"
                                >
                                    Iniciar reparación
                                </button>

                            </form>


                        <?php elseif (
                            $e['estado'] === 'En reparación'
                        ): ?>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= h(generarTokenCSRF()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="accion"
                                    value="cambiar_estado"
                                >

                                <input
                                    type="hidden"
                                    name="id_equipo"
                                    value="<?= (int)$e['id_equipo'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="nuevo_estado"
                                    value="Listo para entrega"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-primario"
                                >
                                    Marcar listo
                                </button>

                            </form>


                        <?php elseif (
                            $e['estado'] === 'Listo para entrega'
                        ): ?>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= h(generarTokenCSRF()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="accion"
                                    value="cambiar_estado"
                                >

                                <input
                                    type="hidden"
                                    name="id_equipo"
                                    value="<?= (int)$e['id_equipo'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="nuevo_estado"
                                    value="Entregado"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-secundario"
                                >
                                    Registrar entrega
                                </button>

                            </form>


                        <?php else: ?>

                            <span class="texto-suave">
                                Entregado
                                <?= formatoFecha(
                                    $e['fecha_entrega']
                                ) ?>
                            </span>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>


            <?php if (empty($equipos)): ?>

                <tr>

                    <td colspan="10">
                        No hay equipos registrados.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </section>

<?php endif; ?>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
        |--------------------------------------------------------------------------
        | Departamento automático
        |--------------------------------------------------------------------------
        */

        const usuario =
            document.getElementById(
                'id_usuario_entrega'
            );

        const departamento =
            document.getElementById(
                'id_departamento'
            );


        /*
        |--------------------------------------------------------------------------
        | Campo "Otro equipo"
        |--------------------------------------------------------------------------
        */

        const otroContenedor =
            document.getElementById(
                'campo-otro-equipo'
            );

        const otroInput =
            document.getElementById(
                'otro_equipo'
            );


        if (
            usuario &&
            departamento
        ) {

            usuario.addEventListener(
                'change',
                function () {

                    const opcion =
                        usuario.options[
                            usuario.selectedIndex
                        ];

                    const idDepartamento =
                        opcion
                            ? opcion.dataset.departamento
                            : '';

                    if (idDepartamento) {

                        departamento.value =
                            idDepartamento;
                    }

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Mostrar / ocultar "Otro"
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                'input[name="tipo_equipo"]'
            )
            .forEach(
                function (radio) {

                    radio.addEventListener(
                        'change',
                        function () {

                            const mostrar =
                                this.value === 'Otro';


                            if (otroContenedor) {

                                otroContenedor.classList.toggle(
                                    'campo-oculto',
                                    !mostrar
                                );

                            }


                            if (otroInput) {

                                otroInput.required =
                                    mostrar;

                            }

                        }
                    );

                }
            );

    }
);

</script>


<?php
require_once __DIR__ . '/../../includes/footer.php';
?>