<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A51 · Показы и клики объявления на месте за день (date + creative_id + slot — уникально). */
class AdStat extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['date' => 'date', 'impressions' => 'integer', 'clicks' => 'integer'];
}
