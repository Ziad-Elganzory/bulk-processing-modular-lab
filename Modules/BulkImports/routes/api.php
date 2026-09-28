<?php

use Illuminate\Support\Facades\Route;
use Modules\BulkImports\Http\Controllers\BulkImportsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('bulkimports', BulkImportsController::class)->names('bulkimports');
});
