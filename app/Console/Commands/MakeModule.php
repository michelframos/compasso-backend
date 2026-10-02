<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Gera um módulo vertical multi-tenant em app/Modules/{Nome}.
 *
 * Convenções:
 * - Schema em português; SoftDeletes em Models e migrations.
 * - PK auto-increment `id`; coluna `id_instituicao` + FK para `instituicoes`.
 * - Model extends BaseInstituicaoModel (trait PertenceAInstituicao).
 * - Rotas sob auth:sanctum + ensure.instituicao.membership (ResolveInstituicao é global na API).
 * - Documente endpoints no Swagger (anotações OpenAPI nos Controllers).
 */
class MakeModule extends Command
{
    protected $signature = 'make:module {name : O nome do módulo (ex: Produtos)}';

    protected $description = 'Cria um módulo multi-tenant (id_instituicao + BaseInstituicaoModel + middleware tenant).';

    public function handle(): int
    {
        $nameInput = $this->argument('name');

        $moduleName = Str::studly($nameInput);
        $singular = Str::studly(Str::singular($nameInput));
        $plural = Str::studly(Str::plural($nameInput));
        $table = Str::snake($plural);
        $routePrefix = Str::kebab($plural);
        $variable = Str::camel($singular);

        $modulePath = app_path("Modules/{$moduleName}");

        if (File::exists($modulePath)) {
            $this->error("O módulo [{$moduleName}] já existe!");

            return Command::FAILURE;
        }

        $this->info("Criando a estrutura para o módulo: {$moduleName}...");

        $directories = [
            'Contracts',
            'DTOs',
            'Http/Controllers',
            'Http/Requests',
            'Http/Resources',
            'Models',
            'Observers',
            'Providers',
            'Repositories',
            'UseCases',
        ];

        foreach ($directories as $dir) {
            File::makeDirectory("{$modulePath}/{$dir}", 0755, true);
        }

        $context = compact('moduleName', 'singular', 'plural', 'table', 'routePrefix', 'variable');

        $this->generateContracts($modulePath, $context);
        $this->generateDTOs($modulePath, $context);
        $this->generateModels($modulePath, $context);
        $this->generateObservers($modulePath, $context);
        $this->generateRepositories($modulePath, $context);
        $this->generateProviders($modulePath, $context);
        $this->generateRoutes($modulePath, $context);
        $this->generateControllers($modulePath, $context);
        $this->generateRequests($modulePath, $context);
        $this->generateResources($modulePath, $context);
        $this->generateUseCases($modulePath, $context);
        $this->generateMigration($context);

        $this->registerServiceProvider($moduleName, $singular);

        $this->info("Módulo [{$moduleName}] gerado com sucesso em app/Modules/{$moduleName}!");
        $this->comment('Multi-tenant: id_instituicao + BaseInstituicaoModel + ensure.instituicao.membership.');
        $this->comment('Documente endpoints no Swagger. Acrescente role:... nas rotas quando necessário.');

        return Command::SUCCESS;
    }

    /**
     * @param  array{moduleName: string, singular: string, plural: string, table: string, routePrefix: string, variable: string}  $ctx
     */
    private function replacePlaceholders(string $content, array $ctx): string
    {
        return str_replace(
            ['{{Module}}', '{{Singular}}', '{{Plural}}', '{{table}}', '{{routePrefix}}', '{{variable}}'],
            [$ctx['moduleName'], $ctx['singular'], $ctx['plural'], $ctx['table'], $ctx['routePrefix'], $ctx['variable']],
            $content
        );
    }

