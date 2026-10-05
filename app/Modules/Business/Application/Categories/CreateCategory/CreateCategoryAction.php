<?php

namespace App\Modules\Business\Application\Categories\CreateCategory;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Events\CategoryCreated;
use App\Core\Services\AuditService;
use App\Modules\Business\Repositories\CategoryRepository;
use App\Modules\Business\Services\BusinessKnowledgeCompiler;
use Illuminate\Support\Str;

class CreateCategoryAction
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly BusinessKnowledgeCompiler $knowledgeCompiler,
        private readonly EventBusInterface $eventBus,
        private readonly AuditService $auditService
    ) {}

    public function execute(CreateCategoryInput $input): CreateCategoryResult
    {
        $slug = Str::slug($input->name);

        $category = $this->categoryRepository->create([
            'tenant_id' => $input->tenantId,
            'name' => $input->name,
            'slug' => $slug,
            'description' => $input->description,
            'search_keywords' => $input->searchKeywords,
            'sort_order' => $input->sortOrder,
            'status' => 'ACTIVE',
            'created_by' => $input->createdById,
        ]);

        // Recompilar snapshot del conocimiento del negocio para la IA en Redis
        $this->knowledgeCompiler->compile($input->tenantId);

        // Auditoría & Eventos
        $this->auditService->record('Business', 'CategoryCreated', newValues: ['uuid' => $category->uuid, 'name' => $category->name]);
        $this->eventBus->dispatch(new CategoryCreated($category));

        return new CreateCategoryResult($category);
    }
}
