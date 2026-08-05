<?php
// ─────────────────────────────────────────────────────────────────────────────
//  email_helper.php — HTML email sender (Brevo API / Mailgun / PHP mail)
// ─────────────────────────────────────────────────────────────────────────────

function ceMailHtml(string $to, string $subject, string $htmlBody): bool {
    $provider = getenv('EMAIL_PROVIDER') ?: 'brevo';

    $devMode = getenv('EMAIL_DEV_MODE') === 'true';

    if ($devMode) {
        error_log("EMAIL DEV MODE [$provider] - To: $to, Subject: $subject");
        error_log("Email body (first 500 chars): " . substr(strip_tags($htmlBody), 0, 500));
        return true;
    }

    if ($provider === 'brevo') {
        return ceMailBrevo($to, $subject, $htmlBody);
    }
    if ($provider === 'mailgun') {
        return ceMailMailgun($to, $subject, $htmlBody);
    }
    return ceMailPhp($to, $subject, $htmlBody);
}

function ceMailBrevo(string $to, string $subject, string $htmlBody): bool {
    $apiKey = getenv('BREVO_API_KEY') ?: '';
    if (!$apiKey) {
        error_log('Brevo: BREVO_API_KEY not set');
        return false;
    }

    $fromEmail = getenv('EMAIL_FROM') ?: 'noreply@classexpress.app';
    $fromName  = getenv('EMAIL_FROM_NAME') ?: 'ClassExpress';
    $plain = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));

    $payload = json_encode([
        'sender'      => ['email' => $fromEmail, 'name' => $fromName],
        'to'          => [['email' => $to]],
        'subject'     => $subject,
        'htmlContent' => $htmlBody,
        'textContent' => $plain,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    error_log("Brevo response: HTTP $httpCode, Error: $error, Response: $response");
    return $httpCode >= 200 && $httpCode < 300;
}

function ceMailMailgun(string $to, string $subject, string $htmlBody): bool {
    $apiKey = getenv('MAILGUN_API_KEY') ?: '';
    $domain = getenv('MAILGUN_DOMAIN') ?: 'sandbox.mailgun.org';

    if (!$apiKey) {
        error_log('Mailgun: MAILGUN_API_KEY not set');
        return false;
    }

    $plain = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));
    $from = 'ClassExpress <noreply@classexpress.app>';

    $data = [
        'from'    => $from,
        'to'      => $to,
        'subject' => $subject,
        'text'    => $plain,
        'html'    => $htmlBody,
    ];

    $ch = curl_init("https://api.mailgun.net/v3/$domain/messages");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . base64_encode('api:' . $apiKey),
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    error_log("Mailgun response: HTTP $httpCode, Error: $error, Response: $response");
    return $httpCode === 200;
}

function ceMailPhp(string $to, string $subject, string $htmlBody): bool {
    $boundary = md5(uniqid());
    $plain    = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));

    $headers  = implode("\r\n", [
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        'From: ClassExpress <noreply@classexpress.app>',
        'Reply-To: ClassExpress <noreply@classexpress.app>',
        'Return-Path: <noreply@classexpress.app>',
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: 1',
        'Importance: High',
    ]);

    $body = "--{$boundary}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
          . $plain . "\r\n\r\n"
          . "--{$boundary}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
          . $htmlBody . "\r\n\r\n"
          . "--{$boundary}--";

    return @mail($to, $subject, $body, $headers, '-fnoreply@classexpress.app');
}

