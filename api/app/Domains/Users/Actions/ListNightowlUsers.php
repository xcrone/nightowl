<?php

namespace App\Domains\Users\Actions;

use App\Domains\Users\Resources\NightowlUserResource;
use App\Models\Telemetry\NightowlUser;
use App\Support\SearchTerm;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/users — legacy, top-level (NOT app-scoped; see this domain's
 * README for why). Paginated, optionally filtered by `?q=` over name/email.
 */
class ListNightowlUsers
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(ActionRequest $request)
    {
        $perPage = min((int) $request->query('per_page', 25), 100);

        $query = NightowlUser::query();

        $q = SearchTerm::fromRequest($request);

        if ($q !== null) {
            // Small dimension table (no index needed): plain ILIKE over
            // name/email, same escaping as the trigram-backed telemetry
            // resources for consistency (a literal "%"/"_" in a name
            // shouldn't be treated as a SQL wildcard).
            $escaped = SearchTerm::escapeForLike($q);

            $query->where(function ($w) use ($escaped) {
                $w->where('name', 'ILIKE', '%'.$escaped.'%')
                    ->orWhere('email', 'ILIKE', '%'.$escaped.'%');
            });
        }

        return NightowlUserResource::collection(
            $query->orderByDesc('updated_at')->paginate($perPage)->withQueryString()
        );
    }
}
