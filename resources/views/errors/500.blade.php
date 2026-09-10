@extends('errors.layout')

@section('code', '500')
@section('title', __('errors.500.title'))
@section('heading', __('errors.500.heading'))
@section('description', __('errors.500.description'))

@section('actions')
    <a href="javascript:window.location.reload()" class="btn btn-primary">{{ __('errors.try_again') }}</a>
    <a href="{{ url('/') }}" class="btn btn-secondary">{{ __('errors.back_home') }}</a>
@endsection
