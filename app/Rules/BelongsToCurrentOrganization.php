<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The value must be the id of a record of the given tenant-owned model in the
 * current organization. The model's own tenant scope does the filtering, so an
 * id from another organization simply is not found.
 */
class BelongsToCurrentOrganization implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(
        private readonly string $model,
        private readonly ?string $message = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = is_string($value)
            && Str::isUuid($value)
            && $this->model::query()->whereKey($value)->exists();

        if (! $exists) {
            $fail($this->message ?? 'Choose an option from the list.');
        }
    }
}
