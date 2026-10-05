<?php

namespace Tests\Feature;

use App\Core\Enums\ProductStatus;
use App\Core\Enums\ProductType;
use App\Modules\Business\Models\Product;

use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    public function test_product_model_attributes_and_casts(): void
    {
        $product = new Product([
            'name' => 'Dona Chocolate Supremo',
            'base_price' => 8.50,
            'product_type' => ProductType::PRODUCT->value,
            'status' => ProductStatus::ACTIVE->value,
            'is_available' => true,
        ]);

        $this->assertEquals('Dona Chocolate Supremo', $product->name);
        $this->assertEquals(8.50, $product->base_price);
        $this->assertEquals(ProductType::PRODUCT, $product->product_type);
        $this->assertEquals(ProductStatus::ACTIVE, $product->status);
        $this->assertTrue($product->is_available);
    }
}
