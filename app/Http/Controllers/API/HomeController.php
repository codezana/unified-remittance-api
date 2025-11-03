<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Companies;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{

    public function index()
    {
        $company = Companies::all();

        if ($company->isEmpty()) {
            return response()->json(['message' => 'No Companies found'], 404);
        }

        return response()->json($company);
    }
    public function show($id)
    {
        $company = Companies::find($id);

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        return response()->json($company);
    }
}
