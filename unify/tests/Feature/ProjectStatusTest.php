<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ProjectStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_status_endpoint_reports_healthy_projects(): void
    {
        Http::fake([
            'http://project1/health' => Http::response(['status' => 'ok'], 200),
            'http://project2/health' => Http::response(['status' => 'ok'], 200),
            'http://project3/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->getJson('/api/projects/status')
            ->assertOk()
            ->assertJsonPath('projects.0.id', 'project1')
            ->assertJsonPath('projects.0.name', 'Students')
            ->assertJsonPath('projects.0.available', true)
            ->assertJsonPath('projects.1.available', true)
            ->assertJsonPath('projects.2.available', true);
    }

    public function test_school_overview_combines_the_three_project_databases(): void
    {
        Http::fake([
            'http://project1/api/students' => Http::response(['data' => [['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.test', 'program' => 'CS']]]),
            'http://project2/api/faculty' => Http::response(['data' => [['id' => 1, 'name' => 'Grace', 'email' => 'grace@example.test', 'department' => 'Engineering']]]),
            'http://project3/api/courses' => Http::response(['data' => [['id' => 1, 'code' => 'CS101', 'title' => 'Computing', 'instructor' => 'Grace']]]),
        ]);

        $this->getJson('/api/school/overview')
            ->assertOk()
            ->assertJsonPath('sections.0.title', 'Students')
            ->assertJsonPath('sections.0.records.0.name', 'Ada')
            ->assertJsonPath('sections.1.records.0.department', 'Engineering')
            ->assertJsonPath('sections.2.records.0.code', 'CS101');
    }

    public function test_school_overview_marks_an_offline_source_unavailable(): void
    {
        Http::fake([
            'http://project1/api/students' => Http::response(['data' => []]),
            'http://project2/api/faculty' => Http::response([], 503),
            'http://project3/api/courses' => Http::response(['data' => []]),
        ]);

        $this->getJson('/api/school/overview')
            ->assertOk()
            ->assertJsonPath('sections.1.available', false)
            ->assertJsonPath('sections.1.records', []);
    }

    public function test_status_endpoint_marks_a_failed_project_unavailable(): void
    {
        Http::fake([
            'http://project1/health' => Http::response(['status' => 'ok'], 200),
            'http://project2/health' => Http::response(['status' => 'error'], 503),
            'http://project3/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->getJson('/api/projects/status')
            ->assertOk()
            ->assertJsonPath('projects.1.id', 'project2')
            ->assertJsonPath('projects.1.available', false);
    }

    public function test_dashboard_page_loads_when_projects_are_offline(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Unify');
    }

    public function test_health_reports_unify_and_its_database(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'unify',
                'database' => 'connected',
            ]);
    }

    public function test_health_returns_503_when_unify_database_is_unavailable(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new RuntimeException('offline'));

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'error',
                'service' => 'unify',
                'database' => 'unavailable',
            ]);
    }
}
