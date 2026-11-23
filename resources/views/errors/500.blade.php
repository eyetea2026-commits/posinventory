@extends('errors.layout')

@section('title', 'Something Went Wrong')
@section('code', '500')
@section('heading', 'Something went wrong')
@section('message', 'The system ran into a problem while loading this page. Nothing you did caused it. Please try again in a moment. If it keeps happening, let your Administrator know.')

@section('actions')
    <a href="javascript:location.reload()" class="error-btn error-btn-primary">Try Again</a>
@endsection
