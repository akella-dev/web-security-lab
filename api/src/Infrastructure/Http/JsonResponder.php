<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface;

final class JsonResponder
{
    /** @param mixed $payload */
    public static function write(ResponseInterface $response, mixed $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}
