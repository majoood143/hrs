@extends('errors.layout')

@section('code', '402')
@section('title', __('errors.402.title'))
@section('heading', __('errors.402.heading'))
@section('description', __('errors.402.description'))

@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">{{ __('errors.back_home') }}</a>
@endsection
