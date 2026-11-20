<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\DataServiceClient;
use App\Support\Input;

/** Exposes paginated interest groups and their indexed member pages to admins. */
final readonly class InterestService
{
    public function __construct(private DataServiceClient $data) {}

    /** @param array<string, string> $query
     *  @return array<string, mixed>
     */
    public function groups(array $query): array
    {
        return $this->data->request('GET', '/interests', Input::pagination($query));
    }

    /** @param array<string, string> $query
     *  @return array<string, mixed>
     */
    public function members(string $interest, array $query): array
    {
        return $this->data->request('GET', '/interests/'.rawurlencode(Input::interest($interest)).'/users', Input::pagination($query));
    }
}
