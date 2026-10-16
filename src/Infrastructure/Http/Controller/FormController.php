<?php

declare(strict_types=1);
namespace App\Infrastructure\Http\Controller;
use App\Application\FormService;
use App\Infrastructure\Http\JsonResponder;
use Psr\Http\Message\{ResponseInterface as Response,ServerRequestInterface as Request};

final readonly class FormController
{
    public function __construct(private FormService $forms) {}
    public function list(Request $request, Response $response, string $eventId): Response { return JsonResponder::write($response,$this->forms->list((int) $eventId)); }
    public function create(Request $request, Response $response, string $eventId): Response { return JsonResponder::write($response,$this->forms->createDraft((int) $eventId,(array) $request->getParsedBody()),201); }
    public function get(Request $request, Response $response, string $eventId, string $versionId): Response { return JsonResponder::write($response,$this->forms->get((int) $eventId,(int) $versionId)); }
    public function save(Request $request, Response $response, string $eventId, string $versionId): Response { return JsonResponder::write($response,$this->forms->save((int) $eventId,(int) $versionId,(array) $request->getParsedBody())); }
    public function preview(Request $request, Response $response, string $eventId, string $versionId): Response
    {
        $result = $this->forms->preview((int) $eventId,(int) $versionId,(array) $request->getParsedBody());
        $output=$result;
        $output['errors'] = (object) $result['errors'];
        $output['answers']=['booking'=>(object)$result['answers']['booking'],'persons'=>(object)array_map(static fn(array $values): object=>(object)$values,$result['answers']['persons'])];

        return JsonResponder::write($response,$output);
    }
    public function publish(Request $request, Response $response, string $eventId, string $versionId): Response { return JsonResponder::write($response,$this->forms->publish((int) $eventId,(int) $versionId,(array) $request->getParsedBody())); }
    public function archive(Request $request, Response $response, string $eventId, string $versionId): Response { return JsonResponder::write($response,$this->forms->archive((int) $eventId,(int) $versionId,(array) $request->getParsedBody())); }
    public function compare(Request $request, Response $response, string $eventId): Response
    {
        $q = $request->getQueryParams();
        foreach (['from','to'] as $key) { if (!isset($q[$key]) || !ctype_digit((string) \App\Application\Input::scalar($q[$key]))) { throw new \App\Application\ValidationException([$key=>'version_id_required']); } }
        return JsonResponder::write($response,$this->forms->compare((int) $eventId,(int) \App\Application\Input::scalar($q['from']),(int) \App\Application\Input::scalar($q['to'])));
    }
    public function binding(Request $request, Response $response, string $id): Response { return JsonResponder::write($response,$this->forms->binding((int) $id)); }
    public function switchPreview(Request $request, Response $response, string $id): Response { return JsonResponder::write($response,$this->forms->switchPreview((int) $id,(array) $request->getParsedBody())); }
    public function switchVersion(Request $request, Response $response, string $id): Response { return JsonResponder::write($response,$this->forms->switchVersion((int) $id,(array) $request->getParsedBody())); }
}
