<?php

namespace App\Http\Requests;

use App\Enums\BoardVisibility;
use App\Support\BoardBackground;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sửa bảng (BoardController@update): tên, phông nền, quyền xem.
 * Quyền: BoardPolicy@update (admin của workspace, bảng chưa lưu trữ) kiểm tra ở authorize(),
 * nên người không có quyền nhận 403 trước khi bị validate.
 * Nền dùng cùng BoardBackground::rule() với StoreBoardRequest (D7).
 */
class UpdateBoardRequest extends FormRequest
{
    protected $errorBag = 'updateBoard';

    public function authorize(): bool
    {
        // {board} đã được route model binding thành model trước khi Request này chạy.
        return $this->user()?->can('update', $this->route('board')) ?? false;
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
            'background' => ['required', 'string', 'max:255', BoardBackground::rule()],
            'visibility' => ['required', Rule::in(BoardVisibility::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tiêu đề bảng.',
            'name.max' => 'Tiêu đề bảng không được vượt quá :max ký tự.',
            'background.required' => 'Vui lòng chọn phông nền.',
            'background.max' => 'Phông nền của bảng không hợp lệ.',
            'visibility.required' => 'Vui lòng chọn quyền xem.',
            'visibility.in' => 'Quyền xem không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tiêu đề bảng',
            'background' => 'Phông nền',
            'visibility' => 'Quyền xem',
        ];
    }
}
