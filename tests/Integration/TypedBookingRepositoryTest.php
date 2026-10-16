<?php

declare(strict_types=1);
namespace Tests\Integration;

use App\Application\Port\{BookingRepositoryInterface,FormRepositoryInterface};
use PDO;
use UnexpectedValueException;

final class TypedBookingRepositoryTest extends ApiTestCase
{
    /** @return array{int,int} */
    private function booking(): array
    {
        $response=$this->dispatch('POST','/api/trips',$this->validTripPayload());
        self::assertSame(201,$response->getStatusCode());
        $trip=json_decode((string)$response->getBody(),true,512,JSON_THROW_ON_ERROR)['id'];
        $repository=$this->app->getContainer()->get(BookingRepositoryInterface::class);
        $id=$repository->create($trip,['contactIdentity'=>'easyverein:77','versionId'=>null,
            'persons'=>[['id'=>'external-77','name'=>'Provider person','birthDate'=>'']]]);
        return [$trip,$id];
    }

    public function testUnknownBirthdayAndNullableReferencesArePreserved(): void
    {
        [$trip,$id]=$this->booking();
        $repository=$this->app->getContainer()->get(BookingRepositoryInterface::class);
        $booking=$repository->get($trip,$id);
        self::assertNotNull($booking);
        self::assertSame('',$booking['persons'][0]['birthDate']);
        self::assertNull($booking['persons'][0]['memberReference']);
        self::assertNull($booking['persons'][0]['guestId']);
        $repository->recordOperation($trip,'typed_operation_001','hash',$id);
        self::assertSame(['request_hash'=>'hash','registration_id'=>$id],$repository->operation($trip,'typed_operation_001'));
    }

    public function testCorruptRevisionIsRejectedInsteadOfCastToZero(): void
    {
        [$trip,$id]=$this->booking();
        $this->app->getContainer()->get(PDO::class)->prepare('UPDATE registrations SET revision=? WHERE id=?')->execute(['invalid',$id]);
        $this->expectException(UnexpectedValueException::class);
        $this->app->getContainer()->get(BookingRepositoryInterface::class)->get($trip,$id);
    }

    public function testMalformedStoredFormDefinitionIsRejected(): void
    {
        $response=$this->dispatch('POST','/api/events',['name'=>'Typed fixture']);
        self::assertSame(201,$response->getStatusCode());
        $event=json_decode((string)$response->getBody(),true,512,JSON_THROW_ON_ERROR)['id'];
        $repository=$this->app->getContainer()->get(FormRepositoryInterface::class);
        $version=$repository->create($event,null,1,[]);
        $this->app->getContainer()->get(PDO::class)->prepare('INSERT INTO form_fields(version_id,field_id,position,definition) VALUES (?,?,?,?)')->execute([$version['id'],'f_broken',0,'{"id":[]}']);
        $this->expectException(UnexpectedValueException::class);
        $repository->get($version['id']);
    }
}
