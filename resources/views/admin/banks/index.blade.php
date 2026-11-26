@extends('layouts.admin', ['title' => 'Банки'])

@section('content')
<h1 class="font-display font-bold text-3xl">Банки <span class="faint text-xl">{{ $banks->count() }}</span></h1>
<div class="glass p-4 overflow-x-auto">
    <table class="table">
        <thead><tr><th>Банк</th><th>Код для Excel</th><th>Вкладов</th><th>Статус</th><th></th></tr></thead>
        <tbody>
        @forelse ($banks as $b)
            <tr>
                <td><div class="flex items-center gap-3"><span class="bank-mark" style="--c: {{ $b->brand_color ?? 'var(--a2)' }}">{{ \Illuminate\Support\Str::limit($b->code, 4, '') }}</span><div><p class="font-semibold">{{ $b->displayName() }}</p><p class="faint text-xs">{{ $b->name }}</p></div></div></td>
                <td class="font-mono text-sm">{{ $b->code }}</td>
                <td class="tabular">{{ $b->deposit_products_count }}</td>
                <td><span class="chip">{{ ['active' => 'работает', 'revoked' => 'лицензия отозвана', 'merged' => 'присоединён'][$b->status] ?? $b->status }}</span></td>
                <td class="text-right"><a href="{{ route('admin.banks.edit', $b) }}" class="btn btn-ghost !py-1.5 text-sm">Изменить</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted text-center py-8">Справочник пуст</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
