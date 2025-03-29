@extends('admin.layouts.master')

@section('content')
    <div class="page-body">
        <div class="container-xl">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Country</h3>
                    <div class="card-actions">
                        <a href="{{ route('admin.country.create') }}" class="btn btn-primary">
                            <!-- Download SVG icon from http://tabler-icons.io/i/plus -->
                            <i class="ti ti-plus"></i>
                            Add new
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            {{-- Table Header --}}
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>EN - Name</th>
                                    <th>AR Name</th>

                                    <th>Action</th>
                                    <th class="w-1"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($country as $item)
                                    <tr>
                                        {{-- Table Items List --}}
                                        <td class="text-secondary">{{ $item->id }}</td>
                                        <td class="text-secondary">{{ $item->en_name }}</td>
                                        <td class="text-secondary">{{ $item->ar_name }}</td>

                                        {{-- Actions Buttons --}}
                                        <td>
                                            {{-- Regions Button --}}
                                            <a href="{{ route('admin.country.edit', $item->id) }}"
                                                class="btn-sm text-warning">
                                                <i class="ti ti-list"></i>
                                            </a>
                                             {{-- Edit Button --}}
                                             <a href="{{ route('admin.country.edit', $item->id) }}"
                                                class="btn-sm btn-primary">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                            {{-- Delete Button --}}
                                            {{-- <a href="{{ route('admin.country.create', $item->id) }}" --}}
                                            <a href="{{ route('admin.country.destroy', $item->id) }}"
                                                class="text-red delete-item">
                                                <i class="ti ti-trash-x"></i>
                                            </a>

                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">No Data Avialable</td>
                                    </tr>
                                @endforelse

                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $country->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
