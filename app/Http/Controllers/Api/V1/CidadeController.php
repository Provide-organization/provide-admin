<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CidadeResource;
use App\Models\Cidade;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CidadeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // Simple endpoint to fetch cities for dropdowns
        return CidadeResource::collection(Cidade::with('estado')->orderBy('nome')->get());
    }
}
