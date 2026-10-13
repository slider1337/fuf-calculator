<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{FormRepositoryInterface,EventRepositoryInterface,TripRepositoryInterface};
use App\Domain\Form\{FormSchema,AnswerValidator};

final readonly class FormService
{
    public function __construct(private FormRepositoryInterface $forms, private EventRepositoryInterface $events, private TripRepositoryInterface $trips, private AccessPolicy $policy, private FormSchema $schema, private AnswerValidator $answers) {}
    private function event(int $id, string $capability, bool $write = false): void
    {
        $this->policy->require('event', $id, $capability);
        $event = $this->events->get($id) ?? throw new NotFoundException('Event not found');
        if ($write && $event->archived) { throw new AccessException('event_archived', 409); }
    }
    private function version(int $eventId, int $id, string $capability, bool $write = false): array
    {
        $this->event($eventId,$capability,$write);
        $v = $this->forms->get($id);
        if ($v === null || $v['eventId'] !== $eventId) { throw new NotFoundException('Form not found'); }
        return $v;
    }
    private function revision(array $p, int $revision): void
    {
        if (!is_int($p['revision'] ?? null) || $p['revision'] !== $revision) { throw new AccessException('revision_conflict', 409); }
    }
    public function list(int $eventId): array
    {
        $this->event($eventId,'event.view');
        return $this->forms->list($eventId);
    }
    public function get(int $eventId, int $id): array { return $this->version($eventId,$id,'event.view'); }
    public function createDraft(int $eventId, array $p): array
    {
        return $this->policy->transaction(function () use ($eventId,$p) {
            $this->event($eventId,'form.manage',true);
            $versions = $this->forms->list($eventId);
            if (array_filter($versions, static fn ($v) => $v['status'] === 'draft')) { throw new AccessException('draft_exists',409); }
            $source = null;
            if (($p['basedOnId'] ?? null) !== null) {
                if (!is_int($p['basedOnId'])) { throw new ValidationException(['basedOnId' => 'integer_required']); }
                $source = $this->version($eventId,$p['basedOnId'],'form.manage');
                if ($source['status'] === 'draft') { throw new AccessException('published_source_required',409); }
            }
            $v = $this->forms->create($eventId,$source['id'] ?? null,$this->policy->actorId(),$source['fields'] ?? []);
            $this->policy->audit('form.draft','event',$eventId,'Entwurf id=' . $v['id']); return $v;
        });
    }
    public function save(int $eventId, int $id, array $p): array
    {
        return $this->policy->transaction(function () use ($eventId,$id,$p) {
            $v = $this->version($eventId,$id,'form.manage',true);
            if ($v['status'] !== 'draft') { throw new AccessException('immutable_form',409); }
            $this->revision($p,$v['revision']);
            if (!is_array($p['fields'] ?? null)) { throw new ValidationException(['fields' => 'list_required']); }
            $fields = $this->normalize($eventId,$p['fields']);
            $v = $this->forms->save($id,$v['revision'],$fields);
            $this->policy->audit('form.save','event',$eventId,'Entwurf id=' . $id . ' revision=' . $v['revision']); return $v;
        });
    }
    private function normalize(int $eventId, array $fields): array
    {
        if (!array_is_list($fields) || count($fields) > 100) { throw new ValidationException(['fields'=>'list_max_100']); }
        $known = [];
        foreach ($this->forms->list($eventId) as $v) { foreach ($v['fields'] as $f) { $known[$f['id']] ??= $f; } }
        $defaults = ['help'=>'','scope'=>'booking','required'=>false,'analytics'=>true,'unit'=>'','integer'=>true,'min'=>null,'max'=>null,'default'=>null,'maxLength'=>500,'minSelections'=>0,'maxSelections'=>1,'mapping'=>null,'visibility'=>['kind'=>'always'],'options'=>[]];
        $result = []; $remap = []; $seen = [];
        foreach ($fields as $i => $f) {
            if (!is_array($f)) { throw new ValidationException(["fields.$i"=>'object_required']); }
            if (array_diff(array_keys($f), [...array_keys($defaults),'id','type','label','semanticChange']) !== []) { throw new ValidationException(["fields.$i"=>'unknown_property']); }
            if (isset($f['semanticChange']) && !is_bool($f['semanticChange'])) { throw new ValidationException(["fields.$i.semanticChange"=>'boolean_required']); }
            $f += $defaults; $originalId = $f['id'] ?? null;
            if ($originalId !== null && (!is_string($originalId) || !isset($known[$originalId]))) { throw new ValidationException(["fields.$i.id"=>'unknown_field_id']); }
            if ($originalId !== null && isset($seen[$originalId])) { throw new ValidationException(["fields.$i.id"=>'duplicate_field_id']); }
            if ($originalId !== null) { $seen[$originalId] = true; }
            $old = $originalId === null ? null : $known[$originalId];
            $newIdentity = $old === null || ($f['semanticChange'] ?? false) || FormSchema::signature($f) !== FormSchema::signature($old);
            $f['id'] = $newIdentity ? 'f_' . bin2hex(random_bytes(16)) : $originalId;
            if ($originalId !== null && $newIdentity) { $remap[$originalId] = $f['id']; }
            if (!is_array($f['options']) || !array_is_list($f['options']) || count($f['options']) > 100) { throw new ValidationException(["fields.$i.options"=>'list_max_100']); }
            $seenOptions = [];
            $knownOptions = array_column($old['options'] ?? [],null,'id');
            foreach ($f['options'] as $j => &$option) {
                if (!is_array($option) || array_diff(array_keys($option), ['id','label','semanticChange']) !== [] || (isset($option['semanticChange']) && !is_bool($option['semanticChange']))) { throw new ValidationException(["fields.$i.options.$j"=>'invalid_option']); }
                $oid = $option['id'] ?? null;
                if ($oid !== null && (!is_string($oid) || !isset($knownOptions[$oid]))) { throw new ValidationException(["fields.$i.options.$j.id"=>'unknown_option_id']); }
                if ($oid !== null && isset($seenOptions[$oid])) { throw new ValidationException(["fields.$i.options.$j.id"=>'duplicate_option_id']); }
                if ($oid !== null) { $seenOptions[$oid] = true; }
                $option['id'] = $newIdentity || $oid === null || ($option['semanticChange'] ?? false) ? 'o_' . bin2hex(random_bytes(16)) : $oid;
                if ($oid !== null && $oid !== $option['id']) { $remap[$oid] = $option['id']; }
                unset($option['semanticChange']);
            }
            unset($option,$f['semanticChange']);
            if (in_array($f['type'] ?? null,['notice','section'],true)) { $f['analytics'] = false; $f['required'] = false; $f['mapping'] = null; }
            $result[] = $f;
        }
        foreach ($result as &$f) {
            if (is_array($f['visibility']) && ($f['visibility']['kind'] ?? null) === 'selection') {
                $fid = $f['visibility']['fieldId'] ?? null; $value = $f['visibility']['value'] ?? null;
                if (is_string($fid) && isset($remap[$fid])) { $f['visibility']['fieldId'] = $remap[$fid]; }
                if (is_string($value) && isset($remap[$value])) { $f['visibility']['value'] = $remap[$value]; }
            }
        }
        unset($f); return $this->schema->validate($result);
    }
    public function preview(int $eventId, int $id, array $p): array
    {
        $v = $this->version($eventId,$id,'event.view'); $context = $p['context'] ?? null;
        if (!is_array($context) || !is_array($p['answers'] ?? [])) { throw new ValidationException(['context'=>'object_required']); }
        if (isset($p['tripId'])) {
            if (!is_int($p['tripId'])) { throw new ValidationException(['tripId'=>'integer_required']); }
            $this->policy->trip($p['tripId'],'trip.view');
            if (($this->policy->metadata($p['tripId'])['event_id'] ?? null) !== $eventId) { throw new ValidationException(['tripId'=>'foreign_event']); }
            $trip = $this->trips->getById($p['tripId']) ?? throw new NotFoundException('Trip not found');
            $context['startDate'] = $trip->startDate()->format('Y-m-d'); $context['adultAgeThreshold'] = $trip->pricingPolicy()->adultAgeThreshold();
        }
        return ['versionId'=>$id,'revision'=>$v['revision']] + $this->answers->validate($this->schema->validate($v['fields']),$context,$p['answers'] ?? []);
    }
    public function publish(int $eventId, int $id, array $p): array
    {
        return $this->policy->transaction(function () use ($eventId,$id,$p) {
            $v = $this->version($eventId,$id,'form.publish',true); $this->revision($p,$v['revision']);
            if ($v['status'] !== 'draft') { throw new AccessException('immutable_form',409); }
            $this->schema->validate($v['fields']); $description = $this->policy->reason(is_string($p['changeDescription'] ?? null) ? $p['changeDescription'] : '');
            $v = $this->forms->publish($id,$v['revision'],$this->policy->actorId(),$description);
            $this->policy->audit('form.publish','event',$eventId,'version=' . $v['number'] . ' · ' . $description); return $v;
        });
    }
    public function archive(int $eventId, int $id, array $p): array
    {
        return $this->policy->transaction(function () use ($eventId,$id,$p) {
            $v = $this->version($eventId,$id,'form.publish',true); $this->revision($p,$v['revision']);
            if ($v['status'] !== 'published') { throw new AccessException('published_version_required',409); }
            $reason = $this->policy->reason(is_string($p['reason'] ?? null) ? $p['reason'] : '');
            $v = $this->forms->archive($id,$v['revision']); $this->policy->audit('form.archive','event',$eventId,'version=' . $v['number'] . ' · ' . $reason); return $v;
        });
    }
    public function compare(int $eventId, int $from, int $to): array { return $this->diff($this->version($eventId,$from,'event.view'),$this->version($eventId,$to,'event.view')); }
    private function diff(?array $from, array $to): array
    {
        $old = array_column($from['fields'] ?? [],null,'id'); $new = array_column($to['fields'],null,'id'); $rows = [];
        foreach (array_unique([...array_keys($new),...array_keys($old)]) as $id) {
            $a = $old[$id] ?? null; $b = $new[$id] ?? null;
            $positionBefore = $a === null ? null : array_search($id, array_keys($old), true);
            $positionAfter = $b === null ? null : array_search($id, array_keys($new), true);
            $change = $a === null ? 'added' : ($b === null ? 'removed' : ($a === $b && $positionBefore === $positionAfter ? 'unchanged' : 'changed'));
            $series = $a !== null && $b !== null && FormSchema::signature($a) === FormSchema::signature($b);
            $rows[] = ['fieldId'=>$id,'change'=>$change,'before'=>$a,'after'=>$b,'compatibleSeries'=>$series,'positionBefore'=>$positionBefore,'positionAfter'=>$positionAfter,'impact'=>match ($change) {'added'=>'not_asked_in_older_versions','removed'=>'historical_answers_preserved',default=>$series ? 'same_series' : 'separate_series'}];
        }
        return ['fromVersionId'=>$from['id'] ?? null,'toVersionId'=>$to['id'],'rows'=>$rows];
    }
    public function binding(int $tripId): array
    {
        $this->policy->trip($tripId,'trip.view');
        $binding = $this->forms->binding($tripId); $eventId = $this->policy->metadata($tripId)['event_id'] ?? null;
        $version = $binding['versionId'] === null ? null : $this->forms->get($binding['versionId']);
        // Trip access reads only its bound historical definition, not unrelated family drafts.
        $choices = $eventId === null || !$this->policy->can('trip',$tripId,'trip.manage') ? [] : array_values(array_filter($this->forms->list($eventId),static fn ($v)=>$v['status']==='published'));
        return $binding + ['version'=>$version,'availableVersions'=>$choices];
    }
    private function target(int $tripId, array $p): array
    {
        $this->policy->trip($tripId,'trip.manage',true); $binding = $this->forms->binding($tripId); $this->revision($p,$binding['revision']);
        if (!is_int($p['versionId'] ?? null)) { throw new ValidationException(['versionId'=>'integer_required']); }
        $target = $this->forms->get($p['versionId']);
        if ($target === null || $target['eventId'] !== ($this->policy->metadata($tripId)['event_id'] ?? null) || $target['status'] !== 'published') { throw new ValidationException(['versionId'=>'published_version_of_same_event_required']); }
        if ($binding['versionId'] === $target['id']) { throw new AccessException('version_already_active',409); }
        return [$binding,$target];
    }
    public function switchPreview(int $tripId, array $p): array
    {
        return $this->policy->transaction(function () use ($tripId,$p) {
            [$b,$v] = $this->target($tripId,$p);
            return $this->diff($b['versionId'] === null ? null : $this->forms->get($b['versionId']),$v) + ['revision'=>$b['revision'],'targetRevision'=>$v['revision'],'existingRegistrationCount'=>$this->policy->can('trip',$tripId,'registration.view') ? $this->forms->registrationCount($tripId) : null,'existingVersionBreakdownAvailable'=>false,'canRequestSupplement'=>$this->policy->can('trip',$tripId,'registration.manage')];
        });
    }
    public function switchVersion(int $tripId, array $p): array
    {
        return $this->policy->writeTrip($tripId,'trip.manage',function () use ($tripId,$p) {
            [$b,$v] = $this->target($tripId,$p);
            if (!is_int($p['targetRevision'] ?? null) || $p['targetRevision'] !== $v['revision']) { throw new AccessException('revision_conflict',409); }
            if (!is_bool($p['requestSupplement'] ?? null)) { throw new ValidationException(['requestSupplement'=>'boolean_required']); }
            if ($p['requestSupplement']) { $this->policy->require('trip',$tripId,'registration.manage'); }
            $reason = $this->policy->reason(is_string($p['reason'] ?? null) ? $p['reason'] : '');
            $result = $this->forms->switchVersion($tripId,$b['revision'],$v['id'],$this->policy->actorId(),$reason,$p['requestSupplement']);
            $this->policy->audit('trip.form.switch','trip',$tripId,'versionId=' . $v['id'] . ' · ' . $reason); return $result;
        });
    }
}
