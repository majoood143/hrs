@extends('errors.layout')

@section('code', '429')
@section('title', __('errors.429.title'))
@section('heading', __('errors.429.heading'))
@section('description', __('errors.429.description'))

@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">{{ __('errors.back_home') }}</a>
@endsection
