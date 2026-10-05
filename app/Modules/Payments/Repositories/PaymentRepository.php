<?php

namespace App\Modules\Payments\Repositories;

use App\Modules\Payments\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentRepository
{
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function findById(int $id): ?Payment
    {
        return Payment::with('attempts')->find($id);
    }

    public function findByUuid(string $uuid): ?Payment
    {
        return Payment::with('attempts')->whereUuid($uuid)->first();
    }

    public function findByTransactionReference(string $gateway, string $reference): ?Payment
    {
        return Payment::where('gateway', $gateway)
            ->where(function ($q) use ($reference) {
                $q->where('gateway_transaction_id', $reference)
                  ->orWhere('gateway_reference', $reference)
                  ->orWhere('merchant_reference', $reference);
            })
            ->first();
    }

    public function updateStatus(int $id, string $status, ?string $paidAt = null): Payment
    {
        $payment = Payment::findOrFail($id);
        $updateData = ['status' => $status];
        if ($paidAt) {
            $updateData['paid_at'] = $paidAt;
        }

        $payment->update($updateData);
        return $payment;
    }

    public function paginate(int $tenantId, ?string $status = null, int $page = 1, int $limit = 15): LengthAwarePaginator
    {
        $query = Payment::with('attempts')->where('tenant_id', $tenantId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('created_at', 'desc')->paginate(perPage: $limit, page: $page);
    }
}
