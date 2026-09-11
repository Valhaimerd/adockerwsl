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
            ->assertJsonPath('projects.0.available', true)
            ->assertJsonPath('projects.1.available', true)
            ->assertJsonPath('projects.2.available', true);
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
