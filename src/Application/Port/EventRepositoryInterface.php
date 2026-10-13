<?php

declare(strict_types=1);
namespace App\Application\Port;
use App\Domain\Event\Event;
interface EventRepositoryInterface
{
    public function all(): array;
    public function get(int $id): ?Event;
    public function save(Event $event): Event;
}
