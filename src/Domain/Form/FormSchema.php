<?php

declare(strict_types=1);
namespace App\Domain\Form;
use App\Application\ValidationException;

/** @phpstan-type Field array{id:string,type:string,scope:string,label:string,help:string,unit:string,required:bool,analytics:bool,integer:bool,min:?string,max:?string,default:?string,maxLength:int,minSelections:int,maxSelections:int,mapping:?string,visibility:array{kind:string,fieldId?:string,value?:bool|string},options:list<array{id:string,label:string}>} */
final class FormSchema
{
    public const TYPES = ['number','checkbox','yes_no','single_select','multi_select','short_text','long_text','date','notice','section'];
    public const MAPPINGS = ['vegetarianMeals' => ['number','checkbox'], 'roomPreference' => ['single_select'], 'arrivalDate' => ['date']];
    public static function date(mixed $v): bool
    {
        if (!is_string($v)) { return false; }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return $d !== false && $d->format('Y-m-d') === $v;
    }
    /**
     * @param array<array-key,mixed> $f
     * @return array<array-key,mixed>
     */
    public static function signature(array $f): array { return [$f['type'] ?? null, $f['scope'], $f['unit'] ?? '', ($f['type'] ?? null) === 'number' ? $f['integer'] : null]; }
    /** @param Field $f */
    public static function answerField(array $f): bool { return !in_array($f['type'], ['notice','section'], true); }
    /**
     * @param array<array-key,mixed> $fields
     * @return list<Field>
     */
    public function validate(array $fields): array
    {
        $errors = []; $ids = []; $previous = []; $optionIds = []; $mappings = [];
        if (!array_is_list($fields) || count($fields) > 100) { throw new ValidationException(['fields' => 'list_max_100']); }
        foreach ($fields as $i => &$f) {
            $key = "fields.$i";
            if (!is_array($f)) { $errors[$key] = 'object_required'; continue; }
            $id = $f['id'] ?? '';
            if (!is_string($id) || !preg_match('/^f_[a-f0-9]{32}$/D', $id) || isset($ids[$id])) { $errors[$key . '.id'] = 'invalid_or_duplicate'; }
            else { $ids[$id] = true; }
            $type = $f['type'] ?? null; $scope = $f['scope'] ?? null;
            if (!in_array($type, self::TYPES, true)) { $errors[$key . '.type'] = 'invalid_type'; }
            if (!in_array($scope, ['booking','person'], true)) { $errors[$key . '.scope'] = 'invalid_scope'; }
            foreach (['label' => 200, 'help' => 2000, 'unit' => 50] as $name => $max) {
                if (!is_string($f[$name] ?? null) || mb_strlen($f[$name]) > $max || ($name === 'label' && trim($f[$name]) === '')) { $errors[$key . '.' . $name] = 'invalid_text'; }
            }
            foreach (['required','analytics','integer'] as $name) { if (!is_bool($f[$name] ?? null)) { $errors[$key . '.' . $name] = 'boolean_required'; } }
            if (in_array($type, ['notice','section'], true) && ($f['required'] || $f['analytics'] || $f['mapping'] !== null)) { $errors[$key] = 'display_field_has_no_answer'; }
            if (!in_array($type, ['number','date'], true) && ($f['min'] !== null || $f['max'] !== null)) { $errors[$key . '.min'] = 'bounds_not_allowed'; }
            if ($type !== 'number' && $f['default'] !== null) { $errors[$key . '.default'] = 'default_not_allowed'; }
            foreach (['maxLength','minSelections','maxSelections'] as $name) { if (!is_int($f[$name]) || $f[$name] < 0 || $f[$name] > 10000) { $errors[$key . '.' . $name] = 'invalid_integer_limit'; } }
            if ($type !== 'number' && $f['unit'] !== '') { $errors[$key . '.unit'] = 'only_number_has_unit'; }
            if ($type === 'number') {
                foreach (['min','max','default'] as $name) {
                    if ($f[$name] !== null) {
                        $v = Decimal::normalize($f[$name]);
                        if ($v === null || ($f['integer'] && str_contains($v, '.'))) { $errors[$key . '.' . $name] = 'decimal_or_integer_required'; }
                        else { $f[$name] = $v; }
                    }
                }
                if (!isset($errors[$key . '.min'], $errors[$key . '.max']) && $f['min'] !== null && $f['max'] !== null && Decimal::normalize($f['min']) !== null && Decimal::normalize($f['max']) !== null && Decimal::compare(self::text($f,'min'), self::text($f,'max')) > 0) { $errors[$key . '.max'] = 'below_min'; }
                if ($f['default'] !== null && !isset($errors[$key . '.default'])) {
                    foreach (['min','max'] as $name) {
                        if ($f[$name] !== null && Decimal::normalize($f[$name]) !== null && ($name === 'min' ? Decimal::compare(self::text($f,'default'), self::text($f,$name)) < 0 : Decimal::compare(self::text($f,'default'), self::text($f,$name)) > 0)) { $errors[$key . '.default'] = 'outside_range'; }
                    }
                }
            }
            if ($type === 'date') {
                foreach (['min','max'] as $name) { if ($f[$name] !== null && !self::date($f[$name])) { $errors[$key . '.' . $name] = 'date_required'; } }
                if ($f['min'] !== null && $f['max'] !== null && $f['min'] > $f['max']) { $errors[$key . '.max'] = 'below_min'; }
            }
            if (in_array($type, ['short_text','long_text'], true) && (!is_int($f['maxLength']) || $f['maxLength'] < 1 || $f['maxLength'] > 10000)) { $errors[$key . '.maxLength'] = 'integer_1_10000'; }
            if (!is_array($f['options']) || !array_is_list($f['options']) || count($f['options']) > 100) { $errors[$key . '.options'] = 'list_max_100'; }
            else {
                foreach ($f['options'] as $j => $o) {
                    if (!is_array($o) || !is_string($o['id'] ?? null) || !preg_match('/^o_[a-f0-9]{32}$/D', $o['id']) || isset($optionIds[$o['id']]) || !is_string($o['label'] ?? null) || trim($o['label']) === '' || mb_strlen($o['label']) > 200) { $errors[$key . ".options.$j"] = 'invalid_or_duplicate_option'; }
                    else { $optionIds[$o['id']] = true; }
                }
                if (in_array($type, ['single_select','multi_select'], true) && $f['options'] === []) { $errors[$key . '.options'] = 'options_required'; }
                if (!in_array($type, ['single_select','multi_select'], true) && $f['options'] !== []) { $errors[$key . '.options'] = 'options_not_allowed'; }
            }
            if ($type === 'multi_select' && (!is_int($f['minSelections']) || !is_int($f['maxSelections']) || $f['minSelections'] < 0 || $f['maxSelections'] < $f['minSelections'] || $f['maxSelections'] > count(is_array($f['options']) ? $f['options'] : []))) { $errors[$key . '.maxSelections'] = 'invalid_selection_bounds'; }
            $v = $f['visibility'];
            if (!is_array($v) || array_diff(array_keys($v), ['kind','fieldId','value']) !== [] || !in_array($v['kind'] ?? null, ['always','children','child','adult','selection'], true)) { $errors[$key . '.visibility'] = 'invalid_condition'; }
            elseif (in_array($v['kind'], ['child','adult'], true) && $scope !== 'person') { $errors[$key . '.visibility'] = 'person_scope_required'; }
            elseif ($v['kind'] === 'selection') {
                $source = is_string($v['fieldId'] ?? null) ? ($previous[$v['fieldId']] ?? null) : null;
                if ($source === null || !in_array($source['type'], ['single_select','multi_select','checkbox','yes_no'], true) || ($scope === 'booking' && $source['scope'] === 'person')) { $errors[$key . '.visibility'] = 'previous_compatible_selection_required'; }
                elseif (in_array($source['type'], ['checkbox','yes_no'], true) ? !is_bool($v['value'] ?? null) : !in_array($v['value'] ?? null, array_column(is_array($source['options']) ? $source['options'] : [], 'id'), true)) { $errors[$key . '.visibility'] = 'invalid_condition_value'; }
            }
            $mapping = $f['mapping'];
            if ($mapping !== null) {
                if (!is_string($mapping) || !isset(self::MAPPINGS[$mapping]) || !in_array($type, self::MAPPINGS[$mapping], true) || ($mapping === 'vegetarianMeals' && ($type === 'number' ? (!$f['integer'] || $scope !== 'booking' || $f['min'] === null || Decimal::normalize($f['min']) === null || Decimal::compare(self::text($f,'min'), '0') < 0) : $scope !== 'person'))) { $errors[$key . '.mapping'] = 'incompatible_mapping'; }
                if (is_string($mapping) && isset($mappings[$mapping])) { $errors[$key . '.mapping'] = 'duplicate_mapping_target'; }
                if (is_string($mapping)) { $mappings[$mapping] = true; }
            }
            if (is_string($id)) { $previous[$id] = $f; }
        }
        unset($f);
        if ($errors !== []) { throw new ValidationException($errors); }
        return array_map(self::definition(...), $fields);
    }

