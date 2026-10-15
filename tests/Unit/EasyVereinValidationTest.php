<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Infrastructure\EasyVereinValidation;
use PHPUnit\Framework\TestCase;
final class EasyVereinValidationTest extends TestCase
{
    public function testReducesFieldErrorsWithoutReflectingValuesOrRemoteMessages(): void
    {
        $body=json_encode(['prologue'=>['This field may not be blank.'],'creator'=>['Invalid pk "123" - object does not exist.'],'errors'=>[['field'=>'end_participation','message'=>'Date rejected secret@example.org Bearer TOKEN']],'unknownSecret'=>['TOKEN'],'detail'=>'sensitive remote text'],JSON_THROW_ON_ERROR);
        self::assertSame([['field'=>'prologue','reason'=>'blank'],['field'=>'creator','reason'=>'reference'],['field'=>'end_participation','reason'=>'invalid'],['field'=>'event','reason'=>'invalid']],EasyVereinValidation::fromBody($body));
        self::assertStringNotContainsString('TOKEN',json_encode(EasyVereinValidation::fromBody($body)));
    }
    public function testRejectsNonJsonAndOversizedBodiesAndHandlesNestedErrors(): void
    {
        foreach(['<html>secret</html>','null','"secret"',str_repeat('x',65537)] as $body)self::assertSame([],EasyVereinValidation::fromBody($body));
        self::assertSame([['field'=>'name','reason'=>'required'],['field'=>'max_participators','reason'=>'range']],EasyVereinValidation::fromBody('{"errors":{"name":["This field is required."],"max_participators":["Ensure this value is greater than or equal to 1."]}}'));
    }
}
