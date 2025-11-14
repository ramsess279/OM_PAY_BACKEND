<?php

namespace App\Http\Controllers;

use App\Models\Compte;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CompteService;
use App\Http\Requests\CreateCompteRequest;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class CompteController extends Controller
{
    use ApiResponseTrait;

    protected $compteService;

    public function __construct(CompteService $compteService)
    {
        $this->compteService = $compteService;
    }

    // public function index()
    // {
    //     $user = auth()->user();
    //     $comptes = $this->compteService->getUserComptes($user);

    //     return $this->successResponse($comptes, 'Comptes récupérés avec succès');
    // }

    // public function store(CreateCompteRequest $request)
    // {
    //     $user = auth()->user();
    //     $compte = $this->compteService->createCompte($user, $request->validated());

    //     return $this->successResponse($compte, 'Compte créé avec succès', 201);
    // }

    public function index()
    {
        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        // Récupérer tous les comptes de l'utilisateur avec leurs transactions
        $comptes = $user->comptes()->with(['transactions' => function ($query) {
            $query->orderBy('date_transaction', 'desc')
                  ->orderBy('created_at', 'desc');
        }])->get();

        if ($comptes->isEmpty()) {
            return $this->errorResponse('Aucun compte trouvé', 404);
        }

        // Formater les comptes avec leurs transactions
        $comptesFormates = $comptes->map(function ($compte) use ($user) {
            $compteArray = $compte->toArray();
            $metadata = $compteArray['metadata'] ?? null;

            // Calculer le solde
            $compte->solde = $compte->getSoldeAttribute();

            // Générer le QR code en base64 avec le numéro de téléphone et l'ID du compte
            $qrData = json_encode([
                'phone' => $user->telephone,
                'account_id' => $compte->id
            ]);
            $qrCode = new QrCode($qrData);
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            $qrCodeBase64 = base64_encode($result->getString());
            $compteArray['qr_code_base64'] = 'data:image/png;base64,' . $qrCodeBase64;

            // Formater les transactions
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

            // Supprimer les données redondantes
            unset($compteArray['created_at']);
            unset($compteArray['updated_at']);
            unset($compteArray['metadata']);
            unset($compteArray['transactions']);

            // Ajouter les transactions formatées
            $compteArray['transactions'] = $compte->transactions_formatted;

            if ($metadata) {
                $compteArray['metadata'] = $metadata;
            }

            return $compteArray;
        });

        // Structure de réponse avec l'utilisateur et ses comptes
        $response = [
            'utilisateur' => [
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'nom_complet' => $user->nom . ' ' . $user->prenom,
                'telephone' => $user->telephone,
                'email' => $user->email,
                'status' => $user->status,
                'role' => $user->role,
            ],
            'comptes' => $comptesFormates
        ];

        return $this->successResponse($response, 'Comptes récupérés avec succès');
    }

    public function soldes()
    {
        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        // Récupérer tous les comptes de l'utilisateur avec leur solde
        $comptes = $user->comptes()->get()->map(function ($compte) {
            return [
                'id_compte' => $compte->id,
                'solde' => $compte->getSoldeAttribute(),
            ];
        });

        if ($comptes->isEmpty()) {
            return $this->errorResponse('Aucun compte trouvé', 404);
        }

        // Calculer le solde total
        $soldeTotal = $comptes->sum('solde');

        return $this->successResponse([
            'data' => $comptes,
            'solde_total' => $soldeTotal
        ], 'Soldes récupérés avec succès');
    }

    public function transactions(Request $request)
    {
        // Récupérer l'utilisateur connecté
        $user = auth()->user();

        // Récupérer les paramètres de filtrage
        $compteId = $request->query('compte_id');
        $type = $request->query('type');
        $statut = $request->query('statut');
        $dateDebut = $request->query('date_debut');
        $dateFin = $request->query('date_fin');
        $limit = $request->query('limit', 20);

        // Vérifier que compte_id est fourni
        if (!$compteId) {
            return $this->errorResponse('Le paramètre compte_id est obligatoire', 400);
        }

        // Vérifier que le compte appartient à l'utilisateur
        $compte = $user->comptes()->where('id', $compteId)->first();
        if (!$compte) {
            return $this->errorResponse('Compte non trouvé ou accès non autorisé', 404);
        }

        // Construire la requête
        $query = Transaction::where('compte_id', $compteId);

        // Appliquer les filtres
        if ($type) {
            $query->where('type', $type);
        }

        if ($statut) {
            $query->where('statut', $statut);
        }

        if ($dateDebut) {
            $query->whereDate('date_transaction', '>=', $dateDebut);
        }

        if ($dateFin) {
            $query->whereDate('date_transaction', '<=', $dateFin);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($statut) {
            $query->where('statut', $statut);
        }

        $transactions = $query->with('compte')
                              ->orderBy('date_transaction', 'desc')
                              ->orderBy('created_at', 'desc')
                              ->paginate($limit);

        // Formater les transactions
        $transactionsFormatees = $transactions->items();
        foreach ($transactionsFormatees as $transaction) {
            $transaction->montant_formate = $this->formaterMontant($transaction);
            $transaction->destinataire_formate = $this->formaterDestinataire($transaction);
            $transaction->date_formatee = $transaction->date_transaction->format('d/m/Y');
            $transaction->compte_info = [
                'id' => $transaction->compte->id,
                'numero_compte' => $transaction->compte->numero_compte,
                'type' => $transaction->compte->type,
            ];
            unset($transaction->compte);
        }

        return $this->successResponse([
            'transactions' => $transactionsFormatees,
            'pagination' => [
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ]
        ], 'Transactions récupérées avec succès');
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
}
