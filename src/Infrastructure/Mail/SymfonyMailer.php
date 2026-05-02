<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Port\MailerInterface;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Throwable;

final class SymfonyMailer implements MailerInterface
{
    private readonly Mailer $mailer;

    public function __construct(
        string $dsn,
        private readonly string $fromAddress,
    ) {
        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);
    }

    public function send(string $to, string $subject, string $textBody, string $htmlBody): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($to)
            ->subject($subject)
            ->text($textBody)
            ->html($htmlBody);

        try {
            $this->mailer->send($email);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Mail konnte nicht versendet werden: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }
    }
}
