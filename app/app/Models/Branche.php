<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branche extends Model
{
    protected $connection = 'sqlsrv_topsnetdb_safe';
    protected $table = 'tblBranchen';
    protected $primaryKey = 'strCode';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
}
