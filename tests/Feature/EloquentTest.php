<?php

namespace Tests\Feature;

use App\Models\Morningnews;
use App\Models\Project;
use App\Models\Stat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentTest extends TestCase
{
    use RefreshDatabase;

    protected function init(): void
    {
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }


    // TASK: Make the model Morningnews work with DB table "morning_news"
    public function test_create_model_incorrect_table(): void
    {
        $article = ['title' => 'Something', 'news_text' => 'Something'];
        Morningnews::create($article);

        $this->assertDatabaseHas('morning_news', $article);
    }

    // TASK: Write Eloquent query to return the newest 3 verified users
    public function test_get_filtered_list(): void
    {
        $user1 = User::factory()->create(['created_at' => now()->subMinutes(5)]);
        $user2 = User::factory()->create(['created_at' => now()->subMinutes(4)]);
        $user3 = User::factory()->create(['created_at' => now()->subMinutes(3), 'email_verified_at' => NULL]);
        $user4 = User::factory()->create(['created_at' => now()->subMinutes(2)]);
        $user5 = User::factory()->create(['created_at' => now()->subMinute()]);

        $response = $this->get('users');

        // This one should be filtered by "email_verified_at is not null"
        $response->assertDontSeeText("/{$user3->name}/");

        // This one should be filtered out by "limit 3"
        $response->assertDontSeeText("/{$user1->name}/");

        // Do we have the correct order?
        $response->assertSeeTextInOrder([
             '1. ' . $user5->name,
             '2. ' . $user4->name,
             '3. ' . $user2->name,
        ]);
    }

    public function test_find_user_or_show_404_page(): void
    {
        $response = $this->get('users/1');
        $response->assertStatus(404);

        $user = User::factory()->create();
        $response = $this->get('users/1');
        $response->assertStatus(200);
        $response->assertViewHas('user', $user);
    }

    public function test_check_or_create_user(): void
    {
        $response = $this->get('users/check/john/john@john.com');
        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'name' => 'john',
            'email' => 'john@john.com'
        ]);

        // Same parameters - should NOT create a new user
        $this->get('users/check/john/john@john.com');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_create_project() {
        $response = $this->post('projects', ['name' => 'Some name']);
        $response->assertRedirect();
    }

    public function test_mass_update_projects() {
        $project = new Project();
        $project->name = 'Old name';
        $project->save();

        $this->assertDatabaseHas('projects', ['name' => 'Old name']);

        $response = $this->post('projects/mass_update', [
            'old_name' => 'Old name',
            'new_name' => 'New name'
        ]);
        $response->assertRedirect();
        $this->assertDatabaseMissing('projects', ['name' => 'Old name']);
        $this->assertDatabaseHas('projects', ['name' => 'New name']);
    }

    public function test_check_or_update_user(): void
    {
        $response = $this->get('users/check_update/john/john@john.com');
        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'name' => 'john',
            'email' => 'john@john.com'
        ]);

        // Same parameters - should NOT create a new user
        $this->get('users/check_update/john/john2@john.com');
        $this->assertDatabaseHas('users', [
            'name' => 'john',
            'email' => 'john2@john.com'
        ]);
    }

    public function test_mass_delete_users(): void
    {
        User::factory(4)->create();
        $this->assertDatabaseCount('users', 4);

        $response = $this->delete('users', [
            'users' => [1, 2, 3]
        ]);
        $response->assertRedirect();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_soft_delete_projects(): void
    {
        $project = new Project();
        $project->name = 'Some name';
        $project->save();

        $response = $this->delete('projects/' . $project->id);
        $response->assertSee('Some name');
    }

    public function test_active_users(): void
    {
        $user = User::factory()->create(['email_verified_at' => NULL]);

        $response = $this->get('users/active');
        $response->assertDontSee($user->name);
    }

    public function test_insert_observer(): void
    {
        $this->post('projects/stats', ['name' => 'Some name']);

        $statsRow = Stat::first();
        $this->assertEquals(1, $statsRow->projects_count);
    }
}
