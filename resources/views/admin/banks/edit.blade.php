@extends('layouts.admin', ['title' => $bank->displayName()])

@section('content')
<h1 class="font-display font-bold text-3xl">{{ $bank->displayName() }}</h1>
<form method="POST" action="{{ route('admin.banks.update', $bank) }}" class="glass p-6 grid sm:grid-cols-2 gap-4 max-w-3xl">
    @csrf @method('PUT')
    <label class="field"><span>Код для Excel</span><input name="code" class="input font-mono" value="{{ old('code', $bank->code) }}" required></label>
    <label class="field"><span>Короткое имя</span><input name="short_name" class="input" value="{{ old('short_name', $bank->short_name) }}"></label>
    <label class="field sm:col-span-2"><span>Полное название</span><input name="name" class="input" value="{{ old('name', $bank->name) }}" required></label>
    <label class="field sm:col-span-2"><span>Название на казахском</span><input name="name_kz" class="input" value="{{ old('name_kz', $bank->name_kz) }}"></label>
    <label class="field"><span>Сайт</span><input name="website" type="url" class="input" value="{{ old('website', $bank->website) }}"></label>
    <label class="field"><span>Фирменный цвет</span><input name="brand_color" type="color" class="input !p-1 h-12" value="{{ old('brand_color', $bank->brand_color ?? '#7b5cff') }}"></label>
    <label class="field"><span>Статус</span>
        <select name="status" class="input">
            @foreach (['active' => 'Работает', 'revoked' => 'Лицензия отозвана', 'merged' => 'Присоединён'] as $v => $l)<option value="{{ $v }}" @selected(old('status', $bank->status) === $v)>{{ $l }}</option>@endforeach
        </select></label>
    <label class="field sm:col-span-2"><span>Описание</span><textarea name="description" class="input">{{ old('description', $bank->description) }}</textarea></label>
    @if ($errors->any())<ul class="sm:col-span-2 error">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
    <div class="sm:col-span-2 flex gap-3"><a href="{{ route('admin.banks.index') }}" class="btn btn-ghost">Назад</a><button class="btn btn-primary">Сохранить</button></div>
</form>
@endsection
