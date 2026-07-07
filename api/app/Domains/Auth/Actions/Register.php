<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Resources\UserResource;
use App\Models\Org;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Self-service registration: creates a new User, founds a new Org for them
 * (attached immediately, so `ListOrgs`'s "no membership -> show every org"
 * dev fallback never triggers for a freshly-registered account), and logs
 * the session in exactly like Login::handle() does. Referenced directly
 * from routes/web.php (not routes/api.php) so it runs through the 'web'
 * middleware group — session + CSRF — per Sanctum's SPA auth pattern.
 *
 * The `unique:users,email` rule only prevents a *sequential* duplicate —
 * two concurrent registrations for the same email can both pass validation
 * and race to User::create(); the loser hits the DB's unique constraint.
 * That's caught below and converted to the same 422 shape the rule itself
 * would have produced, instead of an uncaught QueryException 500ing.
 */
class Register
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'org_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function handle(ActionRequest $request)
    {
        try {
            $user = DB::transaction(function () use ($request) {
                $user = User::create([
                    'name' => $request->validated('name'),
                    'email' => $request->validated('email'),
                    'password' => $request->validated('password'),
                ]);

                $org = Org::create([
                    'name' => $request->validated('org_name'),
                    'account_email' => $user->email,
                    'owner_id' => $user->id,
                    'is_personal' => true,
                ]);

                $org->users()->attach($user->id);

                return $user;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'email' => ['The email has already been taken.'],
            ]);
        }

        Auth::login($user);

        $request->session()->regenerate();

        return response()->json(['user' => new UserResource($user)], 201);
    }
}
