<!-- ============================================================== -->
<!-- JAVASCRIPT CƠ BẢN XỬ LÝ MODAL VÀ DROPDOWN                      -->
<!-- ============================================================== -->
<script>
  // 1. Mở / Đóng Modal
  function openModal(modalId) {
    var el = document.getElementById(modalId);
    if (el) el.classList.remove('hidden');
  }

  function closeModal(modalId) {
    var el = document.getElementById(modalId);
    if (el) el.classList.add('hidden');
  }

  // 2. Bật / Tắt Menu Dropdown
  function toggleElement(elementId) {
    var el = document.getElementById(elementId);
    if (el) el.classList.toggle('hidden');
  }

  // 3. Kiểm tra nút Submit khi nhập tên Workspace (tên Bảng: updateBoardSubmitButton bên dưới)
  function updateWorkspaceSubmitButton() {
    var input = document.getElementById('workspaceNameInput');
    var btn = document.getElementById('workspaceSubmitBtn');
    if (input.value.trim() !== '') {
      btn.disabled = false;
      btn.className = 'w-full py-2.5 rounded-md text-xs font-semibold transition cursor-pointer bg-[#AA6ADA] hover:bg-[#9252c4] text-white shadow-xs';
    } else {
      btn.disabled = true;
      btn.className = 'w-full py-2.5 rounded-md text-xs font-semibold transition bg-gray-100 text-gray-400 cursor-not-allowed';
    }
  }

  // Form bảng dùng chung cho modal TẠO (prefix 'createBoard') và modal SỬA (prefix 'editBoard');
  // id phần tử = prefix + hậu tố (xem board/partials/board-form-fields.blade.php).
  function updateBoardSubmitButton(prefix) {
    var input = document.getElementById(prefix + 'NameInput');
    var btn = document.getElementById(prefix + 'SubmitBtn');
    var msg = document.getElementById(prefix + 'NameRequiredMsg');
    if (!input || !btn) return;
    if (input.value.trim() !== '') {
      btn.disabled = false;
      btn.className = 'w-full py-2.5 rounded text-xs font-semibold transition cursor-pointer bg-[#AA6ADA] hover:bg-[#9252c4] text-white shadow-xs';
      input.classList.remove('border-red-500', 'focus:border-red-500');
      input.classList.add('border-gray-300', 'focus:border-[#AA6ADA]');
      if (msg) msg.classList.add('hidden');
    } else {
      btn.disabled = true;
      btn.className = 'w-full py-2.5 rounded text-xs font-semibold transition bg-gray-100 text-gray-400 cursor-not-allowed';
      input.classList.remove('border-gray-300', 'focus:border-[#AA6ADA]');
      input.classList.add('border-red-500', 'focus:border-red-500');
      if (msg) msg.classList.remove('hidden');
    }
  }

  // 4. Chọn phông nền cho bảng. Giá trị nằm ở data-bg; server vẫn kiểm tra lại (BoardBackground::rule()).
  function selectBoardBackground(clickedElement, prefix) {
    var bgStyle = clickedElement.dataset.bg;
    document.getElementById(prefix + 'BackgroundInput').value = bgStyle;
    document.getElementById(prefix + 'PreviewBox').style.background = bgStyle;

    var form = document.getElementById(prefix + 'Form');
    form.querySelectorAll('.bg-picker-opt').forEach(function(opt) {
      opt.classList.remove('border-gray-900', 'ring-2', 'ring-purple-400');
      opt.classList.add('border-transparent');
      var check = opt.querySelector('.check-icon');
      if (check) check.classList.add('hidden');
    });

    clickedElement.classList.remove('border-transparent');
    clickedElement.classList.add('border-gray-900', 'ring-2', 'ring-purple-400');
    var currentCheck = clickedElement.querySelector('.check-icon');
    if (currentCheck) currentCheck.classList.remove('hidden');
  }

  // 5. Chọn quyền xem bảng (Riêng tư / Không gian làm việc / Công khai)
  function selectBoardVisibility(prefix, visibilityValue, clickedOption) {
    document.getElementById(prefix + 'VisibilityInput').value = visibilityValue;
    document.getElementById(prefix + 'CurrentVisibilityText').innerText = clickedOption.dataset.label;

    ['private', 'workspace', 'public'].forEach(function(type) {
      var iconEl = document.getElementById(prefix + 'VisIcon_' + type);
      if (iconEl) iconEl.classList.toggle('hidden', type !== visibilityValue);
    });

    var dropdown = document.getElementById(prefix + 'VisibilityDropdown');
    dropdown.querySelectorAll('.vis-option').forEach(function(opt) {
      opt.classList.remove('bg-purple-50', 'text-[#AA6ADA]');
      opt.classList.add('text-gray-800');
      var svg = opt.querySelector('.vis-svg');
      if (svg) {
        svg.classList.remove('text-[#AA6ADA]');
        svg.classList.add('text-gray-700');
      }
    });
    clickedOption.classList.remove('text-gray-800');
    clickedOption.classList.add('bg-purple-50', 'text-[#AA6ADA]');
    var activeSvg = clickedOption.querySelector('.vis-svg');
    if (activeSvg) {
      activeSvg.classList.remove('text-gray-700');
      activeSvg.classList.add('text-[#AA6ADA]');
    }

    dropdown.classList.add('hidden');
  }

  // 6. Đóng dropdown khi click ra ngoài màn hình
  document.addEventListener('click', function(e) {
    var userMenu = document.getElementById('userMenuDropdown');
    var wsSelect = document.getElementById('workspaceSelectDropdown');

    if (userMenu && !userMenu.contains(e.target) && !e.target.closest('[onclick*="userMenuDropdown"]')) {
      userMenu.classList.add('hidden');
    }
    if (wsSelect && !wsSelect.contains(e.target) && !e.target.closest('[onclick*="workspaceSelectDropdown"]')) {
      wsSelect.classList.add('hidden');
    }
    // Menu quyền xem của các form bảng (createBoard / editBoard)
    document.querySelectorAll('.board-vis-dropdown').forEach(function(dd) {
      if (!dd.contains(e.target) && !e.target.closest('[onclick*="' + dd.id + '"]')) {
        dd.classList.add('hidden');
      }
    });
  });

  // 7. Đặt trạng thái ban đầu của nút gửi cho các form bảng có trên trang
  //    (form sửa đã có sẵn tên => nút bật; form tạo trống => nút khóa).
  ['createBoard', 'editBoard'].forEach(function(prefix) {
    if (document.getElementById(prefix + 'NameInput')) updateBoardSubmitButton(prefix);
  });
</script>

