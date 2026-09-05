<?php

declare(strict_types=1);

use App\Http\Controllers\Team\AcceptTeamInvitationController;
use App\Http\Controllers\Team\CancelTeamInvitationController;
use App\Http\Controllers\Team\ListTeamInvitationsController;
use App\Http\Controllers\Team\ListTeamMembersController;
use App\Http\Controllers\Team\RemoveTeamMemberController;
use App\Http\Controllers\Team\ResendTeamInvitationController;
use App\Http\Controllers\Team\StoreTeamInvitationController;
use App\Http\Controllers\Team\TransferWorkspaceOwnershipController;
use App\Http\Controllers\Team\UpdateTeamMemberRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/workspaces/{workspace}/team/members', ListTeamMembersController::class);
    Route::delete('/workspaces/{workspace}/team/members/{member}', RemoveTeamMemberController::class)->middleware('throttle:5,1');
    Route::post('/workspaces/{workspace}/team/members/{member}/transfer-ownership', TransferWorkspaceOwnershipController::class)->middleware('throttle:5,1');
    // Yanlış verilmiş bir rolü düzeltmek, üyeyi silip yeniden davet etmeyi
    // gerektirmemeli (`docs/83`).
    Route::put('/workspaces/{workspace}/team/members/{member}/role', UpdateTeamMemberRoleController::class)->middleware('throttle:10,1');

    Route::get('/workspaces/{workspace}/team/invitations', ListTeamInvitationsController::class);
    Route::post('/workspaces/{workspace}/team/invitations', StoreTeamInvitationController::class)->middleware('throttle:5,1');
    Route::delete('/workspaces/{workspace}/team/invitations/{invitation}', CancelTeamInvitationController::class)->middleware('throttle:5,1');
    /*
        E-posta çıkmadıysa sahibin elinde bir hamle OLMALI (`docs/110` P0-06).

        Öncesinde tek yol daveti iptal edip yeniden kurmaktı: ekibini
        kurabilmek için önce onu bozması gerekiyordu. Sınır kardeşleriyle
        aynı — sınırsız bir yeniden gönderme ucu, ürünü başkasının gelen
        kutusuna yönelen bir taciz aracına çevirirdi.
    */
    Route::post('/workspaces/{workspace}/team/invitations/{invitation}/resend', ResendTeamInvitationController::class)->middleware('throttle:5,1');
    Route::post('/invitations/accept/{token}', AcceptTeamInvitationController::class)->middleware('throttle:5,1');
});
