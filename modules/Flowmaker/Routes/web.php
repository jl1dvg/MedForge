<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::group([
    'middleware' => ['web', 'impersonate'],
    'namespace' => 'Modules\Flowmaker\Http\Controllers'
], function () {
    Route::group([
        'middleware' => ['auth']
    ], function () {
        //Flow maker
        Route::get('flowmaker/edit/{flow}', 'Main@edit')->name('flowmaker.edit');
        Route::get('flowmaker/script', 'Main@script')->name('flowmaker.script');
        Route::get('flowmaker/css', 'Main@css')->name('flowmaker.css');
        Route::post('flowmaker/update/{flow}', 'Main@updateFlow')
            ->name('flowmaker.update')
            ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Route::post('flowmakermedia', 'Main@uploadMedia')
            ->name('flowmaker.media.upload')
            ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

         //Flows
         Route::get('flows', 'FlowsController@index')->name('flows.index');
         Route::get('flows/{flow}/edit', 'FlowsController@edit')->name('flows.edit');
         Route::get('flows/create', 'FlowsController@create')->name('flows.create');
         Route::post('flows', 'FlowsController@store')->name('flows.store');
         Route::put('flowsflows/{flow}', 'FlowsController@update')->name('flows.update');
         Route::get('flows/del/{flow}', 'FlowsController@destroy')->name('flows.delete');
    });
});
