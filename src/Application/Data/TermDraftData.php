<?php

declare(strict_types=1);

namespace App\Application\Data;

use App\Application\ValidationException;
use App\Domain\Form\Decimal;
use App\Domain\Form\FormSchema;

/** @phpstan-type PriceGroup array{label: string, price: string|null, category: 'ADULT_DOUBLE'|'ADULT_MULTI'|'CHILD'|'GUEST'}
 * @phpstan-type DraftData array{
 *   title: string, startDate: string, endDate: string|null, location: string,
 *   description: string, calendarReference: string, visibility: 'members'|'private',
 *   capacity: int, registrationDeadline: string|null, priceGroups: list<PriceGroup>,
 *   formVersionId: int|null, mode: 'create'|'link', externalId: string, teamUserIds: list<int>
 * }
 */
final readonly class TermDraftData
{
    /** @param DraftData $data */
    private function __construct(private array $data) {}

    /** @param array<array-key, mixed> $input */
    public static function fromArray(array $input): self
    {
        foreach (['endDate', 'registrationDeadline', 'formVersionId', 'teamUserIds'] as $key) {
            if (!array_key_exists($key, $input)) { throw new ValidationException([$key => 'required_nullable']); }
        }
        if (array_diff(array_keys($input), ['title','startDate','endDate','location','description','calendarReference','visibility','capacity','registrationDeadline','priceGroups','formVersionId','mode','externalId','teamUserIds'])) {
            throw new ValidationException(['draft' => 'unknown_field']);
        }
        $title = self::text($input, 'title', 200);
        if (trim($title) === '') { throw new ValidationException(['title' => 'invalid_text']); }
        $location = self::text($input, 'location', 500);
        $description = self::text($input, 'description', 10000);
        $calendar = self::text($input, 'calendarReference', 200);
        $start = self::date($input['startDate'] ?? null, 'startDate');
        $end = $input['endDate'] === null ? null : self::date($input['endDate'], 'endDate');
        $deadline = $input['registrationDeadline'] === null ? null : self::date($input['registrationDeadline'], 'registrationDeadline');
        if ($end !== null && $end < $start) { throw new ValidationException(['startDate' => 'valid_period_required']); }
        $mode = $input['mode'] ?? null;
        $visibility = $input['visibility'] ?? null;
        $capacity = $input['capacity'] ?? null;
        if (!in_array($mode, ['create', 'link'], true) || !in_array($visibility, ['members', 'private'], true)
            || !is_int($capacity) || $capacity < 0 || $capacity > 1000000) {
            throw new ValidationException(['draft' => 'invalid_mode_visibility_capacity']);
        }
        $externalId = $input['externalId'] ?? null;
        if (!is_string($externalId) || !preg_match('/^(?:[1-9][0-9]{0,18})?$/D', $externalId)) { throw new ValidationException(['externalId' => 'provider_id_or_empty']); }
        $version = $input['formVersionId'];
        if ($version !== null && !is_int($version)) { throw new ValidationException(['formVersionId' => 'published_version_of_event_required']); }
        $groups = self::groups($input['priceGroups'] ?? null);
        $team = $input['teamUserIds'] ?? [];
        if (!is_array($team) || !array_is_list($team) || count($team) > 100) { throw new ValidationException(['teamUserIds' => 'user_id_list']); }
        $ids = [];
        foreach ($team as $id) {
            if (!is_int($id) || $id < 1) { throw new ValidationException(['teamUserIds' => 'user_id_list']); }
            $ids[$id] = $id;
        }
        return new self([
            'title' => $title, 'startDate' => $start, 'endDate' => $end, 'location' => $location,
            'description' => $description, 'calendarReference' => $calendar, 'visibility' => $visibility,
            'capacity' => $capacity, 'registrationDeadline' => $deadline, 'priceGroups' => $groups,
            'formVersionId' => $version, 'mode' => $mode, 'externalId' => $externalId, 'teamUserIds' => array_values($ids),
        ]);
    }

    /** @param array<array-key, mixed> $input */
    private static function text(array $input, string $key, int $max): string
    {
        $value = $input[$key] ?? null;
        if (!is_string($value) || mb_strlen($value) > $max) { throw new ValidationException([$key => 'invalid_text']); }
        return $value;
    }

    private static function date(mixed $value, string $key): string
    {
        if (!is_string($value) || !FormSchema::date($value)) { throw new ValidationException([$key => 'date_required']); }
        return $value;
    }

    /** @return list<PriceGroup> */
    private static function groups(mixed $input): array
    {
        if (!is_array($input) || !array_is_list($input) || count($input) > 50) { throw new ValidationException(['priceGroups' => 'list_max_50']); }
        $groups = []; $names = [];
        foreach ($input as $group) {
            if (!is_array($group) || array_diff(array_keys($group), ['label', 'price', 'category'])) { throw new ValidationException(['priceGroups' => 'unique_label_and_category_required']); }
            $label = $group['label'] ?? null;
            $category = $group['category'] ?? null;
            if (!is_string($label) || trim($label) === '' || mb_strlen($label) > 200 || isset($names[mb_strtolower(trim($label))])
                || !in_array($category, ['ADULT_DOUBLE', 'ADULT_MULTI', 'CHILD', 'GUEST'], true)) {
                throw new ValidationException(['priceGroups' => 'unique_label_and_category_required']);
            }
            $names[mb_strtolower(trim($label))] = true;
            $raw = $group['price'] ?? null;
            $price = $raw === null ? null : Decimal::normalize($raw);
            if ($raw !== null && ($price === null || Decimal::compare($price, '0') < 0 || Decimal::compare($price, '1000000') > 0)) {
                throw new ValidationException(['priceGroups' => 'valid_nonnegative_price']);
            }
            $groups[] = ['label' => $label, 'category' => $category, 'price' => $price];
        }
        return $groups;
    }

    /** @return DraftData */
    public function toArray(): array { return $this->data; }
}
