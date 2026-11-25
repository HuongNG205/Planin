{{--
    Các trường dùng chung của form TẠO và form SỬA bảng: xem trước, phông nền, tiêu đề, quyền xem.
    Biến vào:
      $prefix      : 'createBoard' | 'editBoard' (tiền tố id phần tử; JS ở board/scripts.blade.php dùng cùng tiền tố)
      $name        : tên ban đầu
      $background  : phông nền ban đầu (đã qua BoardBackground::css)
      $visibility  : quyền xem ban đầu (giá trị BoardVisibility)
      $submitLabel : chữ trên nút gửi
    Danh sách phông nền lấy từ config('planin.board_backgrounds') (BoardBackground::presets()),
    danh sách quyền xem lấy từ enum BoardVisibility: không viết cứng lại ở view.
--}}
@php
  $presets = \App\Support\BoardBackground::presets();
  $gradientBackgrounds = array_filter($presets, fn ($label, $value) => str_starts_with($value, 'linear-gradient'), ARRAY_FILTER_USE_BOTH);
  $colorBackgrounds = array_diff_key($presets, $gradientBackgrounds);

  // Ý nghĩa 3 mức quyền xem (khớp BoardPolicy).
  $visibilityDescriptions = [
    'private' => 'Chỉ người tạo bảng và quản trị viên của Không gian làm việc xem được bảng này.',
    'workspace' => 'Tất cả thành viên của Không gian làm việc có thể xem bảng này.',
    'public' => 'Mọi người dùng đã đăng nhập đều có thể xem bảng này bằng liên kết (chỉ đọc).',
  ];
  $visibilityIcons = [
    'private' => '<rect x="5" y="11" width="14" height="10" rx="2" /><path d="M12 15v2" /><path d="M8 11V7a4 4 0 018 0v4" />',
    'workspace' => '<path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 00-3-3.87" /><path d="M16 3.13a4 4 0 010 7.75" />',
    'public' => '<circle cx="12" cy="12" r="10" /><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" /><path d="M2 12h20" />',
  ];
  $visibilityOptions = \App\Enums\BoardVisibility::options();
  $hasName = trim((string) $name) !== '';
@endphp

<input type="hidden" name="background" id="{{ $prefix }}BackgroundInput" value="{{ $background }}" />
<input type="hidden" name="visibility" id="{{ $prefix }}VisibilityInput" value="{{ $visibility }}" />

<!-- HỘP XEM TRƯỚC BẢNG -->
<div
  id="{{ $prefix }}PreviewBox"
  class="w-full h-32 rounded-lg p-2.5 flex flex-col justify-between transition-all duration-300 relative overflow-hidden"
  style="background: {{ $background }};"
>
  <div class="flex justify-center">
    <div class="bg-white/20 backdrop-blur-xs px-2 py-0.5 rounded flex items-center gap-1">
      <img
        src="/logo/planin-logo-board-transparent.svg"
        alt="Planin"
        class="h-3 w-auto brightness-0 invert"
      />
    </div>
  </div>

  <div class="grid grid-cols-3 gap-1.5 h-20 items-end">
    <div class="bg-white/90 rounded p-1 h-full flex flex-col gap-1 shadow-2xs">
      <div class="h-1.5 bg-gray-300 rounded w-8"></div>
      <div class="h-2.5 bg-gray-200 rounded w-full"></div>
      <div class="h-2.5 bg-gray-200 rounded w-full"></div>
    </div>
    <div class="bg-white/90 rounded p-1 h-full flex flex-col gap-1 shadow-2xs">
      <div class="h-1.5 bg-gray-300 rounded w-8"></div>
      <div class="h-2.5 bg-gray-200 rounded w-full"></div>
      <div class="h-2 bg-gray-200 rounded w-3/4"></div>
    </div>
    <div class="bg-white/90 rounded p-1 h-full flex flex-col gap-1 shadow-2xs">
      <div class="h-1.5 bg-gray-300 rounded w-8"></div>
      <div class="h-3 bg-gray-200 rounded w-full"></div>
    </div>
  </div>
</div>

