<?php

use App\Http\Controllers\AnsprechpartnerController;
use App\Http\Controllers\AnbindungController;
use App\Http\Controllers\AuftragController;
use App\Http\Controllers\BrancheController;
use App\Http\Controllers\KundeController;
use App\Http\Controllers\OffeneRechnungenController;
use App\Http\Controllers\RechnungsanschriftController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $mode = session('frontend_mode', 'classic');
    return view($mode . '.dashboard');
})->name('dashboard');

Route::get('/auftraege', [AuftragController::class, 'all'])->name('auftraege.index');

Route::get('/kunden', [KundeController::class, 'index'])->name('kunden.index');
Route::get('/kunden/neu', [KundeController::class, 'create'])->name('kunden.create');
Route::post('/kunden', [KundeController::class, 'store'])->name('kunden.store');

Route::get('/kunden/{kunde}/ansprechpartner/neu', [AnsprechpartnerController::class, 'create'])->name('kunden.ansprechpartner.create');
Route::post('/kunden/{kunde}/ansprechpartner', [AnsprechpartnerController::class, 'store'])->name('kunden.ansprechpartner.store');
Route::get('/kunden/{kunde}/ansprechpartner/{ansprechpartner}/edit', [AnsprechpartnerController::class, 'edit'])->name('kunden.ansprechpartner.edit');
Route::put('/kunden/{kunde}/ansprechpartner/{ansprechpartner}', [AnsprechpartnerController::class, 'update'])->name('kunden.ansprechpartner.update');

Route::get('/kunden/{id}/branchen', [BrancheController::class, 'edit'])->name('kunden.branchen.edit');
Route::put('/kunden/{id}/branchen', [BrancheController::class, 'update'])->name('kunden.branchen.update');

Route::get('/kunden/{kunde}/rechnungsanschriften', [RechnungsanschriftController::class, 'index'])->name('kunden.rechnungsanschriften.index');
Route::get('/kunden/{kunde}/rechnungsanschriften/neu', [RechnungsanschriftController::class, 'create'])->name('kunden.rechnungsanschriften.create');
Route::post('/kunden/{kunde}/rechnungsanschriften', [RechnungsanschriftController::class, 'store'])->name('kunden.rechnungsanschriften.store');
Route::get('/kunden/{kunde}/rechnungsanschriften/{anschrift}/edit', [RechnungsanschriftController::class, 'edit'])->name('kunden.rechnungsanschriften.edit');
Route::put('/kunden/{kunde}/rechnungsanschriften/{anschrift}', [RechnungsanschriftController::class, 'update'])->name('kunden.rechnungsanschriften.update');

Route::get('/kunden/{kunde}/offene-rechnungen', [OffeneRechnungenController::class, 'index'])->name('kunden.offene-rechnungen.index');

Route::get('/kunden/{kunde}/auftraege', [AuftragController::class, 'index'])->name('kunden.auftraege.index');
Route::get('/kunden/{kunde}/auftraege/neu', [AuftragController::class, 'create'])->name('kunden.auftraege.create');
Route::post('/kunden/{kunde}/auftraege', [AuftragController::class, 'store'])->name('kunden.auftraege.store');
Route::get('/kunden/{kunde}/auftraege/{auftrag}/edit', [AuftragController::class, 'edit'])->name('kunden.auftraege.edit');
Route::put('/kunden/{kunde}/auftraege/{auftrag}', [AuftragController::class, 'update'])->name('kunden.auftraege.update');
Route::get('/kunden/{kunde}/auftraege/{auftrag}/positionen/neu', [AuftragController::class, 'createPosition'])->name('kunden.auftraege.positionen.create');
Route::post('/kunden/{kunde}/auftraege/{auftrag}/positionen', [AuftragController::class, 'storePosition'])->name('kunden.auftraege.positionen.store');
Route::get('/kunden/{kunde}/auftraege/{auftrag}/positionen/{position}/edit', [AuftragController::class, 'editPosition'])->name('kunden.auftraege.positionen.edit');
Route::put('/kunden/{kunde}/auftraege/{auftrag}/positionen/{position}', [AuftragController::class, 'updatePosition'])->name('kunden.auftraege.positionen.update');
Route::get('/kunden/{kunde}/auftraege/{auftrag}/positionen/{position}/anbindungen', [AnbindungController::class, 'index'])->name('kunden.auftraege.positionen.anbindungen.index');
Route::get('/kunden/{kunde}/auftraege/{auftrag}/positionen/{position}/anbindungen/neu', [AnbindungController::class, 'create'])->name('kunden.auftraege.positionen.anbindungen.create');
Route::post('/kunden/{kunde}/auftraege/{auftrag}/positionen/{position}/anbindungen', [AnbindungController::class, 'store'])->name('kunden.auftraege.positionen.anbindungen.store');
Route::get('/kunden/{kunde}/auftraege/{auftrag}/positionen/{position}/anbindungen/{anbindung}/edit', [AnbindungController::class, 'edit'])->name('kunden.auftraege.positionen.anbindungen.edit');
Route::put('/kunden/{kunde}/auftraege/{auftrag}/positionen/{position}/anbindungen/{anbindung}', [AnbindungController::class, 'update'])->name('kunden.auftraege.positionen.anbindungen.update');
Route::get('/kunden/{kunde}/auftraege/{auftrag}', [AuftragController::class, 'show'])->name('kunden.auftraege.show');

Route::get('/kunden/{id}/edit', [KundeController::class, 'edit'])->name('kunden.edit');
Route::put('/kunden/{id}', [KundeController::class, 'update'])->name('kunden.update');
Route::get('/kunden/{id}', [KundeController::class, 'show'])->name('kunden.show');

Route::get('/frontend/{mode}', function (Request $request, string $mode) {
    if (!in_array($mode, ['classic', 'modern'], true)) {
        abort(404);
    }
    session(['frontend_mode' => $mode]);
    return redirect()->back();
})->name('frontend.switch');
