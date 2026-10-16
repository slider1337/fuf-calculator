<?php

declare(strict_types=1);
namespace App\Application\Port;
/**
 * @phpstan-type FormVersion array{id:int,eventId:int,number:?int,status:string,revision:int,basedOnId:?int,createdBy:int,createdAt:string,publishedBy:?int,publishedAt:?string,changeDescription:string,fields:list<Field>}
 * @phpstan-type FormHeader array{id:int,eventId:int,number:?int,status:string,revision:int,basedOnId:?int,createdBy:int,createdAt:string,publishedBy:?int,publishedAt:?string,changeDescription:string}
 * @phpstan-type BindingHistory array{id:int,fromVersionId:?int,toVersionId:int,revision:int,actorId:int,createdAt:string,reason:string,requestSupplement:bool}
 * @phpstan-type FormBinding array{versionId:?int,revision:int,history:list<BindingHistory>}
 * @phpstan-import-type Field from \App\Domain\Form\FormSchema
 */
interface FormRepositoryInterface
{
    /** @return list<FormVersion> */
    public function list(int $eventId): array;
    /**
     * @phpstan-impure
     * @return FormVersion|null
     */
    public function get(int $id): ?array;
    /**
     * @return FormVersion
     * @param list<Field> $fields
     */
    public function create(int $eventId, ?int $basedOnId, int $actor, array $fields): array;
    /**
     * @return FormVersion
     * @param list<Field> $fields
     */
    public function save(int $id, int $revision, array $fields): array;
    /** @return FormVersion */
    public function publish(int $id, int $revision, int $actor, string $description): array;
    /** @return FormVersion */
    public function archive(int $id, int $revision): array;
    /** @return FormBinding */
    public function binding(int $tripId): array;
    /** @return FormBinding */
    public function switchVersion(int $tripId, int $revision, int $versionId, int $actor, string $reason, bool $supplement): array;
    /** @return list<array{versionId:?int,count:int}> */
    public function registrationVersions(int $tripId): array;
    public function registrationCount(int $tripId): int;
}
