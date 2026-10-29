<?php

namespace App\Http\Requests\Screening;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreScreeningInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === User::ROLE_PROFESSIONAL
            && $this->user()->isActive();
    }

    public function rules(): array
    {
        return [
            'bioimpedance_client_id' => ['required', 'integer', 'exists:bioimpedance_clients,id'],
            'screening_questionnaire_id' => ['required', 'integer', 'exists:screening_questionnaires,id'],
            'expires_in_minutes' => ['required', 'integer', 'min:15', 'max:10080'],
            'access_mode' => ['required', 'in:remote,reception'],
        ];
    }
}
