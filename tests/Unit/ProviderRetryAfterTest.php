<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Infrastructure\ProviderRetryAfter;
use PHPUnit\Framework\TestCase;
final class ProviderRetryAfterTest extends TestCase {
 public function testSecondsDatesAndInvalidValues(): void {
  self::assertSame(120,ProviderRetryAfter::seconds('120',0));
  self::assertSame(120,ProviderRetryAfter::seconds('Thu, 01 Jan 1970 00:02:00 GMT',0));
  self::assertSame(60,ProviderRetryAfter::seconds('invalid',0));
  self::assertSame(1,ProviderRetryAfter::seconds('0',0));
  self::assertSame(86400,ProviderRetryAfter::seconds('999999999999999999999',0));
 }
}
