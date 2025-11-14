<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'compte_id' => 'required|string|exists:comptes,id',
            'type' => 'required|in:depot,retrait,paiement,transfert',
            'numero_telephone' => 'nullable|string|regex:/^\+221[0-9]{9}$/',
            'code_marchand' => 'nullable|string',
            'montant_transaction' => 'required|numeric|min:0.01',
        ];

        // Pour les transferts, numero_telephone est requis
        if ($this->input('type') === 'transfert') {
            $rules['numero_telephone'] = 'required|string|regex:/^\+221[0-9]{9}$/';
        }

        // Pour les paiements, soit code_marchand soit numero_telephone doit être fourni
        if ($this->input('type') === 'paiement') {
            $rules['code_marchand'] = 'required_without:numero_telephone|string';
            $rules['numero_telephone'] = 'required_without:code_marchand|string|regex:/^\+221[0-9]{9}$/';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'compte_id.required' => 'L\'ID du compte est obligatoire.',
            'compte_id.exists' => 'Le compte spécifié n\'existe pas.',
            'type.required' => 'Le type de transaction est obligatoire.',
            'type.in' => 'Le type doit être depot, retrait, paiement ou transfert.',
            'numero_telephone.required' => 'Le numéro de téléphone est requis.',
            'numero_telephone.regex' => 'Le numéro de téléphone doit être au format +221XXXXXXXXX.',
            'code_marchand.required_without' => 'Le code marchand ou le numéro de téléphone du marchand est requis pour les paiements.',
            'numero_telephone.required_without' => 'Le numéro de téléphone du marchand ou le code marchand est requis pour les paiements.',
            'montant_transaction.required' => 'Le montant de la transaction est obligatoire.',
            'montant_transaction.numeric' => 'Le montant doit être un nombre.',
            'montant_transaction.min' => 'Le montant doit être supérieur à 0.',
        ];
    }
}
