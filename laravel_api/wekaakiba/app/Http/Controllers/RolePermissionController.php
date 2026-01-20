<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RolePermissionController extends Controller
{
   // Liste des rôles
   public function indexRoles()
    {
        try {
            $actualuser = Auth::user();
            if (!$actualuser) {
                return $this->errorResponse("Utilisateur non authentifié.", 401);
            }

            $ese = $this->getEse($actualuser->id);
            if (!$ese) {
                return $this->errorResponse("Entreprise non trouvée pour cet utilisateur.", 404);
            }

            $roles = Role::select('id', 'title', 'description', 'enterprise_id')
                ->where('enterprise_id', $ese->id)
                ->orderBy('title')
                ->get();

            $roles = $roles->map(fn($role) => $this->show($role));

            return $this->successResponse("success", $roles);

        } catch (\Throwable $th) {
            return $this->errorResponse("Erreur interne du serveur : " . $th->getMessage(), 500);
        }
    }

    public function removeRoleFromUsers(Request $request)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $role = Role::findOrFail($request->role_id);
            $userIds = $request->user_ids;
            $rolePermissions = $role->permissions->pluck('name')->toArray();

            $results = [
                'role' => $role->name,
                'total_users' => count($userIds),
                'role_removed' => 0,
                'permissions_removed' => 0,
                'errors' => [],
                'status' => 'success'
            ];

            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if (!$user) {
                    $results['errors'][] = "Utilisateur ID {$userId} introuvable.";
                    continue;
                }

                // Si l'utilisateur possède bien le rôle, on le retire
                if ($user->hasRole($role->name)) {
                    $user->removeRole($role->name);
                    $results['role_removed']++;

                    // Supprimer les permissions liées à ce rôle seulement
                    foreach ($rolePermissions as $permName) {
                        if ($user->hasPermissionTo($permName)) {
                            $user->revokePermissionTo($permName);
                            $results['permissions_removed']++;
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => "Rôle '{$role->name}' supprimé avec succès pour {$results['role_removed']} utilisateur(s).",
                'data' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la suppression du rôle : ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRoleUsers(Request $request, $roleId)
    {
        try {
            $role = Role::findOrFail($roleId);

            $perPage = $request->input('per_page', 10);
            
            $users = User::role($role->name)
                ->with('roles', 'permissions')
                ->paginate($perPage);

            return response()->json([
                'message'=>"success",
                'status' =>200,
                'role' => $role->name,
                'total_users' => $users->total(),
                'current_page' => $users->currentPage(),
                'data' => $users->items(),
                'pagination' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'next_page_url' => $users->nextPageUrl(),
                    'prev_page_url' => $users->previousPageUrl(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors du chargement des utilisateurs du rôle : ' . $e->getMessage());
        }
    }
    
    //Store Role
   public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $actualuser = Auth::user();
        if (!$actualuser) {
            return $this->errorResponse("Utilisateur non authentifié.", 401);
        }

        $ese = $this->getEse($actualuser->id);
        if (!$ese) {
            return $this->errorResponse("Entreprise non trouvée pour cet utilisateur.", 404);
        }

        DB::beginTransaction();
        try {
            $existingRole = Role::where('enterprise_id', $ese->id)
                ->whereRaw('LOWER(name) = ?', [strtolower($request->title)])
                ->first();

            if ($existingRole) {
                return $this->errorResponse(
                    "Un rôle portant ce nom existe déjà dans votre base.",
                    409
                );
            }

            $role = Role::create([
                'title'         => $request->title,
                'name'          => strtolower($request->title),
                'description'   => $request->description,
                'user_id'       => $actualuser->id,
                'enterprise_id' => $ese->id,
                'guard_name'    => 'web',
            ]);

            $permissions = $request->permissions;

            if (!empty($permissions)) {
                $existingPermissions = \Spatie\Permission\Models\Permission::whereIn('name', $permissions)
                    ->pluck('name')
                    ->toArray();

                if (count($existingPermissions) !== count($permissions)) {
                    throw new \Exception("Certaines permissions n'existent pas ou sont invalides.");
                }

                $role->syncPermissions($existingPermissions);
            }

            DB::commit();

            return $this->successResponse('success.',$this->show($role));

        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->errorResponse("Erreur interne du serveur : " . $th->getMessage(), 500);
        }
    }

    public function show(Role $role)
    {
        $actualuser = Auth::user();
        if (!$actualuser) {
            return $this->errorResponse("Utilisateur non authentifié.", 401);
        }

        $ese = $this->getEse($actualuser->id);
        if (!$ese) {
            return $this->errorResponse("Entreprise non trouvée pour cet utilisateur.", 404);
        }

        if ($role->enterprise_id !== $ese->id) {
            return $this->errorResponse("Accès non autorisé à ce rôle.", 403);
        }

        $role->load('permissions');

        $permissions = $role->permissions->pluck('name')->toArray();
        $grouped = [];

        foreach ($permissions as $permission) {
            if (strpos($permission, '.') !== false) {
                [$module, $action] = explode('.', $permission);
                $grouped[$module][] = $permission;
            } else {
                $grouped['autres'][] = $permission;
            }
        }

        $data = [
            'id'          => $role->id,
            'title'       => $role->title,
            'description' => $role->description,
            'permissions' => $grouped,
        ];

        return $data;
    }

    // Assigner un rôle aux utilisateurs
   public function assignRole(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::with('permissions')->findOrFail($request->role_id);
        $rolePermissions = $role->permissions->pluck('id')->toArray();
        $roleName = $role->name;

        $users = User::whereIn('id', $request->user_ids)->get();
        $userIds = $users->pluck('id')->toArray();
        $modelType = User::class;

        $results = [
            'role' => $roleName,
            'total_users' => count($userIds),
            'assigned_roles' => 0,
            'assigned_permissions' => 0,
            'status' => 'success',
            'errors' => [],
        ];

        try {
            DB::beginTransaction();

            // --- 1️⃣ Assigner le rôle à tous les utilisateurs (bulk insertOrIgnore)
            $roleInserts = [];
            foreach ($userIds as $userId) {
                $roleInserts[] = [
                    'role_id' => $role->id,
                    'model_type' => $modelType,
                    'model_id' => $userId,
                ];
            }
            $insertedRoles = DB::table('model_has_roles')->insertOrIgnore($roleInserts);
            $results['assigned_roles'] = count($roleInserts);

            // --- 2️⃣ Assigner toutes les permissions liées au rôle (bulk insertOrIgnore)
            $permissionInserts = [];
            foreach ($userIds as $userId) {
                foreach ($rolePermissions as $permissionId) {
                    $permissionInserts[] = [
                        'permission_id' => $permissionId,
                        'model_type' => $modelType,
                        'model_id' => $userId,
                    ];
                }
            }
            $insertedPerms = DB::table('model_has_permissions')->insertOrIgnore($permissionInserts);
            $results['assigned_permissions'] = count($permissionInserts);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $results['status'] = 'error';
            $results['errors'][] = $e->getMessage();
        }

        return response()->json($results);
    }

    // Assigner des permissions à un utilisateur
    public function assignPermissions(Request $request, User $user)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $user->syncPermissions($request->permissions);

        return response()->json([
            'message' => 'Permissions attribuées avec succès',
            'user' => $user->load('permissions')
        ]);
    }

     // ✅ Assigner ou retirer une permission d’un rôle (et propager aux utilisateurs)
     public function toggleRolePermission(Request $request, $roleId)
    {
        $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|exists:permissions,name',
            'assign' => 'required|boolean',
        ]);

        $role = Role::findOrFail($roleId);

        DB::beginTransaction();

        try {
            if ($request->assign) {
                // ✅ 1. Ajouter toutes les permissions au rôle
                $role->givePermissionTo($request->permissions);

                // ✅ 2. Ajouter à tous les utilisateurs ayant ce rôle
                $userIds = User::role($role->name)->pluck('id');

                $permissionIds = Permission::whereIn('name', $request->permissions)->pluck('id');
                $data = [];

                foreach ($userIds as $userId) {
                    foreach ($permissionIds as $permId) {
                        $data[] = [
                            'permission_id' => $permId,
                            'model_type' => User::class,
                            'model_id' => $userId,
                        ];
                    }
                }

                // ⚡ Insertion en masse avec protection contre doublons
                if (!empty($data)) {
                    DB::table('model_has_permissions')->upsert($data, ['permission_id', 'model_type', 'model_id']);
                }

                $message = "Permissions ajoutées au rôle {$role->name} et à ses utilisateurs.";
            } else {
                // ❌ 1. Retirer les permissions du rôle
                $role->revokePermissionTo($request->permissions);

                // ❌ 2. Retirer ces permissions des utilisateurs de ce rôle
                $permissionIds = Permission::whereIn('name', $request->permissions)->pluck('id');

                DB::table('model_has_permissions')
                    ->whereIn('permission_id', $permissionIds)
                    ->where('model_type', User::class)
                    ->whereIn('model_id', function ($query) use ($role) {
                        $query->select('model_id')
                            ->from('model_has_roles')
                            ->where('role_id', $role->id)
                            ->where('model_type', User::class);
                    })
                    ->delete();

                $message = "Permissions retirées du rôle {$role->name} et de ses utilisateurs.";
            }

            DB::commit();

            return response()->json([
                'message' => $message,
                'status' => 200,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la mise à jour des permissions.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    public function deleteRole(Request $request)
    {
        $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        $actualuser = Auth::user();
        if (!$actualuser) {
            return $this->errorResponse("Utilisateur non authentifié.", 401);
        }

        $ese = $this->getEse($actualuser->id);
        if (!$ese) {
            return $this->errorResponse("Entreprise non trouvée pour cet utilisateur.", 404);
        }

        DB::beginTransaction();
        try {
            $role = Role::where('id', $request->role_id)
                        ->where('enterprise_id', $ese->id)
                        ->first();

            if (!$role) {
                return $this->errorResponse("Rôle introuvable ou non autorisé à être supprimé.", 404);
            }
            $role->syncPermissions([]);

            $role->delete();

            DB::commit();

            return $this->successResponse("success",$request->role_id);

        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->errorResponse("Erreur interne du serveur : " . $th->getMessage(), 500);
        }
    }
}
