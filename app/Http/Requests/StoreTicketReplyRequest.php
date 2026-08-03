<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request: StoreTicketReplyRequest
 *
 * Validates the reply form submission on the Ticket Details page.
 *
 * Authorization:
 *   Any authenticated user may post a reply (agents and admins).
 *
 * Rules:
 *   body – required, non-empty string, max 10,000 characters.
 *          The `string` rule ensures no unexpected types (arrays, etc.)
 *          are accepted. The `max` prevents extremely large payloads
 *          while still allowing long support replies.
 *
 * Messages:
 *   Custom messages are provided so the end-user sees plain English
 *   rather than field-name-based defaults.
 */
class StoreTicketReplyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Any authenticated user (agent or admin) may submit replies.
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
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Custom human-readable validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'The reply message cannot be empty.',
            'body.string'   => 'The reply must be plain text.',
            'body.max'      => 'The reply may not be longer than 2,000 characters.',
        ];
    }
}
