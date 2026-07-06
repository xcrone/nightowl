<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App-management schema (the Org → Teams → Apps hierarchy from docs/).
 *
 * Lives in the api's PRIMARY connection (alongside users) rather than the
 * shared nightowl telemetry DB — these are dashboard-account entities, not
 * telemetry. Telemetry rows reference an app only by its opaque
 * `apps.app_id` string (see agent migration ..._000056_add_app_id_column).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orgs', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('account_email');
            $t->timestamps();
        });

        // Which orgs a dashboard user can see.
        Schema::create('org_user', function (Blueprint $t) {
            $t->foreignId('org_id')->constrained('orgs')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->primary(['org_id', 'user_id']);
        });

        Schema::create('teams', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_id')->constrained('orgs')->cascadeOnDelete();
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('apps', function (Blueprint $t) {
            $t->id();
            // Opaque public id used in every /dashboard/<app-id>/… URL and
            // stamped on every telemetry row's app_id column.
            $t->string('app_id')->unique();
            $t->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $t->string('name');
            $t->string('description')->nullable();
            $t->string('db_connection')->nullable();     // display string on cards
            $t->json('environments')->nullable();        // { name: colorHex }
            $t->string('agent_token')->nullable();       // NIGHTOWL_TOKEN (masked in API)
            $t->timestamp('template_synced_at')->nullable();
            $t->timestamps();
        });

        // Onboarding templates (Settings → "This app's template" / "Apply a
        // template"). A template captures one app's alert/threshold/color
        // config for cloning onto another (secrets excluded).
        Schema::create('templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $t->string('name');
            $t->json('payload')->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
        Schema::dropIfExists('apps');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('org_user');
        Schema::dropIfExists('orgs');
    }
};
