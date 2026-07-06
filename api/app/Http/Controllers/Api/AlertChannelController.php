<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesAppScope;
use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Telemetry\AlertChannel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertChannelController extends Controller
{
    use AuthorizesAppScope;

    protected const TYPES = ['slack', 'discord', 'webhook', 'email'];

    public function index(App $app)
    {
        return response()->json(
            AlertChannel::query()->forApp($app->app_id)->orderBy('name')->get()
        );
    }

    public function store(Request $request, App $app)
    {
        $data = $this->validated($request);
        $data['app_id'] = $app->app_id;

        $channel = AlertChannel::create($data);

        return response()->json($channel, 201);
    }

    public function update(Request $request, App $app, AlertChannel $alertChannel)
    {
        $this->authorizeAppOwned($app, $alertChannel);

        $data = $this->validated($request, $alertChannel);

        $alertChannel->update($data);

        return response()->json($alertChannel);
    }

    public function destroy(App $app, AlertChannel $alertChannel)
    {
        $this->authorizeAppOwned($app, $alertChannel);

        $alertChannel->delete();

        return response()->noContent();
    }

    public function toggle(App $app, AlertChannel $alertChannel)
    {
        $this->authorizeAppOwned($app, $alertChannel);

        $alertChannel->update(['enabled' => ! $alertChannel->enabled]);

        return response()->json($alertChannel);
    }

    protected function validated(Request $request, ?AlertChannel $existing = null): array
    {
        $type = $request->input('type', $existing?->type);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'enabled' => ['sometimes', 'boolean'],
            'config' => ['required', 'array'],
        ]);

        $data['config'] = match ($type) {
            'slack', 'discord' => $request->validate([
                'config.webhook_url' => ['required', 'url'],
            ])['config'],
            'webhook' => $request->validate([
                'config.url' => ['required', 'url'],
                'config.secret' => ['sometimes', 'nullable', 'string'],
            ])['config'],
            'email' => $request->validate([
                'config.recipients' => ['required', 'array', 'min:1'],
                'config.recipients.*' => ['email'],
            ])['config'],
            default => $data['config'],
        };

        return $data;
    }
}
