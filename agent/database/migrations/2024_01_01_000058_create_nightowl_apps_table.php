<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal token->app_id lookup, synced from api's control-plane App model
 * (StoreApp/RegenerateAppToken) whenever an app's agent_token is
 * issued/regenerated. Lets the agent daemon resolve its own app_id at boot
 * from the token it's already configured with, instead of requiring a
 * separately-copied NIGHTOWL_APP_ID env var. Deliberately holds nothing
 * beyond app_id + token_hash — no org/team/name/user data crosses into this
 * database.
 */
return new class extends Migration
{
    protected $connection = 'nightowl';

    public function up(): void
    {
        Schema::connection('nightowl')->create('nightowl_apps', function (Blueprint $table) {
            $table->id();
            $table->string('app_id')->unique();
            $table->string('token_hash', 64)->unique(); // sha256 hex digest
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('nightowl')->dropIfExists('nightowl_apps');
    }
};