    /**
     * Structural validation also protects reads of persisted historical definitions.
     * @return Field
     */
    public static function definition(mixed $value): array
    {
        if (!is_array($value)) { throw new \UnexpectedValueException('Invalid form definition'); }
        $f = $value;
        $visibility = $f['visibility'] ?? null;
        if (!is_array($visibility) || !is_string($visibility['kind'] ?? null)
            || (isset($visibility['fieldId']) && !is_string($visibility['fieldId']))
            || (isset($visibility['value']) && !is_string($visibility['value']) && !is_bool($visibility['value']))) {
            throw new \UnexpectedValueException('Invalid form visibility');
        }
        $condition = ['kind'=>$visibility['kind']];
        if (isset($visibility['fieldId'])) { $condition['fieldId']=$visibility['fieldId']; }
        if (isset($visibility['value'])) { $condition['value']=is_bool($visibility['value']) ? $visibility['value'] : self::text($visibility,'value'); }
        $options = $f['options'] ?? null;
        if (!is_array($options) || !array_is_list($options)) { throw new \UnexpectedValueException('Invalid form options'); }
        $choices=[];
        foreach ($options as $o) {
            if (!is_array($o) || !is_string($o['id'] ?? null) || !is_string($o['label'] ?? null)) { throw new \UnexpectedValueException('Invalid form option'); }
            $choices[]=['id'=>$o['id'],'label'=>$o['label']];
        }
        // Accessors validate each column independently; checks in a foreach do not
        // communicate individual keys to callers of this method.
        return [
            'id'=>self::text($f,'id'),'type'=>self::text($f,'type'),'scope'=>self::text($f,'scope'),
            'label'=>self::text($f,'label'),'help'=>self::text($f,'help'),'unit'=>self::text($f,'unit'),
            'required'=>self::flag($f,'required'),'analytics'=>self::flag($f,'analytics'),'integer'=>self::flag($f,'integer'),
            'min'=>self::bound($f,'min'),'max'=>self::bound($f,'max'),'default'=>self::bound($f,'default'),'mapping'=>self::bound($f,'mapping'),
            'maxLength'=>self::limit($f,'maxLength'),'minSelections'=>self::limit($f,'minSelections'),'maxSelections'=>self::limit($f,'maxSelections'),
            'visibility'=>$condition,'options'=>$choices,
        ];
    }
    /** @param array<array-key,mixed> $f */
    private static function text(array $f,string $key): string { $v=$f[$key]??null; if(!is_string($v))throw new \UnexpectedValueException('Invalid form text'); return $v; }
    /** @param array<array-key,mixed> $f */
    private static function flag(array $f,string $key): bool { $v=$f[$key]??null; if(!is_bool($v))throw new \UnexpectedValueException('Invalid form flag'); return $v; }
    /** @param array<array-key,mixed> $f */
    private static function limit(array $f,string $key): int { $v=$f[$key]??null; if(!is_int($v))throw new \UnexpectedValueException('Invalid form limit'); return $v; }
    /** @param array<array-key,mixed> $f */
    private static function bound(array $f,string $key): ?string { $v=$f[$key]??null; if($v!==null&&!is_string($v))throw new \UnexpectedValueException('Invalid form bound'); return $v; }
}
