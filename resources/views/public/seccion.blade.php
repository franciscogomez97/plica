@extends('layouts.public')

@section('title', 'Ranking '.$seccion->nombre.' · '.$club->nombre)

@section('meta')
    <meta property="og:title" content="Ranking {{ $seccion->nombre }} · {{ $club->nombre }}">
    <meta property="og:description" content="{{ $grupo ? \App\Services\Compartir::resumen($grupo) : 'Todavía sin mangas celebradas.' }}{{ $temporada ? ' · '.$temporada->nombre : '' }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:type" content="website">
    @if ($grupo && $temporada)
        <meta property="og:image" content="{{ \App\Services\Podio::urlRanking($temporada, $seccion, $grupo) }}">
        <meta property="og:image:width" content="{{ \App\Services\Podio::ANCHO }}">
        <meta property="og:image:height" content="{{ \App\Services\Podio::ALTO }}">
        <meta name="twitter:card" content="summary_large_image">
    @elseif ($club->logoUrl())
        <meta property="og:image" content="{{ $club->logoUrl() }}">
        <meta name="twitter:card" content="summary">
    @endif
@endsection

@section('content')
    @include('public.partials.seccion-ranking')
@endsection
