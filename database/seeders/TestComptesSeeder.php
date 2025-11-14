<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Compte;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestComptesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Création de comptes de test avec solde initial de 50 000f...');

        // Créer quelques utilisateurs de test avec leurs comptes
        $usersData = [
            [
                'nom' => 'DIALLO',
                'prenom' => 'Aminata',
                'telephone' => '771234567',
                'email' => 'aminata.diallo@test.com',
                'code_pin' => '1234',
            ],
            [
                'nom' => 'MBAYE',
                'prenom' => 'Ibrahima',
                'telephone' => '772345678',
                'email' => 'ibrahima.mbayé@test.com',
                'code_pin' => '5678',
            ],
            [
                'nom' => 'SOW',
                'prenom' => 'Fatou',
                'telephone' => '773456789',
                'email' => 'fatou.sow@test.com',
                'code_pin' => '9876',
            ],
            [
                'nom' => 'TEST',
                'prenom' => 'User',
                'telephone' => '771234580',
                'email' => 'test.user@test.com',
                'code_pin' => '1234',
            ],
            [
                'nom' => 'INACTIVE',
                'prenom' => 'User',
                'telephone' => '771234581',
                'email' => 'inactive.user@test.com',
                'code_pin' => '5678',
                'status' => 'inactive',
                'otp_code' => '123456',
            ],
        ];

        foreach ($usersData as $userData) {
            // Créer ou mettre à jour l'utilisateur
            $user = User::updateOrCreate(
                ['telephone' => $userData['telephone']],
                [
                    'nom' => $userData['nom'],
                    'prenom' => $userData['prenom'],
                    'email' => $userData['email'],
                    'role' => 'client',
                    'status' => $userData['status'] ?? 'active', // Activer par défaut, ou utiliser la valeur spécifiée
                    'otp_code' => $userData['otp_code'] ?? null, // Code OTP si spécifié
                ]
            );

            // Créer le compte (le solde initial sera ajouté automatiquement)
            $compte = $user->comptes()->create([
                'numero_compte' => $this->generateNumeroCompte(),
                'code_pin' => Hash::make($userData['code_pin']),
                'type' => 'client',
                'date_creation' => now()->toDateString(),
                'statut' => 'actif',
                'metadata' => [
                    'derniereModification' => now(),
                    'version' => 1,
                ],
            ]);

            // Ajouter le solde initial de 50 000f
            Transaction::create([
                'compte_id' => $compte->id,
                'type' => 'depot',
                'montant' => 50000.00,
                'libelle' => 'Solde initial du compte',
                'description' => 'Dépôt initial pour activer le compte',
                'reference' => $this->generateReference('INIT'),
                'date_transaction' => now(),
                'statut' => 'validee',
            ]);

            $this->command->info("✅ Compte créé pour {$userData['nom']} {$userData['prenom']} ({$userData['telephone']}) - Solde: 50 000f");
        }

        $this->command->info('🎉 Comptes de test créés avec succès!');
    }

    /**
     * Génère un numéro de compte unique
     */
    private function generateNumeroCompte(): string
    {
        do {
            $numero = 'OM' . strtoupper(substr(md5(uniqid()), 0, 8));
        } while (Compte::where('numero_compte', $numero)->exists());

        return $numero;
    }

    /**
     * Génère une référence unique pour les transactions
     */
    private function generateReference(string $prefix): string
    {
        return $prefix . date('Ymd') . strtoupper(substr(md5(uniqid()), 0, 8));
    }
}