function ceMailLayout(string $preheader, string $content): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ClassExpress</title>
  <style>
    body{margin:0;padding:0;background:#f4f6fb;font-family:Arial,sans-serif;color:#1e293b}
    .wrap{max-width:580px;margin:0 auto;padding:32px 16px}
    .card{background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #dbe2ee}
    .header{background:#eef1f8;padding:28px 32px;border-bottom:1px solid #dbe2ee;text-align:center}
    .logo{font-size:22px;font-weight:bold;color:#66ddbd;text-decoration:none;letter-spacing:-0.5px}
    .body{padding:32px}
    .btn{display:inline-block;padding:14px 32px;background:#66ddbd;color:#fff !important;
         text-decoration:none;border-radius:8px;font-weight:bold;font-size:15px;margin:20px 0}
    .badge-row{background:#eef1f8;border-radius:8px;padding:16px;margin:16px 0;text-align:center}
    .amount{font-size:28px;font-weight:bold;color:#1e293b}
    .label{color:#64748b;font-size:13px;margin-top:4px}
    .row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eef1f8;font-size:14px}
    .row:last-child{border-bottom:none}
    .row .k{color:#64748b} .row .v{color:#1e293b;font-weight:500}
    .footer{text-align:center;padding:20px;color:#94a3b8;font-size:12px}
    h2{color:#1e293b;margin:0 0 8px} p{margin:0 0 12px;line-height:1.6;font-size:15px;color:#475569}
    a{color:#66ddbd}
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="header"><span class="logo">ClassExpress</span></div>
    <div class="body">{$content}</div>
  </div>
    <div class="footer">
      &copy; <?= date('Y') ?> ClassExpress &middot; Plataforma educativa LATAM<br>
      <small style="color:#94a3b8">Si no solicitaste este correo, puedes ignorarlo con seguridad.</small>
    </div>
</div>
</body>
</html>
HTML;
}

function ceSendVerify(string $email, string $nombre, string $link): bool {
    $content = "
<h2>Verifica tu correo</h2>
<p>Hola <strong>{$nombre}</strong>,</p>
<p>Gracias por registrarte en ClassExpress. Haz clic abajo para activar tu cuenta y comenzar a aprender.</p>
<div style='text-align:center'>
  <a href='{$link}' class='btn'>Verificar correo</a>
</div>
<p style='font-size:13px;color:#64748b'>O copia este enlace: <a href='{$link}'>{$link}</a></p>
<p style='font-size:13px;color:#64748b'>Este enlace expira en 48 horas.</p>
";
    return ceMailHtml($email, 'ClassExpress – Verifica tu correo', ceMailLayout('Verifica tu cuenta en ClassExpress', $content));
}

function ceSendReset(string $email, string $nombre, string $link): bool {
    $content = "
<h2>Restablece tu contraseña</h2>
<p>Hola <strong>{$nombre}</strong>,</p>
<p>Recibimos una solicitud para restablecer tu contraseña de ClassExpress. Haz clic en el botón abajo para crear una nueva.</p>
<div style='text-align:center'>
  <a href='{$link}' class='btn'>Restablecer contraseña</a>
</div>
<p style='font-size:13px;color:#64748b'>O copia este enlace: <a href='{$link}'>{$link}</a></p>
<p style='font-size:13px;color:#64748b'>Este enlace expira en <strong>1 hora</strong>. Si no solicitaste esto, puedes ignorar este correo y tu contraseña no cambiará.</p>
";
    return ceMailHtml($email, 'ClassExpress – Restablece tu contraseña', ceMailLayout('Restablece tu contraseña', $content));
}

function ceSendSessionReceipt(string $email, string $nombre, array $data): bool {
    $sim     = htmlspecialchars($data['simbolo']);
    $amount  = number_format((float)$data['monto_local'], 2, '.', ',');
    $mon     = htmlspecialchars($data['moneda_local']);
    $usd     = number_format((float)$data['monto_usd'], 2);
    $teacher = htmlspecialchars($data['profesor']);
    $clase   = htmlspecialchars($data['clase']);
    $dur     = (int)$data['duracion_min'];
    $date    = date('M j, Y – g:i A');

    $content = "
<h2>Recibo de sesión</h2>
<p>Hola <strong>{$nombre}</strong>, tu sesión se completó y el pago fue registrado.</p>
<div class='badge-row'>
  <div class='amount'>{$sim}{$amount} <span style='font-size:18px;color:#64748b'>{$mon}</span></div>
  <div class='label'>≈ \${$usd} USD</div>
</div>
<div style='margin:16px 0'>
  <div class='row'><span class='k'>Clase</span><span class='v'>{$clase}</span></div>
  <div class='row'><span class='k'>Profesor</span><span class='v'>{$teacher}</span></div>
  <div class='row'><span class='k'>Duración</span><span class='v'>{$dur} minutos</span></div>
  <div class='row'><span class='k'>Fecha</span><span class='v'>{$date}</span></div>
</div>
<p style='font-size:13px;color:#64748b'>¡Gracias por aprender con ClassExpress!</p>
<div style='text-align:center;margin-top:16px'>
  <a href='https://classexpress.app/buscar.php' class='btn' style='font-size:13px;padding:10px 24px'>Busca otra clase</a>
</div>
";
    return ceMailHtml($email, 'ClassExpress – Recibo de sesión', ceMailLayout('Tu recibo de sesión', $content));
}
