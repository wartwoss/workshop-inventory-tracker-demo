<?php

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FurnitureModelController;
use App\Http\Controllers\Api\V1\MaterialController;
use App\Http\Controllers\Api\V1\ProductionOrderController;
use App\Http\Controllers\Api\V1\ProductionQuoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('furniture-models', [FurnitureModelController::class, 'index'])->name('furniture-models.index');
    Route::post('furniture-models', [FurnitureModelController::class, 'store'])->name('furniture-models.store');
    Route::get('furniture-models/{furnitureModel:slug}', [FurnitureModelController::class, 'show'])->name('furniture-models.show');
    Route::put('furniture-models/{furnitureModel:slug}', [FurnitureModelController::class, 'update'])->name('furniture-models.update');
    Route::delete('furniture-models/{furnitureModel:slug}', [FurnitureModelController::class, 'destroy'])->name('furniture-models.destroy');

    Route::post('production/quote', ProductionQuoteController::class)->name('production.quote');
    Route::post('production-orders/confirm', [ProductionOrderController::class, 'confirm'])->name('production-orders.confirm');

    Route::apiResource('materials', MaterialController::class)->except(['destroy']);
    Route::delete('materials/{material}', [MaterialController::class, 'destroy'])->name('materials.destroy');
    Route::post('materials/{material}/adjust', [MaterialController::class, 'adjust'])->name('materials.adjust');
    Route::get('materials/{material}/movements', [MaterialController::class, 'movements'])->name('materials.movements');
});
