<?php

declare(strict_types=1);
namespace App\Domain\Event;
final readonly class Event
{
    public function __construct(public ?int $id, public string $name, public string $description = '', public bool $archived = false, public int $revision = 1)
    {
        if (trim($name) === '' || mb_strlen($name) > 200 || mb_strlen($description) > 10000) {
            throw new \InvalidArgumentException('Name erforderlich (max. 200), Beschreibung max. 10000 Zeichen.');
        }
    }
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'description' => $this->description, 'archived' => $this->archived, 'revision' => $this->revision];
    }
}
