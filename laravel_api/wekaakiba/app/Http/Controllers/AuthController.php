<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use App\Mail\PasswordResetSuccessMail;
use App\Models\PasswordReset;
use Illuminate\Support\Facades\Cache;
use App\Mail\PasswordChangedSecurityAlert;


class AuthController extends Controller
{
     // Configurable
    protected int $maxAttempts = 5;
    protected int $lockoutSeconds = 15 * 60; // 15 minutes

    public function verifyPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|string|size:4',
        ]);

        $user = $request->user();
        if (!$user) {
            return $this->errorResponse('Utilisateur non authentifié', 401);
        }

        // Vérification si compte déjà bloqué
        if ($user->status === 'disabled') {
            return $this->errorResponse('Compte désactivé. Veuillez contacter l’administrateur.', 403);
        }

        // Vérification du PIN (hashé)
        if (Hash::check($request->pin, $user->pin)) {
            // PIN correct → reset failed_attempts
            $user->failed_attempts = 0;
            $user->pin_locked_until = null;
            $user->save();

            return $this->successResponse('success',$user);
        }

        // PIN incorrect → incrémente failed_attempts
        $user->failed_attempts++;

        if ($user->failed_attempts >= $this->maxAttempts) {
            // Bloquer le compte
            $user->status = 'disabled';
            $user->pin_locked_until = now()->addSeconds($this->lockoutSeconds); // optionnel
        }

        $user->save();

        // Message pour le front
        $remaining = max(0, $this->maxAttempts - $user->failed_attempts);
        $message = $user->status === 'disabled'
            ? "PIN incorrect. Compte temporairement désactivé pour {$this->lockoutSeconds} secondes."
            : "PIN incorrect. Il vous reste {$remaining} tentative(s).";

        return $this->errorResponse($message, 403);
    }

    protected function getCacheKey($userId): string
    {
        return "pin_attempts_user_{$userId}";
    }

    public function resetPin(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_pin' => 'required|string|size:4',
        ]);

        // Récupère l'utilisateur automatiquement via token
        $user = $request->user(); // avec sanctum ou auth middleware

        // Vérifie que le mot de passe fourni correspond
        if (!Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse('Mot de passe incorrect', 422);
        }

        // Vérifie la complexité du PIN
        $weakPins = ['0000','1234','1111','9999','2222','3333','4444','5555','6666','7777','8888'];
        if (in_array($request->new_pin, $weakPins)) {
            return $this->errorResponse('Le nouveau PIN est trop simple', 422);
        }

        // Met à jour le PIN
        $user->pin = Hash::make($request->new_pin);
        $user->save();

        // ✅ Utilisation de successResponse pour la réponse
        return $this->successResponse('success',$user);
    }

    // Étape 1 : demande de reset
    public function forgotPassword(Request $request)
    {
        // Vérifie que le type est fourni
        if (!$request->has('type') || empty($request->type)) {
            return $this->errorResponse('Le type de récupération (email ou phone) est requis', 422);
        }

        // Validation de base
        $request->validate([
            'type' => 'in:email,phone',
            'value' => 'required',
        ]);

        // Vérifie la validité du format selon le type
        if ($request->type === 'email' && !filter_var($request->value, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('Adresse email invalide', 422);
        }

        if ($request->type === 'phone' && !preg_match('/^\+?[0-9]{8,15}$/', $request->value)) {
            return $this->errorResponse('Numéro de téléphone invalide', 422);
        }

        // Recherche de l’utilisateur selon le type
        $user = $request->type === 'email'
            ? User::where('email', $request->value)->first()
            : User::where('user_phone', $request->value)->first();

        if (!$user) {
            return $this->errorResponse('Utilisateur introuvable', 404);
        }

        // Génération du token sécurisé Laravel
        $passwordReset=PasswordReset::generateOTP($user->email);

        // Envoi selon le type choisi
        if ($request->type === 'email') {
            try {
                Mail::raw("Votre code de réinitialisation est : $passwordReset->code", function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject('🔐 Réinitialisation du mot de passe');
                });
            } catch (\Exception $e) {
                return $this->errorResponse('Erreur lors de l’envoi de l’email. Veuillez réessayer plus tard.', 500);
            }
        } else {
            // Envoi SMS (si un service SMS est connecté)
            // SmsService::send($user->user_phone, "Code de réinitialisation : $code");
        }

        return $this->successResponse('Code de réinitialisation envoyé avec succès', [
            'type' => $request->type,
            'value' => $request->value,
        ]);
    }


    // Étape 2 : vérification du code OTP
    public function verifyResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required',
        ]);

        $reset = DB::table('password_resets')
            ->where('email', $request->email)
            ->where('code', $request->code)
            ->first();

        if (!$reset || Carbon::parse($reset->created_at)->addMinutes(15)->isPast()) {
            return $this->errorResponse('Code invalide ou expiré',400);
        }

        return $this->successResponse('success',['token' => $reset->token]);
    }

    // Étape 3 : réinitialisation du mot de passe
  public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'user_phone' => 'nullable|string',
            'token' => 'required',
            'password' => 'required|min:6|confirmed'
        ]);

        DB::beginTransaction();

        try {
            // 🔹 1️⃣ Identifier la méthode utilisée
            $isEmailReset = !empty($request->email);
            $isPhoneReset = !empty($request->user_phone);

            if (!$isEmailReset && !$isPhoneReset) {
                return $this->errorResponse("Veuillez fournir un email ou un numéro de téléphone.", 400);
            }

            // 🔹 2️⃣ Vérifier le token dans password_resets
            $resetQuery = DB::table('password_resets')
                ->where('token', $request->token);

            if ($isEmailReset) {
                $resetQuery->where('email', $request->email);
            } else {
                $resetQuery->where('user_phone', $request->user_phone);
            }

            $reset = $resetQuery->first();

            if (!$reset) {
                DB::rollBack();
                return $this->errorResponse("Token invalide ou expiré.", 400);
            }

            // 🔹 3️⃣ Vérifier expiration (60 minutes)
            $expiresAt = \Carbon\Carbon::parse($reset->created_at)->addMinutes(60);
            if (\Carbon\Carbon::now()->gt($expiresAt)) {
                DB::rollBack();
                return $this->errorResponse("Token expiré.", 400);
            }

            // 🔹 4️⃣ Récupérer l’utilisateur
            $userQuery = \App\Models\User::query();
            if ($isEmailReset) {
                $userQuery->where('email', $request->email);
            } else {
                $userQuery->where('user_phone', $request->user_phone);
            }

            $user = $userQuery->first();

            if (!$user) {
                DB::rollBack();
                return $this->errorResponse("Utilisateur introuvable.", 404);
            }

            // 🔹 5️⃣ Mettre à jour le mot de passe
            $user->password = Hash::make($request->password);
            $user->save();

            // 🔹 6️⃣ Supprimer le token utilisé
            DB::table('password_resets')
                ->where($isEmailReset ? 'email' : 'user_phone', $isEmailReset ? $request->email : $request->user_phone)
                ->delete();


            // 🔹 7️⃣ Notification selon le mode de réinitialisation
            if ($isEmailReset && $user->email) {
                Mail::to($user->email)->send(new PasswordResetSuccessMail($user));
            } elseif ($isPhoneReset && $user->user_phone) {
                $smsText = "Bonjour, votre mot de passe a été réinitialisé avec succès. Si ce n'est pas vous, contactez le support immédiatement.";
                Log::info("SMS à {$user->user_phone}: {$smsText}");

                // Exemple Twilio :
                // Twilio::messages()->create($user->user_phone, [
                //     'from' => env('TWILIO_NUMBER'),
                //     'body' => $smsText
                // ]);
            }

            DB::commit();
            return $this->successResponse("Mot de passe réinitialisé avec succès.", null);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de la réinitialisation du mot de passe: ' . $e->getMessage());
            return $this->errorResponse("Échec de la réinitialisation du mot de passe. " . $e->getMessage(), 500);
        }
    }

    public function updateSensitiveInfo(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Utilisateur non authentifié', 401);
        }

        $data = $request->all();

        // === VALIDATION DE BASE ===
        $validator = Validator::make($data, [
            'email' => 'sometimes|required|email',
            'user_phone' => 'sometimes|required|string',
            'old_pin' => 'sometimes|string',
            'new_pin' => 'sometimes|string|min:4|max:6',
            'confirm_pin' => 'sometimes|string',
            'old_password' => 'sometimes|string',
            'new_password' => 'sometimes|string|min:6',
            'confirm_password' => 'sometimes|string',
            'name' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        /**
         * ======================
         * EMAIL
         * ======================
         */
        if (!empty($data['email'])) {
            $existing = User::where('email', $data['email'])
                            ->where('id', '!=', $user->id)
                            ->first();
            if ($existing) {
                return $this->errorResponse('Cet email appartient déjà à un autre utilisateur', 422);
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->errorResponse('Email invalide', 422);
            }

            $user->email = $data['email'];
        }

        /**
         * ======================
         * TÉLÉPHONE
         * ======================
         */
        if (!empty($data['user_phone'])) {
            $existingPhone = User::where('user_phone', $data['user_phone'])
                                ->where('id', '!=', $user->id)
                                ->first();
            if ($existingPhone) {
                return $this->errorResponse('Ce numéro de téléphone est déjà utilisé', 422);
            }

            if (!preg_match('/^\+[1-9]\d{1,14}$/', $data['user_phone'])) {
                return $this->errorResponse('Numéro de téléphone invalide (format attendu : +243...)', 422);
            }

            $user->user_phone = $data['user_phone'];
        }
        /**
         * ======================
         * PIN
         * ======================
         */
            if (!empty($data['old_pin']) || !empty($data['new_pin']) || !empty($data['confirm_pin'])) {
                // Vérifie que tous les champs PIN sont remplis
                if (empty($data['old_pin']) || empty($data['new_pin']) || empty($data['confirm_pin'])) {
                    return $this->errorResponse('Veuillez renseigner tous les champs du PIN', 422);
                }

                // Vérifie la correspondance des nouveaux PIN
                if ($data['new_pin'] !== $data['confirm_pin']) {
                    return $this->errorResponse('Les nouveaux PIN ne correspondent pas', 422);
                }

                // Vérifie que l'ancien PIN est correct (via Hash)
                if (!Hash::check($data['old_pin'], $user->pin)) {
                    return $this->errorResponse('Ancien PIN incorrect', 422);
                }

                // Vérifie la longueur du PIN
                if (strlen($data['new_pin']) !== 4) {
                    return $this->errorResponse('Le PIN doit comporter exactement 4 chiffres', 422);
                }

                // Vérifie la complexité / PIN faibles
                $weakPins = ['0000', '1234', '1111', '9999', '2222', '3333', '4444', '5555', '6666', '7777', '8888'];
                if (in_array($data['new_pin'], $weakPins)) {
                    return $this->errorResponse('Le nouveau PIN est trop simple', 422);
                }

                // Stocke le nouveau PIN hashé
                $user->pin = Hash::make($data['new_pin']);
            }


            /**
             * ======================
             * MOT DE PASSE
             * ======================
             */
            if (!empty($data['old_password']) || !empty($data['new_password']) || !empty($data['confirm_password'])) {
                if (empty($data['old_password']) || empty($data['new_password']) || empty($data['confirm_password'])) {
                    return $this->errorResponse('Veuillez renseigner tous les champs du mot de passe', 422);
                }

                if (!Hash::check($data['old_password'], $user->password)) {
                    return $this->errorResponse('Ancien mot de passe incorrect', 422);
                }

                if ($data['new_password'] !== $data['confirm_password']) {
                    return $this->errorResponse('Les mots de passe ne correspondent pas', 422);
                }

                // Vérifie que le nouveau mot de passe n’est pas déjà utilisé
                $usersToCheck = User::where('id', '!=', $user->id)
                                    ->where('status', 'active')
                                    ->select('id','password')
                                    ->get();

                foreach ($usersToCheck as $u) {
                    if (Hash::check($data['new_password'], $u->password)) {
                        return $this->errorResponse('Ce mot de passe est déjà utilisé par un autre utilisateur', 422);
                    }
                }

                $user->password = Hash::make($data['new_password']);
            }

        /**
         * ======================
         * AUTRES CHAMPS FILLABLES
         * ======================
         */
        $fillable = $user->getFillable();
        foreach ($fillable as $field) {
            if (in_array($field, ['email','user_phone','pin','password'])) continue;
            if (isset($data[$field])) {
                $user->$field = $data[$field];
            }
        }

      if (!$user->uuid && $user->created_at) {
            $random = substr(uniqid(), -3);
            $user->uuid = 'GOM' . $user->created_at->format('YdmHis') . strtoupper($random);
        }

        $user->save();

        return $this->successResponse('success', $user);
    }
    
   public function updateSensitiveInfoPassword(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Utilisateur non authentifié', 401);
        }

        // === COMPTE BLOQUÉ ?
        if ($user->failed_attempts >= 3) {
            return $this->errorResponse(
                "Votre compte est temporairement bloqué après plusieurs tentatives échouées.",
                423
            );
        }

        // === VALIDATION STRICTE ===
        $validator = Validator::make($request->all(), [
            'old_password'     => 'required|string',
            'new_password'     => 'required|string|min:8',
            'confirm_password' => 'required|string|same:new_password',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        // === VÉRIFICATION DE L'ANCIEN MOT DE PASSE
        if (!Hash::check($request->old_password, $user->password)) {

            $user->failed_attempts++;
            $user->save();

            if ($user->failed_attempts >= 3) {
                $user->status = 'blocked';
                $user->save();
                return $this->errorResponse("Compte bloqué après 3 tentatives échouées.", 423);
            }

            return $this->errorResponse("Ancien mot de passe incorrect", 422);
        }

        // reset compteur après succès
        $user->failed_attempts = 0;

        // === PASSWORD STRENGTH CHECKER
        $new = $request->new_password;

        if (
            !preg_match('/[A-Z]/', $new) ||
            !preg_match('/[a-z]/', $new) ||
            !preg_match('/[0-9]/', $new) ||
            !preg_match('/[\W]/', $new)
        ) {
            return $this->errorResponse(
                "Le mot de passe doit contenir au moins : une majuscule, une minuscule, un chiffre et un symbole.",
                422
            );
        }

        // === MISE À JOUR MDP ===
        $user->password = Hash::make($new);

        // === Génération UUID si manquant
        if (!$user->uuid && $user->created_at) {
            $random = substr(uniqid(), -3);
            $user->uuid = 'GOM'.$user->created_at->format('YdmHis').strtoupper($random);
        }

        $user->save();

        // --- INFOS SÉCURITÉ ---
        $ip = $request->ip();
        $userAgent = $request->header('User-Agent');

        $device  = $this->detectDevice($userAgent);
        $os      = $this->detectOS($userAgent);
        $browser = $this->detectBrowser($userAgent);

        // --- ENVOI EMAIL ASYNC APRÈS LA RÉPONSE ---
        if (!empty($user->email)) {
            Mail::to($user->email)->queue(
                new PasswordChangedSecurityAlert(
                    $user,
                    $ip,
                    $device,
                    $os,
                    $browser
                )
            );
        }

        return $this->successResponse("success", $user);
    }


    // Register
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed', // expects password_confirmation
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'pin' => Hash::make($data['pin'])
        ]);

        // créer token API
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'message' => 'Utilisateur créé',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    // Login
   public function login(Request $request)
    {
        DB::beginTransaction();

        try {
            // 1️⃣ Validation des champs
            $data = $request->validate([
                'login'    => 'required|string',
                'password' => 'required|string',
            ]);

            $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'user_name';

            // 2️⃣ Récupération de l'utilisateur avec entreprise
            $user = User::leftJoin('usersenterprises as UE', 'users.id', '=', 'UE.user_id')
                        ->where('users.' . $field, $data['login'])
                        ->where('users.status', 'enabled')
                        ->select('users.*', 'UE.enterprise_id')
                        ->first();

            if (!$user) {
                return $this->errorResponse('Utilisateur non trouvé ou compte désactivé.', 404);
            }

            if (!$user || !Hash::check($data['password'], $user->password)) {
                return $this->errorResponse('Les identifiants sont invalides.', 401);
            }

            // 3️⃣ Entreprise active
            $actualEse = $this->getEse($user->id);
            if ($actualEse) {
                $user->enterprise_id = $actualEse['id'];
            }

            // 4️⃣ Supprimer anciens tokens
            $user->tokens()->delete();

            // 5️⃣ Créer un token via Sanctum
            $tokenExpiration = now()->addMinutes(60);
            $token = $user->createToken('api_token', ['*']);
            $plainTextToken = $token->plainTextToken;

            // 6️⃣ Mettre à jour expires_at dans la table
            $token->accessToken->update([
                'expires_at' => $tokenExpiration,
            ]);

            // 7️⃣ Créer un refresh token (1 jour)
            $refreshTokenString = Str::random(64);
            $refreshToken = RefreshToken::create([
                'user_id'    => $user->id,
                'token'      => hash('sha256', $refreshTokenString),
                'expires_at' => now()->addDay(),
                'revoked'    => false,
            ]);

            // 8️⃣ Gestion super_admin (rôles et permissions)
            if ($user->user_type === 'super_admin') {
                if ($user->roles()->count() === 0) {
                    $enterpriseRoles = \Spatie\Permission\Models\Role::where('enterprise_id', $user->enterprise_id)->get();
                    if ($enterpriseRoles->isNotEmpty()) {
                        $user->syncRoles($enterpriseRoles);
                    }
                }

                if ($user->permissions()->count() === 0) {
                    $allPermissions = \Spatie\Permission\Models\Permission::all();
                    $user->syncPermissions($allPermissions);
                }
            }

            DB::commit();
            $permissions=$user->getAllPermissions()->pluck('name')->toArray();
            // 9️⃣ Retour API
            return $this->successResponse('success', [
                'user'            => $user,
                'enterprise'      => $actualEse,
                'defaultmoney'    => $this->defaultmoney($actualEse['id'] ?? null),
                'access_token'    => $plainTextToken,       // token à utiliser pour Authorization Bearer
                'expires_in'      => 3600,
                'permissions'     =>$permissions,                   // 10 minutes en secondes
                'refresh_token'   => $refreshTokenString,
                'refresh_expires_at' => $refreshToken->expires_at,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('error', $e->getMessage(), 500);
        }
    }

   public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
            'password'      => 'required|string',
        ]);

        $hashed = hash('sha256', $request->refresh_token);

        $tokenRecord = RefreshToken::where('token', $hashed)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenRecord) {
            return $this->errorResponse('Refresh token invalide ou expiré', 401);
        }

        $user =$tokenRecord->user;

        if ($user->status !== 'enabled') {
            return $this->errorResponse('Compte désactivé. Contactez l’administrateur.', 403);
        }

        // Vérifie mot de passe ou PIN
        $isValid = Hash::check($request->password, $user->password) 
                || (!empty($user->pin) && Hash::check($request->password, $user->pin));

        if (!$isValid) {
            $user->failed_attempts = ($user->failed_attempts ?? 0) + 1;

            if ($user->failed_attempts >= 4) {
                $user->status = 'disabled';
                $user->save();
                return $this->errorResponse('Compte désactivé après plusieurs tentatives échouées.', 403);
            }

            $user->save();
            return $this->errorResponse('Mot de passe ou PIN incorrect.', 401);
        }

        // Reset compteur d'échecs
        $user->failed_attempts = 0;
        $user->save();

        // Supprime anciens access tokens
        $user->tokens()->delete();

        // Crée un nouveau access token via Sanctum
        $tokenExpiration = now()->addMinutes(60);
        $token = $user->createToken('api_token', ['*']);
        $plainTextToken = $token->plainTextToken;

        // Mettre à jour expires_at
        $token->accessToken->update([
            'expires_at' => $tokenExpiration,
        ]);

        return $this->successResponse('success', [
            'user'          => $user,
            'access_token'  => $plainTextToken,
            'expires_in'    =>3600 ,// 10 minutes
            'token_created_at'=>$token->accessToken->created_at
        ]);
    }

    // Logout (révocation du token courant)
   public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
            return $this->successResponse('success', null);
        }

        return $this->errorResponse('Utilisateur non authentifié ou token invalide.', 401);
    }

    // Récupérer profil
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
