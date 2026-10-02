<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MakeModuleMultiTenantTest extends TestCase
{
    private ?string $moduleName = null;

    protected function tearDown(): void
    {
        if ($this->moduleName !== null) {
            $this->removerModuloGerado($this->moduleName);
        }

        parent::tearDown();
    }

    public function test_make_module_gera_scaffold_multi_tenant(): void
    {
        $this->moduleName = 'ZzTenant'.uniqid();

        $this->artisan('make:module', ['name' => $this->moduleName])
            ->assertSuccessful();

        $modulePath = app_path("Modules/{$this->moduleName}");
        $singular = \Illuminate\Support\Str::studly(\Illuminate\Support\Str::singular($this->moduleName));
        $plural = \Illuminate\Support\Str::studly(\Illuminate\Support\Str::plural($this->moduleName));
        $table = \Illuminate\Support\Str::snake($plural);

        $modelPath = "{$modulePath}/Models/{$singular}Model.php";
        $routesPath = "{$modulePath}/routes.php";

        $this->assertFileExists($modelPath);
        $this->assertFileExists($routesPath);

        $modelContent = File::get($modelPath);
        $this->assertStringContainsString('extends BaseInstituicaoModel', $modelContent);
        $this->assertStringContainsString("'id_instituicao'", $modelContent);

        $routesContent = File::get($routesPath);
        $this->assertStringContainsString('auth:sanctum', $routesContent);
        $this->assertStringContainsString('ensure.instituicao.membership', $routesContent);

        $migrationFiles = File::glob(database_path("migrations/*_create_{$table}_table.php"));
        $this->assertNotEmpty($migrationFiles);

        $migrationContent = File::get($migrationFiles[0]);
        $this->assertStringContainsString('id_instituicao', $migrationContent);
        $this->assertStringContainsString("->on('instituicoes')", $migrationContent);
    }

    private function removerModuloGerado(string $moduleName): void
    {
        $modulePath = app_path("Modules/{$moduleName}");

        if (File::isDirectory($modulePath)) {
            File::deleteDirectory($modulePath);
        }

        $plural = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($moduleName));
        foreach (File::glob(database_path("migrations/*_create_{$plural}_table.php")) as $migration) {
            File::delete($migration);
        }

        $providersPath = base_path('bootstrap/providers.php');
        $singular = \Illuminate\Support\Str::studly(\Illuminate\Support\Str::singular($moduleName));
        $providerLine = "    App\\Modules\\{$moduleName}\\Providers\\{$singular}ServiceProvider::class,";
        $content = File::get($providersPath);
        $content = str_replace("\n{$providerLine}", '', $content);
        File::put($providersPath, $content);
    }
}
