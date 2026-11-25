{{--
    POPUP MODAL SỬA BẢNG (+ lưu trữ).
    Chỉ include khi người dùng có quyền sửa bảng (can update, $board). Cần biến $board.
--}}
<div
  id="editBoardModal"
  class="hidden fixed inset-0 bg-black/40 backdrop-blur-2xs flex items-center justify-center p-4 z-50 overflow-y-auto"
  onclick="if(event.target === this) closeModal('editBoardModal')"
>
  <div class="bg-white rounded-xl border border-gray-200 w-full max-w-[330px] p-4 shadow-2xl relative my-auto text-[#091E42]">

    <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100">
      <h3 class="text-sm font-bold">Sửa bảng</h3>
      <button
        type="button"
        onclick="closeModal('editBoardModal')"
        class="text-gray-400 hover:text-gray-700 p-1 rounded transition cursor-pointer text-sm font-bold"
      >
        ✕
      </button>
    </div>

    <form id="editBoardForm" method="POST" action="{{ route('boards.update', $board) }}" class="space-y-4">
      @csrf
      @method('PUT')

      @if ($errors->updateBoard->any())
        <div class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700 space-y-0.5">
          @foreach ($errors->updateBoard->all() as $error)
            <p>{{ $error }}</p>
          @endforeach
        </div>
      @endif

      @include('board.partials.board-form-fields', [
        'prefix' => 'editBoard',
        'name' => old('name', $board->name),
        'background' => \App\Support\BoardBackground::css(old('background', $board->background)),
        'visibility' => \App\Enums\BoardVisibility::tryFrom((string) old('visibility', $board->visibility))?->value
            ?? \App\Enums\BoardVisibility::default()->value,
        'submitLabel' => 'Lưu thay đổi',
      ])
    </form>

    {{-- Lưu trữ: form riêng (không lồng trong form sửa). Khôi phục/xóa ở trang "Bảng đã lưu trữ". --}}
    @can('archive', $board)
      <form
        method="POST"
        action="{{ route('boards.archive', $board) }}"
        onsubmit="return confirm('Lưu trữ bảng này? Bảng sẽ ẩn khỏi danh sách; bạn có thể khôi phục trong mục Bảng đã lưu trữ.');"
        class="mt-4 pt-4 border-t border-gray-100"
      >
        @csrf
        <button
          type="submit"
          class="w-full py-2 rounded text-xs font-semibold transition cursor-pointer bg-gray-100 hover:bg-gray-200 text-gray-800"
        >
          Lưu trữ bảng
        </button>
      </form>
    @endcan

  </div>
</div>

@push('scripts')
  <script>
    @if ($errors->updateBoard->any())
      document.addEventListener('DOMContentLoaded', function () { openModal('editBoardModal'); });
    @endif
  </script>
@endpush
