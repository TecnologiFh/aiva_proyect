<?php

namespace Tests\Feature;

use App\Modules\Identity\Application\TransferOwnership\TransferOwnershipInput;
use Tests\TestCase;

class TransferOwnershipTest extends TestCase
{
    public function test_transfer_ownership_input_structure(): void
    {
        $input = new TransferOwnershipInput(
            tenantId: 1,
            currentOwnerId: 10,
            newOwnerUuid: 'd7a2fd77-89b4-4a21-987d-123456789abc',
            previousOwnerNewRole: 'ADMIN'
        );

        $this->assertEquals(1, $input->tenantId);
        $this->assertEquals(10, $input->currentOwnerId);
        $this->assertEquals('ADMIN', $input->previousOwnerNewRole);
    }
}
