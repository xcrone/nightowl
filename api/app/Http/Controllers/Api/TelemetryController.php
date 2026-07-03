<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    public function index(string $resource, Request $request)
    {
        $config = $this->resourceConfig($resource);

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = $config['model'];
        $query = $modelClass::query();

        foreach ($config['filters'] as $key => $filter) {
            $this->applyFilter($query, $filter, $key, $request);
        }

        $this->applySort($query, $config, $request);

        $perPage = min((int) $request->query('per_page', 25), 100);

        return response()->json($query->paginate($perPage)->withQueryString());
    }

    public function show(string $resource, int|string $id)
    {
        $config = $this->resourceConfig($resource);

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = $config['model'];

        return response()->json($modelClass::findOrFail($id));
    }

    protected function applyFilter($query, array $filter, string $key, Request $request): void
    {
        if (array_key_exists('value', $filter)) {
            // Flag filter: presence of a truthy query param triggers a
            // config-fixed where() clause, e.g. ?failed=1 -> status_code >= 500.
            if ($request->has($key) && $request->boolean($key)) {
                $query->where($filter['column'], $filter['op'], $filter['value']);
            }

            return;
        }

        // Param-driven filter: the where() value comes from the query string.
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return;
        }

        $query->where($filter['column'], $filter['op'] ?? '=', $value);
    }

    protected function applySort($query, array $config, Request $request): void
    {
        $sort = (string) $request->query('sort', $config['default_sort'] ?? '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $config['sortable'] ?? [], true)) {
            $column = ltrim($config['default_sort'] ?? '-created_at', '-');
            $direction = 'desc';
        }

        $query->orderBy($column, $direction);
    }

    protected function resourceConfig(string $resource): array
    {
        $config = config("telemetry.resources.{$resource}");

        abort_if($config === null, 404);

        return $config;
    }
}
