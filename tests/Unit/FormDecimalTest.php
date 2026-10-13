<?php

declare(strict_types=1);
namespace Tests\Unit;
use App\Domain\Form\Decimal;
use PHPUnit\Framework\TestCase;

final class FormDecimalTest extends TestCase
{
    public function testFixedPointBoundariesStayExact(): void
    {
        self::assertSame('0',Decimal::normalize('-000.000000'));
        self::assertSame('1.25',Decimal::normalize('001.250000'));
        self::assertSame(1,Decimal::compare('999999999999.999999','999999999999.999998'));
        self::assertSame(-1,Decimal::compare('-999999999999.999999','-999999999999.999998'));
        self::assertSame(1,Decimal::compare('0.000001','0'));
        self::assertSame(0,Decimal::compare('1.250000','1.25'));
        foreach ([0.1,true,null,'1e2','1,25','1000000000000','0.0000001',[],new \stdClass()] as $value) { self::assertNull(Decimal::normalize($value)); }
    }
}
