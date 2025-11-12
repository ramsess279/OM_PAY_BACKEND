<?php

namespace App\Http\Controllers;

use App\Models\Compte;
use App\Services\CompteService;
use App\Http\Requests\CreateCompteRequest;
use App\Http\Traits\ApiResponseTrait;

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

    public function show()
    {
        // Récupérer l'utilisateur connecté
        $user = auth()->user();
        
        // Récupérer le compte via la relation
        $compte = Compte::where('id_client', $user->id)->first();

        if (!$compte) {
            return $this->errorResponse('Compte non trouvé', 404);
        }

        // Vérification de sécurité : le compte appartient à l'utilisateur connecté
        if ($compte->id_client !== $user->id) {
            return $this->errorResponse('Accès non autorisé', 403);
        }
        
        $compte = $this->compteService->getCompteWithTransactions($compte);

        // Formater la réponse : infos client directement dans data
        $compteArray = $compte->toArray();

        // Extraire les métadonnées pour les replacer à la fin
        $metadata = $compteArray['metadata'] ?? null;

        // Inclure les informations de l'utilisateur avant les métadonnées
        $userFields = [
            'nom_complet' => $user->nom . ' ' . $user->prenom,
            'code_pin' => $compte->code_pin,
            'numero' => $user->telephone,
            'email' => $user->email,
            'id_client' => $user->id,
        ];

        // Supprimer les données redondantes
        unset($compteArray['created_at']);
        unset($compteArray['updated_at']);
        unset($compteArray['metadata']);

        // Réorganiser : champs compte + champs utilisateur + métadonnées
        $compteArray = array_merge($compteArray, $userFields);
        if ($metadata) {
            $compteArray['metadata'] = $metadata;
        }

        return $this->successResponse($compteArray, 'Compte récupéré avec succès');
    }
}
