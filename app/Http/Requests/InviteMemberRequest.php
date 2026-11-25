<?php

namespace App\Http\Requests;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Mời thành viên (WorkspaceMemberController@store). Chỉ admin của workspace. */
class InviteMemberRequest extends FormRequest
{
    protected $errorBag = 'inviteMember';

    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace
            && ($this->user()?->can('manageMembers', $workspace) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:190'],
            // Không gửi lên = vai trò mặc định (member), xử lý trong MemberService.
            'role' => ['nullable', Rule::in(WorkspaceRole::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Vui lòng nhập email của người cần mời.',
            'email.email' => 'Địa chỉ email không đúng định dạng.',
            'email.max' => 'Địa chỉ email không được vượt quá :max ký tự.',
            'role.in' => 'Vai trò không hợp lệ.',
        ];
    }
}
