<?php
/**
 * Teste de isolamento do Core (K9). Percorre todas as rotas registradas e exige que cada uma esteja atras de um dos
 * dois guardas do Core: o token de ingestao (middleware ingest) ou o usuario interno (middleware console). Depois
 * chama cada rota de console sem credencial e com credencial errada, e a ingestao sem token e com token invalido,
 * e exige 401 em todas. Nao usa RefreshDatabase de proposito: o teste so le o banco de desenvolvimento e nunca o
 * apaga. Execucao: php artisan test --filter=CoreIsolationTest
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CoreIsolationTest extends TestCase
{
    private function coreRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (preg_match('#^(_ignition|sanctum)#', $uri)) {
                continue;
            }

            $routes[] = [
                'uri' => $uri,
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'middleware' => $route->gatherMiddleware(),
            ];
        }

        return $routes;
    }

    public function test_no_route_is_left_unprotected(): void
    {
        $open = [];

        foreach ($this->coreRoutes() as $route) {
            if (array_intersect($route['middleware'], ['console', 'ingest']) === []) {
                $open[] = implode('|', $route['methods']).' '.$route['uri'];
            }
        }

        $this->assertSame([], $open, 'Rotas sem guarda do Core: '.implode(', ', $open));
    }

    public function test_console_routes_reject_missing_and_wrong_credentials(): void
    {
        $wrong = ['Authorization' => 'Basic '.base64_encode('ninguem@exemplo.com:senha-errada')];
        $checked = 0;

        foreach ($this->coreRoutes() as $route) {
            if (! in_array('console', $route['middleware'], true) || ! in_array('GET', $route['methods'], true)) {
                continue;
            }

            $url = '/'.ltrim(preg_replace('/\{[^}]+\}/', 'unidade-teste', $route['uri']), '/');

            $this->get($url)->assertStatus(401);
            $this->withHeaders($wrong)->get($url)->assertStatus(401);
            $checked++;
        }

        $this->assertGreaterThan(0, $checked);
    }

    public function test_ingest_rejects_missing_and_invalid_token(): void
    {
        $this->postJson('/api/ingest', [])->assertStatus(401);
        $this->withToken('mg_token_invalido')->postJson('/api/ingest', [])->assertStatus(401);
    }
}
