<?php

namespace Tests\Feature;

use App\Modules\Automation\Actions\SendWebhookAction;
use App\Modules\Automation\Engine\ActionExecutor;
use App\Modules\Automation\Engine\VariableResolver;

use Tests\TestCase;

class AutomationActionPipelineTest extends TestCase
{
    public function test_action_executor_runs_decoupled_actions(): void
    {
        $resolver = new VariableResolver();
        $executor = new ActionExecutor($resolver);

        $webhookAction = new SendWebhookAction();
        $executor->registerAction($webhookAction);

        $actionsConfig = [
            [
                'action_type' => 'SEND_WEBHOOK',
                'params' => ['target_url' => 'https://mock.httpbin.org/post'],
                'continue_on_error' => true,
            ]
        ];

        $result = $executor->executePipeline($actionsConfig, ['tenant_id' => 1]);

        $this->assertIsBool($result);
    }
}
