{{-- A22 · Строки отчёта импорта: действие, ключ, дифф полей, причина. $rows, $entities --}}
@php($actionLabels = ['created' => ['новая', 'var(--ok)'], 'updated' => ['изменена', 'var(--brand-1)'], 'deleted' => ['удалена', 'var(--bad)'], 'unchanged' => ['без изменений', 'var(--faint)'], 'skipped' => ['пропущена', 'var(--warn)'], 'error' => ['ошибка', 'var(--bad)']])
<div x-data="{ show: 'changes' }" class="card overflow-hidden">
    <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-[var(--line)]">
        <p class="font-semibold mr-auto">Построчно</p>
        <div class="seg">
            <label><input type="radio" class="sr-only" x-model="show" value="changes">Изменения и ошибки</label>
            <label><input type="radio" class="sr-only" x-model="show" value="all">Все строки</label>
        </div>
    </div>
    <div class="overflow-x-auto max-h-[70vh]">
        <table class="table text-sm">
            <thead class="sticky top-0 bg-[var(--card)] z-[1]"><tr><th>Сущность</th><th>Строка</th><th>Что</th><th>Запись</th><th>Подробности</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr @if (in_array($r['action'], ['unchanged'], true)) x-show="show === 'all'" @endif>
                    <td class="whitespace-nowrap">{{ isset($entities[$r['entity']]) ? $entities[$r['entity']]->label() : $r['entity'] }}</td>
                    <td class="tabular faint">{{ $r['row'] }}</td>
                    <td><span class="chip !py-0 whitespace-nowrap" style="color: {{ $actionLabels[$r['action']][1] }}">{{ $actionLabels[$r['action']][0] }}</span></td>
                    <td class="font-semibold">{{ $r['key'] }}</td>
                    <td class="text-[13px]">
                        @if (! empty($r['message']))<p class="{{ $r['action'] === 'error' ? 'error' : 'muted' }}">{{ $r['message'] }}</p>@endif
                        @foreach ($r['changes'] ?? [] as $field => [$old, $new])
                            <p><span class="mono faint">{{ $field }}</span>
                                <s class="faint">{{ is_array($old) ? json_encode($old, JSON_UNESCAPED_UNICODE) : (is_bool($old) ? ($old ? 'да' : 'нет') : ($old ?? '—')) }}</s> →
                                <b>{{ is_array($new) ? json_encode($new, JSON_UNESCAPED_UNICODE) : (is_bool($new) ? ($new ? 'да' : 'нет') : ($new ?? '—')) }}</b></p>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted text-center py-8">Строк нет</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
