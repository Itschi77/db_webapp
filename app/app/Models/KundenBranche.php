<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KundenBranche extends Model
{
    protected $connection = 'sqlsrv_topsnetdb_safe';
    protected $table = 'tblKundenBranchen';
    public $timestamps = false;
    public $incrementing = false;
    protected $guarded = [];
}
