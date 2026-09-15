<?php

use App\Http\Controllers\AnsprechpartnerController;
use App\Http\Controllers\AnbindungController;
use App\Http\Controllers\AuftragController;
use App\Http\Controllers\BrancheController;
use App\Http\Controllers\KundeController;
use App\Http\Controllers\LastschriftController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\DatevController;
use App\Http\Controllers\FremdaccountingController;
use App\Http\Controllers\OffeneRechnungenController;
use App\Http\Controllers\RechnungsanschriftController;
use App\Http\Controllers\RechnungController;
use App\Http\Controllers\RechnungslaufController;
use App\Http\Controllers\WiedervorlageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $mode = session('frontend_mode', 'classic');
    return view($mode . '.dashboard');
})->name('dashboard');

Route::get('/auftraege', [AuftragController::class, 'all'])->name('auftraege.index');
Route::get('/wiedervorlagen', [WiedervorlageController::class, 'index'])->name('wiedervorlagen.index');
Route::get('/fremdaccounting', [FremdaccountingController::class, 'index'])->name('fremdaccounting.index');
Route::get('/rechnungslauf', [RechnungslaufController::class, 'index'])->name('rechnungslauf.index');
Route::get('/rechnungslauf/export', [RechnungslaufController::class, 'export'])->name('rechnungslauf.export');
Route::get('/lastschriften', [LastschriftController::class, 'index'])->name('lastschriften.index');
Route::post('/lastschriften/bezahlt', [LastschriftController::class, 'markPaid'])->name('lastschriften.mark-paid');
Route::get('/rechnungen', [RechnungController::class, 'index'])->name('rechnungen.index');
Route::get('/rechnungen/{rechnung}', [RechnungController::class, 'show'])->name('rechnungen.show');
Route::get('/rechnungen/{rechnung}/datei', [RechnungController::class, 'file'])->name('rechnungen.file');
Route::get('/datev', [DatevController::class, 'index'])->name('datev.index');
Route::get('/datev/rechnungen', [DatevController::class, 'rechnungen'])->name('datev.rechnungen');
Route::get('/datev/produkte', [DatevController::class, 'produkte'])->name('datev.produkte');
Route::get('/datev/kunden', [DatevController::class, 'kunden'])->name('datev.kunden');
Route::get('/datev/kunden-ohne-datev', [DatevController::class, 'kundenOhneDatev'])->name('datev.kunden-ohne');
Route::get('/datev/kundenkonten', [DatevController::class, 'kundenkonten'])->name('datev.kundenkonten');
Route::get('/dokumentation', [DocumentationController::class, 'migration'])->name('documentation.migration');
Route::get('/handbuch', [DocumentationController::class, 'handbook'])->name('documentation.handbook');
Route::get('/sql-wiki', [DocumentationController::class, 'sqlWiki'])->name('documentation.sql-wiki');

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
