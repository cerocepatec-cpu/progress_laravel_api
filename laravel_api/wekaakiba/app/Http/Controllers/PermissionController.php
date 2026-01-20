<?php
// app/Http/Controllers/PermissionController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function assignRole(Request $request)
    {
        $user = User::findOrFail($request->user_id);
        $role = Role::findByName($request->role);

        $user->assignRole($role);
        return response()->json(['message' => 'Rôle attribué avec succès']);
    }

   public function givePermissions(Request $request)
    {
        $request->validate([
            'users' => 'required|array|min:1',
            'permissions' => 'required|array|min:1'
        ]);

        $authUser = Auth::user();
        $adminEse = $this->getEse($authUser->id);

        DB::beginTransaction();
        try {
            $users = User::whereIn('id', $request->users)
                ->where('status','enabled')
                ->get();

            $success = [];
            $skipped = [];
            $errors = [];

            foreach ($request->users as $userId) {
                $user = $users->firstWhere('id', $userId);

                if (!$user) {
                    $errors[] = "Utilisateur ID $userId introuvable ou désactivé.";
                    continue;
                }

                $userEse = $this->getEse($user->id);
                if (!$userEse || $userEse['id'] != $adminEse['id']) {
                    $errors[] = "Utilisateur {$user->name} n'appartient pas à votre entreprise.";
                    continue;
                }

                $toAssign = array_filter($request->permissions, fn($perm) => !$user->hasPermissionTo($perm));

                if (!empty($toAssign)) {
                    $user->givePermissionTo($toAssign);
                    $success[] = "Permissions attribuées à {$user->name}: " . implode(', ', $toAssign);
                }

                $already = array_filter($request->permissions, fn($perm) => $user->hasPermissionTo($perm));
                foreach ($already as $perm) {
                    $skipped[] = "Permission {$perm} déjà attribuée à {$user->name}.";
                }
            }

            DB::commit();

            return response()->json([
                'success' => $success,
                'skipped' => $skipped,
                'errors' => $errors,
                'message' => 'Traitement terminé'
            ]);
        } catch (\Exception $e) {
            DB::rollBack(); // ROLLBACK TRANSACTION en cas d'erreur
            return response()->json(['message' => 'Erreur lors de l’attribution des permissions', 'error' => $e->getMessage()], 500);
        }
    }

    public function checkPermission(Request $request)
    {
        $user = User::findOrFail($request->user_id);
        $permission = $request->permission;

        return response()->json([
            'has_permission' => $user->can($permission),
        ]);
    }

    public function groupedPermissionsWithUser($userId = null)
    {
        try {
            // Vérifie si un ID est fourni
            if (!$userId) {
                return $this->errorResponse('Identifiant utilisateur requis', 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return $this->errorResponse('Utilisateur introuvable', 404);
            }

            // Récupère toutes les permissions (directes + via rôle)
            $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

            $grouped = [];

            foreach ($userPermissions as $permission) {
                if (strpos($permission, '.') !== false) {
                    [$prefix, $action] = explode('.', $permission, 2);
                } else {
                    $prefix = 'others';
                }

                $grouped[$prefix][] = $permission;
            }

            return $this->successResponse("success",$grouped);
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des permissions : ' . $e->getMessage(), 500);
        }
    }

    public function ungroupedPermissionsForUser($userId = null)
    {
        try {
            // Vérifie si un ID est fourni
            if (!$userId) {
                return $this->errorResponse('Identifiant utilisateur requis', 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return $this->errorResponse('Utilisateur introuvable', 404);
            }

            // Récupère toutes les permissions (directes + via rôle)
            $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

            return $this->successResponse("success",$userPermissions);
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des permissions : ' . $e->getMessage(), 500);
        }
    }


    public function groupedPermissions()
    {
        $permissions = Permission::all()->pluck('name')->toArray();
        $grouped = [];

        foreach ($permissions as $permission) {
            [$prefix, $action] = explode('.', $permission);
            $grouped[$prefix][] = $permission;
        }

        return response()->json($grouped);
    }

    public function getPermissionUsers(Request $request)
    {
        $request->validate([
            'module' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'per_page' => 'nullable|integer|min:1'
        ]);

        try {
            $perPage = $request->input('per_page', 10);
            $module = $request->input('module');
            $permissions = $request->input('permissions', []);

            $query = User::query()->with('roles', 'permissions');

            if ($module) {
                // Si un module est fourni, on cherche tous les utilisateurs ayant au moins une permission de ce module
                $query->whereHas('permissions', function($q) use ($module) {
                    $q->where('name', 'like', "$module.%");
                });
            } elseif (!empty($permissions)) {
                $query->whereHas('permissions', function($q) use ($permissions) {
                    $q->whereIn('name', $permissions);
                });
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous devez fournir soit un module, soit une liste de permissions.'
                ], 400);
            }

            $users = $query->paginate($perPage);

            $usersCollection = collect($users->items())->map(function($user) use ($module, $permissions) {
                if ($module) {
                    $userPermissions = $user->permissions->filter(function($perm) use ($module) {
                        return str_starts_with($perm->name, $module . '.');
                    })->pluck('name');
                } else {
                    $userPermissions = $user->permissions->filter(function($perm) use ($permissions) {
                        return in_array($perm->name, $permissions);
                    })->pluck('name');
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                    'permissions' => $userPermissions
                ];
            });

            return response()->json([
                'status' => 200,
                'message' => 'success',
                'total_users' => $users->total(),
                'data' =>$usersCollection,
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
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors du chargement des utilisateurs : ' . $e->getMessage()
            ], 500);
        }
    }

    public function removeUsersFromPermission(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'integer|exists:users,id',
                'module' => 'nullable|string',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string'
            ]);

            $userIds = $validated['user_ids'];
            $module = $validated['module'] ?? null;
            $permissions = $validated['permissions'] ?? null;

            // Si module ET permissions sont absents → erreur claire
            if (empty($module) && empty($permissions)) {
                return $this->errorResponse(
                    "Vous devez spécifier soit un module, soit une liste de permissions à supprimer.",
                    400
                );
            }

            $affectedCount = 0;
            $errors = [];

            foreach ($userIds as $userId) {
    $user = User::find($userId);
    if (!$user) {
        $errors[] = "Utilisateur ID {$userId} introuvable.";
        continue;
    }

    // Supprimer les permissions directes
    if (!empty($permissions)) {
        foreach ($permissions as $perm) {
            if ($user->hasDirectPermission($perm)) {
                $user->revokePermissionTo($perm);
                $affectedCount++;
            }

            // Supprimer la permission héritée via rôle
            foreach ($user->roles as $role) {
                if ($role->hasPermissionTo($perm)) {
                    $user->removeRole($role->name); // removeRole attend un string ou Role object
                    $affectedCount++;
                }
            }
        }
    }

    // Si module est spécifié, retirer le rôle correspondant
    if (!empty($module)) {
        $roleName = ucfirst($module);
        if ($user->hasRole($roleName)) {
            $user->removeRole($roleName);
        }
    }
}


            return $this->successResponse('success',[
                'total_users' => count($userIds),
                'affected_permissions' => $affectedCount,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Erreur lors de la suppression des permissions : ' . $e->getMessage(),
                500
            );
        }
    }

}
