<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\EasyVereinReadClient;
use App\Infrastructure\EasyVereinData;

/** Read-only, bounded provider catalogue; no participants or private notes. */
final readonly class TermCatalogService
{
    public function __construct(private AccessPolicy $policy, private EasyVereinReadClient $client) {}
    public function search(string $resource, array $p, ?int $tripId = null): array
    {
        if ($tripId === null) { $this->policy->actorId(); }
        else { $this->policy->trip($tripId, 'term.manage'); $this->policy->requireRegistrationMode($tripId, 'easyverein'); }
        if (!in_array($resource, ['events', 'calendars'], true)) { throw new NotFoundException('Catalogue not found'); }
        $q = $p['q'] ?? ''; $offset = $p['offset'] ?? '0'; $selected = $p['selected'] ?? '';
        if (!is_string($q) || mb_strlen($q) > 200 || !is_string($offset) || !preg_match('/^(?:0|[1-9][0-9]{0,4})$/D', $offset) || (int)$offset > 10000 || !is_string($selected) || !preg_match('/^(?:[1-9][0-9]{0,18})?$/D', $selected)) { throw new ValidationException(['search' => 'invalid_query']); }
        $path = $resource === 'events' ? 'event' : 'calendar';
        $query = ['query' => $path === 'event' ? '{id,name,start,end,calendar,canceled}' : '{id,name}'];
        if ($selected !== '') {
            $rows = [$this->client->get($path.'/'.$selected.'/', $query)]; $more = false;
        } else {
            $query += ['limit' => 50, 'offset' => (int)$offset, 'ordering' => $path === 'event' ? 'start,id' : 'name,id'];
            if (trim($q) !== '') { $query['search'] = trim($q); }
            $from = $p['from'] ?? '';
            if (!is_string($from) || ($from !== '' && !\App\Domain\Form\FormSchema::date($from))) { throw new ValidationException(['from' => 'date_or_empty']); }
            if ($path === 'event' && $from !== '') { $query['start__gte'] = $from.'T00:00:00'; }
            $raw = $this->client->get($path.'/', $query);
            if (!is_array($raw['results'] ?? null) || !array_is_list($raw['results']) || count($raw['results']) > 50 || !array_key_exists('next', $raw) || (!is_string($raw['next']) && $raw['next'] !== null)) { throw new AccessException('provider_collection_invalid', 503); }
            $rows = $raw['results']; $more = !empty($raw['next']);
        }
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['name'] ?? null)) { throw new AccessException('provider_collection_invalid', 503); }
            $item = ['id' => EasyVereinData::id($row['id'] ?? null, $path), 'name' => $row['name']];
            if ($selected !== '' && $item['id'] !== $selected) { throw new AccessException('provider_reference_invalid', 503); }
            if ($path === 'event') {
                EasyVereinData::timestamp($row['start'] ?? null); EasyVereinData::timestamp($row['end'] ?? null);
                $item += ['start' => $row['start'], 'end' => $row['end'], 'calendarId' => EasyVereinData::id($row['calendar'] ?? null, 'calendar'), 'canceled' => ($row['canceled'] ?? false) === true];
            }
            $items[] = $item;
        }
        return ['items' => $items, 'nextOffset' => $more ? (int)$offset + 50 : null];
    }
}
