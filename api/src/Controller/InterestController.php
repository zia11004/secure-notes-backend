<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Services\InterestService;

/** Presents admin-only aggregation results without database logic. */
final readonly class InterestController
{
    public function __construct(private InterestService $interests) {}

    public function index(Request $request): Response
    {
        return Response::json(200, $this->interests->groups($request->queryParameters()));
    }

    public function members(Request $request): Response
    {
        return Response::json(200, $this->interests->members($request->routeParameter('interest'), $request->queryParameters()));
    }
}
