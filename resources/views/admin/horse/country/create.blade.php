@extends('admin.layouts.master')

@section('content')
    <div class="page-body">
        <div class="container-xl">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Country</h3>
                    <div class="card-actions">
                        <a href="{{ route('admin.country.index') }}" class="btn btn-primary">
                            <!-- Download SVG icon from http://tabler-icons.io/i/plus -->
                            <i class="ti ti-arrow-left"></i>
                            Back
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.country.store') }}" method="post">
                        @csrf
                        <div class="form-group">
                            <div class="row">
                                <div class="col-md-6">
                                    <x-input-block name="en_name" placeholder="Enter the Country name"
                                        label="Country Name" />

                                </div>
                                <div class="col-md-6">

                                    <x-input-block name="ar_name" placeholder="Enter the AR Country name"
                                        label="Arabic Country Name" />
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <x-input-toggle-block name="status" label="majid" />
                                    <x-input-file-block name="image" label="majid" />
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