    /**
     * @param  array{moduleName: string, singular: string, plural: string, table: string, routePrefix: string, variable: string}  $ctx
     */
    private function generateContracts(string $path, array $ctx): void
    {
        $content = <<<'PHP'
<?php

namespace App\Modules\{{Module}}\Contracts;

use App\Modules\{{Module}}\DTOs\{{Singular}}DTO;
use App\Modules\{{Module}}\Models\{{Singular}}Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface {{Singular}}RepositoryInterface
{
    public function paginate(string $search, int $perPage = 15): LengthAwarePaginator;

    public function findById(string|int $id): {{Singular}}Model;

    public function create({{Singular}}DTO $dto): {{Singular}}Model;

    public function update({{Singular}}Model $model, {{Singular}}DTO $dto): {{Singular}}Model;

    public function delete({{Singular}}Model $model): void;

    public function restore(string|int $id): {{Singular}}Model;
}
PHP;

        File::put(
            "{$path}/Contracts/{$ctx['singular']}RepositoryInterface.php",
            $this->replacePlaceholders($content, $ctx)
        );
    }

    /**
     * @param  array{moduleName: string, singular: string, plural: string, table: string, routePrefix: string, variable: string}  $ctx
     */
    private function generateDTOs(string $path, array $ctx): void
    {
        $content = <<<'PHP'
<?php

namespace App\Modules\{{Module}}\DTOs;

class {{Singular}}DTO
{
    public function __construct(
        public readonly array $data
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self($data);
    }
}
PHP;

        File::put(
            "{$path}/DTOs/{$ctx['singular']}DTO.php",
            $this->replacePlaceholders($content, $ctx)
        );
    }

    /**
     * @param  array{moduleName: string, singular: string, plural: string, table: string, routePrefix: string, variable: string}  $ctx
     */
    private function generateModels(string $path, array $ctx): void
    {
        $content = <<<'PHP'
<?php

namespace App\Modules\{{Module}}\Models;

use App\Modules\Core\Models\BaseInstituicaoModel;

class {{Singular}}Model extends BaseInstituicaoModel
{
    protected $table = '{{table}}';

    protected $fillable = [
        'id_instituicao',
        // Colunas em português (compatível com o legado)
    ];
}
PHP;

        File::put(
            "{$path}/Models/{$ctx['singular']}Model.php",
            $this->replacePlaceholders($content, $ctx)
        );
    }

    /**
     * Migration Laravel padrão: id auto-increment + soft deletes.
     *
     * @param  array{moduleName: string, singular: string, plural: string, table: string, routePrefix: string, variable: string}  $ctx
     */
    private function generateMigration(array $ctx): void
    {
        $timestamp = now()->format('Y_m_d_His');
        $table = $ctx['table'];
        $path = database_path("migrations/{$timestamp}_create_{$table}_table.php");

        $content = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela [{$table}]: id auto-increment, id_instituicao, soft deletes, colunas em português.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table): void {
            \$table->id();
            \$table->unsignedBigInteger('id_instituicao');
            // Colunas de negócio em português
            \$table->string('nome', 255)->nullable();
            \$table->string('status', 1)->default('a');
            \$table->timestamps();
            \$table->softDeletes();

            \$table->foreign('id_instituicao')->references('id')->on('instituicoes');
            \$table->index(['id_instituicao', 'id'], '{$table}_id_instituicao_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;

        File::put($path, $content);
        $this->line("Migration: {$path}");
    }

    /**
     * @param  array{moduleName: string, singular: string, plural: string, table: string, routePrefix: string, variable: string}  $ctx
     */
    private function generateObservers(string $path, array $ctx): void
    {
        $content = <<<'PHP'
<?php

namespace App\Modules\{{Module}}\Observers;

use App\Modules\{{Module}}\Models\{{Singular}}Model;

class {{Singular}}Observer
{
    public function creating({{Singular}}Model ${{variable}}): void
    {
        //
    }
}
PHP;

        File::put(
            "{$path}/Observers/{$ctx['singular']}Observer.php",
            $this->replacePlaceholders($content, $ctx)
        );
    }

    /**
     * @param  array{moduleName: string, singular: string, plural: string, table: string, routePrefix: string, variable: string}  $ctx
     */
    private function generateRepositories(string $path, array $ctx): void
    {
        $content = <<<'PHP'
<?php

namespace App\Modules\{{Module}}\Repositories;

use App\Modules\{{Module}}\Contracts\{{Singular}}RepositoryInterface;
use App\Modules\{{Module}}\DTOs\{{Singular}}DTO;
use App\Modules\{{Module}}\Models\{{Singular}}Model;
use Illuminate\Pagination\LengthAwarePaginator;

class {{Singular}}Repository implements {{Singular}}RepositoryInterface
{
    public function paginate(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return {{Singular}}Model::query()
            ->when(
                filled($search),
                fn ($q) => $q->where('id', 'like', "%{$search}%")
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(string|int $id): {{Singular}}Model
    {
        return {{Singular}}Model::query()
            ->where('id', $id)
            ->firstOrFail();
    }

    public function create({{Singular}}DTO $dto): {{Singular}}Model
    {
        return {{Singular}}Model::create($dto->data);
    }

    public function update({{Singular}}Model $model, {{Singular}}DTO $dto): {{Singular}}Model
    {
        $model->update($dto->data);

        return $model->fresh();
    }

    public function delete({{Singular}}Model $model): void
    {
        $model->delete(); // SoftDeletes
    }

    public function restore(string|int $id): {{Singular}}Model
    {
        $model = {{Singular}}Model::withTrashed()
            ->where('id', $id)
            ->firstOrFail();

        $model->restore();

        return $model->fresh();
    }
}
PHP;

        File::put(
            "{$path}/Repositories/{$ctx['singular']}Repository.php",
            $this->replacePlaceholders($content, $ctx)
        );
    }

    /**
     * @param  array{moduleName: string, singular: string, plural: string, table: string, routePrefix: string, variable: string}  $ctx
     */
    private function generateProviders(string $path, array $ctx): void
    {
        $content = <<<'PHP'
<?php

namespace App\Modules\{{Module}}\Providers;

use App\Modules\{{Module}}\Contracts\{{Singular}}RepositoryInterface;
use App\Modules\{{Module}}\Models\{{Singular}}Model;
use App\Modules\{{Module}}\Observers\{{Singular}}Observer;
use App\Modules\{{Module}}\Repositories\{{Singular}}Repository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class {{Singular}}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            {{Singular}}RepositoryInterface::class,
            {{Singular}}Repository::class,
        );
    }

    public function boot(): void
    {
        {{Singular}}Model::observe({{Singular}}Observer::class);

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__ . '/../routes.php');
    }
}
PHP;

