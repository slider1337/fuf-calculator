<?php
declare(strict_types=1);
namespace App\Application;
/** Only a proven rejection before an external change is safe to retry. No provider body in this exception. */
final class ParticipationRejected extends \RuntimeException {}
