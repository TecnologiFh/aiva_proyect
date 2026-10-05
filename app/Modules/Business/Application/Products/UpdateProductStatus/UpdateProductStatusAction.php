<?php

namespace App\Modules\Business\Application\Products\UpdateProductStatus;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Events\ProductStatusChanged;
use App\Core\Services\AuditService;
use App\Modules\Business\Repositories\ProductRepository;
use App\Modules\Business\Services\BusinessKnowledgeCompiler;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UpdateProductStatusAction
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly BusinessKnowledgeCompiler $knowledgeCompiler,
        private readonly EventBusInterface $eventBus,
        private readonly AuditService $auditService
    ) {}

    public function execute(UpdateProductStatusInput $input): UpdateProductStatusResult
    {
        $product = $this->productRepository->findByUuid($input->productUuid);
        if (!$product) {
            throw new ModelNotFoundException('Producto no encontrado.');
        }

        $oldStatus = $product->status->value;
        $updateData = ['status' => $input->newStatus, 'updated_by' => $input->updatedById];

        if ($input->isAvailable !== null) {
            $updateData['is_available'] = $input->isAvailable;
        }

        $updatedProduct = $this->productRepository->update($product->id, $updateData);

        // Recompilar snapshot de la base de conocimiento en Redis para la IA
        $this->knowledgeCompiler->compile($product->tenant_id);

        // Auditoría & Eventos
        $this->auditService->record('Business', 'ProductStatusChanged', oldValues: ['status' => $oldStatus], newValues: ['status' => $input->newStatus]);
        $this->eventBus->dispatch(new ProductStatusChanged($updatedProduct, $oldStatus, $input->newStatus));

        return new UpdateProductStatusResult($updatedProduct);
    }
}
