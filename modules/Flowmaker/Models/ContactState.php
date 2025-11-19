<?php

namespace Modules\Flowmaker\Models;

use Illuminate\Database\Eloquent\Model;

class ContactState extends Model
{
    protected $table = 'contact_state';

    protected $fillable = [
        'contact_id',
        'state',
        'value', 
        'flow_id'
    ];
}
