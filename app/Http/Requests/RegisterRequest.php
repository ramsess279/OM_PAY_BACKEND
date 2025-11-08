<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tu peux ajouter ici une logique d’autorisation si besoin
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'telephone' => 'required|string|unique:users,telephone',
            'code_pin' => 'required|string|min:4|max:6',
            'role' => 'required|in:client,admin',
        ];
    }

    public function messages(): array
    {
        return [
            'telephone.unique' => 'Ce numéro de téléphone est déjà enregistré.',
            'role.in' => 'Le rôle doit être soit client soit admin.',
        ];
    }
}
