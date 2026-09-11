<?php

namespace Tests\Feature;

use App\Models\Course;
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
            ->assertSee('Courses')
            ->assertSee('Course Catalog');
    }

    public function test_courses_can_be_created_listed_updated_and_deleted(): void
    {
        $created = $this->postJson('/api/courses', [
            'code' => 'CS101',
            'title' => 'Introduction to Computing',
            'instructor' => 'Grace Dela Cruz',
        ])->assertCreated()->json('data');

        $this->getJson('/api/courses')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'CS101');

        $this->putJson('/api/courses/'.$created['id'], [
            'code' => 'CS102',
            'title' => 'Programming Fundamentals',
            'instructor' => 'Grace Dela Cruz',
        ])->assertOk()->assertJsonPath('data.code', 'CS102');

        $this->deleteJson('/api/courses/'.$created['id'])->assertNoContent();
        $this->assertDatabaseCount((new Course)->getTable(), 0);
    }

    public function test_course_fields_are_required(): void
    {
        $this->postJson('/api/courses', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'title', 'instructor']);
    }

    public function test_health_reports_the_service_and_database(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'project3',
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
                'service' => 'project3',
                'database' => 'unavailable',
            ]);
    }
}
