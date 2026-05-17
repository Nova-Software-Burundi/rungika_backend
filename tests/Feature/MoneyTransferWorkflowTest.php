<?php

namespace Tests\Feature;

use App\Models\MoneyTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MoneyTransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_can_move_through_the_usdt_and_payout_workflow(): void
    {
        Storage::fake('public');

        $initiator = User::factory()->create();
        $agent = User::factory()->create();

        $this->actingAs($initiator);

        $createResponse = $this->postJson('/api/portal/transfers', [
            'sender_name' => 'Client in Lusaka',
            'sender_phone' => '+260970000000',
            'recipient_name' => 'Recipient Name',
            'recipient_phone' => '+260960000000',
            'recipient_location' => 'Lusaka',
            'send_amount' => 150,
            'send_currency' => 'USD',
            'usdt_amount' => 150,
            'payout_currency' => 'ZMW',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('status', MoneyTransfer::STATUS_INITIATED);

        $transfer = MoneyTransfer::firstOrFail();

        $proofResponse = $this->postJson("/api/portal/transfers/{$transfer->id}/usdt-proof", [
            'usdt_proof' => UploadedFile::fake()->image('usdt-transfer.png'),
        ]);

        $proofResponse->assertOk()
            ->assertJsonPath('status', MoneyTransfer::STATUS_USDT_PROOF_SUBMITTED);

        Storage::disk('public')->assertExists($transfer->fresh()->usdt_proof_path);

        $this->actingAs($agent);

        $confirmResponse = $this->postJson("/api/portal/transfers/{$transfer->id}/confirm-usdt", [
            'agent_notes' => 'Confirmed in wallet.',
        ]);

        $confirmResponse->assertOk()
            ->assertJsonPath('status', MoneyTransfer::STATUS_USDT_RECEIVED);

        $payoutResponse = $this->postJson("/api/portal/transfers/{$transfer->id}/payout-proof", [
            'payout_reference' => 'CASH-001',
            'payout_amount' => 3800,
            'payout_proof' => UploadedFile::fake()->image('payout-proof.png'),
        ]);

        $payoutResponse->assertOk()
            ->assertJsonPath('status', MoneyTransfer::STATUS_COMPLETED);

        $transfer->refresh();

        $this->assertEquals($agent->id, $transfer->assigned_agent_id);
        $this->assertNotNull($transfer->payout_confirmed_at);
        $this->assertCount(4, $transfer->events);
        Storage::disk('public')->assertExists($transfer->payout_proof_path);
    }
}
