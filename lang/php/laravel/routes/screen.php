<?php

use Illuminate\Support\Facades\Route;
use Otaqq\FormDatalayer\Laravel\Http\SettingsController;

Route::get('/', [SettingsController::class, 'index'])->name('edit');
Route::put('/', [SettingsController::class, 'update'])->name('update');
Route::get('forms/{form}', [SettingsController::class, 'show'])->name('form');
Route::put('forms/{form}', [SettingsController::class, 'updateForm'])->name('form.update');
