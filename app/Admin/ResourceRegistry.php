<?php

namespace App\Admin;

use App\Admin\Resources\BankResource;
use App\Admin\Resources\CountryResource;
use App\Admin\Resources\DepositProductResource;
use App\Admin\Resources\DepositRateResource;
use App\Admin\Resources\UserResource;

/** A24 · Ресурсы админки по ключу URL. Порядок — порядок в меню. */
class ResourceRegistry
{
    /** @var array<string, resource> */
    private array $resources = [];

    public function __construct()
    {
        foreach ([new CountryResource, new BankResource, new DepositProductResource, new DepositRateResource, new UserResource] as $r) {
            $this->resources[$r->key()] = $r;
        }
    }

    /** @return array<string, resource> */
    public function all(): array
    {
        return $this->resources;
    }

    public function get(string $key): Resource
    {
        return $this->resources[$key] ?? abort(404);
    }

    public function keys(): array
    {
        return array_keys($this->resources);
    }
}
