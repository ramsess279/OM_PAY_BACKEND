<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\WelcomeNewUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthService
{
    protected $compteService;
    protected $emailService;

    public function __construct(CompteService $compteService, EmailService $emailService)
    {
        $this->compteService = $compteService;
        $this->emailService = $emailService;
    }

    public function register(array $data)
    {
        // Générer un code OTP à 6 chiffres
        $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        $user = User::create([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'telephone' => $data['telephone'],
            'email' => $data['email'],
            'role' => 'client', // Tous les utilisateurs sont des clients par défaut
            'status' => 'inactive', // Par défaut, le compte est inactif
            'otp_code' => $otpCode, // Code OTP pour l'activation
        ]);

        // Créer un compte par défaut pour l'utilisateur en utilisant le CompteService
        // Cela garantira automatiquement un solde initial de 50 000f
        $compte = $this->compteService->createCompte($user, [
            'type' => 'client',
            'code_pin' => $data['code_pin']
        ]);

        // Envoyer l'email de bienvenue avec le code OTP
        $this->emailService->sendWelcomeEmail($user, $data['code_pin'], $otpCode);

        // Ne pas créer de tokens car le compte n'est pas encore activé
        return [];
    }

    public function login(array $data)
    {
        $user = User::where('telephone', $data['telephone'])->first();

        if (!$user) {
            return null;
        }

        // Vérifier le code PIN sur le compte principal de l'utilisateur
        $compte = $user->comptes()->first();
        if (!$compte || !Hash::check($data['code_pin'], $compte->code_pin)) {
            return null;
        }

        // Vérifier que le compte est actif
        if ($user->status !== 'active') {
            return null;
        }

        // Créer le token d'accès
        $accessTokenResult = $user->createToken('AuthToken');
        $accessTokenResult->token->expires_at = now()->addMinutes(60);
        $accessTokenResult->token->save();
        $accessToken = $accessTokenResult->accessToken;

        // Créer le refresh token
        $refreshTokenResult = $user->createToken('RefreshToken');
        $refreshTokenResult->token->expires_at = now()->addDays(30);
        $refreshTokenResult->token->save();
        $refreshToken = $refreshTokenResult->accessToken;

        // Calculer les dates d'expiration
        $accessTokenExpiresAt = now()->addMinutes(60);
        $refreshTokenExpiresAt = now()->addDays(30);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 60 * 60, // 60 minutes en secondes
            'expires_at' => $accessTokenExpiresAt->toISOString(),
            'refresh_expires_at' => $refreshTokenExpiresAt->toISOString(),
        ];
    }

    public function activate(array $data)
    {
        $user = User::where('telephone', $data['telephone'])->first();

        if (!$user) {
            return null;
        }

        // Vérifier le code PIN sur le compte principal de l'utilisateur
        $compte = $user->comptes()->first();
        if (!$compte || !Hash::check($data['code_pin'], $compte->code_pin)) {
            return null;
        }

        // Vérifier le code OTP
        if ($user->otp_code !== $data['code_otp']) {
            return null;
        }

        // Activer le compte
        $user->update([
            'status' => 'active',
            'otp_code' => null // Supprimer le code OTP après utilisation
        ]);

        return [
            'message' => 'Compte activé avec succès. Vous pouvez maintenant vous connecter.',
            'status' => 'activated'
        ];
    }

    public function logout($user)
    {
        $user->token()->revoke();
    }
}
