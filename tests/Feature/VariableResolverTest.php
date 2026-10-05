<?php

namespace Tests\Feature;

use App\Modules\Automation\Engine\VariableResolver;
use Tests\TestCase;

class VariableResolverTest extends TestCase
{
    public function test_variable_resolver_replaces_dynamic_template_tokens(): void
    {
        $resolver = new VariableResolver();

        $template = "Hola {{customer.name}}, tu pedido {{order.number}} suma S/ {{order.total}}.";
        $context = [
            'customer' => ['name' => 'Luis Fiestas'],
            'order' => ['number' => 'ORD-2026-0001', 'total' => 86.50],
        ];

        $resolved = $resolver->resolve($template, $context);

        $this->assertEquals("Hola Luis Fiestas, tu pedido ORD-2026-0001 suma S/ 86.5.", $resolved);
    }
}
