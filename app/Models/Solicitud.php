<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Solicitud extends Model
{
    protected $table = 'solicituds';

    protected $fillable = ['club_nombre', 'email', 'mensaje'];
}
