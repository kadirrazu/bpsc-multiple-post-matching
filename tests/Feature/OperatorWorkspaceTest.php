<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\MatchingBatch;
use App\Models\User;
use Database\Seeders\DesignationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperatorWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function user($role = 'operator')
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_operator_lists_only_owned_workspaces_and_creator_is_not_spoofable(): void
    {
        $a = $this->user();
        $b = $this->user();
        $owned = MatchingBatch::create(['title' => 'Owned workspace', 'created_by' => $a->id]);
        $foreign = MatchingBatch::create(['title' => 'Foreign workspace', 'created_by' => $b->id]);
        $this->actingAs($a)->get('/matching')->assertOk()->assertSee('Owned workspace')->assertDontSee('Foreign workspace')->assertDontSee('User Management');
        $this->post('/matching', ['title' => 'New workspace', 'created_by' => $b->id])->assertRedirect();
        $this->assertSame($a->id, (int) MatchingBatch::where('title', 'New workspace')->first()->created_by);
        $this->get(route('matching.show', $owned))->assertOk();
        $this->get(route('matching.show', $foreign))->assertForbidden();
    }

    public function test_foreign_workspace_routes_reject_operator_before_read_or_mutation(): void
    {
        $owner = $this->user();
        $operator = $this->user();
        $batch = MatchingBatch::create(['title' => 'Foreign', 'created_by' => $owner->id]);
        $post = $batch->posts()->create(['code' => '123456', 'title' => 'Post']);
        $record = $post->records()->create(['source_row' => 2, 'data' => ['name' => 'Candidate'], 'identity' => [], 'status' => 'REVIEW', 'suggestions' => []]);
        $this->actingAs($operator);
        foreach ([route('matching.show', $batch), route('matching.records', [$batch, $post]), route('matching.review', $batch), route('matching.export', $batch), route('matching.post-export', [$batch, $post])] as $url) {
            $this->get($url)->assertForbidden();
        }
        foreach ([route('matching.post', $batch), route('matching.upload', [$batch, $post]), route('matching.decide', [$batch, $record])] as $url) {
            $this->post($url, [])->assertForbidden();
        }
        $this->delete(route('matching.reset', [$batch, $post]), ['confirmation' => 'DELETE'])->assertForbidden();
        $this->assertSame(1, $post->records()->count());
        $this->get('/users')->assertForbidden();
    }

    public function test_unowned_workspaces_are_admin_only(): void
    {
        $batch = MatchingBatch::create(['title' => 'Legacy']);
        $this->actingAs($this->user())->get(route('matching.show', $batch))->assertForbidden();
        $this->actingAs($this->user('admin'))->get(route('matching.show', $batch))->assertOk();
    }

    public function test_operator_profile_allows_only_designation_and_own_password(): void
    {
        $user = $this->user();
        $designation = Designation::create(['name' => 'Programmer', 'slug' => 'programmer', 'is_active' => true, 'sort_order' => 1]);
        $this->actingAs($user);
        $this->put('/profile', ['designation_id' => $designation->id, 'name' => 'Changed', 'email' => 'changed@example.com', 'role' => 'admin'])->assertSessionHasErrors(['name', 'email', 'role']);
        $this->put('/profile', ['designation_id' => $designation->id])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($designation->id, $user->fresh()->designation_id);
        $this->put('/profile/password', ['current_password' => 'password', 'password' => 'newpassword123', 'password_confirmation' => 'newpassword123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_password_controls_and_product_name_are_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('Multiple Post Matching System')->assertDontSee('BPSC Multiple Post Matching System')->assertSee('data-password-toggle', false);
        $this->seed(DesignationSeeder::class);
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/users/create')->assertOk()->assertSee('data-password-toggle', false);
        $this->get(route('users.edit',$admin))->assertOk()->assertSee('data-password-toggle',false);
    }
}
