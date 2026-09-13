<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zahlungsbedingung extends Model
{
    protected $connection = 'sqlsrv_topsnetdb_safe';
    protected $table = 'tblZahlungsbedingung';
    protected $primaryKey = 'intID';

    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];
}
