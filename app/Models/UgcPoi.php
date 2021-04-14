<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UgcPoi extends Model {
    use HasFactory;

    public function ugc_media() {
        return $this->belongsToMany(UgcMedia::class);
    }
}
