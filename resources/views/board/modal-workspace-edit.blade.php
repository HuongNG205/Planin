{{-- Modal sửa + xóa workspace. Chỉ include khi người dùng là chủ sở hữu (@can('update')). --}}
<div
  id="editWorkspaceModal"
  class="hidden fixed inset-0 bg-black/40 backdrop-blur-2xs flex items-center justify-center p-4 z-50 overflow-y-auto"
  onclick="if(event.target === this) closeModal('editWorkspaceModal')"
>
  <div class="bg-white rounded-xl border border-gray-200 w-full max-w-md p-5 shadow-2xl relative my-auto">
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-gray-100">
      <h3 class="text-base font-bold text-[#091E42]">Cài đặt Không gian làm việc</h3>
      <button type="button" onclick="closeModal('editWorkspaceModal')" class="text-gray-400 hover:text-gray-700 p-1 cursor-pointer font-bold">✕</button>
    </div>

    <form method="POST" action="{{ route('workspaces.update', $currentWorkspace) }}" class="space-y-4">
      @csrf
      @method('PUT')

      @if ($errors->updateWorkspace->any())
        <div class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700 space-y-0.5">
          @foreach ($errors->updateWorkspace->all() as $error)
            <p>{{ $error }}</p>
          @endforeach
        </div>
      @endif

      <div>
        <label class="block text-xs font-bold text-[#091E42] mb-1.5">Tên Không gian làm việc</label>
        <input
          name="name" type="text" required maxlength="150"
          value="{{ old('name', $currentWorkspace->name) }}"
          class="w-full px-3.5 py-2.5 text-xs rounded-md border border-gray-300 focus:outline-none focus:border-[#AA6ADA] focus:ring-1 focus:ring-[#AA6ADA]"
        />
      </div>

      <div>
        <label class="block text-xs font-bold text-[#091E42] mb-1.5">Quyền xem</label>
        <select
          name="visibility"
          class="w-full px-3.5 py-2.5 text-xs rounded-md border border-gray-300 focus:outline-none focus:border-[#AA6ADA] bg-white cursor-pointer"
        >
          @foreach (\App\Enums\WorkspaceVisibility::options() as $value => $label)
            <option value="{{ $value }}" @selected(old('visibility', $currentWorkspace->visibility) === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="block text-xs font-bold text-[#091E42] mb-1.5">
          Mô tả <span class="text-xs font-normal text-gray-400 ml-1">Tùy chọn</span>
        </label>
        <textarea
          name="description" rows="3" maxlength="1000"
          class="w-full px-3.5 py-2 text-xs rounded-md border border-gray-300 focus:outline-none focus:border-[#AA6ADA] resize-none"
        >{{ old('description', $currentWorkspace->description) }}</textarea>
      </div>

      <button type="submit" class="w-full py-2.5 rounded-md text-xs font-semibold bg-[#AA6ADA] hover:bg-[#9252c4] text-white transition cursor-pointer">
        Lưu thay đổi
      </button>
    </form>

    <div class="mt-5 pt-4 border-t border-gray-100">
      <p class="text-xs font-bold text-red-600 mb-1">Khu vực nguy hiểm</p>
      <p class="text-[11px] text-gray-500 mb-2">Xóa không gian làm việc sẽ xóa luôn các bảng bên trong.</p>
      <button
        type="button"
        onclick="closeModal('editWorkspaceModal'); openModal('deleteWorkspaceModal')"
        class="px-3 py-2 rounded-md text-xs font-semibold border border-red-300 text-red-600 hover:bg-red-50 transition cursor-pointer"
      >
        Xóa Không gian làm việc
      </button>
    </div>
  </div>
</div>

{{-- Xác nhận xóa: phải gõ đúng tên workspace mới bấm được (chỉ là lớp bảo vệ giao diện; quyền do WorkspacePolicy@delete kiểm tra) --}}
<div
  id="deleteWorkspaceModal"
  class="hidden fixed inset-0 bg-black/50 backdrop-blur-2xs flex items-center justify-center p-4 z-50 overflow-y-auto"
  onclick="if(event.target === this) closeModal('deleteWorkspaceModal')"
>
  <div class="bg-white rounded-xl border border-gray-200 w-full max-w-sm p-5 shadow-2xl my-auto">
    <h3 class="text-base font-bold text-[#091E42] mb-2">Xóa Không gian làm việc?</h3>
    <p class="text-xs text-gray-600 mb-3">
      Các bảng trong <strong>{{ $currentWorkspace->name }}</strong> cũng sẽ không còn truy cập được.
      Gõ đúng tên không gian làm việc để xác nhận.
    </p>
    <form method="POST" action="{{ route('workspaces.destroy', $currentWorkspace) }}" class="space-y-3">
      @csrf
      @method('DELETE')
      <input
        id="deleteWorkspaceConfirmInput" type="text" autocomplete="off"
        data-name="{{ $currentWorkspace->name }}"
        oninput="updateDeleteWorkspaceButton()"
        class="w-full px-3 py-2 text-xs rounded-md border border-gray-300 focus:outline-none focus:border-red-500"
      />
      <div class="flex gap-2">
        <button type="button" onclick="closeModal('deleteWorkspaceModal')" class="flex-1 py-2 rounded-md text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 cursor-pointer">Hủy</button>
        <button
          type="submit" id="deleteWorkspaceBtn" disabled
          class="flex-1 py-2 rounded-md text-xs font-semibold bg-gray-100 text-gray-400 cursor-not-allowed"
        >Xóa</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
  <script>
    function updateDeleteWorkspaceButton() {
      var input = document.getElementById('deleteWorkspaceConfirmInput');
      var btn = document.getElementById('deleteWorkspaceBtn');
      var ok = input.value.trim() === input.dataset.name;
      btn.disabled = !ok;
      btn.className = 'flex-1 py-2 rounded-md text-xs font-semibold transition ' +
        (ok ? 'bg-red-600 hover:bg-red-700 text-white cursor-pointer' : 'bg-gray-100 text-gray-400 cursor-not-allowed');
    }
    @if ($errors->updateWorkspace->any())
      document.addEventListener('DOMContentLoaded', function () { openModal('editWorkspaceModal'); });
    @endif
  </script>
@endpush
