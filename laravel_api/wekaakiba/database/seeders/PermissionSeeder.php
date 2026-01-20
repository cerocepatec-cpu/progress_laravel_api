<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        // 1️⃣ Création des rôles
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web'],
            [
                'title' => 'Super Administrateur',
                'enterprise_id' => 1,
                'user_id' => 1,
                'permissions' => '[]'
            ]
        );

        $directeur = Role::firstOrCreate(
            ['name' => 'directeur', 'guard_name' => 'web'],
            [
                'title' => 'Directeur des opérations',
                'enterprise_id' => 1,
                'user_id' => 1,
                'permissions' => '[]'
            ]
        );

        $gerant = Role::firstOrCreate(
            ['name' => 'gerant', 'guard_name' => 'web'],
            [
                'title' => 'Gérant des activités',
                'enterprise_id' => 1,
                'user_id' => 1,
                'permissions' => '[]'
            ]
        );

        $comptable = Role::firstOrCreate(
            ['name' => 'comptable', 'guard_name' => 'web'],
            [
                'title' => 'Comptable ou caissiers',
                'enterprise_id' => 1,
                'user_id' => 1,
                'permissions' => '[]'
            ]
        );

        $superviseur = Role::firstOrCreate(
            ['name' => 'superviseur', 'guard_name' => 'web'],
            [
                'title' => 'Superviseur des A.T',
                'enterprise_id' => 1,
                'user_id' => 1,
                'permissions' => '[]'
            ]
        );

        $membre = Role::firstOrCreate(
            ['name' => 'membre', 'guard_name' => 'web'],
            [
                'title' => 'Membre simple sans privilège',
                'enterprise_id' => 1,
                'user_id' => 1,
                'permissions' => '[]'
            ]
        );

        $agentTerrain = Role::firstOrCreate(
            ['name' => 'agent_terrain', 'guard_name' => 'web'],
            [
                'title' => 'Agent de terrain ou Collecteur',
                'enterprise_id' => 1,
                'user_id' => 1,
                'permissions' => '[]'
            ]
        );

        // 2️⃣ Création des permissions (tous les blocs que nous avons construits)
        $permissions = [
            // Facturation
            'facturation.all',
            'facturation.view',
            'facturation.add',
            'facturation.edit',
            'facturation.delete',
            'facturation.can_sell',
            'facturation.cash',
            'facturation.credit',
            'facturation.payer_credit',
            'facturation.proforma',
            'facturation.duplicate',
            'facturation.tva_change',
            'facturation.give_reduction',
            'facturation.invoice_paused',
            'facturation.change_pos_settings',
            'facturation.change_print_settings',
            'facturation.change_default_print_format',
            'facturation.partager',
            'facturation.invoice_message_footer',

            // Départements
            'departements.all',
            'departements.view',
            'departements.add',
            'departements.edit',
            'departements.delete',
            'departements.affect_user',

            // Produits
            'produits.all',
            'produits.view',
            'produits.add',
            'produits.edit',
            'produits.delete',

            // Parrainage
            'parrainage.all',
            'parrainage.view',
            'parrainage.add',
            'parrainage.edit',
            'parrainage.delete',

            // Clients
            'clients.all',
            'clients.view',
            'clients.add',
            'clients.edit',
            'clients.delete',

            // Stock
            'stock.all',
            'stock.view',
            'stock.add',
            'stock.edit',
            'stock.delete',
            'stock.stock_valuation',
            'stock.entry',
            'stock.withdraw',
            'stock.make_ask',
            'stock.validate_ask',
            'stock.make_transfer',
            'stock.validate_transfer',

            // Agents
            'agents.all',
            'agents.view',
            'agents.add',
            'agents.edit',
            'agents.delete',
            'agents.create_account',
            'agents.see_account',
            'agents.see_account_sold',
            'agents.disable',

            // Dépôts
            'depots.all',
            'depots.view',
            'depots.add',
            'depots.edit',
            'depots.view_inventory',
            'depots.delete',

            // Tables
            'tables.all',
            'tables.view',
            'tables.add',
            'tables.edit',
            'tables.delete',

            // Categories
            'services_categories.all',
            'services_categories.view',
            'services_categories.add',
            'services_categories.edit',
            'services_categories.delete',

            // UOM
            'uom.all',
            'uom.view',
            'uom.add',
            'uom.edit',
            'uom.delete',

            // Serveurs
            'serveurs.all',
            'serveurs.view',
            'serveurs.add',
            'serveurs.edit',
            'serveurs.delete',

            // Commandes
            'commandes.all',
            'commandes.view',
            'commandes.add',
            'commandes.edit',
            'commandes.delete',

            // Factures
            'factures.all',
            'factures.view',
            'factures.add',
            'factures.edit',
            'factures.delete',

            // Dépenses
            'depenses.all',
            'depenses.view',
            'depenses.add',
            'depenses.edit',
            'depenses.delete',
            'depenses.partager',

            // Entrée argent
            'entree_argent.all',
            'entree_argent.view',
            'entree_argent.add',
            'entree_argent.edit',
            'entree_argent.delete',
            'entree_argent.partager',

            // Clôtures
            'clotures.all',
            'clotures.view',
            'clotures.add',
            'clotures.edit',
            'clotures.delete',
            'clotures.turn_back',
            'clotures.receive',
            'clotures.correct',
            'clotures.send',

            // Comptes
            'comptes.all',
            'comptes.view',
            'comptes.add',
            'comptes.edit',
            'comptes.delete',

            // Fournisseurs
            'fournisseurs.all',
            'fournisseurs.view',
            'fournisseurs.add',
            'fournisseurs.edit',
            'fournisseurs.delete',

            // Marge brute
            'marge_brute.all',
            'marge_brute.view',
            'marge_brute.add',
            'marge_brute.edit',
            'marge_brute.delete',

            // Rapports
            'rapports.all',
            'rapports.view',
            'rapports.stock',
            'rapports.sell',
            'rapports.finances',
            'rapports.cashbook',
            'rapports.others',

            // Caisses
            'caisses.all',
            'caisses.view',
            'caisses.add',
            'caisses.entry',
            'caisses.withdraw',
            'caisses.report',
            'caisses.can_be_affected',
            'caisses.can_make_transfert',
            'caisses.can_see_history',

            // Entreprises
            'entreprise.all',
            'entreprise.view',
            'entreprise.edit',
            'entreprise.delete',

            // Marges dépenses
            'marges_depenses.all',
            'marges_depenses.view',
            'marges_depenses.edit',
            'marges_depenses.delete',
            'marges_depenses.affect_users',

            // Syncing
            'syncing.send',
            'syncing.receive',
            'syncing.offline',
            'syncing.online',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 3️⃣ Attribution des permissions selon rôle

        // Super Admin : toutes les permissions
        $superAdmin->syncPermissions(Permission::all());

        // Directeur : presque toutes, sauf les accès trop techniques (comme sync offline)
        $directeur->syncPermissions(Permission::whereNotIn('name', [
            'syncing.offline',
            'syncing.online'
        ])->get());

        // Gérant : gestion commerciale + stock + facturation
        $gerant->syncPermissions(Permission::whereIn('name', [
            'facturation.view',
            'facturation.add',
            'facturation.edit',
            'facturation.delete',
            'stock.view',
            'stock.add',
            'stock.edit',
            'stock.delete',
            'produits.view',
            'produits.add',
            'produits.edit',
            'produits.delete',
            'clients.view',
            'clients.add',
            'clients.edit',
            'clients.delete',
            'parrainage.view',
            'parrainage.add',
            'parrainage.edit',
            'parrainage.delete',
            'departements.view',
            'departements.affect_user',
            'commandes.view',
            'commandes.add',
            'commandes.edit',
            'commandes.delete',
        ])->get());

        // Comptable : finances et comptes
        $comptable->syncPermissions(Permission::whereIn('name', [
            'depenses.view',
            'depenses.add',
            'depenses.edit',
            'depenses.delete',
            'depenses.partager',
            'entree_argent.view',
            'entree_argent.add',
            'entree_argent.edit',
            'entree_argent.delete',
            'entree_argent.partager',
            'clotures.view',
            'clotures.receive',
            'clotures.correct',
            'clotures.send',
            'comptes.view',
            'comptes.add',
            'comptes.edit',
            'comptes.delete',
            'caisses.view',
            'caisses.entry',
            'caisses.withdraw',
            'caisses.report',
            'caisses.can_be_affected',
            'rapports.view',
            'rapports.finances',
            'rapports.cashbook',
        ])->get());

        // Superviseur : suivi général, rapports, stock
        $superviseur->syncPermissions(Permission::whereIn('name', [
            'rapports.view',
            'rapports.stock',
            'rapports.sell',
            'rapports.others',
            'stock.view',
            'stock.entry',
            'stock.withdraw',
        ])->get());

        // Membre : accès limité aux commandes/factures
        $membre->syncPermissions(Permission::whereIn('name', [
            'commandes.view',
            'facturation.view',
            'facturation.proforma',
        ])->get());

        // Agent de terrain : accès uniquement à clients et ventes
        $agentTerrain->syncPermissions(Permission::whereIn('name', [
            'clients.view',
            'clients.add',
            'facturation.view',
            'facturation.can_sell',
            'facturation.cash',
            'facturation.credit',
            'facturation.payer_credit',
        ])->get());
    }
}