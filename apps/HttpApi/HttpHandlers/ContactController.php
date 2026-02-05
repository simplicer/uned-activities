<?php

declare(strict_types=1);

namespace HttpApi\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shared\Infrastructure\Email\SmtpEmailService;

/**
 * Contact controller for legal form submissions.
 */
final readonly class ContactController
{
    public function __construct(
        private SmtpEmailService $emailService,
        private string $recipient,
        private string $context = 'Contacto',
    ) {
    }

    /**
     * POST /contact - Submit contact form.
     */
    public function submit(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $name = trim((string) ($body['name'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $subject = trim((string) ($body['subject'] ?? ''));
        $message = trim((string) ($body['message'] ?? ''));
        $honeypot = trim((string) ($body['website'] ?? ''));

        if ($honeypot !== '') {
            return $response->withStatus(204);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json($response, 400, [
                'error' => 'validation_error',
                'message' => 'Email inválido.',
            ]);
        }

        if ($message === '' || mb_strlen($message) < 10) {
            return $this->json($response, 400, [
                'error' => 'validation_error',
                'message' => 'El mensaje es demasiado corto.',
            ]);
        }

        if ($this->recipient === '') {
            return $this->json($response, 500, [
                'error' => 'server_error',
                'message' => 'No se pudo procesar la solicitud.',
            ]);
        }

        $sent = $this->emailService->sendContactMessage(
            $this->recipient,
            $email,
            $name,
            $subject !== '' ? \sprintf('%s - %s', $this->context, $subject) : $this->context,
            $message
        );

        if (!$sent) {
            return $this->json($response, 500, [
                'error' => 'server_error',
                'message' => 'No se pudo enviar el mensaje.',
            ]);
        }

        return $this->json($response, 200, [
            'message' => 'Mensaje enviado correctamente.',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(Response $response, int $status, array $payload): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));

        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
