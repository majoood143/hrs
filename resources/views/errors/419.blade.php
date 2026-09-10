@extends('errors.layout')

@section('code', '419')
@section('title', __('errors.419.title'))
@section('heading', __('errors.419.heading'))
@section('description', __('errors.419.description'))

@section('actions')
    <a href="javascript:window.location.reload()" class="btn btn-primary">{{ __('errors.refresh') }}</a>
    <a href="{{ url('/') }}" class="btn btn-secondary">{{ __('errors.back_home') }}</a>
@endsection
