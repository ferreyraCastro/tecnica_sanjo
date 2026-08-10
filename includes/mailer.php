<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: includes/mailer.php
//
// Envío de correo por SMTP usando la librería PHPMailer
// (solo la clase de transporte SMTP, sin el wrapper
// de alto nivel, para no depender de Composer).
//
// Mientras SMTP_HABILITADO sea false (ver includes/config.php)
// enviarCorreo() no hace nada y devuelve false: el sistema
// sigue funcionando normalmente, simplemente no se despachan
// los correos hasta que se completen las credenciales.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\SMTP;


// ============================================================
// PLANTILLA HTML DE CORREO
// Mantiene la identidad visual del sistema (rojo institucional).
// ============================================================

function plantillaCorreoHtml(
    string $titulo,
    string $mensajeHtml,
    string $urlBoton = '',
    string $textoBoton = ''
): string {

    $boton = '';

    if ($urlBoton !== '' && $textoBoton !== '') {

        $boton = '
            <div style="text-align:center;margin:28px 0 6px;">
                <a href="' . htmlspecialchars($urlBoton, ENT_QUOTES, 'UTF-8') . '"
                    style="display:inline-block;background:#B12626;color:#FFFFFF;
                    text-decoration:none;padding:12px 24px;border-radius:8px;
                    font-weight:bold;font-family:Segoe UI,Arial,sans-serif;">
                    ' . htmlspecialchars($textoBoton, ENT_QUOTES, 'UTF-8') . '
                </a>
            </div>
        ';
    }

    return '
        <!doctype html>
        <html lang="es">
        <head><meta charset="utf-8"></head>
        <body style="margin:0;padding:0;background:#F5F6F8;
            font-family:Segoe UI,Arial,sans-serif;">

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                style="background:#F5F6F8;padding:30px 12px;">
                <tr>
                    <td align="center">

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                            style="max-width:560px;background:#FFFFFF;border-radius:16px;
                            overflow:hidden;box-shadow:0 5px 18px rgba(0,0,0,.08);">

                            <tr>
                                <td style="background:linear-gradient(135deg,#760000,#B12626);
                                    padding:26px 28px;color:#FFFFFF;">
                                    <div style="font-size:13px;opacity:.85;">
                                        Colegio San José · Sistema de Gestión Técnica
                                    </div>
                                    <div style="font-size:20px;font-weight:800;margin-top:6px;">
                                        ' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:28px;color:#444444;font-size:14px;line-height:1.6;">
                                    ' . $mensajeHtml . '
                                    ' . $boton . '
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:16px 28px;background:#FAFAFA;
                                    color:#999999;font-size:11px;text-align:center;">
                                    Este es un mensaje automático, no respondas a este correo.
                                </td>
                            </tr>

                        </table>

                    </td>
                </tr>
            </table>

        </body>
        </html>
    ';
}


// ============================================================
// ENVIAR CORREO POR SMTP
// ============================================================

function enviarCorreo(
    string $destinatario,
    string $nombreDestinatario,
    string $asunto,
    string $cuerpoHtml
): bool {

    if (!SMTP_HABILITADO) {
        return false;
    }

    if (!emailValido($destinatario)) {
        return false;
    }

    $smtp = new SMTP();

    try {

        $hostConexion =
            SMTP_SEGURIDAD === 'ssl'
                ? 'ssl://' . SMTP_HOST
                : SMTP_HOST;

        $conectado =
            $smtp->connect(
                $hostConexion,
                SMTP_PUERTO,
                15
            );

        if (!$conectado) {

            error_log(
                'SMTP: no se pudo conectar a '
                . SMTP_HOST
            );

            return false;
        }

        $nombreLocal =
            gethostname()
            ?: 'localhost';

        $smtp->hello($nombreLocal);

        if (SMTP_SEGURIDAD === 'tls') {

            if (!$smtp->startTLS()) {

                error_log(
                    'SMTP: fallo STARTTLS - '
                    . ($smtp->getError()['error'] ?? '')
                );

                $smtp->close();

                return false;
            }

            // Después de STARTTLS hay que saludar de nuevo.

            $smtp->hello($nombreLocal);
        }

        if (
            !$smtp->authenticate(
                SMTP_USUARIO,
                SMTP_CLAVE
            )
        ) {

            error_log(
                'SMTP: fallo autenticación - '
                . ($smtp->getError()['error'] ?? '')
            );

            $smtp->close();

            return false;
        }

        if (!$smtp->mail(SMTP_DESDE_EMAIL)) {

            $smtp->close();

            return false;
        }

        if (!$smtp->recipient($destinatario)) {

            $smtp->close();

            return false;
        }

        $asuntoCodificado =
            mb_encode_mimeheader(
                $asunto,
                'UTF-8',
                'B',
                "\r\n"
            );

        $nombreDesdeCodificado =
            mb_encode_mimeheader(
                SMTP_DESDE_NOMBRE,
                'UTF-8',
                'B',
                "\r\n"
            );

        $encabezadoPara =
            $destinatario;

        if ($nombreDestinatario !== '') {

            $encabezadoPara =
                mb_encode_mimeheader(
                    $nombreDestinatario,
                    'UTF-8',
                    'B',
                    "\r\n"
                )
                . ' <' . $destinatario . '>';
        }

        $encabezados = [

            'Date: ' . date('r'),

            'From: ' . $nombreDesdeCodificado
                . ' <' . SMTP_DESDE_EMAIL . '>',

            'To: ' . $encabezadoPara,

            'Subject: ' . $asuntoCodificado,

            'Message-ID: <'
                . bin2hex(random_bytes(16))
                . '@colegiodesanjose.edu.ar>',

            'MIME-Version: 1.0',

            'Content-Type: text/html; charset=UTF-8',

            'Content-Transfer-Encoding: 8bit'

        ];

        $mensajeCompleto =
            implode(SMTP::LE, $encabezados)
            . SMTP::LE . SMTP::LE
            . $cuerpoHtml;

        if (!$smtp->data($mensajeCompleto)) {

            $smtp->close();

            return false;
        }

        $smtp->quit();

        $smtp->close();

        return true;

    } catch (Throwable $e) {

        error_log(
            'Error enviando correo: '
            . $e->getMessage()
        );

        if ($smtp->connected()) {

            $smtp->close();
        }

        return false;
    }
}


