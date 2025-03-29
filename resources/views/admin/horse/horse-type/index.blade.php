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
                                @forelse ($horse_Types as $item)
                                    <tr>
                                        {{-- Table Items List --}}
                                        <td class="text-secondary">{{ $item->id }}</td>
                                        <td class="text-secondary">{{ $item->en_name }}</td>
                                        <td class="text-secondary">{{ $item->ar_name }}</td>
                                        {{-- Actions Buttons --}}
                                        <td>
                                            {{-- Edit Button --}}
                                            <a href="{{ route('admin.horse-type.edit', $item->id) }}"
                                                class="btn-sm btn-primary">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                            {{-- Delete Button --}}
                                            {{-- <a href="{{ route('admin.horse-type.create', $item->id) }}" --}}
                                            <a href="{{ route('admin.horse-type.destroy', $item->id) }}"
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
                    {{ $horse_Types->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