        File::put(
            "{$path}/Providers/{$ctx['singular']}ServiceProvider.php",
            $this->replacePlaceholders($content, $ctx)
        );
    }

    /**
     * @param  array{moduleName: string, singular: string, plural: string, table: string, routePrefix: string, variable: string}  $ctx
     */
    private function generateRoutes(string $path, array $ctx): void
    {
        $content = <<<'PHP'
<?php

use App\Modules\{{Module}}\Http\Controllers\{{Singular}}Controller;
use Illuminate\Support\Facades\Route;

/*
| Rotas do módulo {{Module}}.
| Prefixadas com /api pelo {{Singular}}ServiceProvider.
| ResolveInstituicao é aplicado globalmente no grupo api (bootstrap).
*/
Route::middleware(['auth:sanctum', 'ensure.instituicao.membership'])->prefix('{{routePrefix}}')->group(function () {
    Route::get('/', [{{Singular}}Controller::class, 'index']);
    Route::post('/', [{{Singular}}Controller::class, 'store']);
    Route::get('/{id}', [{{Singular}}Controller::class, 'show']);
    Route::put('/{id}', [{{Singular}}Controller::class, 'update']);
    Route::delete('/{id}', [{{Singular}}Controller::class, 'destroy']);
    Route::post('/{id}/restore', [{{Singular}}Controller::class, 'restore']);
});
PHP;

        File::put(
            "{$path}/routes.php",
            $this->replacePlaceholders($content, $ctx)
        );
    }

    /**
     * @param  array{moduleName: string, singular: string, plural: string, table: string, routePrefix: string, variable: string}  $ctx
     */
    private function generateControllers(string $path, array $ctx): void
    {
        $content = <<<'PHP'
<?php

namespace App\Modules\{{Module}}\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Support\ApiResponse;
use App\Modules\{{Module}}\Contracts\{{Singular}}RepositoryInterface;
use App\Modules\{{Module}}\DTOs\{{Singular}}DTO;
use App\Modules\{{Module}}\Http\Requests\Store{{Singular}}Request;
use App\Modules\{{Module}}\Http\Requests\Update{{Singular}}Request;
use App\Modules\{{Module}}\Http\Resources\{{Singular}}Resource;
use App\Modules\{{Module}}\UseCases\Create{{Singular}}UseCase;
use App\Modules\{{Module}}\UseCases\Delete{{Singular}}UseCase;
use App\Modules\{{Module}}\UseCases\Restore{{Singular}}UseCase;
use App\Modules\{{Module}}\UseCases\Update{{Singular}}UseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Documente as operações no Swagger (OA\Tag, OA\Get/Post/...).
 */
class {{Singular}}Controller extends Controller
{
    public function __construct(
        private readonly {{Singular}}RepositoryInterface $repository,
        private readonly Create{{Singular}}UseCase $createUseCase,
        private readonly Update{{Singular}}UseCase $updateUseCase,
        private readonly Delete{{Singular}}UseCase $deleteUseCase,
        private readonly Restore{{Singular}}UseCase $restoreUseCase,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $results = $this->repository->paginate(
            search: $request->get('search', ''),
            perPage: (int) $request->get('per_page', 15)
        );

        return {{Singular}}Resource::collection($results);
    }

    public function store(Store{{Singular}}Request $request): JsonResponse
    {
        $dto = {{Singular}}DTO::fromRequest($request->validated());
        $model = $this->createUseCase->execute($dto);

        return ApiResponse::created(
            new {{Singular}}Resource($model),
            '{{Singular}} criado com sucesso.'
        );
    }

    public function show(string $id): {{Singular}}Resource
    {
        $model = $this->repository->findById($id);

        return new {{Singular}}Resource($model);
    }

    public function update(Update{{Singular}}Request $request, string $id): JsonResponse
    {
        $model = $this->repository->findById($id);
        $dto = {{Singular}}DTO::fromRequest($request->validated());
        $updated = $this->updateUseCase->execute($model, $dto);

        return ApiResponse::success(
            new {{Singular}}Resource($updated),
            '{{Singular}} atualizado com sucesso.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->deleteUseCase->execute($id);

        return ApiResponse::message('{{Singular}} removido com sucesso.');
    }

    public function restore(string $id): JsonResponse
    {
        $model = $this->restoreUseCase->execute($id);

        return ApiResponse::success(
            new {{Singular}}Resource($model),
            '{{Singular}} restaurado com sucesso.'
        );
    }
}
PHP;

        File::put(
            "{$path}/Http/Controllers/{$ctx['singular']}Controller.php",
            $this->replacePlaceholders($content, $ctx)
        );
    }

    /**
     * @param  array{moduleName: string, singular: string, plural: string, table: string, routePrefix: string, variable: string}  $ctx
     */
    private function generateRequests(string $path, array $ctx): void
    {
        $contentStore = <<<'PHP'
<?php

namespace App\Modules\{{Module}}\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Store{{Singular}}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Regras (colunas em português)
            'nome' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'size:1'],
        ];
    }
}
PHP;

        $contentUpdate = <<<'PHP'
