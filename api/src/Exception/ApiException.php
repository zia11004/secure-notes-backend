<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/** An expected failure exposed through the public API's stable error contract. */
final class ApiException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }
}
