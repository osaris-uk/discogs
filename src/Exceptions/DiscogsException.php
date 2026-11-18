<?php

namespace Osaris\Discogs\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class DiscogsException extends RuntimeException
{
    public ?Response $response = null;

    public static function fromResponse(Response $response): self
    {
        $status = $response->status();

        $message = $response->json('message');
        $message = is_string($message) && $message !== ''
            ? $message
            : 'Discogs API request failed with status '.$status.'.';

        $class = match (true) {
            $status === 401 => AuthenticationException::class,
            $status === 403 => ForbiddenException::class,
            $status === 404 => NotFoundException::class,
            $status === 409 => ConflictException::class,
            $status === 422 => ValidationException::class,
            $status === 429 => RateLimitException::class,
            default => self::class,
        };

        $exception = new $class($message, $status);
        $exception->response = $response;

        return $exception;
    }

    public function status(): ?int
    {
        return $this->response?->status();
    }
}
