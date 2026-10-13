<?php

declare(strict_types=1);
namespace App\Infrastructure\Http\Auth;
use App\Application\Port\ActorInterface;
final class SessionActor implements ActorInterface
{
    public function userId(): ?int { return Session::userId(); }
}
