<?php

namespace App\Modules\Payments\Application\ListPayments;

use App\Modules\Payments\Repositories\PaymentRepository;

class ListPaymentsAction
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository
    ) {}

    public function execute(ListPaymentsInput $input): ListPaymentsResult
    {
        $paginator = $this->paymentRepository->paginate(
            tenantId: $input->tenantId,
            status: $input->status,
            page: $input->page,
            limit: $input->limit
        );

        return new ListPaymentsResult($paginator);
    }
}
