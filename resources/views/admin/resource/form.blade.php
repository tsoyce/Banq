{{-- A24 · Форма ресурса: поля по группам (карточки), подсказки, ошибки; справа — дополнительный блок (ставки вклада и т.п.). --}}
@extends('layouts.admin', ['title' => $model ? $resource->title($model) : 'Добавить '.$resource->singular()])

@php($aside = $model ? $resource->editAside($model) : null)

@section('content')
<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <a href="{{ route('admin.resource.index', $resource->key()) }}" class="muted text-sm inline-flex items-center gap-1 hover:text-[var(--text)]">@include('partials.icon', ['i' => 'back', 'c' => 'w-4 h-4']) {{ $resource->label() }}</a>
        <h1 class="font-display font-bold text-3xl mt-1">{{ $model ? $resource->title($model) : 'Добавить '.$resource->singular() }}</h1>
        @if ($model)<p class="faint text-xs mt-1">#{{ $model->getKey() }} @if ($model->updated_at)· изменено {{ $model->updated_at->diffForHumans() }}@endif</p>@endif
    </div>
    @if ($model && ($url = $resource->publicUrl($model)))<a href="{{ $url }}" target="_blank" class="btn btn-ghost !py-2.5 text-sm">@include('partials.icon', ['i' => 'external', 'c' => 'w-4 h-4']) На сайте</a>@endif
</header>

<div class="grid gap-6 {{ $aside ? 'xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]' : '' }} items-start">
    <form method="POST" action="{{ $model ? route('admin.resource.update', [$resource->key(), $model->getKey()]) : route('admin.resource.store', $resource->key()) }}" class="space-y-4 max-w-4xl" enctype="multipart/form-data" novalidate>
        @csrf
        @if ($model) @method('PUT') @endif

        @if ($errors->any())
            <div class="card p-4 text-sm error" role="alert"><p class="font-semibold mb-1">Проверь поля:</p><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @foreach ($resource->groups($model) as $group => $fields)
            <fieldset class="card p-6">
                <legend class="sr-only">{{ $group }}</legend>
                <p class="font-display font-bold text-lg mb-4">{{ $group }}</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach ($fields as $f)
                        @include('admin.resource.field', ['f' => $f, 'value' => $resource->value($model, $f, $defaults), 'resource' => $resource])
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <div class="card p-4 flex flex-wrap items-center gap-3 sticky bottom-4 z-10 shadow-lg">
            <button class="btn btn-primary">@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4']) Сохранить</button>
            @unless ($model)<button name="_then" value="new" class="btn btn-ghost">Сохранить и добавить ещё</button>@endunless
            <a href="{{ route('admin.resource.index', $resource->key()) }}" class="btn btn-ghost">Отмена</a>
        </div>
    </form>

    @if ($aside)<div class="space-y-4 min-w-0">{!! $aside !!}</div>@endif
</div>

@if ($model && $resource->canDelete())
    <form method="POST" action="{{ route('admin.resource.destroy', [$resource->key(), $model->getKey()]) }}" class="max-w-4xl" onsubmit="return confirm('Удалить безвозвратно?')">
        @csrf @method('DELETE')
        <button class="btn btn-outline !py-2 text-sm text-[var(--bad)]">@include('partials.icon', ['i' => 'trash', 'c' => 'w-4 h-4']) Удалить {{ $resource->singular() }}</button>
    </form>
@endif
@endsection
