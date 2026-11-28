{{-- A46 · Флаг. $code — код флага/страны (KZ, eu, gb-sct) или $currency — код валюты (USD → флаг США).
     $round — кружок, $c — классы (размер), $fallback — буквы, если флага нет. Источник — App\Support\Flags. --}}
{!! \App\Support\Flags::img(isset($currency) ? \App\Support\Flags::forCurrency($currency) : ($code ?? null), $c ?? '', $round ?? false, $fallback ?? false, $title ?? null) !!}
