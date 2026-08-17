package mail

import (
	"bytes"
	"context"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"net/smtp"
	"net/url"
	"strings"
	"time"

	"classexpress/internal/config"
)

// Sender sends transactional emails (Brevo, Mailgun, or PHP mail fallback).
type Sender struct {
	cfg *config.Config
}

// New builds a sender from config.
func New(cfg *config.Config) *Sender {
	return &Sender{cfg: cfg}
}

// Send mirrors ceMailHtml.
func (s *Sender) Send(to, subject, htmlBody string) bool {
	if s.cfg.EmailDevMode {
		log.Printf("EMAIL DEV MODE [%s] - To: %s, Subject: %s", s.cfg.EmailProvider, to, subject)
		return true
	}
	switch s.cfg.EmailProvider {
	case "brevo":
		return s.brevo(to, subject, htmlBody)
	case "mailgun":
		return s.mailgun(to, subject, htmlBody)
	default:
		return s.phpMail(to, subject, htmlBody)
	}
}

func (s *Sender) brevo(to, subject, htmlBody string) bool {
	if s.cfg.BrevoAPIKey == "" {
		log.Println("Brevo: BREVO_API_KEY not set")
		return false
	}
	from := s.cfg.EmailFrom
	if from == "" {
		from = "noreply@classexpress.app"
	}
	fromName := s.cfg.EmailFromName
	if fromName == "" {
		fromName = "ClassExpress"
	}
	payload := map[string]any{
		"sender":      map[string]string{"email": from, "name": fromName},
		"to":          []map[string]string{{"email": to}},
		"subject":     subject,
		"htmlContent": htmlBody,
		"textContent": plainText(htmlBody),
	}
	body, err := json.Marshal(payload)
	if err != nil {
		return false
	}

	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, "https://api.brevo.com/v3/smtp/email", bytes.NewReader(body))
	if err != nil {
		return false
	}
	req.Header.Set("Accept", "application/json")
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("api-key", s.cfg.BrevoAPIKey)

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		log.Printf("Brevo request error: %v", err)
		return false
	}
	defer resp.Body.Close()
	log.Printf("Brevo response: HTTP %d", resp.StatusCode)
	return resp.StatusCode >= 200 && resp.StatusCode < 300
}

func (s *Sender) mailgun(to, subject, htmlBody string) bool {
	if s.cfg.MailgunAPIKey == "" {
		log.Println("Mailgun: MAILGUN_API_KEY not set")
		return false
	}
	domain := s.cfg.MailgunDomain
	if domain == "" {
		domain = "sandbox.mailgun.org"
	}

	data := url.Values{}
	data.Set("from", "ClassExpress <noreply@classexpress.app>")
	data.Set("to", to)
	data.Set("subject", subject)
	data.Set("text", plainText(htmlBody))
	data.Set("html", htmlBody)

	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	req, err := http.NewRequestWithContext(ctx, http.MethodPost,
		"https://api.mailgun.net/v3/"+domain+"/messages", strings.NewReader(data.Encode()))
	if err != nil {
		return false
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	req.Header.Set("Authorization", "Basic "+base64.StdEncoding.EncodeToString([]byte("api:"+s.cfg.MailgunAPIKey)))

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		log.Printf("Mailgun request error: %v", err)
		return false
	}
	defer resp.Body.Close()
	log.Printf("Mailgun response: HTTP %d", resp.StatusCode)
	return resp.StatusCode == 200
}

func (s *Sender) phpMail(to, subject, htmlBody string) bool {
	boundary := fmt.Sprintf("ce-%x", time.Now().UnixNano())
	plain := plainText(htmlBody)

	var b strings.Builder
	b.WriteString("MIME-Version: 1.0\r\n")
	b.WriteString("Content-Type: multipart/alternative; boundary=\"" + boundary + "\"\r\n")
	b.WriteString("From: ClassExpress <noreply@classexpress.app>\r\n")
	b.WriteString("Reply-To: ClassExpress <noreply@classexpress.app>\r\n")
	b.WriteString("Return-Path: <noreply@classexpress.app>\r\n")
	b.WriteString("X-Mailer: Go\r\n")
	b.WriteString("X-Priority: 1\r\n")
	b.WriteString("Importance: High\r\n")

	body := "--" + boundary + "\r\n" +
		"Content-Type: text/plain; charset=UTF-8\r\n\r\n" +
		plain + "\r\n\r\n" +
		"--" + boundary + "\r\n" +
		"Content-Type: text/html; charset=UTF-8\r\n\r\n" +
		htmlBody + "\r\n\r\n" +
		"--" + boundary + "--"

	msg := []byte("To: " + to + "\r\n" + b.String() + "\r\n" + body)
	if err := smtp.SendMail("localhost:25", nil, "noreply@classexpress.app", []string{to}, msg); err != nil {
		log.Printf("PHP mail fallback error: %v", err)
		return false
	}
	return true
}

