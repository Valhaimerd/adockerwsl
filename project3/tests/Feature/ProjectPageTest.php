<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ProjectPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_home_page_exposes_project_copy_to_vue(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Project 3')
            ->assertSee('Future Work');
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
