<?php

use App\Http\Controllers\Api\ActivationController;
use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\FeatureController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\LicenseServerController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NodeController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ValidationController;
use Illuminate\Support\Facades\Route;

// Public license validation. Clients call these with a license key. Base: /api/v1
Route::prefix('v1')->name('api.')->group(function () {
    Route::post('validate', [ValidationController::class, 'validateKey'])->middleware('throttle:120,1')->name('validate');
    Route::get('public-key', [ValidationController::class, 'publicKey'])->name('public-key');
});

// Authenticated admin REST API. Bearer token (api_tokens) auth; results are
// scoped to the token owner (admins see everything). Base: /api/v1
Route::prefix('v1')->name('api.')->middleware('api.token')->group(function () {
    Route::get('me', fn (\Illuminate\Http\Request $r) => $r->user()->only(['id', 'name', 'email', 'role']));

    // Catalog (global).
    Route::apiResource('locations', LocationController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('features', FeatureController::class);
    Route::apiResource('plans', PlanController::class);

    // Customers & licenses (owner-scoped; licenses inherit their customer's owner).
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('licenses', LicenseController::class);
    Route::get('licenses/{license}/download', [LicenseController::class, 'download'])->name('licenses.download');
    Route::post('licenses/{license}/status', [LicenseController::class, 'setStatus'])->name('licenses.status');
    Route::post('licenses/{license}/renew', [LicenseController::class, 'renew'])->name('licenses.renew');
    Route::post('licenses/{id}/restore', [LicenseController::class, 'restore'])->name('licenses.restore');

    // Activations (owner-scoped, read + delete).
    Route::apiResource('activations', ActivationController::class)->only(['index', 'show', 'destroy']);

    // License servers / nodes (owner-scoped).
    Route::apiResource('servers', LicenseServerController::class)->parameters(['servers' => 'licenseServer']);

    // Administration.
    Route::apiResource('users', UserController::class);
    Route::apiResource('api-tokens', ApiTokenController::class)->only(['index', 'store', 'destroy'])->parameters(['api-tokens' => 'apiToken']);
});

// License-server (node) replication. Authenticated by the node's enrollment
// token (Bearer). Nodes pull the full valid-license set for offline validation.
// Base: /api/node/v1
Route::prefix('node/v1')->name('node.')->group(function () {
    Route::get('sync', [NodeController::class, 'sync'])->name('sync');
    Route::post('heartbeat', [NodeController::class, 'heartbeat'])->name('heartbeat');
});
