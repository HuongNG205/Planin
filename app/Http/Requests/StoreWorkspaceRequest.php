<?php

namespace App\Http\Requests;

use App\Enums\WorkspaceVisibility;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tạo workspace (WorkspaceController@store).
 * Chỉ nhận name, description, visibility. user_id, color, role KHÔNG lấy từ input.
 * Trường 'type' cũ đã bỏ vì không có cột nào lưu và không nơi nào dùng.
 */
class StoreWorkspaceRequest extends FormRequest
{
    /** Lỗi vào error bag riêng để modal tạo workspace hiện đúng lỗi của nó. */
    protected $errorBag = 'createWorkspace';

    public function authorize(): bool
    {
        return $this->user()?->can('create', Workspace::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            // Không gửi lên = mặc định private (xử lý trong WorkspaceService).
            'visibility' => ['nullable', Rule::in(WorkspaceVisibility::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên không gian làm việc.',
            'name.max' => 'Tên không gian làm việc không được vượt quá :max ký tự.',
            'description.max' => 'Mô tả không được vượt quá :max ký tự.',
            'visibility.in' => 'Quyền xem không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên không gian làm việc',
            'description' => 'Mô tả',
            'visibility' => 'Quyền xem',
        ];
    }
}