// ============================================================
// NOTIFICAR NUEVO TICKET
// Se envía al docente (confirmación) y al equipo técnico
// (alerta de nueva solicitud).
// ============================================================

function notificarNuevoTicket(
    PDO $conexion,
    array $solicitud,
    int $idSolicitud
): void {

    if (!SMTP_HABILITADO) {
        return;
    }

    $numero = numeroTicket($idSolicitud);

    $urlTicket =
        'http://localhost'
        . rutaBase()
        . 'ver_solicitud.php?id='
        . $idSolicitud;


    // ========================================================
    // CONFIRMACIÓN AL DOCENTE
    // ========================================================

    enviarCorreo(
        $solicitud['correo'] ?? '',
        trim(
            ($solicitud['nombre'] ?? '')
            . ' ' .
            ($solicitud['apellido'] ?? '')
        ),
        'Ticket ' . $numero . ' registrado',
        plantillaCorreoHtml(
            'Solicitud registrada',
            '<p>Tu solicitud <strong>' . htmlspecialchars($numero, ENT_QUOTES, 'UTF-8') . '</strong>
            "' . htmlspecialchars($solicitud['titulo'] ?? '', ENT_QUOTES, 'UTF-8') . '"
            fue registrada correctamente y va a ser evaluada por el equipo técnico.</p>
            <p>Podés seguir su estado desde el sistema.</p>',
            $urlTicket,
            'Ver mi solicitud'
        )
    );


    // ========================================================
    // ALERTA AL EQUIPO TÉCNICO
    // ========================================================

    foreach (obtenerTecnicos($conexion) as $tecnico) {

        enviarCorreo(
            $tecnico['correo'] ?? '',
            trim(
                ($tecnico['nombre'] ?? '')
                . ' ' .
                ($tecnico['apellido'] ?? '')
            ),
            'Nuevo ticket ' . $numero,
            plantillaCorreoHtml(
                'Nueva solicitud',
                '<p>Se registró una nueva solicitud
                <strong>' . htmlspecialchars($numero, ENT_QUOTES, 'UTF-8') . '</strong>.</p>
                <p><strong>Título:</strong> '
                . htmlspecialchars($solicitud['titulo'] ?? '', ENT_QUOTES, 'UTF-8') . '<br>
                <strong>Prioridad:</strong> '
                . htmlspecialchars($solicitud['prioridad'] ?? '', ENT_QUOTES, 'UTF-8') . '</p>',
                $urlTicket,
                'Ver solicitud'
            )
        );
    }
}


// ============================================================
// NOTIFICAR NUEVO COMENTARIO / MENSAJE CON EL TÉCNICO
// ============================================================

function notificarComentario(
    int $idSolicitud,
    string $numeroTicket,
    string $tituloSolicitud,
    string $correoDestinatario,
    string $nombreDestinatario,
    string $autor,
    string $comentario
): void {

    if (!SMTP_HABILITADO) {
        return;
    }

    $urlTicket =
        'http://localhost'
        . rutaBase()
        . 'ver_solicitud.php?id='
        . $idSolicitud;

    enviarCorreo(
        $correoDestinatario,
        $nombreDestinatario,
        'Nuevo mensaje en ' . $numeroTicket,
        plantillaCorreoHtml(
            'Nuevo mensaje',
            '<p><strong>' . htmlspecialchars($autor, ENT_QUOTES, 'UTF-8') . '</strong>
            escribió en la solicitud
            <strong>' . htmlspecialchars($numeroTicket, ENT_QUOTES, 'UTF-8') . '</strong>
            ("' . htmlspecialchars($tituloSolicitud, ENT_QUOTES, 'UTF-8') . '"):</p>
            <p style="background:#F8F8F8;border-left:3px solid #B12626;
            padding:10px 14px;border-radius:8px;white-space:pre-line;">'
            . nl2br(htmlspecialchars($comentario, ENT_QUOTES, 'UTF-8')) . '</p>',
            $urlTicket,
            'Responder en el sistema'
        )
    );
}
