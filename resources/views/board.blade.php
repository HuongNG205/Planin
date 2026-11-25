@extends('layouts.app')

@section('title', 'Planin - Không gian làm việc')

@section('sidebar')
    @include('board.sidebar')
@endsection

@section('content')
    @include('board.main')
@endsection

@section('modals')
    @include('board.modal-workspace')
    @if ($currentWorkspace)
        @can('viewMembers', $currentWorkspace)
            @include('board.modal-members')
        @endcan
        @can('update', $currentWorkspace)
            @include('board.modal-workspace-edit')
        @endcan
    @endif
    @if ($currentWorkspace)
        @can('create', [\App\Models\Board::class, $currentWorkspace])
            @include('board.modal-board')
        @endcan
    @endif
@endsection

{{-- JavaScript xử lý Modal và Dropdown --}}
@push('scripts')
    @include('board.scripts')
@endpush
