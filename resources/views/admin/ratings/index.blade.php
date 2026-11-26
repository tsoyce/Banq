{{-- A18 · Рейтинги в админке: три оценки по банкам, правка авторской, снимок. --}}
@extends('layouts.admin', ['title' => 'Рейтинги'])
@php use App\Enums\RatingKind; @endphp

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="font-display font-bold text-3xl">Рейтинги</h1>
        <p class="muted text-sm mt-1">Авторскую оценку ставите вы. Объективный считается из данных, народный — из одобренных отзывов. Снимок для ▲▼ сохраняется каждую ночь.</p>
    </div>
    <form method="POST" action="{{ route('admin.ratings.snapshot') }}">@csrf<button class="btn btn-ghost">Сохранить снимок сейчас</button></form>
</div>
<div class="card p-4 overflow-x-auto">
    <table class="table tabular">
        <thead><tr><th>Банк</th>@foreach (RatingKind::cases() as $k)<th class="!text-right">{{ $k->label() }}</th>@endforeach<th>Данные</th><th></th></tr></thead>
        <tbody>
        @foreach ($scores as $s)
            <tr>
                <td><div class="flex items-center gap-3"><span class="bank-mark" style="--c: {{ $s->bank->brand_color ?? 'var(--ink)' }}">{{ $s->bank->mark() }}</span><span class="font-semibold">{{ $s->bank->displayName() }}</span></div></td>
                <td class="text-right">@if ($s->author)<b>{{ score_format($s->author->score) }}</b><span class="block text-xs muted">{{ $s->author->period->format('d.m.Y') }}</span>@else<span class="faint">—</span>@endif</td>
                <td class="text-right">@if ($s->objective['has_data'])<b>{{ score_format($s->objective['score'], 2) }}</b><span class="block text-xs muted">#{{ $s->rank(RatingKind::Objective) }}</span>@else<span class="faint">нет ставок</span>@endif</td>
                <td class="text-right">@if ($s->people)<b>{{ score_format($s->people['score'], 2) }}</b><span class="block text-xs muted">{{ $s->people['count'] }} отз.</span>@else<span class="faint">—</span>@endif</td>
                <td class="text-xs muted">{{ $s->bank->assets_bn ? 'активы ✓' : 'нет активов' }} · {{ $s->bank->founded_year ? 'год ✓' : 'нет года' }}</td>
                <td class="text-right"><a href="{{ route('admin.ratings.edit', $s->bank) }}" class="btn btn-ghost !py-1.5 text-sm">Оценить</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
