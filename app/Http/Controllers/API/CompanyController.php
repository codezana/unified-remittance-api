<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Companies as Company;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CompanyController extends Controller
{

    // Get a list of all company (GET /company)
    public function index()
    {
        $company = Company::all();

        if ($company->isEmpty()) {
            return response()->json(['message' => 'No companies found'], 404);
        }

        return response()->json($company);
    }


    public function show($id)
    {
        $company = Company::find($id);
    
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
    
        return response()->json($company);
    }


    // Create a new company (POST /company)
    public function store(Request $request)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'name' => 'required',
        ]);

        // Create a new company using the validated data
        $company = Company::create($validatedData);

        // Check if the company was successfully created
        if ($company) {
            return response()->json(['success' => 'Company created successfully'], 201);
        } else {
            return response()->json(['error' => 'Failed to create company'], 500);
        }
    }

    // Update an existing company (PUT /company/{id})
    public function update(Request $request, $id)
    {
        // Find the company by id or fail
        $company = Company::find($id);

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        // Log the current company data
        $updatedData = $request->merge(json_decode($request->getContent(), true));

        // Create an array with the updated data from the request
        $updatedData = [
            'name' => $updatedData['name'],
        ];

        // Attempt to update the company with the new data
        if (!$company->update($updatedData)) {
            return response()->json(['message' => 'Failed to update Company'], 500);
        }

        return response()->json(['success' => 'Company updated successfully'], 200);
    }


    // Delete a company (DELETE /company/{id})
    public function destroy($id)
    {

        $company = Company::find($id);

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $company->delete();

        return response()->json(['message' => 'Company deleted successfully']);
    }
}
