<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Traits\BelongsToCompany;
class Plan extends Model {
    use SoftDeletes, BelongsToCompany;
    protected $fillable = ['name','banner','thumbnail','description','price','interval','interval_count','stripe_price_id','stripe_product_id','features','is_active','sort_order','company_id'];
    protected $casts = ['price'=>'decimal:2', 'is_active'=>'boolean'];

    // DB column is JSON; frontend sends/expects a newline-separated string.
    // This accessor/mutator bridges the two transparently.
    public function items()
    {
        return $this->hasMany(PlanItem::class);
    }

    protected function features(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value
                ? implode("\n", json_decode($value, true) ?? [$value])
                : '',
            set: fn($value) => json_encode(
                is_array($value)
                    ? $value
                    : array_values(array_filter(array_map('trim', explode("\n", $value ?? ''))))
            ),
        );
    }
}
