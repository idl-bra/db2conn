<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gepro extends Model
{
    protected $connection = 'db2';
    protected $table = 'GEPRO';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    // Exemplo: descomente e ajuste conforme suas colunas
    // protected $fillable = ['DESCRICAO', 'ATIVO'];
}
