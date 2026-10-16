<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;

final class PhpstanBoundaryRegressionTest extends ApiTestCase
{
    public function testMalformedNestedTripInputIsRejectedWithoutPersisting(): void
    {
        $invalidPayloads = [
            ['name'=>['unexpected']],
            ['bookings'=>['unexpected','unexpected','unexpected']],
            ['groupExpenses'=>[['label'=>'Bus','amount'=>['unexpected']]]],
            ['roomReservations'=>['unexpected']],
        ];
        foreach ($invalidPayloads as $invalid) {
            $response=$this->dispatch('POST','/api/trips',array_replace($this->validTripPayload(),$invalid));
            self::assertSame(422,$response->getStatusCode(),(string)$response->getBody());
            self::assertSame(0,(int)$this->app->getContainer()->get(PDO::class)->query('SELECT COUNT(*) FROM trips')->fetchColumn());
        }
    }

    public function testCorruptStoredSettlementCannotBecomeAZeroAmount(): void
    {
        $created=$this->dispatch('POST','/api/trips',$this->validTripPayload());
        self::assertSame(201,$created->getStatusCode());
        $id=json_decode((string)$created->getBody(),true,512,JSON_THROW_ON_ERROR)['id'];
        $closed=$this->dispatch('POST',"/api/trips/$id/close",['reason'=>'Abschluss für Snapshot-Prüfung']);
        self::assertSame(200,$closed->getStatusCode(),(string)$closed->getBody());
        $pdo=$this->app->getContainer()->get(PDO::class);
        $snapshot=json_decode($pdo->query("SELECT settlement_snapshot FROM trips WHERE id=$id")->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
        $snapshot['surplus']='not-a-number';
        $pdo->prepare('UPDATE trips SET settlement_snapshot=? WHERE id=?')->execute([json_encode($snapshot,JSON_THROW_ON_ERROR),$id]);
        $response=$this->dispatch('GET',"/api/trips/$id/settlement");
        self::assertSame(500,$response->getStatusCode());
        self::assertSame('server_error',json_decode((string)$response->getBody(),true,512,JSON_THROW_ON_ERROR)['error']);
    }
}
