<?php

declare(strict_types=1);

namespace App\Http;

/** Explicit HTTP response, encoded once at the outer application boundary. */
final readonly class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $status,
        public string $body,
        public array $headers = [],
    ) {}

    /** Serializes a JSON response with headers that prevent sensitive caching.
     *  @param array<string, mixed> $data
     *  @param array<string, string> $headers
     */
    public static function json(int $status, array $data, array $headers = []): self
    {
        return new self($status, json_encode($data, JSON_THROW_ON_ERROR), [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
            ...$headers,
        ]);
    }

    /** @param array<string, string> $headers Additional headers for this response. */
    public function withHeaders(array $headers): self
    {
        return new self($this->status, $this->body, [...$this->headers, ...$headers]);
    }

    /** Sends the prepared response from the single HTTP entry point. */
    public function send(): void
    {
        http_response_code($this->status);
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }
}
