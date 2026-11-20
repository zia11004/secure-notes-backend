<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Exception\ApiException;
use RuntimeException;

/** Shared, lock-protected fixed-window limiter for a single-VPS PHP deployment. */
final readonly class FileRateLimiter
{
    public function __construct(private string $directory, private int $perMinute) {}

    /** Uses a file lock so concurrent PHP workers share one request count. */
    public function check(string $clientIp): void
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Rate limit storage is unavailable.');
        }

        $handle = fopen($this->directory.'/'.hash('sha256', $clientIp).'.json', 'c+');
        if ($handle === false) {
            throw new RuntimeException('Rate limit storage is unavailable.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Rate limit storage lock failed.');
            }

            $now = time();
            $previous = json_decode(stream_get_contents($handle) ?: '', true);
            $start = is_array($previous) && is_int($previous['start'] ?? null) ? $previous['start'] : $now;
            $count = is_array($previous) && is_int($previous['count'] ?? null) ? $previous['count'] : 0;
            if ($now - $start >= 60) {
                $start = $now;
                $count = 0;
            }
            if ($count >= $this->perMinute) {
                throw new ApiException('RATE_LIMITED', 'Too many requests.', 429, [
                    'Retry-After' => (string) max(1, 60 - ($now - $start)),
                ]);
            }

            rewind($handle);
            $record = json_encode(['start' => $start, 'count' => $count + 1], JSON_THROW_ON_ERROR);
            if (! ftruncate($handle, 0)
                || fwrite($handle, $record) !== strlen($record)
                || ! fflush($handle)) {
                throw new RuntimeException('Rate limit storage write failed.');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
