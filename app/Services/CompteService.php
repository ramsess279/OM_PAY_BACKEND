<?php

namespace App\Services;

use App\Models\Compte;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

class CompteService
{
    public function getUserComptes(User $user)
    {
        return $user->comptes()->get()->each(function ($compte) {
            $compte->solde = $compte->getSoldeAttribute();
        });
    }

    public function createCompte(User $user, array $data)
    {
        $compte = $user->comptes()->create([
            'numero_compte' => $this->generateNumeroCompte(),
            'code_pin' => \Illuminate\Support\Facades\Hash::make($data['code_pin']),
            'type' => $data['type'] ?? 'client',
            'date_creation' => now()->toDateString(),
            'statut' => 'actif',
            'metadata' => [
                'derniereModification' => now(),
                'version' => 1,
            ],
        ]);

        // Ajouter automatiquement le solde initial de 50 000f
        $this->ajouterSoldeInitial($compte);

        return $compte;
    }

    public function getCompteWithTransactions(Compte $compte)
    {
        $compte->solde = $compte->getSoldeAttribute();

        // Charger les transactions avec eager loading et les formater
        $compte->load(['transactions' => function ($query) {
            $query->orderBy('date_transaction', 'desc')
                  ->orderBy('created_at', 'desc');
        }]);

        // Formater les transactions pour l'affichage
        $compte->transactions_formatted = $compte->transactions->map(function ($transaction) {
            return [
                'libelle' => $transaction->libelle,
                'montant' => $this->formaterMontant($transaction),
                'destinataire' => $this->formaterDestinataire($transaction),
                'date' => $transaction->date_transaction->format('d/m/Y'),
                'reference' => $transaction->reference,
                'type' => $transaction->type,
                'statut' => $transaction->statut,
            ];
        });

        return $compte;
    }

    /**
     * Formate les informations du destinataire d'une transaction
     */
    private function formaterDestinataire($transaction)
    {
        $info = [
            'nom' => null,
            'numero' => null,
            'est_client' => false
        ];

        switch ($transaction->type) {
            case 'transfert':
                if ($transaction->numero_destinataire) {
                    $destinataireUser = User::where('telephone', $transaction->numero_destinataire)->first();

                    if ($destinataireUser) {
                        $info['nom'] = $destinataireUser->nom . ' ' . $destinataireUser->prenom;
                        $info['numero'] = $transaction->numero_destinataire;
                        $info['est_client'] = true;
                    } else {
                        $info['numero'] = $transaction->numero_destinataire;
                        $info['est_client'] = false;
                    }
                }
                break;

            case 'paiement':
                if ($transaction->code_marchand) {
                    $info['code_marchand'] = $transaction->code_marchand;
                    $info['numero'] = $transaction->code_marchand;
                }
                break;

            case 'depot':
            case 'retrait':
                $info['type_operation'] = $transaction->type === 'depot' ? 'Dépôt' : 'Retrait';
                break;
        }

        return $info;
    }

    /**
     * Formate le montant d'une transaction
     */
    private function formaterMontant($transaction): string
    {
        return match ($transaction->type) {
            'depot' => '+' . number_format($transaction->montant, 0, ',', ' ') . ' CFA',
            'frais' => '-' . number_format($transaction->montant, 0, ',', ' ') . ' CFA',
            'transfert', 'paiement', 'retrait' => '-' . number_format($transaction->montant, 0, ',', ' ') . ' CFA',
            default => $transaction->montant . ' CFA'
        };
    }

    /**
     * Ajoute un solde initial de 50 000f à un nouveau compte
     */
    private function ajouterSoldeInitial(Compte $compte)
    {
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
    }

    /**
     * Génère une référence unique pour les transactions
     */
    private function generateReference(string $prefix): string
    {
        return $prefix . date('Ymd') . strtoupper(substr(md5(uniqid()), 0, 8));
    }

    private function generateNumeroCompte(): string
    {
        do {
            $numero = 'OM' . strtoupper(substr(md5(uniqid()), 0, 8));
        } while (Compte::where('numero_compte', $numero)->exists());

        return $numero;
    }
}