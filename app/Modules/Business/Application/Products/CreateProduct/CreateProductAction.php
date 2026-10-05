<?php

namespace App\Modules\Business\Application\Products\CreateProduct;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Enums\ProductStatus;
use App\Core\Enums\ProductType;
use App\Core\Events\ProductCreated;
use App\Core\Services\AuditService;
use App\Modules\Business\Models\ProductOption;
use App\Modules\Business\Models\ProductOptionValue;
use App\Modules\Business\Repositories\CategoryRepository;
use App\Modules\Business\Repositories\ProductRepository;
use App\Modules\Business\Services\BusinessKnowledgeCompiler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateProductAction
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly BusinessKnowledgeCompiler $knowledgeCompiler,
        private readonly EventBusInterface $eventBus,
        private readonly AuditService $auditService
    ) {}

    public function execute(CreateProductInput $input): CreateProductResult
    {
        $category = $this->categoryRepository->findByUuid($input->categoryUuid);
        if (!$category) {
            throw new ModelNotFoundException('Categoría no encontrada.');
        }

        if ($input->sku && $this->productRepository->findBySku($input->sku, $input->tenantId)) {
            throw ValidationException::withMessages(['sku' => ['El SKU ingresado ya existe en esta empresa.']]);
        }

        $slug = Str::slug($input->name);

        $product = DB::transaction(function () use ($input, $category, $slug) {
            $prod = $this->productRepository->create([
                'tenant_id' => $input->tenantId,
                'category_id' => $category->id,
                'name' => $input->name,
                'slug' => $slug,
                'description' => $input->description,
                'search_keywords' => $input->searchKeywords,
                'product_type' => $input->productType,
                'sku' => $input->sku,
                'base_price' => $input->basePrice,
                'image_path' => $input->imagePath,
                'status' => ProductStatus::ACTIVE->value,
                'is_available' => true,
                'created_by' => $input->createdById,
            ]);

            // Procesar Opciones y Valores (Tamaños, Sabores, Toppings)
            foreach ($input->options as $optionData) {
                $option = ProductOption::create([
                    'tenant_id' => $input->tenantId,
                    'product_id' => $prod->id,
                    'name' => $optionData['name'],
                    'is_required' => $optionData['is_required'] ?? false,
                    'min_selectable' => $optionData['min_selectable'] ?? 0,
                    'max_selectable' => $optionData['max_selectable'] ?? 1,
                ]);

                foreach ($optionData['values'] ?? [] as $valueData) {
                    ProductOptionValue::create([
                        'tenant_id' => $input->tenantId,
                        'option_id' => $option->id,
                        'name' => $valueData['name'],
                        'price_modifier' => $valueData['price_modifier'] ?? 0.00,
                        'is_default' => $valueData['is_default'] ?? false,
                    ]);
                }
            }

            return $prod;
        });

        // Recompilar snapshot de la base de conocimiento en Redis para la IA
        $this->knowledgeCompiler->compile($input->tenantId);

        // Auditoría & Eventos
        $this->auditService->record('Business', 'ProductCreated', newValues: ['uuid' => $product->uuid, 'name' => $product->name, 'price' => $product->base_price]);
        $this->eventBus->dispatch(new ProductCreated($product));

        return new CreateProductResult($product->load(['category', 'options.values']));
    }
}
