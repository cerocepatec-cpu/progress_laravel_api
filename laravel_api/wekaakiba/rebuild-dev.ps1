Write-Host "======================"
Write-Host "🔧 RESTAURATION ENV DEV"
Write-Host "======================"

# Nettoyage des caches Laravel
Write-Host "🧼 Nettoyage des caches Laravel..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

# Réinstallation des dépendances PHP avec dev
Write-Host "📦 Installation des dépendances avec dev..."
composer install

# Réinstallation des dépendances JS si package.json existe
if (Test-Path "package.json") {
    Write-Host "📦 Installation des packages npm..."
    npm install
    Write-Host "🔨 Compilation des assets en dev..."
    npm run dev
} else {
    Write-Host "⚠️ Aucun fichier package.json trouvé."
}

# Publication des assets facultative
php artisan vendor:publish --all --force

Write-Host "✅ Environnement de développement prêt !"
