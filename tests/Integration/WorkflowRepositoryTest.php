<?php

declare(strict_types=1);
namespace Tests\Integration;

use App\Application\Port\TermRepositoryInterface;
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
use PDO;

final class WorkflowRepositoryTest extends ApiTestCase
{
    public function testTypedRepositoryAndLegacyBridgeShareOnePersistedState(): void
    {
        $created=$this->dispatch('POST','/api/trips',$this->validTripPayload());
        self::assertSame(201,$created->getStatusCode());$trip=json_decode((string)$created->getBody(),true)['id'];
        $repository=$this->app->getContainer()->get(TermRepositoryInterface::class);
        $legacy=$this->app->getContainer()->get(SqliteWorkflowRepository::class);
        self::assertNull($repository->draft($trip));
        $repository->saveDraft($trip,2,['title'=>'Ausflug']);
        self::assertSame(['revision'=>2,'data'=>['title'=>'Ausflug']],$legacy->draft($trip));
        $repository->bindLink($trip,'77',['externalId'=>'77','customMetadata'=>['keep'=>true]]);
        self::assertSame($repository->link($trip),$legacy->link($trip));
        $repository->requestCreation($trip,'repository_operation_001','request-hash',['title'=>'Ausflug'],1,null);
        self::assertSame($repository->job($trip),$legacy->job($trip));
        self::assertTrue($repository->externalIdIsLinked('77'));
        self::assertFalse($repository->externalIdIsLinked('78'));
    }

    public function testCorruptStoredJsonIsRejectedInsteadOfBecomingAnEmptyDraft(): void
    {
        $created=$this->dispatch('POST','/api/trips',$this->validTripPayload());
        $trip=json_decode((string)$created->getBody(),true)['id'];
        $this->app->getContainer()->get(PDO::class)->prepare('INSERT INTO term_drafts (trip_id,revision,draft_json) VALUES (?,1,?)')->execute([$trip,'true']);
        $this->expectException(\UnexpectedValueException::class);
        $this->app->getContainer()->get(TermRepositoryInterface::class)->draft($trip);
    }
}
