<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending org-membership invitations. Replaces the previous instant-attach
 * flow (App\Domains\Apps\Actions\AddOrgMember) with an explicit
 * invite/accept-or-decline step. The `email` column is intentionally not a
 * foreign key to `users` — an invite can target any email address, and is
 * matched to a real user later (at accept time) by comparing `email` against
 * the logged-in user's own email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_invitations', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('org_id')->constrained('orgs')->cascadeOnDelete();
            $t->string('email');
            $t->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->default('pending');
            $t->timestamp('responded_at')->nullable();
            $t->timestamps();

            $t->index(['org_id', 'email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_invitations');
    }
};
