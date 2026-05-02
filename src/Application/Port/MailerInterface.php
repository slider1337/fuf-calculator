<?php

declare(strict_types=1);

namespace App\Application\Port;

interface MailerInterface
{
    public function send(string $to, string $subject, string $textBody, string $htmlBody): void;
}
