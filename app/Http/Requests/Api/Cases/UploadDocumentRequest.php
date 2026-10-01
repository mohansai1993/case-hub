<?php

namespace App\Http\Requests\Api\Cases;

use Illuminate\Foundation\Http\FormRequest;

/** Body: multipart/form-data, field "file". */
class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required', 'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png',
                'max:' . config('casehub.case_documents.max_kb'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'The file must not be larger than 20MB.',
            'file.mimes' => 'The file must be a PDF, Word document, JPG, or PNG.',
        ];
    }
}
