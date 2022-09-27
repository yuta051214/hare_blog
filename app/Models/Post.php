<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments(){
        return $this->hasMany(Comment::class);
    }

    protected $fillable = [
        'title',
        'body',
    ];

    public function getImageUrlAttribute(){
        return Storage::url($this->image_path);
    }

    public function getImagePathAttribute(){
        return "images/posts/" . $this->image;
    }
}
