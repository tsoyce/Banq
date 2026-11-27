<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Resources\PostResource;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Card;
use App\Models\CardTier;
use App\Models\DepositProduct;
use App\Models\PaymentSystem;
use App\Models\Post;
use App\Models\ServicePackage;
use App\Support\Blog\PostRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** A37 · Помощники редактора публикаций: загрузка картинок, поиск сущностей для связей и вставок, превью вставки. */
class BlogController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192']], [], ['image' => 'картинка']);

        return response()->json(['url' => PostResource::storeImage($request->file('image'), 'images')]);
    }

    /** Поиск по банкам, вкладам, картам, пакетам, платёжным системам и уровням. Ключ — «тип:slug». */
    public function lookup(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        $only = $request->query('type');
        $keys = array_filter(array_map('trim', explode(',', (string) $request->query('keys'))));
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q).'%';
        $items = collect();

        // Подписи для уже выбранных ключей (форма правки)
        foreach ($keys as $key) {
            if ($m = Post::resolveLink($key)) {
                $items->push($this->item(strtok($key, ':'), $m));
            }
        }
        if (mb_strlen($q) < 2) {
            return response()->json(['items' => $items->values()]);
        }

        $sources = [
            'bank' => fn () => Bank::query()->with('country')->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('short_name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('aliases', 'like', $like)),
            'deposit' => fn () => DepositProduct::query()->with('bank')->where(fn ($w) => $w->where('name', 'like', $like)->orWhereHas('bank', fn ($b) => $b->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('short_name', 'like', $like))),
            'card' => fn () => Card::query()->with('bank')->where(fn ($w) => $w->where('name', 'like', $like)->orWhereHas('bank', fn ($b) => $b->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('short_name', 'like', $like))),
            'package' => fn () => ServicePackage::query()->with('bank')->where(fn ($w) => $w->where('name', 'like', $like)->orWhereHas('bank', fn ($b) => $b->where('name', 'like', $like)->orWhere('code', 'like', $like))),
            'payment_system' => fn () => PaymentSystem::query()->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('short_name', 'like', $like)),
            'card_tier' => fn () => CardTier::query()->with('paymentSystem')->where('name', 'like', $like),
        ];
        foreach ($sources as $type => $query) {
            if ($only && $only !== $type) {
                continue;
            }
            foreach ($query()->limit(6)->get() as $m) {
                $items->push($this->item($type, $m));
            }
        }

        return response()->json(['items' => $items->unique('key')->values()]);
    }

    /** Превью вставки продукта в редакторе — та же карточка, что на сайте. */
    public function embed(Request $request, PostRenderer $renderer): Response
    {
        $html = $renderer->embed((string) $request->query('type'), (string) $request->query('slug'));

        return response($html ?: '<div class="embed-missing">Продукт скрыт или удалён — на сайте вставка не покажется</div>');
    }

    private function item(string $type, $m): array
    {
        $title = match ($type) {
            'bank', 'payment_system' => $m->displayName(),
            default => $m->name,
        };
        $sub = match ($type) {
            'bank' => trim(($m->country?->flag ?? '').' '.$m->code),
            'deposit', 'card', 'package' => $m->bank?->displayName(),
            'card_tier' => $m->paymentSystem?->displayName(),
            default => null,
        };

        return ['key' => $type.':'.$m->slug, 'type' => $type, 'label' => Post::LINK_LABELS[$type], 'title' => $title, 'sub' => $sub,
            'embeddable' => $type !== 'card_tier', 'cascade' => collect(Post::cascade($type, $m))->map(fn ($x) => Post::LINK_LABELS[$x[0]].': '.($x[0] === 'bank' || $x[0] === 'payment_system' ? $x[1]->displayName() : $x[1]->name))->unique()->values()];
    }
}
