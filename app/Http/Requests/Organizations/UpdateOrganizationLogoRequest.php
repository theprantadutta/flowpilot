<?php

namespace App\Http\Requests\Organizations;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateOrganizationLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permission::SettingsManage->value);
    }

    /**
     * Raster images only: SVG can carry scripts and is served from our own origin.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                File::image()
                    ->types(['png', 'jpg', 'jpeg', 'webp'])
                    ->max((int) config('flowpilot.uploads.max_image_kb', 2048))
                    ->dimensions(Rule::dimensions()->minWidth(64)->minHeight(64)->maxWidth(4096)->maxHeight(4096)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.image' => 'Upload a PNG, JPG or WebP image.',
            'logo.mimes' => 'Upload a PNG, JPG or WebP image.',
            'logo.max' => 'The logo must be 2 MB or smaller.',
            'logo.dimensions' => 'Use an image between 64 and 4096 pixels on each side.',
        ];
    }
}
