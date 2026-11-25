<!-- ========================================== -->
<!-- BODY KHU VỰC CHÍNH                         -->
<!-- ========================================== -->
<main class="flex-1 bg-white overflow-y-auto px-8 sm:px-12 lg:px-16 py-8">

  <!-- KHỐI NỘI DUNG TOÀN BODY CĂN GIỮA -->
  <div class="max-w-4xl mx-auto w-full space-y-6">

    <!-- TIÊU ĐỀ CHUNG TRÊN CÙNG -->
    <h2 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-gray-500">
      Các không gian làm việc của bạn
    </h2>

    <!-- TRƯỜNG HỢP 1: CHƯA CÓ WORKSPACE NÀO (và không đang xem workspace công khai của người khác) -->
    @if ($workspaces->isEmpty() && ! $currentWorkspace)
      <div class="space-y-4 pt-2">
        <div class="flex flex-wrap items-center gap-3">
          <span class="text-base text-gray-700">
            Bạn không phải là thành viên của bất kỳ không gian làm việc nào.
          </span>
          <button
            type="button"
            onclick="openModal('createWorkspaceModal')"
            class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-[#091E42] font-semibold text-sm transition cursor-pointer shadow-2xs"
          >
            <svg class="w-4 h-4 text-gray-700 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="16" rx="2" />
              <line x1="9" y1="4" x2="9" y2="20" />
              <line x1="15" y1="4" x2="15" y2="20" />
            </svg>
            <span>Tạo Không gian làm việc</span>
          </button>
        </div>
      </div>
    @elseif ($currentWorkspace)
      <!-- TRƯỜNG HỢP 2: ĐÃ CÓ WORKSPACE -->
      <div class="space-y-6">

        <!-- HÀNG TIÊU ĐỀ WORKSPACE -->
        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-gray-100">

          <!-- Bên trái: Avatar vuông bo góc + Tên workspace -->
          <div class="flex items-center space-x-3">
            <div class="w-8 h-8 rounded-md bg-[#AA6ADA] text-white flex items-center justify-center font-bold text-sm shrink-0 shadow-2xs">
              {{ mb_strtoupper(mb_substr($currentWorkspace->name ?? 'W', 0, 1, 'UTF-8'), 'UTF-8') }}
            </div>
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-base sm:text-lg font-bold text-[#091E42] tracking-tight">
                  {{ $currentWorkspace->name }}
                </h1>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-600">
                  {{ $currentWorkspace->visibility_label }}
                </span>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded bg-purple-50 text-[#AA6ADA]">
                  {{ $isOwner ? 'Chủ sở hữu' : ($currentRole?->label() ?? 'Khách (chỉ xem)') }}
                </span>
              </div>
              @if ($currentWorkspace->description)
                <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $currentWorkspace->description }}</p>
              @endif
            </div>
          </div>

          <!-- Bên phải: Các nút tiện ích -->
          <div class="flex items-center space-x-2">
            <button type="button" class="flex items-center space-x-1.5 px-3 py-1.5 rounded bg-gray-100 text-gray-800 text-xs font-semibold hover:bg-gray-200 transition cursor-pointer shadow-2xs">
              <svg class="w-3.5 h-3.5 text-gray-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="16" rx="2" />
                <line x1="9" y1="4" x2="9" y2="20" />
                <line x1="15" y1="4" x2="15" y2="20" />
              </svg>
              <span>Bảng</span>
            </button>

            @can('viewMembers', $currentWorkspace)
            <button type="button" onclick="openModal('membersModal')" class="flex items-center space-x-1.5 px-3 py-1.5 rounded hover:bg-gray-100 text-gray-700 text-xs font-medium transition cursor-pointer">
              <svg class="w-3.5 h-3.5 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M22 21v-2a4 4 0 00-3-3.87" />
                <path d="M16 3.13a4 4 0 010 7.75" />
              </svg>
              <span>Thành viên</span>
            </button>
            @endcan

            @can('update', $currentWorkspace)
              <button type="button" onclick="openModal('editWorkspaceModal')" class="flex items-center space-x-1.5 px-3 py-1.5 rounded hover:bg-gray-100 text-gray-700 text-xs font-medium transition cursor-pointer">
              <svg class="w-3.5 h-3.5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <span>Cài đặt</span>
            </button>
            @endcan

            @can('viewArchived', [\App\Models\Board::class, $currentWorkspace])
              <a href="{{ route('boards.archived', $currentWorkspace) }}" class="flex items-center space-x-1.5 px-3 py-1.5 rounded hover:bg-gray-100 text-gray-700 text-xs font-medium transition cursor-pointer">
                <svg class="w-3.5 h-3.5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
                <span>Lưu trữ</span>
              </a>
            @endcan
          </div>

        </div>

        <!-- LƯỚI DANH SÁCH BẢNG -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">

          @foreach ($currentWorkspace->boards as $board)
            @php $isStarred = in_array($board->id, $starredBoardIds, true); @endphp
            {{-- Thẻ bảng = <a> trỏ boards.show. Nút sao là form riêng nằm NGOÀI <a> (không lồng phần tử tương tác). --}}
            <div class="relative group h-26 transition-transform duration-200 hover:-translate-y-0.5">
              <a
                href="{{ route('boards.show', $board) }}"
                title="{{ $board->name }}"
                class="h-26 rounded-lg overflow-hidden border border-gray-200/80 hover:shadow-md transition-all duration-200 flex flex-col justify-between bg-white"
              >
                <!-- Nửa trên: Phông nền gradient/màu sắc -->
                <div
                  class="flex-1 w-full relative transition"
                  style="background: {{ \App\Support\BoardBackground::css($board->background) }};"
                >
                  <div class="absolute inset-0 bg-black/10 group-hover:bg-black/20 transition"></div>
                </div>

                <!-- Nửa dưới: Nền trắng in tên bảng -->
                <div class="h-9 px-3 flex items-center gap-1.5 bg-white border-t border-gray-100">
                  @if ($board->visibility === \App\Enums\BoardVisibility::Private->value)
                    <svg class="w-3 h-3 text-gray-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="Riêng tư">
                      <rect x="5" y="11" width="14" height="10" rx="2" />
                      <path d="M8 11V7a4 4 0 018 0v4" />
                    </svg>
                  @endif
                  <h4 class="text-xs sm:text-sm font-bold text-[#091E42] truncate">
                    {{ $board->name }}
                  </h4>
                </div>
              </a>

              <!-- Đánh dấu sao (BoardStarController) -->
              <form method="POST" action="{{ route('boards.star', $board) }}" class="absolute bottom-11 right-2">
                @csrf
                <button
                  type="submit"
                  class="transition cursor-pointer hover:text-yellow-300 {{ $isStarred ? 'text-yellow-300 opacity-100' : 'text-white opacity-0 group-hover:opacity-100 focus:opacity-100' }}"
                  title="{{ $isStarred ? 'Bỏ đánh dấu sao' : 'Đánh dấu sao' }}"
                >
                  <svg class="w-4 h-4" fill="{{ $isStarred ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                  </svg>
                </button>
              </form>
            </div>
          @endforeach

          @can('create', [\App\Models\Board::class, $currentWorkspace])
          <!-- Ô TẠO BẢNG MỚI (admin của workspace) -->
          <div
            onclick="openModal('createBoardModal')"
            class="h-26 rounded-lg bg-gray-100 hover:bg-gray-200 transition-all duration-200 flex items-center justify-center cursor-pointer p-4 text-center select-none shadow-2xs hover:shadow-xs hover:-translate-y-0.5"
          >
            <span class="text-xs sm:text-sm font-semibold text-gray-700">
              Tạo bảng mới
            </span>
          </div>
          @else
            @if ($currentWorkspace->boards->isEmpty())
              <p class="col-span-full text-sm text-gray-500">Chưa có bảng nào mà bạn có thể xem trong không gian làm việc này.</p>
            @endif
          @endcan

        </div>

      </div>
    @endif

  </div>

</main>

