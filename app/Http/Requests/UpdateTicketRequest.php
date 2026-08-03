<?php

namespace App\Http\Requests;

use App\Enums\TicketStatus;
use App\Enums\TicketCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status'       => ['required', Rule::enum(TicketStatus::class)],
            'category'     => ['nullable', Rule::enum(TicketCategory::class)],
            'sender_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'sender_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
