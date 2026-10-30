<?php
declare(strict_types=1);
namespace App\Infrastructure;

final readonly class EasyVereinResponse
{
    /** @param array<string,string> $headers */
    public function __construct(public int $status, public string|false $body, public array $headers=[]) {}
    public function refreshNeeded(): bool
    {
        foreach($this->headers as $name=>$value) {
            if(in_array(strtolower($name),['token_refresh_needed','tokenrefreshneeded'],true)
                && in_array(strtolower(trim($value)),['true','1'],true))return true;
        }
        return false;
    }
    public function retryAfter(): int { return ProviderRetryAfter::seconds($this->headers['retry-after']??'60'); }
}
