{{-- A33 · Марка платёжной системы (стилизованная, без картинок). $ps — PaymentSystem, $c — классы размера/цвета. --}}
@switch($ps->code)
    @case('VISA')<span class="ps-mark ps-visa {{ $c ?? '' }}" @isset($s) style="{{ $s }}" @endisset aria-label="Visa">VISA</span>@break
    @case('MASTERCARD')<span class="ps-mark ps-mc {{ $c ?? '' }}" @isset($s) style="{{ $s }}" @endisset aria-label="Mastercard"><i></i><i></i></span>@break
    @case('UNIONPAY')<span class="ps-mark ps-up {{ $c ?? '' }}" @isset($s) style="{{ $s }}" @endisset aria-label="UnionPay"><i></i><i></i><i></i></span>@break
    @default<span class="ps-mark ps-text {{ $c ?? '' }}" @isset($s) style="{{ $s }}" @endisset aria-label="{{ $ps->name }}">{{ $ps->mark() }}</span>
@endswitch
