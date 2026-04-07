<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PessoaResource;
use App\Models\Pessoa;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PessoaController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // Simple endpoint to fetch pessoas for dropdowns (e.g. as gesture)
        return PessoaResource::collection(Pessoa::orderBy('nome_completo')->get());
    }
}
