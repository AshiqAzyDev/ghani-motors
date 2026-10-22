<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceCounter extends Model
{
    protected $table = 'invoice_counters';

    public $timestamps = false;

    protected $guarded = [];
}
