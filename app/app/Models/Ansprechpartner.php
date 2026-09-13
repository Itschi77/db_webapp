<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ansprechpartner extends Model
{
    protected $connection = 'sqlsrv_topsnetdb_safe';
    protected $table = 'tblAnsprechpartner';
    protected $primaryKey = 'intID';

    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function kunde(): BelongsTo
    {
        return $this->belongsTo(
            Kunde::class,
            'intKID',
            'intID'
        );
    }
}
