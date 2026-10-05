<?php

use App\Modules\Tenant\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::post('/tenants', [TenantController::class, 'store']);
