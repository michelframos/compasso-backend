#!/bin/bash
set -e

echo "─────────────────────────────────────────────"
echo " Camerata Backend - Inicializando..."
echo "─────────────────────────────────────────────"

# Aguarda o banco de dados estar disponível
echo "⏳ Aguardando banco de dados..."
until php artisan db:monitor > /dev/null 2>&1; do
    sleep 2
    echo "   ↪ Banco ainda não disponível, tentando novamente..."
done
echo "✅ Banco de dados disponível!"

# Gera a APP_KEY se não existir
if [ -z "$APP_KEY" ]; then
    echo "🔑 Gerando APP_KEY..."
    php artisan key:generate --force
fi

# Garante que o diretório de cache existe
mkdir -p /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/bootstrap/cache

# Cria o link simbólico do storage
echo "🔗 Criando link do storage..."
php artisan storage:link --force 2>/dev/null || true

# Roda as migrations
echo "🗄️  Rodando migrations..."
php artisan migrate --force

# Otimiza a aplicação para produção
echo "⚡ Otimizando aplicação..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "─────────────────────────────────────────────"
echo " Inicialização concluída! Iniciando serviços..."
echo "─────────────────────────────────────────────"

# Verifica se foi passado um comando diferente (ex: queue worker)
if [ "$#" -gt 0 ]; then
    echo "🚀 Executando comando customizado: $@"
    exec "$@"
fi

# Inicia Nginx + PHP-FPM via Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
