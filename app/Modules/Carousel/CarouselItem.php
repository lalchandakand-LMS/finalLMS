<?php

namespace App\Modules\Carousel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarouselItem extends Model
{
    protected $table = 'website_carousel_items';

    protected $fillable = ['carousel_id', 'title', 'description', 'image_url', 'link_url', 'sort_order'];

    public function carousel(): BelongsTo
    {
        return $this->belongsTo(Carousel::class, 'carousel_id');
    }
}