<?php

namespace App\Services\Pixelforge;

use RuntimeException;

class PixelforgeApiException extends RuntimeException
{
    /** @param array<string, array<int, string>> $errors */
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        public readonly ?string $errorCode,
        public readonly ?string $retryAfter,
        public readonly ?string $parameter,
        public readonly array $errors,
    ) {
        parent::__construct($message, $httpStatus);
    }
}
