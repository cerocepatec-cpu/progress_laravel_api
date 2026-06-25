# Progress Business API

API Laravel 13 construite a partir du legacy `system/` et branchee sur la base MySQL `progress_business`.

## Pre-requis

- PHP 8.3 portable du projet: `..\php83.cmd`
- Composer cible projet: `..\composer83.cmd`
- MySQL local avec la base `progress_business`

## Demarrage

Depuis `C:\Users\CERO\Desktop\progress\api`:

```bat
..\php83.cmd artisan serve
```

L'API sera disponible sur `http://127.0.0.1:8000/api/v1`.

## Authentification

- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`

Le login accepte `username`, `member_id` ou `member_code` dans le champ `login`.
L'auth utilise Sanctum et la table `personal_access_tokens`.

## Domaines exposes

- `members`: consultation, mise a jour, creation MLM
- `network`: directs, downline, arbre, permutations, validations de niveaux
- `accounting`: entrees, sorties, transferts, CASH, rapports, grand livre membre
- `inventory`: depots, affectations, inventaire, mouvements, transferts
- `catalog`: pays, villes, categories, produits, unites
- `invoices`: listing, detail, creation
- `settings`: points MAJ, points adhesion, cout d inscription, delai de validation, periodes MAJ
- `notifications`: listing et lecture

## Notes de migration

- La base peut etre reconstruite depuis `progress_business (1).sql` via la migration `2026_06_12_000001_import_progress_business_dump.php`.
- L import normalise automatiquement les incoherences SQL du dump legacy qui bloquent MySQL moderne, sans changer la logique metier.
- La logique MLM 4xN est portee dans `app/Services/MlmService.php`.
- Les mots de passe legacy en clair restent acceptes pour ne pas casser l'existant.
- Les incoherences legacy les plus bloquantes ont ete corrigees dans l'API:
  - activation du mode `accountancy_mode` pour une adhesion
  - correction de la permutation et de la mise a jour Builder
  - securisation transactionnelle des mutations e-wallet, stock et facturation
