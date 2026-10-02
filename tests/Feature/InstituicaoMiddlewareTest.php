<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Http\Middleware\EnsureInstituicaoMembership;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstituicaoMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['resolve.instituicao'])->get('/_test/instituicao-context', function () {
            return response()->json([
                'id' => InstituicaoContext::id(),
                'slug' => InstituicaoContext::slug(),
            ]);
        });

        Route::middleware(['resolve.instituicao'])->get('/_test/instituicao-route/{slug}', function () {
            return response()->json([
                'id' => InstituicaoContext::id(),
                'slug' => InstituicaoContext::slug(),
            ]);
        });

        Route::middleware(['auth:sanctum', 'resolve.instituicao', 'ensure.instituicao.membership'])
            ->get('/_test/instituicao-membership', fn () => response()->json(['ok' => true]));
    }

    protected function tearDown(): void
    {
        InstituicaoContext::clear();

        parent::tearDown();
    }

    public function test_resolve_instituicao_define_contexto_pelo_header(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();

        $response = $this->getJson('/_test/instituicao-context', [
            'X-Tenant-Slug' => 'default',
        ]);

        $response->assertOk()
            ->assertJson([
                'id' => $instituicao->id,
                'slug' => 'default',
            ]);

        $this->assertFalse(InstituicaoContext::has());
    }

    public function test_resolve_instituicao_retorna_404_para_slug_inexistente(): void
    {
        $this->getJson('/_test/instituicao-context', [
            'X-Tenant-Slug' => 'nao-existe',
        ])->assertNotFound();
    }

    public function test_resolve_instituicao_retorna_403_para_instituicao_inativa(): void
    {
        Instituicao::create([
            'slug' => 'inativa',
            'nome_fantasia' => 'Inativa',
            'status' => Instituicao::STATUS_INATIVO,
        ]);

        $this->getJson('/_test/instituicao-context', [
            'X-Tenant-Slug' => 'inativa',
        ])->assertForbidden();
    }

    public function test_resolve_instituicao_passa_sem_slug(): void
    {
        $this->getJson('/_test/instituicao-context')
            ->assertOk()
            ->assertJson([
                'id' => null,
                'slug' => null,
            ]);
    }

    public function test_ensure_membership_exige_contexto(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/_test/instituicao-membership')
            ->assertStatus(422);
    }

    public function test_ensure_membership_nega_usuario_sem_vinculo(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/_test/instituicao-membership', [
                'X-Tenant-Slug' => 'default',
            ])
            ->assertForbidden();
    }

    public function test_ensure_membership_permite_usuario_vinculado(): void
    {
        $user = User::factory()->create();
        $instituicao = Instituicao::where('slug', 'default')->first();

        InstituicaoUsuario::create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $accessToken = $user->createToken('test');
        $accessToken->accessToken->update(['id_instituicao' => $instituicao->id]);

        $this->withToken($accessToken->plainTextToken)
            ->getJson('/_test/instituicao-membership', [
                'X-Tenant-Slug' => 'default',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_resolve_instituicao_aceita_slug_na_rota(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();

        $this->getJson('/_test/instituicao-route/default')
            ->assertOk()
            ->assertJson([
                'id' => $instituicao->id,
                'slug' => 'default',
            ]);
    }

    public function test_ensure_membership_rejeita_pivot_inativo(): void
    {
        $user = User::factory()->create();
        $instituicao = Instituicao::where('slug', 'default')->first();

        InstituicaoUsuario::create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_INATIVO,
        ]);

        $middleware = new EnsureInstituicaoMembership;
        InstituicaoContext::setFromModel($instituicao);

        $request = Request::create('/_test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(403, $response->getStatusCode());
    }
}
