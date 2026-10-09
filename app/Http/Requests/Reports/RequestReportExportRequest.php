<?php

namespace App\Http\Requests\Reports;

use App\Enums\ExportStatus;
use App\Enums\ReportBucket;
use App\Models\ReportExport;
use App\Models\User;
use App\Support\Reports\ReportQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RequestReportExportRequest extends FormRequest
{
    /**
     * Exports a member may have waiting at once.
     */
    public const int MAXIMUM_PENDING = 3;

    /**
     * Permission is checked by the controller, which knows the report.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'range' => ['required', Rule::in(array_keys(ReportQuery::PRESETS))],
            'from' => ['exclude_unless:range,custom', 'required', 'date_format:Y-m-d'],
            'to' => ['exclude_unless:range,custom', 'required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'bucket' => ['nullable', Rule::enum(ReportBucket::class)],
            'group' => ['nullable', 'string', 'max:40'],
            'filters' => ['nullable', 'array'],
            'filters.*' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $user */
                $user = $this->user();

                $pending = ReportExport::query()
                    ->where('user_id', $user->id)
                    ->whereIn('status', [ExportStatus::Queued->value, ExportStatus::Processing->value])
                    ->count();

                if ($pending >= self::MAXIMUM_PENDING) {
                    $validator->errors()->add('export', 'You already have '.self::MAXIMUM_PENDING.' exports being prepared. Wait for one to finish first.');
                }
            },
        ];
    }

    /**
     * The parameters in the shape ReportParameters reads.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        /** @var array<string, string|null> $filters */
        $filters = $this->validated('filters') ?? [];

        // Filters first, so they can never replace the range or grouping.
        return [
            ...array_filter($filters, fn (?string $value): bool => $value !== null && $value !== ''),
            'range' => $this->validated('range'),
            'from' => $this->validated('from'),
            'to' => $this->validated('to'),
            'bucket' => $this->validated('bucket'),
            'group' => $this->validated('group'),
        ];
    }
}
