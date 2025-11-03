<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Companies, Customer};
use Illuminate\Support\Facades\Validator;

class CustomersController extends Controller
{
    public function index($name)
    {
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $customer = Customer::query()
            ->where('company_id', $company->id)
            ->with('company')->get();

        if ($customer->isEmpty()) {
            return response()->json(['message' => 'No Customers found'], 404);
        }
        $transformedCustomers = $customer->map(function ($customer) {
            return [
                'id' => $customer->id,
                'company_id' => $customer->company->name,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'email' => $customer->email
            ];
        });

        return response()->json(['data' => $transformedCustomers]);
    }

    public function show($name, $id)
    {
        // Retrieve the company by name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Retrieve the Customer record by ID and company ID
        $customer = Customer::where('company_id', $company->id)->find($id);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        // Transform the Customer record to return as JSON
        $transformedCustomer = [
            'id' => $customer->id,
            'company_id' => $customer->company->name,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'email' => $customer->email
        ];

        return response()->json(['data' => $transformedCustomer]);
    }

    public function store(Request $request, $name)
    {
        // Retrieve the company by name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Custom validation error handling

        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string',
            'phone' => 'nullable',
            'address' => 'nullable|string',
            'email' => 'nullable|email',
        ]);
        if ($validator->fails()) {
            // Return only the first error without the message
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        // Retrieve the validated data
        $validatedData = $validator->validated();
        $validatedData['company_id'] = $company->id;

        // Create a new Customer record
        $customer = Customer::create($validatedData);

        return response()->json(['success' => 'Customer created successfully', 'data' => $customer], 201);
    }

    // Update a Customer (PUT /Customer/{id})
    public function update(Request $request, $name, $id)
    {
        // Retrieve the company by name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Retrieve the Customer record by ID and company ID
        $customer = Customer::where('company_id', $company->id)->find($id);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        // Validation rules
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string',
            'phone' => 'nullable',
            'address' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            // Return only the first error without the message
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        // Update basic fields
        $fieldsToUpdate = [
            'name',
            'phone',
            'address',
            'email',
        ];
        foreach ($fieldsToUpdate as $field) {
            if ($request->has($field)) {
                $customer->$field = $request->$field;
            }
        }
        // Save the updated Customer record
        $customer->save();

        return response()->json([
            'message' => 'Customer updated successfully',
            'data' => $customer->refresh(),
        ], 200);
    }

    // Delete a Customer (DELETE /Customer/{id})
    public function destroy($name, $id)
    {
        // Retrieve the company by name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Retrieve the Customer record by ID and company ID
        $customer = Customer::where('company_id', $company->id)->find($id);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        // Delete the Customer record
        $customer->delete();

        return response()->json(['message' => 'Customer deleted successfully'], 200);
    }
}
