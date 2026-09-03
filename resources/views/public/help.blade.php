@extends('public.layout')

@section('content')
    {{-- Makale DİLE GÖRE DOSYADAN gelir (`docs/89`): belge, arayüz etiketi
         değil. Başlık ve açıklama da makalenin kendi içinden. --}}
    @include($helpView)
@endsection
