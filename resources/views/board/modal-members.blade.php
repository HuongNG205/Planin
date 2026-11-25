{{-- Modal Thành viên. Chỉ include khi người xem là thành viên (@can('viewMembers')).
     Cần: $currentWorkspace, $members (đã sắp xếp, có ->pivot->role). --}}
@php
  $memberErrors = collect($errors->inviteMember->all())->merge($errors->updateMemberRole->all());
@endphp

<div
  id="membersModal"
  class="hidden fixed inset-0 bg-black/40 backdrop-blur-2xs flex items-center justify-center p-4 z-50 overflow-y-auto"
  onclick="if(event.target === this) closeModal('membersModal')"
>
  <div class="bg-white rounded-xl border border-gray-200 w-full max-w-lg p-5 shadow-2xl relative my-auto">
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-gray-100">
      <h3 class="text-base font-bold text-[#091E42]">Thành viên ({{ $members->count() }})</h3>
      <button type="button" onclick="closeModal('membersModal')" class="text-gray-400 hover:text-gray-700 p-1 cursor-pointer font-bold">✕</button>
    </div>

    @if ($memberErrors->isNotEmpty())
      <div class="mb-3 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700 space-y-0.5">
        @foreach ($memberErrors as $error)
          <p>{{ $error }}</p>
        @endforeach
      </div>
    @endif

    {{-- Mời thành viên: chỉ admin --}}
    @can('manageMembers', $currentWorkspace)
      <form method="POST" action="{{ route('workspaces.members.store', $currentWorkspace) }}" class="flex gap-2 mb-4">
        @csrf
        <input
          name="email" type="email" required maxlength="190" placeholder="Email người cần mời"
          value="{{ old('email') }}"
          class="flex-1 min-w-0 px-3 py-2 text-xs rounded-md border border-gray-300 focus:outline-none focus:border-[#AA6ADA]"
        />
        <select name="role" class="px-2 py-2 text-xs rounded-md border border-gray-300 bg-white cursor-pointer focus:outline-none focus:border-[#AA6ADA]">
          @foreach (\App\Enums\WorkspaceRole::options() as $value => $label)
            <option value="{{ $value }}" @selected(old('role', \App\Enums\WorkspaceRole::default()->value) === $value)>{{ $label }}</option>
          @endforeach
        </select>
        <button type="submit" class="px-3 py-2 rounded-md text-xs font-semibold bg-[#AA6ADA] hover:bg-[#9252c4] text-white transition cursor-pointer">Mời</button>
      </form>
    @endcan

    <ul class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
      @foreach ($members as $member)
        <li class="flex items-center gap-3 py-2.5">
          @if ($member->avatar_url)
            <img src="{{ $member->avatar_url }}" alt="" class="w-8 h-8 rounded-full object-cover shrink-0" />
          @else
            <div class="w-8 h-8 rounded-full bg-[#AA6ADA] text-white flex items-center justify-center text-xs font-bold shrink-0">
              {{ $member->initials }}
            </div>
          @endif

          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-[#091E42] truncate">
              {{ $member->name }}
              @if ($member->is(auth()->user())) <span class="font-normal text-gray-400">(Bạn)</span> @endif
            </p>
            <p class="text-[11px] text-gray-500 truncate">{{ $member->email }}</p>
          </div>

          @if ($currentWorkspace->isOwnedBy($member))
            <span class="text-[11px] font-semibold px-2 py-1 rounded bg-purple-50 text-[#AA6ADA]">Chủ sở hữu</span>
          @else
            @can('updateMemberRole', [$currentWorkspace, $member])
              <form method="POST" action="{{ route('workspaces.members.update', [$currentWorkspace, $member]) }}">
                @csrf
                @method('PUT')
                <select
                  name="role" onchange="this.form.submit()"
                  class="px-2 py-1 text-[11px] rounded border border-gray-300 bg-white cursor-pointer focus:outline-none focus:border-[#AA6ADA]"
                >
                  @foreach (\App\Enums\WorkspaceRole::options() as $value => $label)
                    <option value="{{ $value }}" @selected($member->pivot->role === $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </form>
            @else
              <span class="text-[11px] font-semibold px-2 py-1 rounded bg-gray-100 text-gray-600">
                {{ \App\Enums\WorkspaceRole::tryFrom($member->pivot->role)?->label() }}
              </span>
            @endcan

            @can('removeMember', [$currentWorkspace, $member])
              <form
                method="POST"
                action="{{ route('workspaces.members.destroy', [$currentWorkspace, $member]) }}"
                onsubmit="return confirm('Xóa thành viên này khỏi không gian làm việc? Họ cũng sẽ bị gỡ khỏi các thẻ được giao.');"
              >
                @csrf
                @method('DELETE')
                <button type="submit" class="text-gray-400 hover:text-red-600 p-1 cursor-pointer" title="Xóa khỏi không gian làm việc">✕</button>
              </form>
            @endcan
          @endif
        </li>
      @endforeach
    </ul>
  </div>
</div>

@if ($memberErrors->isNotEmpty() || session('open_modal') === 'membersModal')
  @push('scripts')
    <script>
      // Mở lại modal sau khi mời/đổi vai trò/xóa (thành công hoặc có lỗi).
      document.addEventListener('DOMContentLoaded', function () { openModal('membersModal'); });
    </script>
  @endpush
@endif
