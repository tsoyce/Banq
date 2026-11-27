<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A37 · Реакция читателя на публикацию (Post::REACTIONS). Одна реакция каждого вида от пользователя. */
class PostReaction extends Model
{
    protected $guarded = ['id'];
}
