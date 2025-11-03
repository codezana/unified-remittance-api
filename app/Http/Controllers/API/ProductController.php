<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Companies, Product, SaleItem};
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{

    private function getCompanyByName($name)
    {
        $company = Companies::where('name', $name)->first();
        if (!$company) {
            throw new \Exception('Company not found');
        }
        return $company;
    }


    public function index($name)
    {
        try {
            $company = $this->getCompanyByName($name);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $product = Product::where('company_id', $company->id)->with('company')->get();
        if ($product->isEmpty()) {
            return response()->json(['message' => 'No Products found'], 404);
        }

        $transformedProducts = $product->map(function ($product) {
            return [
                'id' => $product->id,
                'company_id' => $product->company->name,
                'name' => $product->name,
                'qty' => $product->qty,
                'meter' => $product->meter,
                'unit_price' => $product->unit_price,
                'sell_price' => $product->sell_price,
                'amount' => $product->amount,
                'sold' => $product->sold,
                'sold_meter' => $product->sold_meter,
                'sold_qty' => $product->sold_qty,
                'profit' => $product->profit,
                'supplier_name' => $product->supplier_name,
            ];
        });

        return response()->json(['data' => $transformedProducts]);
    }





    public function show($name, $id)
    {
        // Retrieve the company by name
        try {
            $company = $this->getCompanyByName($name);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
        // Retrieve the Product record by ID and company ID
        $product = Product::where('company_id', $company->id)->find($id) ?? response()->json(['message' => 'Product not found'], 404);


        // Transform the Product record to return as JSON
        $transformedProduct = [
            'id' => $product->id,
            'company_id' => $product->company->name,
            'name' => $product->name,
            'qty' => $product->qty,
            'meter' => $product->meter,
            'unit_price' => $product->unit_price,
            'sell_price' => $product->sell_price,
            'amount' => $product->amount,
            'sold' => $product->sold,
            'sold_meter' => $product->sold_meter,
            'sold_qty' => $product->sold_qty,
            'profit' => $product->profit,
            'supplier_name' => $product->supplier_name,
        ];

        return response()->json(['data' => $transformedProduct]);
    }



    public function store(Request $request, $name)
    {
        // Retrieve the company by name
        try {
            $company = $this->getCompanyByName($name);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'qty' => 'nullable|integer',
            'meter' => 'nullable|numeric',
            'unit_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'supplier_name' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            // Return only the first error without the message
            return response()->json(['message' => $validator->errors()->first()], 422);
        }
// Retrieve the validated data
$validatedData = $validator->validated();

        $validatedData['company_id'] = $company->id;


        $validatedData['amount'] = isset($validatedData['meter']) ? $validatedData['meter'] * $validatedData['unit_price'] : $validatedData['qty'] * $validatedData['unit_price'];

        // Create a new Product record
        $product = Product::create($validatedData);

        return response()->json(['success' => 'Product created successfully', 'data' => $product], 201);
    }

    // Update a Product (PUT /Product/{id})
    public function update(Request $request, $name, $id)
    {
        // Retrieve the company by name
        try {
            $company = $this->getCompanyByName($name);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        // Retrieve the Product record by ID and company ID
        $product = Product::where('company_id', $company->id)->find($id) ?? response()->json(['message' => 'Product not found'], 404);


        // Validation rules
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string',
            'qty' => 'nullable|integer',
            'meter' => 'nullable|numeric',
            'unit_price' => 'sometimes|required|numeric|min:0',
            'sell_price' => 'sometimes|required|numeric|min:0',
            'supplier_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            // Return only the first error without the message
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        // Update basic fields
        $fieldsToUpdate = [
            'name',
            'qty',
            'meter',
            'unit_price',
            'sell_price',
            'supplier_name',
        ];
        foreach ($fieldsToUpdate as $field) {
            if ($request->has($field)) {
                $product->$field = $request->$field;
            }
        }

        $product->amount = isset($product->meter) ? $product->meter * $product->unit_price : $product->qty * $product->unit_price;

        // Save the updated Product record
        $product->save();

        return response()->json(['message' => 'Product updated successfully', 'data' => $product->refresh()], 200);
    }


    // Delete a Product (DELETE /Product/{id})
    public function destroy($name, $id)
    {
        // Retrieve the company by name
        try {
            $company = $this->getCompanyByName($name);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        // Retrieve the Product record by ID and company ID
        $product = Product::where('company_id', $company->id)->find($id) ?? response()->json(['message' => 'Product not found'], 404);

        if (SaleItem::where('product_id', $product->id)->exists()) {
            return response()->json(['message' => 'Product is used in Sale Items, cannot delete'], 422);
        }

        // Delete the Product record
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully'], 200);
    }
}
