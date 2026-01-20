Write-Host "======================"
Write-Host "🛠️  DÉPLOIEMENT EN COURS..."
Write-Host "======================"

# Supprimer les fichiers de logs
Write-Host "🧹 Suppression des logs..."
Remove-Item -Force -Recurse -ErrorAction SilentlyContinue "storage\logs\*.log"

# Nettoyer les caches Laravel
Write-Host "🧼 Nettoyage des caches Laravel..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

# Recompiler les caches optimisés
Write-Host "⚡ Compilation des caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Installer les dépendances PHP (prod only)
Write-Host "📦 Installation des dépendances PHP sans dev..."
composer install --no-dev --optimize-autoloader

# Supprimer fichiers inutiles
Write-Host "🧹 Suppression des fichiers inutiles..."
Remove-Item -Force -Recurse -ErrorAction SilentlyContinue `
  tests, .git, .env.example, README.md, webpack.mix.js, vite.config.js, node_modules, package.json, package-lock.json

Write-Host "✅ Déploiement terminé. Prêt pour la production !"
