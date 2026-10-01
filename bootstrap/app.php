<?php

use App\Console\Commands\ConsumeDashboardImportStatusUpdates;
use App\Console\Commands\ConsumeFailedOrderChunks;
use App\Console\Commands\ConsumeImportRequests;
use App\Console\Commands\ConsumeOrderChunkResults;
use App\Console\Commands\ConsumeOrderChunks;
use App\Console\Commands\DeclareRabbitMqTopology;
use App\Console\Commands\PublishBulkImportsOutbox;
use App\Console\Commands\PublishDashboardOutbox;
use App\Console\Commands\PublishOrdersOutbox;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->withCommands([
        DeclareRabbitMqTopology::class,
        ConsumeOrderChunks::class,
        PublishOrdersOutbox::class,
        ConsumeFailedOrderChunks::class,
        ConsumeImportRequests::class,
        PublishBulkImportsOutbox::class,
        ConsumeOrderChunkResults::class,
        ConsumeDashboardImportStatusUpdates::class,
        PublishDashboardOutbox::class,
    ])
    ->create();
