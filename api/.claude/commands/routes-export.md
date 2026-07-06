---
description: Regenerate the SPA's route map from the api (composer routes:export) — not yet implemented.
allowed-tools: Bash
---

Routes are meant to be api-owned via `composer routes:export`, but **this command doesn't
exist yet** — there is no `routes:export` composer script, no artisan command backing it,
and no generated route map on the web side. Before running anything:

1. Check `api/composer.json`'s `scripts` block for `routes:export`. If it's missing (it is,
   as of this writing), say so plainly instead of guessing at a command that will fail.
2. If the user wants this built: it needs (a) an artisan command that walks
   `api/routes/api.php` (and, once domains exist, each `app/Domains/<Module>/Routes/api.php`)
   and dumps named routes to a JSON/JS file, (b) a `routes:export` entry in
   `composer.json`'s `scripts`, and (c) a small `route(name, params)` helper on the web
   side that reads the generated file. That's real implementation work — confirm scope
   with the user before building it, don't do it silently as part of an unrelated task.
3. Once it exists, this command's job is: run `composer routes:export`, then remind that
   the generated route file(s) on the web side are produced artifacts — never hand-edited
   — and are consumed via the `route()` helper.
