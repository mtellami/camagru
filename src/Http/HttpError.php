<?php

declare(strict_types=1);

namespace Camagru\Http;

final class HttpError extends \RuntimeException
{
    /** Carry an expected HTTP status, public message, and optional field errors. */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $fields = [],
    ) {
        parent::__construct($message);
    }
}
