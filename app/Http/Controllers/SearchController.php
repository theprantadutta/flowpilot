<?php

namespace App\Http\Controllers;

use App\Support\Search\GlobalSearch;
use App\Support\Search\SearchResult;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, Tenancy $tenancy, GlobalSearch $search): JsonResponse
    {
        $membership = $tenancy->membership();
        abort_if($membership === null, 403);

        $results = $search->search($tenancy->currentOrFail(), $membership, $request->string('q')->toString());

        return response()->json([
            'results' => array_map(fn (SearchResult $result): array => $result->toArray(), $results),
        ]);
    }
}
