---
name: uuid-public-ids
description: Every user-facing identifier in a NightOwl api domain (app/Domains/<Module>/) is a UUID, never the auto-increment primary key. Use whenever you add or change a migration/schema, a model, or a Resource under app/Domains/<Module>/ — give the table a public `uuid` column, bind routes on it, and serialize the uuid (never the integer `id`) to the SPA. Use any time an identifier crosses the api → web boundary in new domain work.
---

# UUIDs are the only public identifier (new domain work)

For models built under `app/Domains/`, the database's auto-increment `id` is an
**internal** key: it exists for fast joins and foreign keys inside the api. It **must
never leave the api**. Anything the SPA/web can see — JSON responses, URLs, route params,
event payloads sent outward — identifies a record by its **UUID**, never by the integer
primary key.

This is independent of the existing `app_id`/`App` opaque-identifier scheme (root
`CLAUDE.md`) that scopes telemetry to a specific app — that mechanism stays as-is and
isn't a UUID.

Why: sequential integer ids leak row counts and growth rate, invite enumeration/IDOR
probing, and couple the public contract to a physical storage detail. A UUID is opaque,
non-guessable, and stable to expose.

## When this triggers

Any change under `app/Domains/<Module>/` that touches an identifier:

- **Schema / migration** — creating or altering a table for a user-facing model.
- **Model** — a model whose records are ever returned or referenced by the SPA.
- **Resource** (`Resources/`) — anything serialized to JSON.
- **Routes** — a route with a model/id parameter (`/things/{thing}`).
- **Events / jobs / notifications** whose payload leaves the api or reaches the web.

## The three rules

### 1. Schema — every user-facing table gets a `uuid` column

Keep `$table->id()` as the internal PK (joins, FKs). Add an indexed, unique `uuid` right after it:

```php
Schema::create('things', function (Blueprint $table) {
    $table->id();                       // internal only — never exposed
    $table->uuid('uuid')->unique();     // the public identifier
    // ... other columns
    $table->timestamps();
});
```

Foreign keys between tables keep using the integer `id` — they never cross the boundary. Only add a
uuid to tables whose rows the SPA can address; pure pivot/lookup tables don't need one.

### 2. Model — generate the uuid and bind routes to it

Generate the uuid on create and make it the route key so `/things/{thing}` resolves by uuid, not id:

```php
protected static function booted(): void
{
    static::creating(function (self $model): void {
        $model->uuid ??= (string) Str::uuid();
    });
}

public function getRouteKeyName(): string
{
    return 'uuid';
}
```

(Do **not** use `HasUuids` — it replaces the primary key with a uuid. Here the integer PK stays for
internal joins; the uuid is an additional public handle.)

### 3. Resource — serialize the uuid under a `uuid` key, never the integer id

```php
public function toArray(Request $request): array
{
    return [
        'uuid' => $this->uuid,        // ✅ public identifier, keyed as `uuid`
        'name' => $this->name,
        // 'id' => $this->id,         // ❌ NEVER — the integer PK must not leave the api
    ];
}
```

Relationships and references serialize the related record's uuid the same way. Request rules that
accept an identifier validate/resolve against `uuid` (`exists:things,uuid`), never the integer id.

## Self-check before you call the change done

> Did I add/change a table, model, Resource, or id-bearing route/payload for a user-facing
> record in a domain?
> → Then does it have a `uuid` column, bind/resolve on `uuid`, and serialize the **uuid** (never the
> integer `id`) everywhere it crosses to the web?

If any JSON field, URL, or outward payload carries the auto-increment `id`, the change is not
finished.

## Note

This is guidance Claude follows on recognizing the change, backed by a `PreToolUse` hook
(`api/.claude/hooks/uuid-public-ids-block.sh`) that denies a Resource/migration edit
matching the id-leak or uuid-less-table patterns.
