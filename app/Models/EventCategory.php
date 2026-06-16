<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class EventCategory extends Model {
    use BelongsToCompany;
    protected $fillable = ['name', 'company_id'];

    public function events() {
        return $this->belongsToMany(Event::class, 'event_category_event');
    }
}
