<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\CreateOrganization;
use App\Enums\CompanySize;
use App\Enums\Industry;
use App\Enums\UseCase;
use App\Http\Requests\Organizations\StoreOrganizationRequest;
use App\Models\User;
use App\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * The guided flow for creating an organization. Used for a user's first
     * organization and for any they add later.
     */
    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('onboarding/Create', [
            'isFirstOrganization' => $user->usableMemberships()->isEmpty(),
            'industries' => Industry::options(),
            'companySizes' => CompanySize::options(),
            'useCases' => UseCase::options(),
            'currencies' => Currencies::options(),
        ]);
    }

    public function store(StoreOrganizationRequest $request, CreateOrganization $createOrganization): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $organization = $createOrganization->handle($user, $request->organizationAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$organization->name} is ready."]);

        return to_route('overview', ['organization' => $organization->slug]);
    }
}
