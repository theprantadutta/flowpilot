<?php

namespace App\Http\Controllers;

use App\Actions\Ai\RequestOperationsBrief;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AiBriefController extends Controller
{
    /**
     * Ask for a fresh operations brief; it is written in the background.
     */
    public function store(Request $request, RequestOperationsBrief $requestBrief): RedirectResponse
    {
        Gate::authorize(Permission::AiUse->value);

        /** @var User $user */
        $user = $request->user();

        $requestBrief->handle($user);

        return back();
    }
}
