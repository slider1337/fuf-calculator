<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\{AccessException,Port\AccessRepositoryInterface};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
final readonly class ProviderRequestBudget {
 public function __construct(private SqliteWorkflowRepository $store,private AccessRepositoryInterface $access,private \PDO $pdo) {}
 public function defer(string $bucket,int $seconds): void {
  $this->access->transaction(fn()=>$this->store->query('INSERT INTO provider_api_cooldown (bucket,until_at) VALUES (?,?) ON CONFLICT(bucket) DO UPDATE SET until_at=MAX(until_at,excluded.until_at)',[$bucket,time()+max(60,$seconds)]));
 }
 public function reserve(string $bucket): void {
  if($this->pdo->inTransaction()){throw new AccessException('provider_network_transaction_forbidden',503);}
  $this->access->transaction(function()use($bucket){
   $now=time();$until=(int)$this->store->query('SELECT until_at FROM provider_api_cooldown WHERE bucket=?',[$bucket])->fetchColumn();
   if($until>$now)throw new AccessException('provider_rate_limited',503,$until-$now);
   $limit=max(1,min(90,(int)(getenv('EASYVEREIN_REQUESTS_PER_MINUTE')?:60)));
   $this->store->query('DELETE FROM provider_api_calls WHERE called_at<=?',[$now-60]);
   if((int)$this->store->query('SELECT COUNT(*) FROM provider_api_calls WHERE bucket=?',[$bucket])->fetchColumn()>=$limit){throw new AccessException('provider_rate_limited',503,60);}
   $this->store->query('INSERT INTO provider_api_calls (bucket,called_at) VALUES (?,?)',[$bucket,$now]);
  });
 }
}
