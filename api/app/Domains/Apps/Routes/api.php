<?php

use App\Domains\Apps\Actions\AcceptOrgInvitation;
use App\Domains\Apps\Actions\CancelOrgInvitation;
use App\Domains\Apps\Actions\DeclineOrgInvitation;
use App\Domains\Apps\Actions\DestroyApp;
use App\Domains\Apps\Actions\DestroyOrg;
use App\Domains\Apps\Actions\DestroyTeam;
use App\Domains\Apps\Actions\InviteOrgMember;
use App\Domains\Apps\Actions\ListApps;
use App\Domains\Apps\Actions\ListOrgInvitations;
use App\Domains\Apps\Actions\ListOrgMembers;
use App\Domains\Apps\Actions\ListOrgs;
use App\Domains\Apps\Actions\ListReceivedInvitations;
use App\Domains\Apps\Actions\RemoveOrgMember;
use App\Domains\Apps\Actions\ShowApp;
use App\Domains\Apps\Actions\ShowDashboard;
use App\Domains\Apps\Actions\StoreApp;
use App\Domains\Apps\Actions\StoreOrg;
use App\Domains\Apps\Actions\StoreTeam;
use App\Domains\Apps\Actions\TransferOrgOwnership;
use App\Domains\Apps\Actions\UpdateApp;
use App\Domains\Apps\Actions\UpdateOrg;
use App\Domains\Apps\Actions\UpdateTeam;
use Illuminate\Support\Facades\Route;

// Org -> Teams -> Apps hierarchy (docs/pages/org-dashboard.md). The {app}
// param binds App by its opaque app_id (App::getRouteKeyName); {org}/{team}
// bind by uuid (Org::getRouteKeyName/Team::getRouteKeyName).
Route::get('/orgs', ListOrgs::class);
Route::post('/orgs', StoreOrg::class);
Route::put('/orgs/{org}', UpdateOrg::class);
Route::put('/orgs/{org}/owner', TransferOrgOwnership::class);
Route::delete('/orgs/{org}', DestroyOrg::class);
Route::get('/orgs/{org}/members', ListOrgMembers::class);
Route::delete('/orgs/{org}/members/{user}', RemoveOrgMember::class);
Route::get('/orgs/{org}/invitations', ListOrgInvitations::class);
Route::post('/orgs/{org}/invitations', InviteOrgMember::class);
Route::delete('/orgs/{org}/invitations/{invitation}', CancelOrgInvitation::class);
Route::get('/invitations', ListReceivedInvitations::class);
Route::post('/invitations/{invitation}/accept', AcceptOrgInvitation::class);
Route::post('/invitations/{invitation}/decline', DeclineOrgInvitation::class);
Route::post('/orgs/{org}/teams', StoreTeam::class);
Route::put('/orgs/{org}/teams/{team}', UpdateTeam::class);
Route::delete('/orgs/{org}/teams/{team}', DestroyTeam::class);

Route::get('/apps', ListApps::class);
Route::get('/apps/{app}', ShowApp::class);
Route::post('/teams/{team}/apps', StoreApp::class);
Route::put('/apps/{app}', UpdateApp::class);
Route::delete('/apps/{app}', DestroyApp::class);

// Per-app dashboard summary — registered ahead of the generic
// /{resource} catch-all (app/Actions/ later): 'dashboard' isn't a
// telemetry resource key, so there's no shadowing either way.
Route::prefix('apps/{app}')->group(function () {
    Route::get('/dashboard', ShowDashboard::class);
});
