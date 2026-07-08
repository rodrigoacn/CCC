<?php
// ─────────────────────────────────────────────────────────────────────────────
//  email_helper.php — HTML email sender (uses Mailgun API or logs for dev)
// ─────────────────────────────────────────────────────────────────────────────

function ceMailHtml(string $to, string $subject, string $htmlBody): bool {
    $apiKey = getenv('MAILGUN_API_KEY') ?: '';
    $domain = getenv('MAILGUN_DOMAIN') ?: 'sandbox.mailgun.org';
    
    // Development mode: log instead of sending
    $devMode = getenv('EMAIL_DEV_MODE') === 'true';
    
    if ($devMode || !$apiKey) {
        error_log("EMAIL DEV MODE - To: $to, Subject: $subject");
        error_log("Email body (first 500 chars): " . substr(strip_tags($htmlBody), 0, 500));
        return true; // Simulate success
    }
    
    $plain = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));
    $from = 'ClassExpress <noreply@classexpress.app>';
    
    $data = [
        'from' => $from,
        'to' => $to,
        'subject' => $subject,
        'text' => $plain,
        'html' => $htmlBody,
    ];
    
    $ch = curl_init("https://api.mailgun.net/v3/$domain/messages");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode('api:' . $apiKey),
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    error_log('Mailgun response: HTTP ' . $httpCode . ', Error: ' . $error . ', Response: ' . $response);
    
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
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ClassExpress</title>
  <style>
    body{margin:0;padding:0;background:#111;font-family:Arial,sans-serif;color:#ccc}
    .wrap{max-width:580px;margin:0 auto;padding:32px 16px}
    .card{background:#1e1e1e;border-radius:12px;overflow:hidden;border:1px solid #333}
    .header{background:#212121;padding:28px 32px;border-bottom:1px solid #333;text-align:center}
    .logo{font-size:22px;font-weight:bold;color:#fff;text-decoration:none;letter-spacing:-0.5px}
    .body{padding:32px}
    .btn{display:inline-block;padding:14px 32px;background:#6c757d;color:#fff !important;
         text-decoration:none;border-radius:8px;font-weight:bold;font-size:15px;margin:20px 0}
    .badge-row{background:#111;border-radius:8px;padding:16px;margin:16px 0;text-align:center}
    .amount{font-size:28px;font-weight:bold;color:#fff}
    .label{color:#888;font-size:13px;margin-top:4px}
    .row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #2a2a2a;font-size:14px}
    .row:last-child{border-bottom:none}
    .row .k{color:#888} .row .v{color:#fff;font-weight:500}
    .footer{text-align:center;padding:20px;color:#555;font-size:12px}
    h2{color:#fff;margin:0 0 8px} p{margin:0 0 12px;line-height:1.6;font-size:15px}
    a{color:#aaa}
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
    <small style="color:#444">Si no solicitaste este correo, puedes ignorarlo con seguridad.</small>
  </div>
</div>
</body>
</html>
HTML;
}

function ceSendVerify(string $email, string $nombre, string $link): bool {
    $content = "
<h2>Verifica tu correo</h2>
<p>Hola <strong style='color:#fff'>{$nombre}</strong>,</p>
<p>Gracias por registrarte en ClassExpress. Haz clic abajo para activar tu cuenta y comenzar a aprender.</p>
<div style='text-align:center'>
  <a href='{$link}' class='btn'>Verificar correo</a>
</div>
<p style='font-size:13px;color:#666'>O copia este enlace: <a href='{$link}'>{$link}</a></p>
<p style='font-size:13px;color:#555'>Este enlace expira en 48 horas.</p>
";
    return ceMailHtml($email, 'ClassExpress – Verifica tu correo', ceMailLayout('Verifica tu cuenta en ClassExpress', $content));
}

function ceSendReset(string $email, string $nombre, string $link): bool {
    $content = "
<h2>Restablece tu contraseña</h2>
<p>Hola <strong style='color:#fff'>{$nombre}</strong>,</p>
<p>Recibimos una solicitud para restablecer tu contraseña de ClassExpress. Haz clic en el botón abajo para crear una nueva.</p>
<div style='text-align:center'>
  <a href='{$link}' class='btn'>Restablecer contraseña</a>
</div>
<p style='font-size:13px;color:#666'>O copia este enlace: <a href='{$link}'>{$link}</a></p>
<p style='font-size:13px;color:#555'>Este enlace expira en <strong style='color:#aaa'>1 hora</strong>. Si no solicitaste esto, puedes ignorar este correo y tu contraseña no cambiará.</p>
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
<p>Hola <strong style='color:#fff'>{$nombre}</strong>, tu sesión se completó y el pago fue registrado.</p>
<div class='badge-row'>
  <div class='amount'>{$sim}{$amount} <span style='font-size:18px;color:#888'>{$mon}</span></div>
  <div class='label'>≈ \${$usd} USD</div>
</div>
<div style='margin:16px 0'>
  <div class='row'><span class='k'>Clase</span><span class='v'>{$clase}</span></div>
  <div class='row'><span class='k'>Profesor</span><span class='v'>{$teacher}</span></div>
  <div class='row'><span class='k'>Duración</span><span class='v'>{$dur} minutos</span></div>
  <div class='row'><span class='k'>Fecha</span><span class='v'>{$date}</span></div>
</div>
<p style='font-size:13px;color:#555'>¡Gracias por aprender con ClassExpress!</p>
<div style='text-align:center;margin-top:16px'>
  <a href='https://classexpress.app/buscar.php' class='btn' style='font-size:13px;padding:10px 24px'>Busca otra clase</a>
</div>
";
    return ceMailHtml($email, 'ClassExpress – Recibo de sesión', ceMailLayout('Tu recibo de sesión', $content));
}
