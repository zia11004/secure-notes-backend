<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Config\AppConfig;
use App\Exception\ApiException;
use JsonException;

/** Calls the private Mongoose service without exposing its token to browsers. */
final readonly class DataServiceClient
{
    public function __construct(private AppConfig $config) {}

    /** @return array<string, mixed> The validated response from the private service. */
    public function request(string $method, string $path, array $payload = []): array
    {
        if (! str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, '://')) {
            throw new ApiException('INTERNAL_ERROR', 'Invalid internal service path.', 500);
        }

        $method = strtoupper($method);
        $url = $this->config->dataServiceUrl.$path;
        if ($method === 'GET' && $payload !== []) {
            $url .= '?'.http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-Service-Token: '.$this->config->dataServiceToken,
            ],
        ]);
        if (! in_array($method, ['GET', 'DELETE'], true)) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
        }

        $body = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if ($body === false) {
            throw new ApiException('DATA_SERVICE_UNAVAILABLE', 'The data service is unavailable.', 503);
        }
        if ($status === 204) {
            return [];
        }

        try {
            $result = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException('DATA_SERVICE_ERROR', 'The data service returned invalid JSON.', 502);
        }
        if (! is_array($result)) {
            throw new ApiException('DATA_SERVICE_ERROR', 'The data service returned an invalid response.', 502);
        }
        if ($status >= 200 && $status < 300) {
            return $result;
        }
        if (in_array($status, [400, 401, 403, 404, 409, 422], true) && is_array($result['error'] ?? null)) {
            $error = $result['error'];
            if (is_string($error['code'] ?? null) && is_string($error['message'] ?? null)) {
                throw new ApiException($error['code'], $error['message'], $status);
            }
        }

        throw new ApiException('DATA_SERVICE_UNAVAILABLE', 'The data service is unavailable.', 503);
    }
}
