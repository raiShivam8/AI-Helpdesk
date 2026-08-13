<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreInboundEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sender_email'   => 'required|email|max:255',
            'sender_name'    => 'required|string|max:255',
            'subject'        => 'required|string|max:255',
            'body'           => 'required|string',
            'body_html'      => 'nullable|string',
            'attachments'    => 'nullable|array',
            'attachment'     => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,zip,txt|max:10240',
            'attachments.*'  => 'nullable',
        ];
    }
}
