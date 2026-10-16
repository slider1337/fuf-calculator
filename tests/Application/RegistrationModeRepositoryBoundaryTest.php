<?php

declare(strict_types=1);
namespace Tests\Application;

use App\Application\{AccessException, AccessPolicy, RegistrationModeService};
use App\Application\Data\RegistrationModeState;
use App\Application\Port\{AccessRepositoryInterface, ActorInterface, RegistrationModeRepositoryInterface};
use App\Domain\Trip\RegistrationMode;
use PHPUnit\Framework\TestCase;

final class RegistrationModeRepositoryBoundaryTest extends TestCase
{
    private function policy(): AccessPolicy
    {
        $access=$this->createStub(AccessRepositoryInterface::class);
        $access->method('role')->willReturn('user');
        $access->method('capabilities')->willReturn(['trip.view','trip.manage']);
        $access->method('metadata')->willReturn(['archived_at'=>null,'closed_at'=>null]);
        $access->method('transaction')->willReturnCallback(static fn(callable $operation)=>$operation());
        $actor=$this->createStub(ActorInterface::class);$actor->method('userId')->willReturn(1);
        return new AccessPolicy($access,$actor);
    }

    public function testModeChangeUsesDomainRepositoryWithoutAnyDatabaseDependency(): void
    {
        $repository=$this->createMock(RegistrationModeRepositoryInterface::class);
        $repository->method('state')->willReturnOnConsecutiveCalls(new RegistrationModeState(RegistrationMode::EasyVerein,3),new RegistrationModeState(RegistrationMode::ClubdeskCsv,4));
        $repository->expects(self::once())->method('disableMemberRegistration')->with(7);
        $repository->expects(self::once())->method('change')->with(7,RegistrationMode::ClubdeskCsv);
        $service=new RegistrationModeService($this->policy(),$repository);
        self::assertSame(['registrationMode'=>'clubdesk_csv','revision'=>4],$service->change(7,['registrationMode'=>'clubdesk_csv','revision'=>3,'reason'=>'CSV übernehmen','confirmed'=>true]));
    }

    public function testActiveOperationsBlockModeChangeBeforeAnyMutation(): void
    {
        $repository=$this->createMock(RegistrationModeRepositoryInterface::class);
        $repository->method('state')->willReturn(new RegistrationModeState(RegistrationMode::EasyVerein,3));
        $repository->method('hasActiveOperations')->willReturn(true);
        $repository->expects(self::never())->method('change');$repository->expects(self::never())->method('disableMemberRegistration');
        $service=new RegistrationModeService($this->policy(),$repository);
        try { $service->change(7,['registrationMode'=>'clubdesk_csv','revision'=>3,'reason'=>'CSV übernehmen','confirmed'=>true]);self::fail('Changed mode with an active job'); }
        catch (AccessException $e) { self::assertSame('registration_mode_operation_in_progress',$e->error); }
    }
}
