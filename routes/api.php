<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:10,1')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::get('/campaigns', [CampaignController::class, 'index']);
Route::get('/campaigns/{campaign}', [CampaignController::class, 'show']);
Route::get('/campaigns/{campaign}/thumbnail', [CampaignController::class, 'thumbnail'])
    ->name('campaigns.thumbnail');
Route::get(
    '/campaigns/{campaign}/milestones/{milestone}/proof',
    [CampaignController::class, 'verifiedMilestoneProof'],
);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware('role:creator')->prefix('creator')->group(function (): void {
        Route::get('/campaigns', [CampaignController::class, 'creatorIndex']);
        Route::post('/campaigns', [CampaignController::class, 'store']);
        Route::post('/campaigns/{campaign}/milestones/{milestone}/proof', [CampaignController::class, 'submitProof']);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function (): void {
        Route::get('/creators', [AdminController::class, 'creators']);
        Route::get('/creators/{creator}/id-card', [AdminController::class, 'idCard']);
        Route::post('/creators/{creator}/review', [AdminController::class, 'reviewCreator']);
        Route::get('/campaigns', [AdminController::class, 'campaigns']);
        Route::post('/campaigns/{campaign}/review', [AdminController::class, 'reviewCampaign']);
        Route::get('/milestones/{milestone}/proof', [AdminController::class, 'milestoneProof']);
        Route::post('/milestones/{milestone}/review', [AdminController::class, 'reviewMilestone']);
    });
});
