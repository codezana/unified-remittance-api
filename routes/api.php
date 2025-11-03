<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\{
    HomeController,
    CompanyController,
    DetailController,
    BalanceController,
    HistoryController,
    FileController,
    ProductController,
    SalesController,
    CustomersController
};

Route::middleware('json')->group(function () {

    //Home page
    Route::apiResource('/', HomeController::class);

    // Company page
    Route::apiResource('/company', CompanyController::class);

    Route::prefix('/company/{name}')->group(function () {
        Route::apiResource('/balance', BalanceController::class)->parameters(['balance' => 'id']);
        Route::apiResource('/detail', DetailController::class)->parameters(['detail' => 'id']);
        Route::apiResource('/history', HistoryController::class)->parameters(['history' => 'id']);
        Route::apiResource('/product', ProductController::class)->parameters(['product' => 'id']);
        Route::apiResource('/sales', SalesController::class)->parameters(['sales' => 'id']);
        Route::apiResource('/customers', CustomersController::class)->parameters(['customers' => 'id']);
    });
    
    //Manage delete spescfic item form sale -> items 
    Route::delete('/delete/{id}/item/{itemid}', [SalesController::class, 'deleteItem']);
    //Manage files
    Route::post('/file/{id}', [FileController::class, 'updatefile']);

    Route::delete('/delete/{id}/file/{fileType}', [FileController::class, 'destroyFile']);

    Route::get('/company/{name}/file/{filetype}/{filename}', [FileController::class, 'serveFile']);
});
