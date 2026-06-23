<?php
// ─────────────────────────────────────────────────────────────────────────────
//  email_helper.php — HTML email sender (uses PHP mail())
// ─────────────────────────────────────────────────────────────────────────────

function ceMailHtml(string $to, string $subject, string $htmlBody): bool {
    $boundary = md5(uniqid());
    $plain    = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));

    $headers  = implode("\r\n", [
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        'From: ClassExpress <noreply@classexpress.app>',
        'X-Mailer: PHP/' . phpversion(),
        'X-ClassExpress: 1',
    ]);

    $body = "--{$boundary}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
          . $plain . "\r\n\r\n"
          . "--{$boundary}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
          . $htmlBody . "\r\n\r\n"
          . "--{$boundary}--";

    return mail($to, $subject, $body, $headers);
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
    &copy; <?= date('Y') ?> ClassExpress &middot; LATAM Education Platform<br>
    <small style="color:#444">If you didn't request this email, you can safely ignore it.</small>
  </div>
</div>
</body>
</html>
HTML;
}

function ceSendVerify(string $email, string $nombre, string $link): bool {
    $content = "
<h2>Verify your email</h2>
<p>Hello <strong style='color:#fff'>{$nombre}</strong>,</p>
<p>Thanks for signing up to ClassExpress! Click below to activate your account and start learning.</p>
<div style='text-align:center'>
  <a href='{$link}' class='btn'>Verify Email Address</a>
</div>
<p style='font-size:13px;color:#666'>Or copy this link: <a href='{$link}'>{$link}</a></p>
<p style='font-size:13px;color:#555'>This link expires in 48 hours.</p>
";
    return ceMailHtml($email, 'ClassExpress – Verify your email', ceMailLayout('Verify your ClassExpress account', $content));
}

function ceSendReset(string $email, string $nombre, string $link): bool {
    $content = "
<h2>Reset your password</h2>
<p>Hello <strong style='color:#fff'>{$nombre}</strong>,</p>
<p>We received a request to reset your ClassExpress password. Click the button below to set a new one.</p>
<div style='text-align:center'>
  <a href='{$link}' class='btn'>Reset Password</a>
</div>
<p style='font-size:13px;color:#666'>Or copy this link: <a href='{$link}'>{$link}</a></p>
<p style='font-size:13px;color:#555'>This link expires in <strong style='color:#aaa'>1 hour</strong>. If you didn't request a reset, you can ignore this email — your password won't change.</p>
";
    return ceMailHtml($email, 'ClassExpress – Reset your password', ceMailLayout('Reset your password', $content));
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
<h2>Session Receipt</h2>
<p>Hello <strong style='color:#fff'>{$nombre}</strong>, your session has been completed and payment recorded.</p>
<div class='badge-row'>
  <div class='amount'>{$sim}{$amount} <span style='font-size:18px;color:#888'>{$mon}</span></div>
  <div class='label'>≈ \${$usd} USD</div>
</div>
<div style='margin:16px 0'>
  <div class='row'><span class='k'>Class</span><span class='v'>{$clase}</span></div>
  <div class='row'><span class='k'>Teacher</span><span class='v'>{$teacher}</span></div>
  <div class='row'><span class='k'>Duration</span><span class='v'>{$dur} minutes</span></div>
  <div class='row'><span class='k'>Date</span><span class='v'>{$date}</span></div>
</div>
<p style='font-size:13px;color:#555'>Thank you for learning with ClassExpress!</p>
<div style='text-align:center;margin-top:16px'>
  <a href='https://classexpress.app/buscar.php' class='btn' style='font-size:13px;padding:10px 24px'>Find Another Class</a>
</div>
";
    return ceMailHtml($email, 'ClassExpress – Session Receipt', ceMailLayout('Your session receipt', $content));
}
