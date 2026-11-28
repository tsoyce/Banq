<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\CodeSnippet;
use App\Models\Country;
use App\Services\Ads\Snippets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A50 · Сайт → Коды и счётчики: Google Analytics 4 / Google Ads, Tag Manager, Яндекс Метрика, AdSense — по идентификатору;
 * «Свой код» — HTML как есть. Только администратор: код выполняется у всех посетителей.
 */
class CodeSnippetResource extends Resource
{
    public function key(): string
    {
        return 'code-snippets';
    }

    public function label(): string
    {
        return 'Коды и счётчики';
    }

    public function singular(): string
    {
        return 'код';
    }

    public function model(): string
    {
        return CodeSnippet::class;
    }

    public function icon(): string
    {
        return 'code';
    }

    public function roles(): array
    {
        return ['admin'];
    }

    public function description(): ?string
    {
        return 'Метрики, теги и рекламные сети на всех страницах сайта (кроме админки). Для готовых сервисов достаточно идентификатора — код соберётся сам.';
    }

    public function search(): array
    {
        return ['name', 'tracking_id', 'code'];
    }

    public function defaultSort(): array
    {
        return ['sort', 'asc'];
    }

    public function filters(): array
    {
        return ['provider' => ['label' => 'Сервис', 'options' => CodeSnippet::PROVIDERS, 'apply' => fn ($q, $v) => $q->where('provider', $v)]];
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Название', 'sort' => 'name'],
            ['key' => 'provider', 'label' => 'Сервис', 'sort' => 'provider', 'render' => fn (CodeSnippet $s) => CodeSnippet::PROVIDERS[$s->provider] ?? $s->provider],
            ['key' => 'tracking_id', 'label' => 'ID / место', 'render' => fn (CodeSnippet $s) => self::html('<span class="font-mono text-xs">'.e($s->provider === 'custom' ? (CodeSnippet::PLACEMENTS[$s->placement] ?? $s->placement) : $s->tracking_id).'</span>')],
            ['key' => 'countries', 'label' => 'Страны', 'render' => fn (CodeSnippet $s) => $s->countries ? implode(', ', $s->countries) : 'все'],
            ['key' => 'skip_staff', 'label' => 'Без сотрудников', 'render' => fn (CodeSnippet $s) => self::yesNo($s->skip_staff)],
            ['key' => 'is_active', 'label' => 'Включён', 'sort' => 'is_active', 'render' => fn (CodeSnippet $s) => self::yesNo($s->is_active)],
        ];
    }

    public function fields(?Model $model = null): array
    {
        return [
            ['name' => 'name', 'label' => 'Название', 'rules' => ['required', 'string', 'max:120'], 'placeholder' => 'Google Analytics'],
            ['name' => 'provider', 'label' => 'Сервис', 'type' => 'select', 'options' => CodeSnippet::PROVIDERS, 'rules' => ['required'], 'default' => 'gtag'],
            ['name' => 'tracking_id', 'label' => 'Идентификатор', 'rules' => ['nullable', 'string', 'max:60'], 'class' => 'font-mono',
                'help' => 'GA4: G-XXXXXXX · Google Ads: AW-123456789 · Tag Manager: GTM-XXXXXX · Метрика: номер счётчика · AdSense: ca-pub-… (строка для ads.txt добавится сама)'],
            ['name' => 'placement', 'label' => 'Куда вставлять (для своего кода)', 'type' => 'select', 'options' => CodeSnippet::PLACEMENTS, 'rules' => ['required'], 'default' => 'head'],
            ['name' => 'code', 'label' => 'Свой код (HTML)', 'type' => 'textarea', 'class' => 'font-mono text-xs', 'rules' => ['nullable', 'string', 'max:20000'], 'span' => 2,
                'help' => 'Вставляется на каждую страницу как есть. Для готовых сервисов оставьте пустым.'],
            ['name' => 'countries', 'label' => 'Только в странах', 'type' => 'checks', 'options' => fn () => Country::query()->ordered()->pluck('name', 'code')->all(), 'group' => 'Где работает',
                'help' => 'Ничего не отмечено — во всех странах. Например, Метрика — только для России.'],
            ['name' => 'skip_staff', 'label' => 'Не загружать для сотрудников (админы и редакторы не портят статистику)', 'type' => 'bool', 'default' => true, 'group' => 'Где работает'],
            ['name' => 'is_active', 'label' => 'Включён', 'type' => 'bool', 'default' => true, 'group' => 'Где работает'],
            ['name' => 'sort', 'label' => 'Порядок', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:65000'], 'default' => 100, 'group' => 'Где работает'],
        ];
    }

    public function saving(Model $m, array $data, Request $request): void
    {
        if ($m->provider === 'custom') {
            if (trim((string) $m->code) === '') {
                throw ValidationException::withMessages(['code' => 'Вставьте код или выберите готовый сервис.']);
            }
            $m->tracking_id = null;

            return;
        }
        $id = trim((string) $m->tracking_id);
        if (in_array($m->provider, ['gtag', 'gtm', 'adsense'], true)) {
            $id = strtoupper($id);
            $id = $m->provider === 'adsense' ? 'ca-pub-'.preg_replace('/^(CA-)?PUB-/', '', $id) : $id;
        }
        if (! Snippets::validId($m->provider, $id)) {
            throw ValidationException::withMessages(['tracking_id' => 'Идентификатор не похож на '.CodeSnippet::PROVIDERS[$m->provider].'. Пример есть под полем.']);
        }
        $m->tracking_id = $id;
        $m->placement = 'head';
    }

    public function summary(): array
    {
        return ['Включено' => CodeSnippet::where('is_active', true)->count(), 'Всего' => CodeSnippet::count()];
    }
}
