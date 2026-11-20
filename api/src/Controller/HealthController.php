<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\DataServiceClient;

/** Separates process liveness from the MongoDB-backed readiness check. */
final readonly class HealthController
{
    public function __construct(private DataServiceClient $dataService) {}

    /** Reports PHP process liveness without depending on MongoDB. */
    public function health(Request $request): Response
    {
        return Response::json(200, ['status' => 'ok']);
    }

    /** Reports readiness only when the private database service responds. */
    public function ready(Request $request): Response
    {
        $this->dataService->request('GET', '/health');

        return Response::json(200, ['status' => 'ok']);
    }
}
