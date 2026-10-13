<?php
/**
 * Teste de isolamento do Core (K9) exigindo protecao de todas as rotas internas pelo middleware console ou ingest.
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

            if (preg_match('#^(_ignition|sanctum|login|logout)#', $uri)) {
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
