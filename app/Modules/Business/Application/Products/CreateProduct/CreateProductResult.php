<?php

namespace App\Modules\Business\Application\Products\CreateProduct;

use App\Modules\Business\Models\Product;

class CreateProductResult
{
    public function __construct(
        public readonly Product $product
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->product->uuid,
            'name' => $this->product->name,
            'slug' => $this->product->slug,
            'category' => $this->product->category?->name,
            'product_type' => $this->product->product_type->value,
            'sku' => $this->product->sku,
            'base_price' => (float) $this->product->base_price,
            'image_path' => $this->product->image_path,
            'status' => $this->product->status->value,
            'is_available' => $this->product->is_available,
            'options' => $this->product->options->map(fn($opt) => [
                'uuid' => $opt->uuid,
                'name' => $opt->name,
                'is_required' => $opt->is_required,
                'values' => $opt->values->map(fn($v) => [
                    'uuid' => $v->uuid,
                    'name' => $v->name,
                    'price_modifier' => (float) $v->price_modifier,
                ])->toArray(),
            ])->toArray(),
            'created_at' => $this->product->created_at?->toIso8601String(),
        ];
    }
}