<!-- MỤC CHỌN PHÔNG NỀN -->
<div>
  <label class="block text-xs font-semibold text-gray-700 mb-1.5">
    Phông nền
  </label>

  <!-- Hàng 1: phông nền gradient -->
  <div class="grid grid-cols-4 gap-1.5 mb-2">
    @foreach ($gradientBackgrounds as $value => $label)
      @php $selected = $background === $value; @endphp
      <div
        data-bg="{{ $value }}"
        title="{{ $label }}"
        onclick="selectBoardBackground(this, '{{ $prefix }}')"
        class="bg-picker-opt h-10 rounded cursor-pointer relative overflow-hidden transition hover:opacity-90 flex items-center justify-center border {{ $selected ? 'border-gray-900 ring-2 ring-purple-400' : 'border-transparent' }}"
        style="background: {{ $value }};"
      >
        <div class="check-icon {{ $selected ? '' : 'hidden ' }}w-4 h-4 rounded-full bg-white text-gray-900 flex items-center justify-center text-[10px] font-bold shadow-xs">
          ✓
        </div>
      </div>
    @endforeach
  </div>

  <!-- Hàng 2: màu đơn sắc -->
  <div class="grid grid-cols-4 gap-1.5">
    @foreach ($colorBackgrounds as $value => $label)
      @php $selected = $background === $value; @endphp
      <div
        data-bg="{{ $value }}"
        title="{{ $label }}"
        onclick="selectBoardBackground(this, '{{ $prefix }}')"
        class="bg-picker-opt h-8 rounded cursor-pointer relative overflow-hidden transition hover:opacity-90 flex items-center justify-center border {{ $selected ? 'border-gray-900 ring-2 ring-purple-400' : 'border-transparent' }}"
        style="background-color: {{ $value }};"
      >
        <div class="check-icon {{ $selected ? '' : 'hidden ' }}w-3.5 h-3.5 rounded-full bg-white text-gray-900 flex items-center justify-center text-[9px] font-bold">
          ✓
        </div>
      </div>
    @endforeach
  </div>
</div>

<!-- MỤC TIÊU ĐỀ BẢNG * -->
<div>
  <label class="block text-xs font-semibold text-gray-700 mb-1">
    Tiêu đề bảng <span class="text-red-500">*</span>
  </label>
  <input
    name="name"
    id="{{ $prefix }}NameInput"
    type="text"
    maxlength="150"
    value="{{ $name }}"
    oninput="updateBoardSubmitButton('{{ $prefix }}')"
    class="w-full px-3 py-1.5 text-xs rounded border {{ $hasName ? 'border-gray-300 focus:border-[#AA6ADA]' : 'border-red-500 focus:border-red-500' }} focus:outline-none transition bg-white"
    required
  />
  <p id="{{ $prefix }}NameRequiredMsg" class="{{ $hasName ? 'hidden ' : '' }}text-[11px] text-red-500 mt-1 font-medium">
    Tiêu đề bảng là bắt buộc
  </p>
</div>

<!-- MỤC QUYỀN XEM -->
<div class="relative">
  <label class="block text-xs font-semibold text-gray-700 mb-1">
    Quyền xem
  </label>

  <button
    type="button"
    onclick="toggleElement('{{ $prefix }}VisibilityDropdown')"
    class="w-full px-3 py-2 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50 flex items-center justify-between text-[#091E42] cursor-pointer transition"
  >
    <div class="flex items-center space-x-2">
      @foreach ($visibilityIcons as $type => $paths)
        <svg id="{{ $prefix }}VisIcon_{{ $type }}" class="{{ $visibility === $type ? '' : 'hidden ' }}w-4 h-4 text-gray-700 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $paths !!}</svg>
      @endforeach
      <span id="{{ $prefix }}CurrentVisibilityText" class="font-medium">{{ $visibilityOptions[$visibility] ?? $visibility }}</span>
    </div>
    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
  </button>

  <!-- Menu xổ xuống -->
  <div
    id="{{ $prefix }}VisibilityDropdown"
    class="board-vis-dropdown hidden absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl z-50 py-1 text-xs max-h-60 overflow-y-auto"
  >
    @foreach ($visibilityOptions as $type => $label)
      @php $active = $visibility === $type; @endphp
      <div
        data-label="{{ $label }}"
        onclick="selectBoardVisibility('{{ $prefix }}', '{{ $type }}', this)"
        class="vis-option p-2.5 hover:bg-gray-100 cursor-pointer transition flex items-start space-x-2.5 {{ $loop->first ? '' : 'border-t border-gray-100 ' }}{{ $active ? 'bg-purple-50 text-[#AA6ADA]' : 'text-gray-800' }}"
      >
        <svg class="vis-svg w-5 h-5 shrink-0 mt-0.5 {{ $active ? 'text-[#AA6ADA]' : 'text-gray-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $visibilityIcons[$type] ?? '' !!}</svg>
        <div>
          <p class="font-semibold text-xs">{{ $label }}</p>
          <p class="text-[10px] text-gray-500 leading-tight mt-0.5">
            {{ $visibilityDescriptions[$type] ?? '' }}
          </p>
        </div>
      </div>
    @endforeach
  </div>
</div>

<!-- NÚT GỬI -->
<div class="pt-2">
  <button
    type="submit"
    id="{{ $prefix }}SubmitBtn"
    class="w-full py-2.5 rounded text-xs font-semibold transition cursor-pointer bg-gray-100 text-gray-400 cursor-not-allowed"
    disabled
  >
    {{ $submitLabel }}
  </button>
</div>
