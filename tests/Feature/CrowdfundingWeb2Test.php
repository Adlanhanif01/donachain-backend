<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrowdfundingWeb2Test extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_cannot_create_an_admin(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Public User',
            'email' => 'public@example.test',
            'password' => 'long-password',
            'password_confirmation' => 'long-password',
            'role' => 'admin',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('status', false)
            ->assertJsonStructure(['status', 'message', 'data' => ['errors']]);
        $this->assertDatabaseMissing('users', ['email' => 'public@example.test']);
    }

    public function test_creator_registration_stores_kyc_and_starts_pending(): void
    {
        Storage::fake('private');

        $response = $this->post('/api/auth/register', [
            'name' => 'Campaign Creator',
            'email' => 'creator@example.test',
            'password' => 'long-password',
            'password_confirmation' => 'long-password',
            'role' => 'creator',
            'phone' => '08123456789',
            'address' => 'Jakarta',
            'id_card' => UploadedFile::fake()->image('identity.png'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.user.role', 'creator')
            ->assertJsonPath('data.user.roles', ['creator', 'donor'])
            ->assertJsonPath('data.user.verification_status', 'pending');

        $creator = User::where('email', 'creator@example.test')->firstOrFail();
        $this->assertNotNull($creator->id_card_path);
        Storage::disk('private')->assertExists($creator->id_card_path);
    }

    public function test_creator_accounts_also_have_donor_role_without_changing_primary_role(): void
    {
        $creator = User::factory()->create([
            'role' => 'creator',
            'verification_status' => 'pending',
        ]);

        $this->assertSame('creator', $creator->role);
        $this->assertSame(['creator', 'donor'], $creator->roles);
        $this->assertTrue($creator->hasRole('creator'));
        $this->assertTrue($creator->hasRole('donor'));
        $this->assertFalse($creator->hasRole('admin'));

        Sanctum::actingAs($creator);
        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.role', 'creator')
            ->assertJsonPath('data.roles', ['creator', 'donor']);

        Route::middleware(['auth:sanctum', 'role:donor'])
            ->get('/api/test-donor-role', fn () => response()->noContent());
        $this->getJson('/api/test-donor-role')->assertNoContent();

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/test-donor-role')->assertForbidden();
    }

    public function test_only_approved_creators_can_submit_campaigns_with_exact_milestone_total(): void
    {
        Storage::fake('public');
        $creator = User::factory()->create([
            'role' => 'creator',
            'verification_status' => 'pending',
        ]);
        Sanctum::actingAs($creator);

        $payload = $this->campaignPayload();
        $this->postJson('/api/creator/campaigns', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('status', false);
        $this->assertDatabaseCount('campaigns', 0);

        $creator->update(['verification_status' => 'approved']);
        $payload['milestones'][1]['amount_percentage'] = 40;
        $this->postJson('/api/creator/campaigns', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.milestones.0', 'Total persentase seluruh milestone harus tepat 100%.');
        $this->assertDatabaseCount('campaigns', 0);

        $payload['milestones'][1]['amount_percentage'] = 50;
        $response = $this->post('/api/creator/campaigns', [
            ...$payload,
            'thumbnail' => UploadedFile::fake()->image('campaign.png'),
        ]);
        $response->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonCount(2, 'data.milestones');

        $campaign = Campaign::firstOrFail();
        $this->assertNotNull($campaign->thumbnail_path);
        Storage::disk('public')->assertExists($campaign->thumbnail_path);
        $response->assertJsonPath('data.thumbnail_url', route('campaigns.thumbnail', $campaign));
        $this->get("/api/campaigns/{$campaign->id}/thumbnail")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
        $this->assertDatabaseCount('campaigns', 1);
        $this->assertDatabaseCount('milestones', 2);
    }

    public function test_campaign_thumbnail_must_be_a_supported_image_under_five_megabytes(): void
    {
        $creator = User::factory()->create([
            'role' => 'creator',
            'verification_status' => 'approved',
        ]);
        Sanctum::actingAs($creator);

        $this->post('/api/creator/campaigns', [
            ...$this->campaignPayload(),
            'thumbnail' => UploadedFile::fake()->create('campaign.pdf', 100, 'application/pdf'),
        ])->assertUnprocessable()
            ->assertJsonStructure(['data' => ['errors' => ['thumbnail']]]);

        $this->assertDatabaseCount('campaigns', 0);
    }

    public function test_admin_can_approve_creator_and_campaign_review_is_role_protected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $creator = User::factory()->create([
            'role' => 'creator',
            'verification_status' => 'pending',
        ]);
        $campaign = Campaign::create([
            'creator_id' => $creator->id,
            'title' => 'Test campaign',
            'description' => 'A test campaign.',
            'target_amount' => '1.000000000000000000',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($creator);
        $this->getJson('/api/admin/creators')->assertForbidden();

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/creators/{$creator->id}/review", ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.verification_status', 'approved');
        $this->postJson("/api/admin/campaigns/{$campaign->id}/review", ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_verified_milestone_proof_is_public_only_after_admin_approval(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('milestone-proofs/proof.pdf', 'verified evidence');

        $creator = User::factory()->create(['role' => 'creator']);
        $campaign = Campaign::create([
            'creator_id' => $creator->id,
            'title' => 'Approved campaign',
            'description' => 'A public campaign.',
            'target_amount' => '1.000000000000000000',
            'status' => 'active',
        ]);
        $milestone = Milestone::create([
            'campaign_id' => $campaign->id,
            'title' => 'First milestone',
            'description' => 'Evidence required.',
            'amount_percentage' => 100,
            'proof_path' => 'milestone-proofs/proof.pdf',
            'status' => 'waiting_approval',
        ]);

        $proofUrl = "/api/campaigns/{$campaign->id}/milestones/{$milestone->id}/proof";
        $this->get($proofUrl)->assertNotFound();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson("/api/admin/milestones/{$milestone->id}/review", ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $this->get($proofUrl)->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_api_allows_the_configured_vite_origin(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/campaigns');

        $response->assertNoContent();
        $this->assertSame(
            'http://localhost:5173',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }

    private function campaignPayload(): array
    {
        return [
            'title' => 'Community water project',
            'category' => 'Community',
            'description' => 'A campaign description.',
            'target_amount' => '1.25',
            'milestones' => [
                [
                    'title' => 'First stage',
                    'description' => 'Complete the first stage.',
                    'amount_percentage' => 50,
                ],
                [
                    'title' => 'Final stage',
                    'description' => 'Complete the final stage.',
                    'amount_percentage' => 50,
                ],
            ],
        ];
    }
}
