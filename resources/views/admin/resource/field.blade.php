{{-- A24 · Одно поле формы ресурса. Имя с точкой (regulator.short) → name="regulator[short]". --}}
@php
    $type = $f['type'] ?? 'text';
    $dot = $f['name'];
    $name = str_contains($dot, '.') ? preg_replace('/\.([^.]+)/', '[$1]', $dot) : $dot;
    $id = 'f-'.str_replace('.', '-', $dot);
    $val = old($dot, $value);
    $required = in_array('required', $f['rules'] ?? [], true);
    $span = ($f['span'] ?? 1) === 2 || in_array($type, ['textarea', 'json', 'checks'], true) ? 'sm:col-span-2' : '';
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
            @case('checks')
                {{-- A32 · набор флажков: name[]; опции с «группа|подпись» группируются --}}
                @php($groups = collect($resource->options($f['options']))->groupBy(fn ($l) => str_contains($l, '|') ? \Illuminate\Support\Str::before($l, '|') : '', true))
                <div class="grid gap-3">
                    @foreach ($groups as $g => $opts)
                        <div class="flex flex-wrap gap-2 items-center">
                            @if ($g !== '')<span class="text-xs font-semibold faint w-full sm:w-28 shrink-0">{{ $g }}</span>@endif
                            @foreach ($opts as $v => $l)
                                <label class="check-pill">
                                    <input type="checkbox" name="{{ $name }}[]" value="{{ $v }}" class="sr-only" @checked(in_array((string) $v, array_map('strval', (array) $val), true))>
                                    {{ \Illuminate\Support\Str::after($l, '|') }}
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
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
            @case('image')
                {{-- A54 · картинка: загрузить файл (SVG/PNG/WebP) или указать путь/URL; пусто — рисунок по умолчанию --}}
                <div class="flex items-center gap-3" x-data="{ src: @js($val), pick(e) { const f = e.target.files[0]; if (f) this.src = URL.createObjectURL(f) } }">
                    <span class="grid place-items-center w-20 h-14 rounded-xl bg-[var(--card-2)] border border-[var(--line)] overflow-hidden shrink-0">
                        <img x-show="src" :src="src" alt="" class="max-w-full max-h-full object-contain" @if (! $val) x-cloak @endif>
                        <span x-show="!src" class="text-[10px] faint text-center leading-tight px-1">по умол&shy;чанию</span>
                    </span>
                    <div class="grid gap-2 flex-1 min-w-0">
                        <input type="file" name="{{ $name }}__upload" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/webp,image/jpeg" class="text-sm" @change="pick($event)">
                        <input id="{{ $id }}" name="{{ $name }}" value="{{ $val }}" class="input !py-2 text-sm font-mono" placeholder="/uploads/… или https://…" @input="src = $event.target.value">
                    </div>
                </div>
                @error($dot.'__upload')<p class="error text-xs">{{ $message }}</p>@enderror
                @break
            @case('color')
                <div class="flex gap-2"><input type="color" class="input !p-1 h-12 w-16" value="{{ $val ?: '#7b5cff' }}" oninput="this.nextElementSibling.value = this.value" aria-label="{{ $f['label'] }}"><input id="{{ $id }}" name="{{ $name }}" class="input font-mono" value="{{ $val }}" placeholder="#7b5cff" oninput="if (/^#[0-9a-f]{6}$/i.test(this.value)) this.previousElementSibling.value = this.value"></div>
                @break
            @default
                <input id="{{ $id }}" name="{{ $name }}" value="{{ $val }}" class="input {{ $f['class'] ?? '' }}"
                       type="{{ ['number' => 'number', 'decimal' => 'number', 'date' => 'date', 'datetime' => 'datetime-local', 'email' => 'email', 'url' => 'url', 'password' => 'password'][$type] ?? 'text' }}"
                       @if ($type === 'decimal') step="any" inputmode="decimal" @endif @if ($type === 'number') step="1" inputmode="numeric" @endif
                       @if ($type === 'password') autocomplete="new-password" @endif placeholder="{{ $f['placeholder'] ?? '' }}">
        @endswitch
        @if (! empty($f['help']))<p class="faint text-xs">{{ $f['help'] }}</p>@endif
        @error($dot)<p class="error text-xs">{{ $message }}</p>@enderror
    </div>
@endif
