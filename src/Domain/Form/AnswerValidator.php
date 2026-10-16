<?php

declare(strict_types=1);
namespace App\Domain\Form;
use App\Application\ValidationException;

/**
 * @phpstan-type AnswerEntry array{state:string,value:mixed}
 * @phpstan-type AnswerMaps array{booking:array<array-key,AnswerEntry>,persons:array<array-key,array<array-key,AnswerEntry>>}
 * @phpstan-type ValidationResult array{valid:bool,errors:array<string,string>,answers:AnswerMaps,persons:list<array{id:string,age:int,child:bool}>,startDate:string,adultAgeThreshold:int}
 */
final class AnswerValidator
{
    /** @param array<array-key,mixed> $fields
     * @param array<array-key,mixed> $context
     * @param array<array-key,mixed> $answers
     * @return ValidationResult */
    public function validate(array $fields, array $context, array $answers): array
    {
        $date = $context['startDate'] ?? null; $threshold = $context['adultAgeThreshold'] ?? null; $people = $context['persons'] ?? null;
        if (!is_string($date) || !FormSchema::date($date) || !is_int($threshold) || $threshold < 1 || $threshold > 100 || !is_array($people) || !array_is_list($people) || count($people) > 100) { throw new ValidationException(['context' => 'valid_date_age_threshold_and_person_list_required']); }
        $persons = []; $hasChildren = false;
        foreach ($people as $p) {
            if (!is_array($p) || !is_string($p['id'] ?? null) || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $p['id']) || isset($persons[$p['id']]) || !is_string($p['birthDate']??null) || !FormSchema::date($p['birthDate']) || $p['birthDate'] > $date) { throw new ValidationException(['context.persons' => 'unique_person_id_and_valid_birth_date_required']); }
            $age = (new \DateTimeImmutable($p['birthDate']))->diff(new \DateTimeImmutable($date))->y;
            $persons[$p['id']] = ['id' => $p['id'], 'age' => $age, 'child' => $age < $threshold]; $hasChildren = $hasChildren || $age < $threshold;
        }
        $booking = $answers['booking'] ?? []; $individual = $answers['persons'] ?? [];
        if (!is_array($booking) || !is_array($individual)) { throw new ValidationException(['answers' => 'answer_maps_required']); }
        $errors = []; $result = ['booking' => [], 'persons' => []];
        foreach (array_keys($individual) as $pid) { if (!isset($persons[$pid]) || !is_array($individual[$pid])) { throw new ValidationException(['persons.' . $pid => 'unknown_person_or_invalid_map']); } }
        $byId = array_column($fields, null, 'id');
        foreach (['booking' => $booking] + array_combine(array_map(static fn ($id) => 'persons.' . $id, array_keys($persons)), array_map(static fn ($id) => $individual[$id] ?? [], array_keys($persons))) as $path => $values) {
            foreach (array_keys($values) as $id) { if (!isset($byId[$id]) || $byId[$id]['scope'] !== ($path === 'booking' ? 'booking' : 'person') || !FormSchema::answerField($byId[$id])) { $errors[$path . '.' . $id] = 'unknown_field_or_wrong_scope'; } }
        }
        foreach ($fields as $f) {
            if(!is_array($f)||!is_string($f['id']??null))throw new ValidationException(['fields'=>'invalid_definition']);
            $targets = $f['scope'] === 'booking' ? ['booking' => null] : $persons;
            foreach ($targets as $key => $person) {
                $pid = $person['id'] ?? null; $values = $pid === null ? $booking : ($individual[$pid] ?? []);
                $path = ($pid === null ? 'booking' : 'persons.' . $pid) . '.' . $f['id'];
                $visibility = $f['visibility'];
                if(!is_array($visibility)||!is_array($values))throw new ValidationException(['fields'=>'invalid_definition']);
                $visible = match ($visibility['kind']) {
                    'always' => true, 'children' => $hasChildren, 'child' => $person['child'], 'adult' => !$person['child'],
                    'selection' => $this->selected($visibility, $result['booking'], $pid === null ? [] : ($result['persons'][$pid] ?? [])),
                };
                $entry = ['state' => 'not_applicable', 'value' => null];
                if ($visible && !FormSchema::answerField($f)) { $entry['state'] = 'display'; }
                elseif ($visible) {
                    $value = $values[$f['id']] ?? null;
                    $missing = $value === null || $value === '' || (is_array($value) && $value === []);
                    $entry['state'] = $missing ? 'missing' : 'answered';
                    if ($missing) { if ($f['required']) { $errors[$path] = 'required'; } }
                    else {
                        [$valid, $normalized] = $this->value($f, $value);
                        if (!$valid) { $errors[$path] = 'invalid_value'; $entry['state'] = 'invalid'; }
                        else { $entry['value'] = $normalized; }
                    }
                }
                if ($pid === null) { $result['booking'][$f['id']] = $entry; }
                else { $result['persons'][$pid][$f['id']] = $entry; }
            }
        }
        return ['valid' => $errors === [], 'errors' => $errors, 'answers' => $result, 'persons' => array_values($persons), 'startDate' => $date, 'adultAgeThreshold' => $threshold];
    }
    /** @param array<array-key,mixed> $v
     * @param array<array-key,AnswerEntry> $booking
     * @param array<array-key,AnswerEntry> $person */
    private function selected(array $v, array $booking, array $person): bool
    {
        $e = $person[$v['fieldId']] ?? $booking[$v['fieldId']] ?? null;
        return $e !== null && $e['state'] === 'answered' && (is_array($e['value']) ? in_array($v['value'], $e['value'], true) : $e['value'] === $v['value']);
    }
    /** @param array<array-key,mixed> $f
     * @return array{bool,mixed} */
    private function value(array $f, mixed $v): array
    {
        switch ($f['type']) {
            case 'number':
                $n = Decimal::normalize($v);
                return [$n !== null && (!$f['integer'] || !str_contains($n, '.')) && ($f['min'] === null || Decimal::compare($n, $f['min']) >= 0) && ($f['max'] === null || Decimal::compare($n, $f['max']) <= 0), $n];
            case 'checkbox': return [is_bool($v) && (!$f['required'] || $v), $v];
            case 'yes_no': return [is_bool($v), $v];
            case 'single_select': return [is_string($v) && in_array($v, array_column($f['options'], 'id'), true), $v];
            case 'multi_select':
                return [is_array($v) && array_is_list($v) && count(array_filter($v, 'is_string')) === count($v) && count(array_unique($v, SORT_REGULAR)) === count($v) && count($v) >= $f['minSelections'] && count($v) <= $f['maxSelections'] && array_diff($v, array_column($f['options'], 'id')) === [], $v];
            case 'short_text': case 'long_text': return [is_string($v) && trim($v) !== '' && mb_strlen($v) <= $f['maxLength'], $v];
            case 'date': return [FormSchema::date($v) && ($f['min'] === null || $v >= $f['min']) && ($f['max'] === null || $v <= $f['max']), $v];
        }
        return [false, null];
    }
}
