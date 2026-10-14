<?php
declare(strict_types=1);
namespace App\Infrastructure\Mail;
use App\Application\Port\MailerInterface;
/** Default for development/staging: every actual application mail has exactly the authorized test recipient. */
final readonly class TestRecipientMailer implements MailerInterface {
 public function __construct(private MailerInterface $inner) {}
 public function send(string $to,string $subject,string $textBody,string $htmlBody): void {
  $this->inner->send('me@benna.me',$subject,$textBody,$htmlBody);
 }
}
