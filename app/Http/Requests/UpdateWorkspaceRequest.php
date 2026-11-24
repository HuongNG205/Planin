<?php

namespace App\Http\Requests;

use App\Enums\WorkspaceVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sửa workspace (WorkspaceController@update). Chỉ chủ sở hữu được sửa (WorkspacePolicy@update).
 * Kiểm tra quyền nằm ở authorize() nên người không có quyền nhận 403 trước khi bị validate.
 */
class UpdateWorkspaceRequest extends FormRequest
{
    protected $errorBag = 'updateWorkspace';

    public function authorize(): bool
    {
        // {workspace} đã được route model binding thành model trước khi Request này chạy.
        return $this->user()?->can('update', $this->route('workspace')) ?? false;
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
            'visibility' => ['required', Rule::in(WorkspaceVisibility::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên không gian làm việc.',
            'name.max' => 'Tên không gian làm việc không được vượt quá :max ký tự.',
            'description.max' => 'Mô tả không được vượt quá :max ký tự.',
            'visibility.required' => 'Vui lòng chọn quyền xem.',
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
