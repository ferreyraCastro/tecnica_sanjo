<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/notificaciones.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';


// ============================================================
// REQUERIR LOGIN
// ============================================================

requerirLogin();


// ============================================================
// VERIFICAR USUARIO ACTIVO
// ============================================================

if (!verificarUsuarioActivo($conexion)) {

    $_SESSION['mensaje_login'] =
        'Tu sesión finalizó o tu cuenta se encuentra inactiva.';

    header(
        'Location: ' . url('login.php')
    );

    exit;
}


$idUsuario = (int)usuarioId();


// ============================================================
// ACCIONES (marcar leída / marcar todas)
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !validarCsrf(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        flash(
            'error',
            'La sesión del formulario expiró. Intentá nuevamente.'
        );

    } else {

        $accion =
            $_POST['accion']
            ?? '';

        if ($accion === 'marcar_leida') {

            marcarNotificacionLeida(
                $conexion,
                (int)($_POST['id_notificacion'] ?? 0),
                $idUsuario
            );

        } elseif ($accion === 'marcar_todas') {

            marcarTodasNotificacionesLeidas(
                $conexion,
                $idUsuario
            );

            flash(
                'success',
                'Todas las notificaciones fueron marcadas como leídas.'
            );
        }
    }

    header(
        'Location: ' . url('notificaciones.php')
    );

    exit;
}


// ============================================================
// LISTADO
// ============================================================

$notificaciones =
    obtenerNotificaciones(
        $conexion,
        $idUsuario,
        100
    );

$cantidadNoLeidas =
    contarNotificaciones(
        $conexion,
        $idUsuario
    );

$flash = obtenerFlash();


// ============================================================
// HEADER
// ============================================================

require_once __DIR__ . '/includes/header.php';

?>


<style>

.notif-wrapper {

    max-width: 900px;

    margin: 0 auto;

    padding: 5px 12px 45px;

}


.notif-hero {

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    color: #FFFFFF;

    padding: 28px;

    border-radius: 20px;

    margin-bottom: 22px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    flex-wrap: wrap;

}


.notif-hero h1 {

    margin: 0 0 5px;

    font-size: 24px;

    font-weight: 800;

}


.notif-hero p {

    margin: 0;

    color: rgba(255,255,255,.8);

    font-size: 13px;

}


.btn-marcar-todas {

    background: #FFFFFF;

    color: #760000;

    border: none;

    border-radius: 10px;

    padding: 10px 16px;

    font-weight: 700;

    white-space: nowrap;

}


.btn-marcar-todas:hover {

    background: #F4F4F4;

    color: #B12626;

}


.notif-item {

    display: flex;

    gap: 14px;

    align-items: flex-start;

    background: #FFFFFF;

    border: 1px solid #ECECEC;

    border-radius: 15px;

    padding: 16px 18px;

    margin-bottom: 12px;

    box-shadow: 0 4px 15px rgba(0,0,0,.04);

}


.notif-item.no-leida {

    border-left: 4px solid #B12626;

    background: #FFF9F9;

}


.notif-icon {

    min-width: 42px;

    width: 42px;

    height: 42px;

    border-radius: 12px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #F2E5E5;

    color: #760000;

    font-size: 18px;

}


.notif-titulo {

    font-weight: 700;

    color: #333333;

}


.notif-mensaje {

    color: #666666;

    font-size: 13px;

    margin-top: 3px;

}


.notif-fecha {

    color: #999999;

    font-size: 11px;

    margin-top: 6px;

}


.notif-acciones {

    display: flex;

    flex-direction: column;

    gap: 6px;

    align-items: flex-end;

}


.btn-notif-link {

    font-size: 12px;

    font-weight: 700;

    color: #760000;

    text-decoration: none;

}


.btn-notif-link:hover {

    color: #B12626;

}


.btn-notif-leida {

    font-size: 11px;

    color: #999999;

    background: none;

    border: none;

    padding: 0;

}


.btn-notif-leida:hover {

    color: #760000;

}


.empty-box {

    text-align: center;

    padding: 45px 15px;

    color: #909090;

}


.empty-box i {

    display: block;

    font-size: 40px;

    color: #D0D0D0;

    margin-bottom: 10px;

}

</style>


<div class="notif-wrapper">


    <section class="notif-hero">

        <div>

            <h1>
                <i class="bi bi-bell me-1"></i>
                Notificaciones
            </h1>

            <p>

                <?= $cantidadNoLeidas ?>

                <?= $cantidadNoLeidas === 1 ? 'sin leer' : 'sin leer' ?>

            </p>

        </div>


        <?php if ($cantidadNoLeidas > 0): ?>

            <form method="POST">

                <?= csrfInput() ?>

                <input type="hidden" name="accion" value="marcar_todas">

                <button type="submit" class="btn-marcar-todas">

                    <i class="bi bi-check2-all me-1"></i>

                    Marcar todas como leídas

                </button>

            </form>

        <?php endif; ?>

    </section>


    <?php if ($flash): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <?= e($flash['mensaje']) ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

        </div>

    <?php endif; ?>


    <?php if (empty($notificaciones)): ?>

        <div class="empty-box">

            <i class="bi bi-bell-slash"></i>

            No tenés notificaciones todavía.

        </div>

    <?php else: ?>

        <?php foreach ($notificaciones as $notif): ?>

            <div class="notif-item <?= (int)$notif['leida'] === 0 ? 'no-leida' : '' ?>">

                <div class="notif-icon">

                    <i class="bi bi-bell"></i>

                </div>

                <div class="flex-grow-1">

                    <div class="notif-titulo">

                        <?= e($notif['titulo']) ?>

                    </div>

                    <div class="notif-mensaje">

                        <?= e($notif['mensaje']) ?>

                    </div>

                    <div class="notif-fecha">

                        <?= e(fechaArgentina($notif['fecha'])) ?>

                    </div>

                </div>

                <div class="notif-acciones">

                    <?php if (!empty($notif['enlace'])): ?>

                        <a
                            href="<?= e(url($notif['enlace'])) ?>"
                            class="btn-notif-link"
                        >
                            Ver
                            <i class="bi bi-arrow-right"></i>
                        </a>

                    <?php endif; ?>

                    <?php if ((int)$notif['leida'] === 0): ?>

                        <form method="POST">

                            <?= csrfInput() ?>

                            <input type="hidden" name="accion" value="marcar_leida">

                            <input
                                type="hidden"
                                name="id_notificacion"
                                value="<?= (int)$notif['id_notificacion'] ?>"
                            >

                            <button type="submit" class="btn-notif-leida">

                                Marcar como leída

                            </button>

                        </form>

                    <?php endif; ?>

                </div>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>


</div>


<?php

require_once __DIR__ . '/includes/footer.php';

?>
