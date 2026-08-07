@extends('layouts.react')

@section('content')
    <div
        id="app"
        data-page="dashboard"
        data-user-name="{{ auth()->user()->name }}"
        class="min-h-screen"
    ></div>
@endsection