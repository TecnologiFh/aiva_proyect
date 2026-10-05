<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    protected $table = 'business_profiles';

    protected $fillable = [
        'tenant_id',
        'commercial_name',
        'legal_name',
        'tax_id',
        'business_category',
        'description',
        'logo_url',
        'favicon_url',
        'primary_color',
        'secondary_color',
        'phone',
        'email',
        'address',
        'website_url',
        'social_links',
        'opening_hours',
        'non_working_days',
        'avg_preparation_mins',
        'avg_delivery_mins',
        'min_order_amount',
        'business_policies',
    ];

    protected $casts = [
        'social_links' => 'array',
        'opening_hours' => 'array',
        'non_working_days' => 'array',
        'business_policies' => 'array',
        'min_order_amount' => 'decimal:2',
    ];
}
