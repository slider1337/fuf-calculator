<?php

declare(strict_types=1);
namespace Tests\Unit;

use App\Application\Data\TermDraftData;
use App\Application\TermFields;
use App\Application\ValidationException;
use App\Infrastructure\Persistence\StoredRow;
use PHPUnit\Framework\TestCase;

final class WorkflowDataTest extends TestCase
{
    private function draft(): array
    {
        return ['title'=>'Ausflug','startDate'=>'2027-01-20','endDate'=>null,'registrationDeadline'=>'2027-01-10',
            'location'=>'Erding','description'=>'Beschreibung','calendarReference'=>'12','visibility'=>'members',
            'capacity'=>0,'priceGroups'=>[['label'=>'Erwachsene','category'=>'ADULT_DOUBLE','price'=>'12.50']],
            'formVersionId'=>null,'mode'=>'create','externalId'=>'','teamUserIds'=>[2,1,2]];
    }

    public function testDraftPreservesOptionalValuesAndCanonicalPriceAndTeamOrder(): void
    {
        $input=$this->draft();$input['priceGroups'][]=['label'=>'Kinder','category'=>'CHILD'];
        $data=TermDraftData::fromArray($input)->toArray();
        self::assertSame('12.5',$data['priceGroups'][0]['price']);
        self::assertNull($data['priceGroups'][1]['price']); // Remains incomplete; no invented free price.
        self::assertSame([2,1],$data['teamUserIds']);
        self::assertSame(0,$data['capacity']);self::assertNull($data['endDate']);
    }

    public function testMalformedDraftValuesCannotReachPersistence(): void
    {
        foreach (['startDate'=>['2027-01-20'],'endDate'=>'2027-02-30','teamUserIds'=>['1'],'mode'=>true,'capacity'=>'40','unexpected'=>'ignored?'] as $field=>$value) {
            $input=$this->draft();$input[$field]=$value;
            try { TermDraftData::fromArray($input); self::fail('Accepted malformed '.$field); }
            catch (ValidationException $error) { self::assertNotEmpty($error->errors()); }
        }
    }

    public function testObservedZeroCapacityIsNotAWritePermission(): void
    {
        self::assertSame(['capacity'=>0],TermFields::observed(['capacity'=>0]));
        $this->expectException(ValidationException::class);TermFields::changes(['capacity'=>0]);
    }

    public function testDateFieldsNormalizeToUtcAndRejectNestedValues(): void
    {
        self::assertSame(['start'=>'2027-01-20T09:00:00+00:00'],TermFields::changes(['start'=>'2027-01-20T10:00:00+01:00']));
        $this->expectException(ValidationException::class);TermFields::observed(['title'=>['unexpected']]);
    }

    public function testStoredRowsRejectLossyIntegerCastsAndScalarJson(): void
    {
        foreach ([['revision'=>'4oops'],['revision'=>4.5],['revision'=>true]] as $input) {
            try { StoredRow::from($input)->integer('revision'); self::fail('Accepted invalid stored integer'); }
            catch (\UnexpectedValueException) { self::assertTrue(true); }
        }
        self::assertSame(4,StoredRow::from(['revision'=>'4'])->integer('revision'));
        $this->expectException(\UnexpectedValueException::class);StoredRow::from(['data'=>'true'])->jsonArray('data');
    }
}
