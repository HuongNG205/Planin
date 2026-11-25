{{--
    TRANG BẢNG (boards.show). Hiện mới là KHUNG: tiêu đề, phông nền, quyền xem, nút gắn sao/sửa.
    Cột và thẻ (Vue) sẽ gắn vào vùng #board-canvas ở bước 7.
    Biến: $board (đã load workspace, user), $workspace, $isStarred.
--}}
@extends('layouts.board')

@section('title', $board->name . ' | Planin')

@section('content')
  <div class="h-full flex flex-col">

    <!-- THANH TIÊU ĐỀ BẢNG -->
    <div class="shrink-0 px-4 sm:px-6 py-3 bg-black/25 backdrop-blur-sm text-white flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-x-3 gap-y-1 min-w-0">
        <a
          href="{{ route('workspaces.show', $workspace) }}"
          class="p-1.5 rounded hover:bg-white/20 transition cursor-pointer"
          title="Về không gian làm việc &quot;{{ $workspace->name }}&quot;"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </a>

        <h1 class="text-lg font-bold truncate max-w-[60vw]">{{ $board->name }}</h1>

        @can('star', $board)
          <form method="POST" action="{{ route('boards.star', $board) }}">
            @csrf
            <button
              type="submit"
              class="p-1.5 rounded hover:bg-white/20 transition cursor-pointer {{ $isStarred ? 'text-yellow-300' : 'text-white' }}"
              title="{{ $isStarred ? 'Bỏ đánh dấu sao' : 'Đánh dấu sao' }}"
            >
              <svg class="w-4 h-4" fill="{{ $isStarred ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
              </svg>
            </button>
          </form>
        @endcan

        <span class="text-[11px] font-semibold px-2 py-0.5 rounded bg-white/20">{{ $board->visibility_label }}</span>
        <span class="text-xs text-white/80 truncate">
          {{ $workspace->name }} · Tạo bởi {{ $board->user?->name ?? 'không rõ' }}
        </span>
      </div>

      <div class="flex items-center gap-2">
        @can('update', $board)
          <button
            type="button"
            onclick="openModal('editBoardModal')"
            class="flex items-center space-x-1.5 px-3 py-1.5 rounded bg-white/20 hover:bg-white/30 text-xs font-semibold transition cursor-pointer"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            <span>Sửa bảng</span>
          </button>
        @endcan

        @can('restore', $board)
          <form method="POST" action="{{ route('boards.restore', $board) }}">
            @csrf
            <button type="submit" class="px-3 py-1.5 rounded bg-white text-[#091E42] hover:bg-gray-100 text-xs font-semibold transition cursor-pointer">
              Khôi phục bảng
            </button>
          </form>
        @endcan
      </div>
    </div>

    @if ($board->isArchived())
      <div class="shrink-0 px-4 sm:px-6 py-2 bg-yellow-100 text-yellow-900 text-xs font-medium">
        Bảng này đã được lưu trữ{{ $board->archived_at ? ' từ ' . $board->archived_at->format('d/m/Y') : '' }}. Hãy khôi phục để tiếp tục làm việc.
      </div>
    @endif

    <!-- VÙNG CỘT VÀ THẺ (bước 7 sẽ mount Vue ở đây) -->
    <div id="board-canvas" class="flex-1 min-h-0 overflow-x-auto p-4 sm:p-6">
      <div class="inline-block rounded-lg bg-white/80 backdrop-blur-sm px-4 py-3 text-sm text-gray-700 shadow-2xs">
        Khung bảng đã sẵn sàng. Các cột và thẻ sẽ hiển thị tại đây.
      </div>
    </div>

  </div>

  @can('update', $board)
    @include('board.modal-board-edit')
  @endcan
@endsection
