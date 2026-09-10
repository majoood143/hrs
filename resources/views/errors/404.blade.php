@extends('errors.layout')

@section('code', '404')
@section('title', __('errors.404.title'))
@section('heading', __('errors.404.heading'))
@section('description', __('errors.404.description'))

@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">{{ __('errors.back_home') }}</a>
@endsection
