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

    public function store(Request $request)
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

        // Valider les paramètres (via query string ou body)
        $validated = $this->validateTransactionRequest($request);

        try {
            $transaction = $this->transactionService->createTransaction($compte, $validated);

            return $this->successResponse($transaction, 'Transaction effectuée avec succès', 201);

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Valider la requête de transaction (supporte query string et body)
     */
    private function validateTransactionRequest(Request $request)
    {
        // Utiliser les données de la query string ou du body
        $data = $request->query();
        if (empty($data)) {
            $data = $request->all();
        }

        // Validation conditionnelle : soit numero_telephone soit code_marchand
        $data['numero_telephone'] = $request->query('numero_telephone');
        $data['code_marchand'] = $request->query('code_marchand');
        $data['montant_transaction'] = $request->query('montant_transaction') ?? $request->input('montant_transaction');

        if (empty($data['numero_telephone']) && empty($data['code_marchand'])) {
            throw new \Exception('Fournissez soit un numéro de téléphone (pour transfert) soit un code marchand (pour paiement)');
        }

        if (!empty($data['numero_telephone']) && !empty($data['code_marchand'])) {
            throw new \Exception('Fournissez soit un numéro de téléphone soit un code marchand, pas les deux');
        }

        // Valider le montant
        if (empty($data['montant_transaction']) || !is_numeric($data['montant_transaction']) || $data['montant_transaction'] < 100) {
            throw new \Exception('Le montant est requis et doit être minimum 100 CFA');
        }

        // Valider le numéro de téléphone
        if (!empty($data['numero_telephone'])) {
            if (!preg_match('/^[0-9]{9}$/', $data['numero_telephone'])) {
                throw new \Exception('Numéro de téléphone invalide (9 chiffres requis)');
            }
        }

        // Valider le code marchand
        if (!empty($data['code_marchand'])) {
            $marchand = \App\Models\Marchand::where('code_marchand', $data['code_marchand'])->first();
            if (!$marchand) {
                throw new \Exception('Code marchand invalide. Consultez /api/marchands pour la liste des codes disponibles');
            }
        }

        return $data;
    }

    public function show(Transaction $transaction)
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
            $transaction = $this->transactionService->getTransaction($compte, $transaction);

            return $this->successResponse($transaction, 'Transaction récupérée avec succès');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}
