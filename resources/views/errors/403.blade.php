@extends('errors.layout')

@section('code', '403')
@section('title', __('errors.403.title'))
@section('heading', __('errors.403.heading'))
@section('description', __('errors.403.description'))

@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">{{ __('errors.back_home') }}</a>
@endsection
