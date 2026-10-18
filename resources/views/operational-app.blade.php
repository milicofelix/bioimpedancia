@extends('layouts.react')

@push('head')
    <meta name="theme-color" content="#ad3f5b" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#722a3e" media="(prefers-color-scheme: dark)">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ricosty">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" sizes="180x180" href="/pwa/icons/apple-touch-icon.png">
@endpush

@section('content')
    <div id="app" data-page="operational-app" class="min-h-screen"></div>
@endsection
