<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    protected $fillable = ['key', 'name_az', 'description_az', 'sort_order'];

    public static function all($columns = ['*'])
    {
        return parent::orderBy('sort_order')->get($columns);
    }
}
