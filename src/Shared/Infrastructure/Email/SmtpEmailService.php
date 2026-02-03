<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Email;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * SMTP Email Service.
 */
final class SmtpEmailService
{
    public function __construct(
        private readonly string $fromEmail,
        private readonly string $fromName,
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $encryption,
    ) {
    }

    /**
     * Send magic link email for authentication.
     */
    public function sendMagicLink(string $toEmail, string $magicLink, string $userName = ''): bool
    {
        $subject = 'Tu enlace de acceso - Buscador UNED';
        $message = $this->renderMagicLinkTemplate($magicLink, $userName);

        return $this->send($toEmail, $subject, $message);
    }

    /**
     * Send registration confirmation email.
     */
    public function sendRegistrationConfirmation(string $toEmail, string $confirmLink): bool
    {
        $subject = 'Confirma tu registro - Buscador UNED';
        $message = $this->renderRegistrationTemplate($confirmLink);

        return $this->send($toEmail, $subject, $message);
    }

    private function send(string $toEmail, string $subject, string $body): bool
    {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $this->host;
        $mailer->Port = $this->port;
        $mailer->SMTPAuth = $this->username !== '';
        $mailer->Username = $this->username;
        $mailer->Password = $this->password;

        if ($this->encryption !== '') {
            $mailer->SMTPSecure = $this->normalizeEncryption($this->encryption);
        }

        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($this->fromEmail, $this->fromName);
        $mailer->addAddress($toEmail);
        $mailer->Subject = $subject;
        $mailer->isHTML(true);
        $mailer->Body = $body;
        $mailer->AltBody = strip_tags($body);
        $mailer->SMTPDebug = SMTP::DEBUG_OFF;

        return $mailer->send();
    }

    private function normalizeEncryption(string $value): string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            default => $value,
        };
    }

    /**
     * Render magic link email template.
     */
    private function renderMagicLinkTemplate(string $magicLink, string $userName = ''): string
    {
        $greeting = $userName !== '' ? "Hola $userName," : 'Hola,';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Tu enlace de acceso</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 12px 30px; background-color: #008C45; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #888; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="color: #008C45;">Buscador de Actividades UNED</h2>
        <p>$greeting</p>
        <p>Haz clic en el siguiente botón para acceder a tu cuenta:</p>
        <p><a href="$magicLink" class="button">Acceder ahora</a></p>
        <p>O copia y pega este enlace en tu navegador:</p>
        <p style="word-break: break-all; color: #666;">$magicLink</p>
        <p style="color: #888; font-size: 14px;">Este enlace expirará en 15 minutos.</p>
        <div class="footer">
            <p>Si no solicitaste este enlace, puedes ignorar este correo.</p>
            <p>&copy; 2026 <a href="https://simplicer.com" style="color: #008C45;">Simplicer SL</a> - Licencia MIT</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render registration confirmation email template.
     */
    private function renderRegistrationTemplate(string $confirmLink): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Confirma tu registro</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 12px 30px; background-color: #008C45; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #888; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="color: #008C45;">Buscador de Actividades UNED</h2>
        <p>Gracias por registrarte en el Buscador de Actividades UNED.</p>
        <p>Para completar tu registro, haz clic en el siguiente botón:</p>
        <p><a href="$confirmLink" class="button">Confirmar registro</a></p>
        <p>O copia y pega este enlace en tu navegador:</p>
        <p style="word-break: break-all; color: #666;">$confirmLink</p>
        <div class="footer">
            <p>&copy; 2026 <a href="https://simplicer.com" style="color: #008C45;">Simplicer SL</a> - Licencia MIT</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
