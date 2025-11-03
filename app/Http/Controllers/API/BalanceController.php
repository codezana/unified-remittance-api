<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Balance, Companies};
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BalanceController extends Controller
{
    // List all balances for a given company in the current month
    // GET /balance
    public function index($name)
    {
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        $balance = Balance::query()
            // ->whereYear('created_at', Carbon::now()->year)
            // ->whereMonth('created_at', Carbon::now()->month)
            ->where('company_id', $company->id)
            ->with('company')->get();

        if ($balance->isEmpty()) {
            return response()->json(['message' => 'No Balances found'], 404);
        }

        return response()->json($balance);
    }


    // Show a balance by id
    // GET /balance/{id}
    public function show($id, $name)
    {
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        $balance = Balance::where('company_id', $company->id)->find($id);

        if (!$balance) {
            return response()->json(['message' => 'balance not found'], 404);
        }

        return response()->json($balance);
    }


    // Create a new balance (POST /balance)
    public function store(Request $request, $name)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'balance' => 'required|numeric|min:0',
            'remain' => 'sometimes|required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            // Return only the first error without the message
            return response()->json(['message' => $validator->errors()->first()], 422);
        }


        $validatedData = $validator->validated();


        $validatedData['remain'] = $validatedData['balance'];
        // Get the company name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        $validatedData['company_id'] = $company->id;

        // Create a new balance using the validated data
        $balance = Balance::create($validatedData);

        // Check if the balance was successfully created
        if ($balance) {
            return response()->json(['success' => 'balance created successfully'], 201);
        } else {
            return response()->json(['error' => 'Failed to create balance'], 500);
        }
    }

    public function update(Request $request, $name, $id)
    {
        // Retrieve the company by name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Retrieve the balance record by ID and company ID
        $balance = Balance::where('company_id', $company->id)->find($id);
  
        if (!$balance) {
            return response()->json(['message' => 'Balance not found'], 404);
        }

        $balance->balance = $request->input('balance');
        $balance->remain = $balance->balance-$balance->depreciated;
        $balance->save();

        return response()->json(['message' => 'Balance updated successfully', 'data' => $balance], 200);
    }

    // Delete a balance (DELETE /balance/{id})
    public function destroy($name, $id)
    {
        // Retrieve the company by name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Retrieve the balance record by ID and company ID
        $balance = Balance::where('company_id', $company->id)->find($id);

        if (!$balance) {
            return response()->json(['message' => 'Balance not found'], 404);
        }

        // Delete the balance record
        $balance->delete();

        return response()->json(['message' => 'Balance deleted successfully'], 200);
    }
}
