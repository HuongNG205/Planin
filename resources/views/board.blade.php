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
        @can('update', $currentWorkspace)
            @include('board.modal-workspace-edit')
        @endcan
    @endif
    @include('board.modal-board')
@endsection

{{-- JavaScript xử lý Modal và Dropdown --}}
@push('scripts')
    @include('board.scripts')
@endpush
