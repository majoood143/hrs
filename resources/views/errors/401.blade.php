@extends('errors.layout')

@section('code', '401')
@section('title', __('errors.401.title'))
@section('heading', __('errors.401.heading'))
@section('description', __('errors.401.description'))

@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">{{ __('errors.back_home') }}</a>
@endsection
