<?php

namespace App\Http\Controllers;

use App\Models\Compte;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Http\Requests\CreateTransactionRequest;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use ApiResponseTrait;

    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function index(Request $request)
    {
        // Récupérer l'utilisateur connecté et son compte
        $user = auth()->user();
        $compte = Compte::where('id_client', $user->id)->first();

        if (!$compte) {
            return $this->errorResponse('Compte non trouvé', 404);
        }

        // Vérification de sécurité : le compte appartient à l'utilisateur connecté
        if ($compte->id_client !== $user->id) {
            return $this->errorResponse('Accès non autorisé', 403);
        }

        // $this->authorize('view', $compte); // Désactivé temporairement

        $filters = $request->only(['type', 'statut']);
        $transactions = $this->transactionService->getTransactions($compte, $filters);

        return $this->successResponse($transactions, 'Transactions récupérées avec succès');
    }

    public function store(CreateTransactionRequest $request)
    {
        // Récupérer l'utilisateur connecté et son compte
        $user = auth()->user();
        $compte = Compte::where('id_client', $user->id)->first();

        if (!$compte) {
            return $this->errorResponse('Compte non trouvé', 404);
        }

        // Vérification de sécurité : le compte appartient à l'utilisateur connecté
        if ($compte->id_client !== $user->id) {
            return $this->errorResponse('Accès non autorisé', 403);
        }

        // $this->authorize('update', $compte); // Désactivé temporairement

        try {
            $transaction = $this->transactionService->createTransaction($compte, $request->validated());

            return $this->successResponse($transaction, 'Transaction effectuée avec succès', 201);

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }


    public function show(string $reference)
    {
        // Récupérer l'utilisateur connecté et son compte
        $user = auth()->user();
        $compte = Compte::where('id_client', $user->id)->first();

        if (!$compte) {
            return $this->errorResponse('Compte non trouvé', 404);
        }

        // Vérification de sécurité : le compte appartient à l'utilisateur connecté
        if ($compte->id_client !== $user->id) {
            return $this->errorResponse('Accès non autorisé', 403);
        }

        // $this->authorize('view', $compte); // Désactivé temporairement

        try {
            $transaction = $this->transactionService->getTransactionByReference($compte, $reference);

            // Formater la réponse comme pour la création
            $formattedTransaction = [
                'libelle' => $transaction->libelle,
                'montant' => $transaction->type === 'depot' ? '+' . number_format($transaction->montant, 0, ',', ' ') . ' CFA' : '-' . number_format($transaction->montant, 0, ',', ' ') . ' CFA',
                'expediteur' => $this->transactionService->getExpediteurInfo($transaction),
                'destinataire' => $this->transactionService->getDestinataireInfo($transaction),
                'date_transaction' => $transaction->date_transaction->toISOString(),
                'reference' => $transaction->reference,
                'type' => $transaction->type,
                'statut' => $transaction->statut,
            ];

            return $this->successResponse($formattedTransaction, 'Transaction récupérée avec succès');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}
