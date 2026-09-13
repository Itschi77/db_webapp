<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bankverbindung extends Model
{
    protected $connection = 'sqlsrv_accountings';
    protected $table = 'tblBankverbindung';
    protected $primaryKey = 'intID';

    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];
}
