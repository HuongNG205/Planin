{{--
    POPUP MODAL TẠO BẢNG.
    Chỉ include khi người dùng có quyền tạo bảng (can create, [Board::class, $currentWorkspace]).
    Các trường dùng chung với modal sửa: board/partials/board-form-fields.
--}}
<div
  id="createBoardModal"
  class="hidden fixed inset-0 bg-black/40 backdrop-blur-2xs flex items-center justify-center p-4 z-50 overflow-y-auto"
  onclick="if(event.target === this) closeModal('createBoardModal')"
>
  <div class="bg-white rounded-xl border border-gray-200 w-full max-w-[330px] p-4 shadow-2xl relative">

    <!-- Header của Popup -->
    <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100">
      <button
        type="button"
        onclick="closeModal('createBoardModal')"
        class="text-gray-500 hover:text-gray-800 p-1 rounded transition cursor-pointer"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>

      <h3 class="text-sm font-bold text-[#091E42] text-center">
        Tạo bảng
      </h3>

      <button
        type="button"
        onclick="closeModal('createBoardModal')"
        class="text-gray-400 hover:text-gray-700 p-1 rounded transition cursor-pointer text-sm font-bold"
      >
        ✕
      </button>
    </div>

    {{-- workspace_id nằm trên URL (route lồng), không gửi qua input. --}}
    <form id="createBoardForm" method="POST" action="{{ route('boards.store', $currentWorkspace) }}" class="space-y-4">
      @csrf

      @if ($errors->createBoard->any())
        <div class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700 space-y-0.5">
          @foreach ($errors->createBoard->all() as $error)
            <p>{{ $error }}</p>
          @endforeach
        </div>
      @endif

      @include('board.partials.board-form-fields', [
        'prefix' => 'createBoard',
        'name' => old('name', ''),
        // Giá trị old() do người dùng gửi: luôn qua css() để chỉ in ra preset/mã màu hợp lệ.
        'background' => \App\Support\BoardBackground::css(old('background')),
        'visibility' => \App\Enums\BoardVisibility::tryFrom((string) old('visibility'))?->value
            ?? \App\Enums\BoardVisibility::default()->value,
        'submitLabel' => 'Tạo mới',
      ])
    </form>

  </div>
</div>

@if ($errors->createBoard->any())
  @push('scripts')
    <script>
      // Có lỗi validate: mở lại modal tạo bảng.
      document.addEventListener('DOMContentLoaded', function () { openModal('createBoardModal'); });
    </script>
  @endpush
@endif
