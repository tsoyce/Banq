{{-- A24 · Одно поле формы ресурса. Имя с точкой (regulator.short) → name="regulator[short]". --}}
@php
    $type = $f['type'] ?? 'text';
    $dot = $f['name'];
    $name = str_contains($dot, '.') ? preg_replace('/\.([^.]+)/', '[$1]', $dot) : $dot;
    $id = 'f-'.str_replace('.', '-', $dot);
    $val = old($dot, $value);
    $required = in_array('required', $f['rules'] ?? [], true);
    $span = ($f['span'] ?? 1) === 2 || in_array($type, ['textarea', 'json'], true) ? 'sm:col-span-2' : '';
@endphp
@if ($type === 'bool')
    <label class="switch {{ $span }} self-end py-2" for="{{ $id }}">
        <span>{{ $f['label'] }}</span>
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1" class="sr-only" @checked((bool) $val)><i></i>
    </label>
@else
    <div class="field content-start {{ $span }}">
        <label for="{{ $id }}" class="text-[0.85rem] font-semibold">{{ $f['label'] }} @if ($required)<span class="text-[var(--bad)]" aria-hidden="true">*</span>@endif</label>
        @switch($type)
            @case('textarea')
                <textarea id="{{ $id }}" name="{{ $name }}" rows="4" class="input {{ $f['class'] ?? '' }}" placeholder="{{ $f['placeholder'] ?? '' }}">{{ $val }}</textarea>
                @break
            @case('json')
                <textarea id="{{ $id }}" name="{{ $name }}" rows="5" class="input font-mono text-sm" spellcheck="false" placeholder="{{ $f['placeholder'] ?? '{}' }}">{{ $val }}</textarea>
                @break
            @case('list')
                <textarea id="{{ $id }}" name="{{ $name }}" rows="3" class="input {{ $f['class'] ?? '' }}" placeholder="{{ $f['placeholder'] ?? '' }}">{{ $val }}</textarea>
                @break
            @case('select')
                <select id="{{ $id }}" name="{{ $name }}" class="input">
                    @unless ($required)<option value="">—</option>@endunless
                    @foreach ($resource->options($f['options']) as $v => $l)
                        @continue($v === '' && ! $required)
                        <option value="{{ $v }}" @selected((string) $val === (string) $v)>{{ $l }}</option>
                    @endforeach
                </select>
                @break
            @case('color')
                <div class="flex gap-2"><input type="color" class="input !p-1 h-12 w-16" value="{{ $val ?: '#7b5cff' }}" oninput="this.nextElementSibling.value = this.value" aria-label="{{ $f['label'] }}"><input id="{{ $id }}" name="{{ $name }}" class="input font-mono" value="{{ $val }}" placeholder="#7b5cff" oninput="if (/^#[0-9a-f]{6}$/i.test(this.value)) this.previousElementSibling.value = this.value"></div>
                @break
            @default
                <input id="{{ $id }}" name="{{ $name }}" value="{{ $val }}" class="input {{ $f['class'] ?? '' }}"
                       type="{{ ['number' => 'number', 'decimal' => 'number', 'date' => 'date', 'email' => 'email', 'url' => 'url', 'password' => 'password'][$type] ?? 'text' }}"
                       @if ($type === 'decimal') step="any" inputmode="decimal" @endif @if ($type === 'number') step="1" inputmode="numeric" @endif
                       @if ($type === 'password') autocomplete="new-password" @endif placeholder="{{ $f['placeholder'] ?? '' }}">
        @endswitch
        @if (! empty($f['help']))<p class="faint text-xs">{{ $f['help'] }}</p>@endif
        @error($dot)<p class="error text-xs">{{ $message }}</p>@enderror
    </div>
@endif
