#!/bin/bash
# Sobe o ambiente completo do Multi-Guard com um comando. Verifica as ferramentas (PHP 8.1+, extensoes, Node 18+,
# Composer, Docker), instala dependencias que faltem, sobe o banco e espera ele ficar saudavel, ativa o
# TimescaleDB, aplica as migrations, semeia a topologia de laboratorio se o banco estiver vazio e gera o token do
# simulador se ele ainda nao existir no .env. Depois inicia em paralelo o servidor web, o agendador (que roda o
# avaliador), o simulador e o Vite, com a saida de cada um prefixada pelo nome. Ctrl+C encerra todos.
# Uso: ./scripts/up.sh            sobe tudo
#      ./scripts/up.sh --no-sim   sobe tudo sem o simulador de telemetria

set -u
cd "$(dirname "$0")/.."

fail() { echo "ERRO: $1"; exit 1; }
step() { echo; echo "==> $1"; }

SIM=1
[ "${1:-}" = "--no-sim" ] && SIM=0

step "Verificando o ambiente"
command -v php >/dev/null || fail "php não encontrado"
php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || fail "PHP 8.1 ou mais novo é necessário (atual: $(php -r 'echo PHP_VERSION;'))"
for ext in pdo_pgsql pgsql mbstring xml curl zip; do
    php -m | grep -qix "$ext" || fail "extensão do PHP ausente: $ext (ex.: sudo apt install php8.3-pgsql php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip)"
done
command -v composer >/dev/null || fail "composer não encontrado"
command -v node >/dev/null || fail "node não encontrado"
node -e 'process.exit(parseInt(process.versions.node) >= 18 ? 0 : 1)' || fail "Node 18 ou mais novo é necessário"
command -v npm >/dev/null || fail "npm não encontrado"
command -v docker >/dev/null || fail "docker não encontrado"
docker info >/dev/null 2>&1 || fail "o Docker não está rodando. Abra o Docker Desktop e confira a integração com o WSL"
[ -f .env ] || fail "arquivo .env ausente"
[ -f config/multiguard.php ] || fail "config/multiguard.php ausente"

[ -d vendor ] || { step "Instalando dependências do PHP"; composer install || fail "composer install falhou"; }
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force
[ -d node_modules ] || { step "Instalando dependências do front"; npm install || fail "npm install falhou"; }

step "Subindo o banco"
docker compose up -d --wait db || fail "o banco não ficou saudável (veja: docker compose logs db)"
docker compose exec -T db psql -U multiguard -d multiguard -qc "CREATE EXTENSION IF NOT EXISTS timescaledb;" || fail "não consegui ativar o TimescaleDB"

step "Aplicando migrations"
php artisan migrate --force || fail "as migrations falharam"

count() { docker compose exec -T db psql -U multiguard -d multiguard -tAc "$1" | tr -d '[:space:]'; }

if [ "$(count 'SELECT count(*) FROM devices')" = "0" ]; then
    step "Semeando a topologia de laboratório"
    php artisan db:seed --class=LabTopologySeeder --force || fail "o seeder falhou"
fi

if [ "$(count 'SELECT count(*) FROM users')" = "0" ]; then
    echo "AVISO: nenhum usuário do console. Rode: php artisan mg:user-create seu@email --name=Nome"
fi

if [ "$SIM" = "1" ] && ! grep -Eq '^MG_LAB_TOKEN=.+' .env; then
    step "Gerando o token do simulador"
    TOKEN=$(php artisan mg:token-create unidade-teste --name=lab-simulate | grep -o 'mg_[A-Za-z0-9]*' | head -1)
    [ -n "$TOKEN" ] || fail "não consegui gerar o token do simulador"
    sed -i '/^MG_LAB_TOKEN=/d' .env
    printf '\nMG_LAB_TOKEN=%s\n' "$TOKEN" >> .env
    php artisan config:clear >/dev/null
fi

step "Iniciando os serviços (Ctrl+C para parar tudo)"
trap 'trap - INT TERM EXIT; echo; echo "Parando os serviços..."; kill 0' INT TERM EXIT

run() { name=$1; shift; "$@" 2>&1 | sed -u "s/^/[$name] /" & }

run web php artisan serve
run agendador php artisan schedule:work
[ "$SIM" = "1" ] && run simulador php artisan mg:lab-simulate --interval=5
run vite npm run dev

echo
echo "Console: http://127.0.0.1:8000/dashboard   Mapa de Rede: http://127.0.0.1:8000/map   Login: http://127.0.0.1:8000/login"
wait
