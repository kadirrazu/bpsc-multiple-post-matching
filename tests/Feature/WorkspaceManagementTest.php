<?php

namespace Tests\Feature;

use App\Models\MatchingBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user($role = 'operator')
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function data($user, $title = 'Workspace')
    {
        $batch = MatchingBatch::create(['title' => $title, 'created_by' => $user->id]);
        $post = $batch->posts()->create(['code' => '123456', 'title' => 'Post', 'headers' => ['name'], 'row_count' => 2]);
        $person = $batch->people()->create();
        $post->records()->create(['matching_person_id' => $person->id, 'source_row' => 2, 'data' => ['name' => 'First'], 'identity' => [], 'status' => 'CONFIRMED', 'suggestions' => [], 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
        $post->records()->create(['source_row' => 3, 'data' => ['name' => 'Pending'], 'identity' => [], 'status' => 'REVIEW', 'suggestions' => [$person->id => ['record_id' => 1]]]);

        return [$batch, $post, $person];
    }

    public function test_owner_edits_title_without_changing_creator_or_imports(): void
    {
        $user = $this->user();
        [$batch,$post] = $this->data($user);
        $other = $this->user();
        $this->actingAs($user);
        $this->get(route('matching.edit', $batch))->assertOk()->assertSee('Save Changes');
        $this->put(route('matching.update', $batch), ['title' => 'Renamed', 'created_by' => $other->id])->assertRedirect(route('matching.index'));
        $this->assertSame('Renamed', $batch->fresh()->title);
        $this->assertSame($user->id, (int) $batch->fresh()->created_by);
        $this->assertSame(2, $post->records()->count());
    }

    public function test_confirmed_delete_removes_all_related_data_and_keeps_other_workspace(): void
    {
        $user = $this->user();
        [$batch,$post,$person] = $this->data($user);
        [$other,$otherPost] = $this->data($user, 'Other workspace');
        $ids = $post->records()->pluck('id');
        $this->actingAs($user);
        $this->get(route('matching.confirm-delete', $batch))->assertOk()->assertSee('Type DELETE to confirm');
        $this->delete(route('matching.destroy', $batch), ['confirmation' => 'WRONG'])->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('matching_batches', ['id' => $batch->id]);
        $this->delete(route('matching.destroy', $batch), ['confirmation' => 'DELETE'])->assertRedirect(route('matching.index'));
        $this->assertDatabaseMissing('matching_batches', ['id' => $batch->id]);
        $this->assertDatabaseMissing('matching_posts', ['id' => $post->id]);
        $this->assertDatabaseMissing('matching_people', ['id' => $person->id]);
        foreach ($ids as $id) {
            $this->assertDatabaseMissing('matching_records', ['id' => $id]);
        }
        $this->assertDatabaseHas('matching_batches', ['id' => $other->id]);
        $this->assertSame(2, $otherPost->records()->count());
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_foreign_operator_cannot_edit_or_delete_and_admin_can_manage(): void
    {
        $owner = $this->user();
        [$batch] = $this->data($owner);
        $this->actingAs($this->user());
        $this->get(route('matching.edit', $batch))->assertForbidden();
        $this->put(route('matching.update', $batch), ['title' => 'Forged'])->assertForbidden();
        $this->get(route('matching.confirm-delete', $batch))->assertForbidden();
        $this->delete(route('matching.destroy', $batch), ['confirmation' => 'DELETE'])->assertForbidden();
        $this->assertSame('Workspace', $batch->fresh()->title);
        $this->actingAs($this->user('admin'))->put(route('matching.update', $batch), ['title' => 'Admin renamed'])->assertRedirect();
        $this->delete(route('matching.destroy', $batch), ['confirmation' => 'DELETE'])->assertRedirect();
        $this->assertDatabaseMissing('matching_batches',['id' => $batch->id]);
    }
}
