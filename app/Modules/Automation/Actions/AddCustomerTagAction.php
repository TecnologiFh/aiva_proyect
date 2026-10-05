<?php

namespace App\Modules\Automation\Actions;

use App\Modules\CRM\Repositories\CustomerTagRepository;
use App\Modules\CRM\Models\Customer;

class AddCustomerTagAction implements AutomationActionInterface
{
    public function __construct(
        private readonly CustomerTagRepository $tagRepository
    ) {}

    public function execute(array $params, array $context): bool
    {
        $tenantId = $context['tenant_id'] ?? null;
        $tagName = $params['tag_name'] ?? null;
        $customerPhone = $context['customer']['phone'] ?? null;

        if (!$tenantId || !$tagName) {
            return false;
        }

        $tag = $this->tagRepository->findOrCreateTag($tenantId, $tagName, 'SYSTEM');

        if ($customerPhone) {
            $customer = Customer::where('tenant_id', $tenantId)
                ->whereHas('identities', fn($q) => $q->where('identifier', $customerPhone))
                ->first();

            if ($customer) {
                $customer->tags()->syncWithoutDetaching([$tag->id]);
            }
        }

        return true;
    }

    public function getType(): string
    {
        return 'ADD_CUSTOMER_TAG';
    }
}
