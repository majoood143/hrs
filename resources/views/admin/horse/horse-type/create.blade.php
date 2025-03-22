@extends('admin.layouts.master')

@section('content')
    <div class="page-body">
        <div class="container-xl">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Horse Type</h3>
                    <div class="card-actions">
                        <a href="{{ route('admin.horse-type.create') }}" class="btn btn-primary">
                            <!-- Download SVG icon from http://tabler-icons.io/i/plus -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                                viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                <path d="M12 5l0 14"></path>
                                <path d="M5 12l14 0"></path>
                            </svg>
                            Add new
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.horse-type.store') }}" method="post">
                        @csrf
                        <div class="form-group">
                            <div class="mb-3">
                                <label class="form-label">EN Name</label>
                                <input type="text" class="form-control" name="en_name" placeholder="Enter English Name">
                                <x-input-error :messages="$errors->get('en_name')" class="mt-2" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">AR Name</label>
                                <input type="text" class="form-control" name="ar_name" placeholder="Enter Arabic Name">
                                <x-input-error :messages="$errors->get('en_name')" class="mt-2" />
                            </div>
                            <div class="mb-3">
                                <button class="btn btn-primary"> 
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-database-plus">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M4 6c0 1.657 3.582 3 8 3s8 -1.343 8 -3s-3.582 -3 -8 -3s-8 1.343 -8 3" />
                                    <path d="M4 6v6c0 1.657 3.582 3 8 3c1.075 0 2.1 -.08 3.037 -.224" />
                                    <path d="M20 12v-6" />
                                    <path d="M4 12v6c0 1.657 3.582 3 8 3c.166 0 .331 -.002 .495 -.006" />
                                    <path d="M16 19h6" />
                                    <path d="M19 16v6" />
                                </svg>
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
