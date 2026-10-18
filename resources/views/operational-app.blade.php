@extends('layouts.react')

@push('head')
    <meta name="theme-color" content="#d88b9a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Ricosty">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/pwa/icons/apple-touch-icon.png">
@endpush

@section('content')
    <div id="app" data-page="operational-app" class="min-h-screen"></div>
@endsection
