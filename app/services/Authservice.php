<?php

namespace App\Services;

use App\Models\User;

class AuthService
{
    public function register(array $data)
    {
        $user = User::create([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'telephone' => $data['telephone'],
            'code_pin' => bcrypt($data['code_pin']),
            'role' => $data['role'],
        ]);

        $token = $user->createToken('AuthToken')->accessToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function login(array $data)
    {
        $user = User::where('telephone', $data['telephone'])->first();

        if (!$user || !password_verify($data['code_pin'], $user->code_pin)) {
            return null;
        }

        $token = $user->createToken('AuthToken')->accessToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout($user)
    {
        $user->token()->revoke();
    }
}
