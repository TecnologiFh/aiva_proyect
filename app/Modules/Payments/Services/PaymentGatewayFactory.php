<?php

namespace App\Modules\Payments\Services;

use App\Core\Contracts\Payments\PaymentGatewayInterface;
use App\Modules\Payments\Providers\MockPaymentGateway;
use App\Modules\Payments\Providers\StripePaymentGateway;
use App\Modules\Payments\Providers\YapePlinGateway;

class PaymentGatewayFactory
{
    public function make(?string $gateway = null): PaymentGatewayInterface
    {
        $selectedGateway = strtoupper($gateway ?? config('services.payments.default', env('PAYMENT_GATEWAY', 'MOCK')));

        return match ($selectedGateway) {
            'YAPE_PLIN', 'YAPE', 'PLIN' => new YapePlinGateway(),
            'STRIPE' => new StripePaymentGateway(),
            default => new MockPaymentGateway(),
        };
    }
}
