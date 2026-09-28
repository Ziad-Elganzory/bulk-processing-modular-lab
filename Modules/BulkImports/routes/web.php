<?php

use Illuminate\Support\Facades\Route;
use Modules\BulkImports\Http\Controllers\BulkImportsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('bulkimports', BulkImportsController::class)->names('bulkimports');
});
