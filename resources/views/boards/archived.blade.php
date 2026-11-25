{{--
    TRANG BẢNG ĐÃ LƯU TRỮ (boards.archived): xem, khôi phục, xóa. Chỉ admin của workspace.
    Biến: $workspace, $boards (archivedBoards, đã eager load user).
--}}
@extends('layouts.app')

@section('title', 'Bảng đã lưu trữ | ' . $workspace->name)

@section('content')
  <main class="flex-1 bg-white overflow-y-auto px-8 sm:px-12 lg:px-16 py-8">
    <div class="max-w-4xl mx-auto w-full space-y-6">

      <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-gray-100">
        <div class="min-w-0">
          <a href="{{ route('workspaces.show', $workspace) }}" class="text-xs font-semibold text-[#AA6ADA] hover:underline">
            ← {{ $workspace->name }}
          </a>
          <h1 class="text-base sm:text-lg font-bold text-[#091E42] tracking-tight mt-1">Bảng đã lưu trữ</h1>
        </div>
      </div>

      @if ($boards->isEmpty())
        <p class="text-sm text-gray-500">Không có bảng nào đã lưu trữ.</p>
      @else
        <ul class="divide-y divide-gray-100 border border-gray-200 rounded-lg">
          @foreach ($boards as $board)
            <li class="flex flex-wrap items-center gap-3 px-4 py-3">
              <span class="w-12 h-8 rounded shrink-0 border border-gray-200/70" style="background: {{ \App\Support\BoardBackground::css($board->background) }};"></span>

              <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-[#091E42] truncate">{{ $board->name }}</p>
                <p class="text-xs text-gray-500">
                  Tạo bởi {{ $board->user?->name ?? 'không rõ' }}
                  · Lưu trữ lúc {{ $board->archived_at?->format('d/m/Y H:i') }}
                </p>
              </div>

              <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('boards.restore', $board) }}">
                  @csrf
                  <button type="submit" class="px-3 py-1.5 rounded bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-semibold transition cursor-pointer">
                    Khôi phục
                  </button>
                </form>

                <form
                  method="POST"
                  action="{{ route('boards.destroy', $board) }}"
                  onsubmit="return confirm('Xóa bảng này? Bảng sẽ không còn truy cập được.');"
                >
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="px-3 py-1.5 rounded text-red-600 hover:bg-red-50 text-xs font-semibold transition cursor-pointer">
                    Xóa
                  </button>
                </form>
              </div>
            </li>
          @endforeach
        </ul>
      @endif

    </div>
  </main>
@endsection

@push('scripts')
  @include('board.scripts')
@endpush
