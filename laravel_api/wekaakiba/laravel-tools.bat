@echo off
title Outils Laravel - Déploiement / Dev

:menu
cls
echo =====================================
echo    LARAVEL - OUTILS DÉPLOIEMENT
echo =====================================
echo.
echo    1. Deployer pour la production
echo    2. Restaurer l'environnement de dev
echo    0. Quitter
echo.
set /p choice=Choisis une option :

if "%choice%"=="1" goto deploy
if "%choice%"=="2" goto dev
if "%choice%"=="0" exit
goto menu

:deploy
cls
echo 🔄 Exécution du script de déploiement...
powershell -ExecutionPolicy Bypass -File "deploy.ps1"
pause
goto menu

:dev
cls
echo 🔄 Exécution du script de restauration dev...
powershell -ExecutionPolicy Bypass -File "rebuild-dev.ps1"
pause
goto menu
