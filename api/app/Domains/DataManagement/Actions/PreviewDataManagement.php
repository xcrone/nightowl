<?php

namespace App\Domains\DataManagement\Actions;

use App\Models\App;
use App\Models\Telemetry\CacheEvent;
use App\Models\Telemetry\CommandRecord;
use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\JobRecord;
use App\Models\Telemetry\LogRecord;
use App\Models\Telemetry\MailRecord;
use App\Models\Telemetry\NotificationRecord;
use App\Models\Telemetry\OutgoingRequest;
use App\Models\Telemetry\QueryRecord;
use App\Models\Telemetry\RequestRecord;
use App\Models\Telemetry\ScheduledTask;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Selective retention tooling (docs/pages/data-management.md). Preview how
 * many rows of each telemetry category fall in a chosen (older-than-30-day)
 * window before deleting. The actual delete is a no-op here to match the
 * read-only demo banner.
 */
class PreviewDataManagement
{
    use AsAction;

    /** data-type chip => model. Mirrors the sidebar Activity group + Logs. */
    private const TYPES = [
        'requests' => RequestRecord::class,
        'queries' => QueryRecord::class,
        'exceptions' => ExceptionRecord::class,
        'commands' => CommandRecord::class,
        'jobs' => JobRecord::class,
        'cache-events' => CacheEvent::class,
        'mail' => MailRecord::class,
        'notifications' => NotificationRecord::class,
        'outgoing-requests' => OutgoingRequest::class,
        'scheduled-tasks' => ScheduledTask::class,
        'logs' => LogRecord::class,
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['required', 'date'],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => ['string'],
        ];
    }

    public function handle(App $app, ActionRequest $request): array
    {
        $data = $request->validated();

        $to = Carbon::parse($data['to']);
        $from = isset($data['from']) ? Carbon::parse($data['from']) : Carbon::createFromTimestamp(0);

        $counts = [];
        foreach ($data['types'] as $type) {
            if (! isset(self::TYPES[$type])) {
                continue;
            }
            $model = self::TYPES[$type];
            $counts[$type] = $model::query()->forApp($app->app_id)
                ->whereBetween('created_at', [$from, $to])->count();
        }

        return ['counts' => $counts, 'total' => array_sum($counts)];
    }
}
