<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Infrastructure\ProviderRequestBudget;
use App\Application\AccessException;
use PDO;
final class ProviderBudgetTest extends ApiTestCase {
 public function testSharedBudgetStopsBeforeHttpAndSeparatesTokens(): void {
  putenv('EASYVEREIN_REQUESTS_PER_MINUTE=1');try{$b=$this->app->getContainer()->get(ProviderRequestBudget::class);$b->reserve('fake-token-hash');$b->reserve('other-fake-token-hash');
   try{$b->reserve('fake-token-hash');self::fail('Limit');}catch(AccessException $e){self::assertSame('provider_rate_limited',$e->error);}
   self::assertSame(2,(int)$this->app->getContainer()->get(PDO::class)->query('SELECT COUNT(*) FROM provider_api_calls')->fetchColumn());
  }finally{putenv('EASYVEREIN_REQUESTS_PER_MINUTE');}
 }
 public function testRetryAfterIsSharedAcrossWorkersAndRequestsBeforeNetwork(): void {
  $budget=$this->app->getContainer()->get(ProviderRequestBudget::class);$budget->defer('fixture',900);
  try{$budget->reserve('fixture');self::fail('Cooldown ignored');}catch(AccessException $e){self::assertSame('provider_rate_limited',$e->error);self::assertGreaterThanOrEqual(899,$e->retryAfter);}
  $budget->reserve('other-token');self::assertSame(1,(int)$this->app->getContainer()->get(PDO::class)->query('SELECT COUNT(*) FROM provider_api_calls')->fetchColumn());
 }
 public function testNetworkBudgetCannotBeEnteredDuringBusinessTransaction(): void {
  $db=$this->app->getContainer()->get(PDO::class);$db->beginTransaction();try{
   try{$this->app->getContainer()->get(ProviderRequestBudget::class)->reserve('fixture');self::fail('Open transaction');}catch(AccessException $e){self::assertSame('provider_network_transaction_forbidden',$e->error);}
  }finally{$db->rollBack();}
 }
}
