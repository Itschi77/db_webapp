<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kunde extends Model
{
    protected $connection = 'sqlsrv_topsnetdb_safe';
    protected $table = 'tblKunde';
    protected $primaryKey = 'intID';

    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function ansprechpartner(): HasMany
    {
        return $this->hasMany(
            Ansprechpartner::class,
            'intKID',
            'intID'
        );
    }

    public function projekte(): HasMany
    {
        return $this->hasMany(
            Projekt::class,
            'intKID',
            'intID'
        );
    }

    public function zahlungsbedingung(): BelongsTo
    {
        return $this->belongsTo(
            Zahlungsbedingung::class,
            'intZahlungsbedingungID',
            'intID'
        );
    }
}
