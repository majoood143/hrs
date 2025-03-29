@extends('admin.layouts.master')

@section('content')
    <div class="page-body">
        <div class="container-xl">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Horse Gender</h3>
                    <div class="card-actions">
                        <a href="{{ route('admin.horse-gender.index') }}" class="btn btn-primary">
                            <!-- Download SVG icon from http://tabler-icons.io/i/plus -->
                            <i class="ti ti-arrow-left"></i>
                            Back
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.horse-gender.store') }}" method="post">
                        @csrf
                        <div class="form-group">
                            <div class="row">
                                <div class="col-md-6">
                                    <x-input-block name="en_name" placeholder="Enter the English Gender name"
                                        label="English Gender Name" />

                                </div>
                                <div class="col-md-6">

                                    <x-input-block name="ar_name" placeholder="Enter the AR Gender name"
                                        label="Arabic Gender Name" />
                                </div>
                            </div>
                            <div class="mb-3">
                                <button class="btn btn-primary"> 
                                    <i class="ti ti-database-plus"></i>
                                    create
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
