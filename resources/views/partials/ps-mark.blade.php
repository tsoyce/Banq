{{-- A33 · Марка платёжной системы (стилизованная, без картинок; A54 — или загруженный знак, $altMark — второй вариант). $ps — PaymentSystem, $c — классы размера/цвета. --}}
@if ($ps->logo){{-- A54 · загруженный в админке знак --}}<img src="{{ ($altMark ?? false) && $ps->logo_alt ? $ps->logo_alt : $ps->logo }}" alt="{{ $ps->name }}" class="ps-mark ps-logo {{ $c ?? '' }}" @isset($s) style="{{ $s }}" @endisset loading="lazy">
@else
@switch($ps->code)
    @case('VISA')<span class="ps-mark ps-visa {{ $c ?? '' }}" @isset($s) style="{{ $s }}" @endisset aria-label="Visa">VISA</span>@break
    @case('MASTERCARD')<span class="ps-mark ps-mc {{ $c ?? '' }}" @isset($s) style="{{ $s }}" @endisset aria-label="Mastercard"><i></i><i></i></span>@break
    @case('UNIONPAY')<span class="ps-mark ps-up {{ $c ?? '' }}" @isset($s) style="{{ $s }}" @endisset aria-label="UnionPay"><i></i><i></i><i></i></span>@break
    @default<span class="ps-mark ps-text {{ $c ?? '' }}" @isset($s) style="{{ $s }}" @endisset aria-label="{{ $ps->name }}">{{ $ps->mark() }}</span>
@endswitch
@endif
