<?php
declare(strict_types=1);
namespace App\Application;
/** Only a proven rejection before an external change is safe to retry. No raw provider body. */
final class ParticipationRejected extends \RuntimeException
{
    public function __construct(string $message='',int $code=0,public readonly array $validationErrors=[]) {parent::__construct($message,$code);}
}