<?php

namespace App\Modules\{{Module}}\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Update{{Singular}}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Regras (colunas em português)
            'nome' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'size:1'],
        ];
    }
}
PHP;

        File::put(
            "{$path}/Http/Requests/Store{$ctx['singular']}Request.php",
            $this->replacePlaceholders($contentStore, $ctx)
        );
        File::put(
            "{$path}/Http/Requests/Update{$ctx['singular']}Request.php",
            $this->replacePlaceholders($contentUpdate, $ctx)
        );
    }

    /**
     * @param  array{moduleName: string, singular: string, plural: string, table: string, routePrefix: string, variable: string}  $ctx
     */
    private function generateResources(string $path, array $ctx): void
    {
        $content = <<<'PHP'
<?php

namespace App\Modules\{{Module}}\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class {{Singular}}Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
PHP;

        File::put(
            "{$path}/Http/Resources/{$ctx['singular']}Resource.php",
            $this->replacePlaceholders($content, $ctx)
        );
    }

    /**
     * @param  array{moduleName: string, singular: string, plural: string, table: string, routePrefix: string, variable: string}  $ctx
     */
    private function generateUseCases(string $path, array $ctx): void
    {
        $createContent = <<<'PHP'
<?php

namespace App\Modules\{{Module}}\UseCases;

use App\Modules\{{Module}}\Contracts\{{Singular}}RepositoryInterface;
use App\Modules\{{Module}}\DTOs\{{Singular}}DTO;
use App\Modules\{{Module}}\Models\{{Singular}}Model;

class Create{{Singular}}UseCase
{
    public function __construct(
        private readonly {{Singular}}RepositoryInterface $repository
    ) {}

    public function execute({{Singular}}DTO $dto): {{Singular}}Model
    {
        return $this->repository->create($dto);
    }
}
PHP;

        $updateContent = <<<'PHP'
<?php

namespace App\Modules\{{Module}}\UseCases;

use App\Modules\{{Module}}\Contracts\{{Singular}}RepositoryInterface;
use App\Modules\{{Module}}\DTOs\{{Singular}}DTO;
use App\Modules\{{Module}}\Models\{{Singular}}Model;

class Update{{Singular}}UseCase
{
    public function __construct(
        private readonly {{Singular}}RepositoryInterface $repository
    ) {}

    public function execute({{Singular}}Model $model, {{Singular}}DTO $dto): {{Singular}}Model
    {
        return $this->repository->update($model, $dto);
    }
}
PHP;

        $deleteContent = <<<'PHP'
<?php

namespace App\Modules\{{Module}}\UseCases;

use App\Modules\{{Module}}\Contracts\{{Singular}}RepositoryInterface;

class Delete{{Singular}}UseCase
{
    public function __construct(
        private readonly {{Singular}}RepositoryInterface $repository
    ) {}

    public function execute(string|int $id): void
    {
        $model = $this->repository->findById($id);
        $this->repository->delete($model);
    }
}
PHP;

        $restoreContent = <<<'PHP'
<?php

namespace App\Modules\{{Module}}\UseCases;

use App\Modules\{{Module}}\Contracts\{{Singular}}RepositoryInterface;
use App\Modules\{{Module}}\Models\{{Singular}}Model;

class Restore{{Singular}}UseCase
{
    public function __construct(
        private readonly {{Singular}}RepositoryInterface $repository
    ) {}

    public function execute(string|int $id): {{Singular}}Model
    {
        return $this->repository->restore($id);
    }
}
PHP;

        File::put("{$path}/UseCases/Create{$ctx['singular']}UseCase.php", $this->replacePlaceholders($createContent, $ctx));
        File::put("{$path}/UseCases/Update{$ctx['singular']}UseCase.php", $this->replacePlaceholders($updateContent, $ctx));
        File::put("{$path}/UseCases/Delete{$ctx['singular']}UseCase.php", $this->replacePlaceholders($deleteContent, $ctx));
        File::put("{$path}/UseCases/Restore{$ctx['singular']}UseCase.php", $this->replacePlaceholders($restoreContent, $ctx));
    }

    private function registerServiceProvider(string $moduleName, string $singular): void
    {
        $providersPath = base_path('bootstrap/providers.php');

        if (! File::exists($providersPath)) {
            return;
        }

        $content = File::get($providersPath);
        $newProvider = "    App\\Modules\\{$moduleName}\\Providers\\{$singular}ServiceProvider::class,";

        if (str_contains($content, $newProvider)) {
            return;
        }

        if (str_contains($content, '// Módulos')) {
            $content = str_replace(
                '// Módulos',
                "// Módulos\n{$newProvider}",
                $content
            );
        } else {
            $content = preg_replace(
                '/(\s*\];\s*$)/',
                "\n{$newProvider}\n$1",
                $content
            );
        }

        File::put($providersPath, $content);
        $this->line('ServiceProvider registrado automaticamente em bootstrap/providers.php!');
    }
}
