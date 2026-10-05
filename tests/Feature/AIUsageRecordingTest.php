<?php

namespace Tests\Feature;

use App\Modules\AI\Models\AIRequest;

use Tests\TestCase;

class AIUsageRecordingTest extends TestCase
{
    public function test_ai_request_model_casts_and_attributes(): void
    {
        $request = new AIRequest([
            'tenant_id' => 1,
            'provider' => 'OPENAI',
            'model' => 'gpt-4o-mini',
            'prompt_version' => 'v1.0',
            'intent_detected' => 'PURCHASE_INTENT',
            'confidence' => 0.985,
            'input_tokens' => 150,
            'output_tokens' => 50,
            'estimated_cost' => 0.000052,
            'latency_ms' => 320,
            'status' => 'SUCCESS',
        ]);

        $this->assertEquals(1, $request->tenant_id);
        $this->assertEquals('OPENAI', $request->provider);
        $this->assertEquals('v1.0', $request->prompt_version);
        $this->assertEquals(0.985, $request->confidence);
        $this->assertEquals(150, $request->input_tokens);
        $this->assertEquals(50, $request->output_tokens);
        $this->assertEquals(0.000052, $request->estimated_cost);
    }
}
