<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

/**
 * Validates an uploaded attachment by extension, real content type and size.
 * Authorization happens in the controller, against the record the file is for.
 */
class StoreAttachmentRequest extends FormRequest
{
    /**
     * The MIME types each allowed extension may really contain. Checked against
     * the file's content, so a script renamed to .pdf is still refused.
     *
     * @var array<string, list<string>>
     */
    public const array MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(array_keys(self::MIME_TYPES))->max((int) config('flowpilot.uploads.max_file_kb', 20480)),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $extension = strtolower($value->getClientOriginalExtension());
                    $allowed = self::MIME_TYPES[$extension] ?? [];

                    if ($allowed === [] || ! in_array($value->getMimeType(), $allowed, true)) {
                        $fail('The file’s contents do not match its type. Upload a PDF, image, Office document, text, CSV or ZIP file.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a file to upload.',
            'file.mimes' => 'Upload a PDF, image, Office document, text, CSV or ZIP file.',
            'file.max' => 'Files can be up to 20 MB.',
        ];
    }
}
