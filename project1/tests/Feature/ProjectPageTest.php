<?php

namespace Tests\Feature;

use App\Models\Student;
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
            ->assertSee('Students')
            ->assertSee('Student Directory');
    }

    public function test_students_can_be_created_listed_updated_and_deleted(): void
    {
        $created = $this->postJson('/api/students', [
            'name' => 'Ada Santos',
            'email' => 'ada@example.test',
            'program' => 'Computer Science',
        ])->assertCreated()->json('data');

        $this->getJson('/api/students')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ada Santos');

        $this->putJson('/api/students/'.$created['id'], [
            'name' => 'Ada Reyes',
            'email' => 'ada@example.test',
            'program' => 'Information Technology',
        ])->assertOk()->assertJsonPath('data.name', 'Ada Reyes');

        $this->deleteJson('/api/students/'.$created['id'])->assertNoContent();
        $this->assertDatabaseCount((new Student)->getTable(), 0);
    }

    public function test_student_fields_are_required(): void
    {
        $this->postJson('/api/students', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'program']);
    }

    public function test_health_reports_the_service_and_database(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'project1',
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
                'service' => 'project1',
                'database' => 'unavailable',
            ]);
    }
}