func plainText(html string) string {
	out := html
	for _, tag := range []string{"<br>", "<br/>", "<br />", "</p>", "</div>", "</h2>"} {
		out = strings.ReplaceAll(out, tag, "\n")
	}
	out = stripTags(out)
	return strings.TrimSpace(out)
}

func stripTags(s string) string {
	var b strings.Builder
	inTag := false
	for _, r := range s {
		switch {
		case r == '<':
			inTag = true
		case r == '>':
			inTag = false
		case !inTag:
			b.WriteRune(r)
		}
	}
	return b.String()
}

// Layout wraps content in the shared email template.
func Layout(preheader, content string) string {
	return fmt.Sprintf(`<!DOCTYPE html>
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
    <div class="body">%s</div>
  </div>
    <div class="footer">
      &copy; %d ClassExpress &middot; Plataforma educativa LATAM<br>
      <small style="color:#94a3b8">Si no solicitaste este correo, puedes ignorarlo con seguridad.</small>
    </div>
</div>
</body>
</html>`, content, time.Now().Year())
}

// SendVerify mirrors ceSendVerify.
func (s *Sender) SendVerify(to, nombre, link string) bool {
	content := fmt.Sprintf(`<h2>Verifica tu correo</h2>
<p>Hola <strong>%s</strong>,</p>
<p>Gracias por registrarte en ClassExpress. Haz clic abajo para activar tu cuenta y comenzar a aprender.</p>
<div style='text-align:center'>
  <a href='%s' class='btn'>Verificar correo</a>
</div>
<p style='font-size:13px;color:#64748b'>O copia este enlace: <a href='%s'>%s</a></p>
<p style='font-size:13px;color:#64748b'>Este enlace expira en 48 horas.</p>
`, nombre, link, link, link)
	return s.Send(to, "ClassExpress – Verifica tu correo", Layout("Verifica tu cuenta en ClassExpress", content))
}

// SendReset mirrors ceSendReset.
func (s *Sender) SendReset(to, nombre, link string) bool {
	content := fmt.Sprintf(`<h2>Restablece tu contraseña</h2>
<p>Hola <strong>%s</strong>,</p>
<p>Recibimos una solicitud para restablecer tu contraseña de ClassExpress. Haz clic en el botón abajo para crear una nueva.</p>
<div style='text-align:center'>
  <a href='%s' class='btn'>Restablecer contraseña</a>
</div>
<p style='font-size:13px;color:#64748b'>O copia este enlace: <a href='%s'>%s</a></p>
<p style='font-size:13px;color:#64748b'>Este enlace expira en <strong>1 hora</strong>. Si no solicitaste esto, puedes ignorar este correo y tu contraseña no cambiará.</p>
`, nombre, link, link, link)
	return s.Send(to, "ClassExpress – Restablece tu contraseña", Layout("Restablece tu contraseña", content))
}

// SessionReceipt carries the data needed to render a session payment receipt.
type SessionReceipt struct {
	Simbolo     string
	MontoLocal  string
	MonedaLocal string
	MontoUSD    string
	Profesor    string
	Clase       string
	DuracionMin int
}

// SendSessionReceipt mirrors ceSendSessionReceipt.
func (s *Sender) SendSessionReceipt(to, nombre string, r SessionReceipt) bool {
	date := time.Now().Format("Jan 2, 2006 – 15:04")
	content := fmt.Sprintf(`<h2>Recibo de sesión</h2>
<p>Hola <strong>%s</strong>, tu sesión se completó y el pago fue registrado.</p>
<div class='badge-row'>
  <div class='amount'>%s%s <span style='font-size:18px;color:#64748b'>%s</span></div>
  <div class='label'>≈ $%s USD</div>
</div>
<div style='margin:16px 0'>
  <div class='row'><span class='k'>Clase</span><span class='v'>%s</span></div>
  <div class='row'><span class='k'>Profesor</span><span class='v'>%s</span></div>
  <div class='row'><span class='k'>Duración</span><span class='v'>%d minutos</span></div>
  <div class='row'><span class='k'>Fecha</span><span class='v'>%s</span></div>
</div>
<p style='font-size:13px;color:#64748b'>¡Gracias por aprender con ClassExpress!</p>
<div style='text-align:center;margin-top:16px'>
  <a href='https://classexpress.app/buscar.php' class='btn' style='font-size:13px;padding:10px 24px'>Busca otra clase</a>
</div>
`, nombre, r.Simbolo, r.MontoLocal, r.MonedaLocal, r.MontoUSD,
		r.Clase, r.Profesor, r.DuracionMin, date)
	return s.Send(to, "ClassExpress – Recibo de sesión", Layout("Tu recibo de sesión", content))
}
