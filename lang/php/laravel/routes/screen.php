<?php

use Illuminate\Support\Facades\Route;
use Otaqq\FormDatalayer\Laravel\Http\SettingsController;

Route::get('/', [SettingsController::class, 'edit'])->name('edit');
Route::put('/', [SettingsController::class, 'update'])->name('update');
