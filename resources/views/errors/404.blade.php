{{-- Shown instead of Laravel's plain "404 | Not Found" page when an address
     doesn't exist, e.g. a typo like /Cashier/dashboard (addresses are
     lowercase, and the Cashier's home is /cashier/pos). --}}
@extends('errors.layout')

@section('title', 'Page Not Found')
@section('code', '404')
@section('heading', 'Page not found')
@section('message', "The page you're looking for doesn't exist. Please check the address for typos, or go back to your home page.")
