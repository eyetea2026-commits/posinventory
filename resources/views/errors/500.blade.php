{{-- Shown instead of Laravel's plain "500 | Server Error" page when something
     unexpected fails on the server (production only: with APP_DEBUG on,
     developers get the full error screen instead). The real error is still
     written to storage/logs/laravel.log. --}}
@extends('errors.layout')

@section('title', 'Something Went Wrong')
@section('code', '500')
@section('heading', 'Something went wrong')
@section('message', 'The system ran into a problem while loading this page. Nothing you did caused it. Please try again in a moment. If it keeps happening, let your Administrator know.')

@section('actions')
    <a href="javascript:location.reload()" class="error-btn error-btn-primary">Try Again</a>
@endsection
