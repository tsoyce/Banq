<?php

namespace App\Admin;

use App\Admin\Resources\BankResource;
use App\Admin\Resources\CardResource;
use App\Admin\Resources\CardTierResource;
use App\Admin\Resources\CountryResource;
use App\Admin\Resources\DepositProductResource;
use App\Admin\Resources\DepositRateResource;
use App\Admin\Resources\CommentResource;
use App\Admin\Resources\PaymentSystemResource;
use App\Admin\Resources\PostCategoryResource;
use App\Admin\Resources\PostResource;
use App\Admin\Resources\ServicePackageResource;
use App\Admin\Resources\UserResource;

/** A24 · Ресурсы админки по ключу URL. Порядок — порядок в меню. */
class ResourceRegistry
{
    /** @var array<string, resource> */
    private array $resources = [];

    public function __construct()
    {
        // A32 · карты, пакеты, платёжные системы и уровни
        foreach ([new CountryResource, new BankResource, new DepositProductResource, new DepositRateResource,
            new CardResource, new ServicePackageResource, new PaymentSystemResource, new CardTierResource, new UserResource,
            new PostResource, new PostCategoryResource, new CommentResource] as $r) { // A37 · блог
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
