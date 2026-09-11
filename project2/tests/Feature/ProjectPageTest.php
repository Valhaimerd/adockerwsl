<?php

namespace Tests\Feature;

use App\Models\Faculty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ProjectPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_home_page_exposes_project_copy_to_vue(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Faculty')
            ->assertSee('Faculty Directory');
    }

    public function test_faculty_can_be_created_listed_updated_and_deleted(): void
    {
        $created = $this->postJson('/api/faculty', [
            'name' => 'Grace Dela Cruz',
            'email' => 'grace@example.test',
            'department' => 'Engineering',
        ])->assertCreated()->json('data');

        $this->getJson('/api/faculty')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Grace Dela Cruz');

        $this->putJson('/api/faculty/'.$created['id'], [
            'name' => 'Grace Lim',
            'email' => 'grace@example.test',
            'department' => 'Science',
        ])->assertOk()->assertJsonPath('data.name', 'Grace Lim');

        $this->deleteJson('/api/faculty/'.$created['id'])->assertNoContent();
        $this->assertDatabaseCount((new Faculty)->getTable(), 0);
    }

    public function test_faculty_fields_are_required(): void
    {
        $this->postJson('/api/faculty', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'department']);
    }

    public function test_health_reports_the_service_and_database(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'project2',
                'database' => 'connected',
            ]);
    }

    public function test_health_returns_503_when_the_database_is_unavailable(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new RuntimeException('offline'));

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'error',
                'service' => 'project2',
                'database' => 'unavailable',
            ]);
    }
}
