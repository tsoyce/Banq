<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Resource;
use App\Admin\ResourceRegistry;
use App\Http\Controllers\Controller;
use App\Models\DepositProduct;
use App\Services\Exchange\DataExchange;
use App\Services\Exchange\EntityRegistry;
use App\Services\Exchange\ImportOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A24 · Универсальный CRUD админки по описанию ресурса (App\Admin\Resource):
 * список (поиск, фильтры, сортировка, пагинация, массовые действия, экспорт), создание, правка, удаление.
 * Ставки создаются через движок обмена — правило «новая ставка закрывает старую» действует и здесь.
 */
class ResourceController extends Controller
{
    public function __construct(private ResourceRegistry $registry) {}

    private function resource(Request $request, string $key): Resource
    {
        $r = $this->registry->get($key);
        abort_unless(in_array($request->user()?->role, $r->roles(), true), 403);

        return $r;
    }

    private function find(Resource $r, string $id): Model
    {
        return ($r->model())::query()->findOrFail($id);
    }

    public function index(Request $request, string $key): View
    {
        $r = $this->resource($request, $key);
        $perPage = in_array((int) $request->query('per'), [25, 50, 100, 200], true) ? (int) $request->query('per') : 25;
        $items = $r->applyRequest($r->query(), $request)->paginate($perPage)->withQueryString();

        return view('admin.resource.index', [
            'resource' => $r, 'items' => $items, 'perPage' => $perPage, 'sort' => $r->sort($request),
            'activeFilters' => collect($r->filters())->keys()->filter(fn ($f) => $request->filled($f))->count() + ($request->filled('q') ? 1 : 0),
        ]);
    }

    public function create(Request $request, string $key): View
    {
        $r = $this->resource($request, $key);
        abort_unless($r->canCreate(), 403);

        return view('admin.resource.form', ['resource' => $r, 'model' => null, 'defaults' => $r->defaults($request)]);
    }

    public function store(Request $request, string $key): RedirectResponse
    {
        $r = $this->resource($request, $key);
        abort_unless($r->canCreate(), 403);
        $data = $request->validate($r->rules(null));

        if ($key === 'rates') {
            return $this->storeRate($request, $data);
        }

        $model = new ($r->model());
        $r->fill($model, $data);
        $r->saving($model, $data, $request);
        $model->save();

        return $this->afterSave($request, $r, $model, 'создан');
    }

    public function edit(Request $request, string $key, string $id): View
    {
        $r = $this->resource($request, $key);
        $model = $this->find($r, $id);
        abort_unless($r->canEdit($model), 404);

        return view('admin.resource.form', ['resource' => $r, 'model' => $model, 'defaults' => []]);
    }

    public function update(Request $request, string $key, string $id): RedirectResponse
    {
        $r = $this->resource($request, $key);
        $model = $this->find($r, $id);
        abort_unless($r->canEdit($model), 404);
        $data = $request->validate($r->rules($model));

        $r->fill($model, $data);
        $r->saving($model, $data, $request);
        $model->save();

        return $this->afterSave($request, $r, $model, 'сохранён');
    }

    public function destroy(Request $request, string $key, string $id): RedirectResponse
    {
        $r = $this->resource($request, $key);
        abort_unless($r->canDelete(), 403);
        $model = $this->find($r, $id);
        if ($model->is($request->user())) {
            return back()->withErrors(['bulk' => 'Себя удалить нельзя.']);
        }
        try {
            $model->delete();
        } catch (QueryException) {
            return back()->withErrors(['bulk' => 'Нельзя удалить: на запись ссылаются другие данные (например, у страны есть банки).']);
        }

        return redirect()->route('admin.resource.index', $key)->with('status', 'Удалено: '.$r->title($model));
    }

    /** Массовое действие над отмеченными (ids[]) или над всем, что попало в фильтр (all=1). */
    public function bulk(Request $request, string $key): RedirectResponse|Response
    {
        $r = $this->resource($request, $key);
        $actions = $r->bulkActions() + ($r->exchangeEntity() ? ['export' => ['label' => 'Экспорт']] : []);
        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys($actions))],
            'ids' => ['array'], 'ids.*' => ['integer'], 'all' => ['boolean'], 'format' => ['nullable', 'in:json,csv,xlsx'],
        ]);

        // all=1 — ровно тот запрос, что на экране (поиск и фильтры приходят из скрытых полей)
        $query = $request->boolean('all')
            ? $r->applyRequest($r->query(), $request->duplicate(query: $request->input('filters', [])))
            : $r->query()->whereKey($data['ids'] ?? []);
        $models = $query->limit(5000)->get();
        if ($models->isEmpty()) {
            return back()->withErrors(['bulk' => 'Ничего не выбрано.']);
        }

        if ($data['action'] === 'export') {
            $entity = app(EntityRegistry::class)->get($r->exchangeEntity());
            $format = app(DataExchange::class)->format($data['format'] ?? 'json');
            $body = $format->write([$entity->key() => $models->map(fn ($m) => $entity->toRow($m))->all()]);

            return response($body, 200, [
                'Content-Type' => $format->mime(),
                'Content-Disposition' => 'attachment; filename="'.$entity->key().'-'.now()->format('Ymd-His').'.'.$format->extension().'"',
            ]);
        }

        try {
            $message = ($actions[$data['action']]['run'])($models, $request);
        } catch (QueryException) {
            return back()->withErrors(['bulk' => 'Действие не выполнено: на часть записей ссылаются другие данные.']);
        }

        return back()->with('status', $message);
    }

    /** Новая ставка — через движок обмена: та же логика истории, что у импорта и API. */
    private function storeRate(Request $request, array $data): RedirectResponse
    {
        $product = DepositProduct::query()->findOrFail($data['deposit_product_id']);
        $report = app(DataExchange::class)->import(['deposit_rates' => [array_filter([
            'product' => $product->slug, 'currency' => $data['currency'] ?? null, 'term_months' => $data['term_months'] ?? null,
            'amount_from' => $data['amount_from'] ?? null, 'amount_to' => $data['amount_to'] ?? null, 'rate' => $data['rate'],
            'effective_rate' => $data['effective_rate'] ?? null, 'valid_from' => $data['valid_from'] ?? null, 'source' => 'manual',
        ], fn ($v) => $v !== null && $v !== '')]], new ImportOptions(source: 'admin', userId: $request->user()->id, filename: 'admin-form', format: 'form'));

        $row = $report->rows[0] ?? null;
        if (($row['action'] ?? null) === 'error') {
            return back()->withInput()->withErrors(['rate' => $row['message']]);
        }
        $msg = match ($row['action'] ?? null) {
            'updated' => 'Новая ставка сохранена, прежняя закрыта — история сохранена.',
            'unchanged' => 'Такая ставка уже действует — ничего не изменилось.',
            default => 'Ставка добавлена.',
        };
        $back = $request->input('_back');

        return ($back && str_starts_with($back, url('/admin')) ? redirect($back) : redirect()->route('admin.resource.edit', ['deposits', $product->id]))->with('status', $msg);
    }

    private function afterSave(Request $request, Resource $r, Model $model, string $verb): RedirectResponse
    {
        $msg = ($verb === 'создан' ? 'Добавлено' : 'Сохранено').": «{$r->title($model)}»";

        return $request->input('_then') === 'new'
            ? redirect()->route('admin.resource.create', $r->key())->with('status', $msg)
            : redirect()->route('admin.resource.edit', [$r->key(), $model->getKey()])->with('status', $msg);
    }
}
