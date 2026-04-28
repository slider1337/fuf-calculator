<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use JsonException;
use Psr\Http\Message\ResponseInterface;

final class JsonResponder
{
    /**
     * @throws JsonException
     */
    public static function write(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($data, JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}

