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
        $user = User::create([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'telephone' => $data['telephone'],
            'email' => $data['email'],
            'role' => 'client', // Tous les utilisateurs sont des clients par défaut
        ]);

        // Créer un compte par défaut pour l'utilisateur en utilisant le CompteService
        // Cela garantira automatiquement un solde initial de 50 000f
        $compte = $this->compteService->createCompte($user, [
            'type' => 'client',
            'code_pin' => $data['code_pin']
        ]);

        // Envoyer l'email de bienvenue via le service email robuste
        $this->emailService->sendWelcomeEmail($user, $data['code_pin']);

        $tokenResult = $user->createToken('AuthToken');
        $tokenResult->token->expires_at = now()->addMinutes(60);
        $tokenResult->token->save();
        $token = $tokenResult->accessToken;

        $refreshTokenResult = $user->createToken('RefreshToken');
        $refreshTokenResult->token->expires_at = now()->addDays(30);
        $refreshTokenResult->token->save();
        $refreshToken = $refreshTokenResult->accessToken;

        // Calculer les dates d'expiration
        $accessTokenExpiresAt = now()->addMinutes(60);
        $refreshTokenExpiresAt = now()->addDays(30);

        return [
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'telephone' => $user->telephone,
            'email' => $user->email,
            'numero_compte' => $compte->numero_compte,
            'code_pin' => $data['code_pin'], // Code PIN en clair pour référence
            'solde' => $compte->solde,
            'date_creation' => $compte->date_creation->toISOString(),
        ];
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

    public function logout($user)
    {
        $user->token()->revoke();
    }
}
