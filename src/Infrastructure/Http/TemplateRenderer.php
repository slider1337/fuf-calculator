<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface;

final class TemplateRenderer
{
    public static function render(ResponseInterface $response, string $template, array $data = []): ResponseInterface
    {
        $path = __DIR__ . '/../../../templates/' . $template;
        extract($data, EXTR_SKIP);

        ob_start();
        require $path;
        $html = (string) ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
