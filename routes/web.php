<?php

use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Scramble::registerUiRoute('docs/business');
Scramble::registerJsonSpecificationRoute('docs/business/api.json');
