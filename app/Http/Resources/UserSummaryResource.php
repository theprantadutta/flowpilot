<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The minimum about a person that lists and pickers need.
 *
 * @mixin User
 */
class UserSummaryResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, avatar: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar' => $this->avatar,
        ];
    }
}
