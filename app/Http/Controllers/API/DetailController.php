<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Detail, Companies, Balance};
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DetailController extends Controller
{

    public function index($name)
    {
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $detail = Detail::query()
            ->where('company_id', $company->id)
            ->with('company')->get();

        if ($detail->isEmpty()) {
            return response()->json(['message' => 'No Details found'], 404);
        }
        $transformedDetails = $detail->map(function ($detail) {
            return [
                'id' => $detail->id,
                'invoice_number' => $detail->invoice_number,
                'container' => $detail->container,
                'item' => $detail->item,
                'date' => $detail->date,
                'price' => $detail->price,
                'price_dollar' => $detail->price_dollar,
                'sender_company' => $detail->sender_company,
                'receiver_company' => $detail->receiver_company,
                'note' => $detail->note,
                'before_bank' => array_map(fn($path) => Storage::url($path), json_decode($detail->before_bank, true) ?? []),
                'after_bank' => array_map(fn($path) => Storage::url($path), json_decode($detail->after_bank, true) ?? [])
            ];
        });

        return response()->json(['data' => $transformedDetails]);
    }




    public function show($name, $id)
    {
        // Retrieve the company by name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Retrieve the detail record by ID and company ID
        $detail = Detail::where('company_id', $company->id)->find($id);

        if (!$detail) {
            return response()->json(['message' => 'Detail not found'], 404);
        }

        // Transform the detail record to return as JSON
        $transformedDetail = [
            'id' => $detail->id,
            'invoice_number' => $detail->invoice_number,
            'container' => $detail->container,
            'item' => $detail->item,
            'date' => $detail->date,
            'price' => $detail->price,
            'price_dollar' => $detail->price_dollar,
            'sender_company' => $detail->sender_company,
            'receiver_company' => $detail->receiver_company,
            'note' => $detail->note,
            'before_bank' => array_map(fn($path) => Storage::url($path), json_decode($detail->before_bank, true) ?? []),
            'after_bank' => array_map(fn($path) => Storage::url($path), json_decode($detail->after_bank, true) ?? [])
        ];

        return response()->json(['data' => $transformedDetail]);
    }

    public function store(Request $request, $name)
    {
        // Retrieve the company by name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }


        // Validate the incoming request data
        $validator = Validator::make($request->all(), [

            'invoice_number' => 'required|string',
            'container' => 'required|string',
            'item' => 'required|string',
            'date' => 'required|date',
            'price' => 'required|numeric|min:0',
            'price_dollar' => 'nullable|numeric',
            'sender_company' => 'required|string',
            'receiver_company' => 'required|string',
            'note' => 'nullable|string',
            'before_bank.*' => 'nullable|file|mimes:pdf',
            'after_bank.*' => 'nullable|file|mimes:pdf',
        ]);

        if ($validator->fails()) {
            // Return only the first error without the message
            return response()->json(['message' => $validator->errors()->first()], 422);
        }
        // Retrieve the validated data
        $validatedData = $validator->validated();
        $validatedData['company_id'] = $company->id;

        // Update the current month's balance
        $currentMonthBalance = Balance::where('company_id', $company->id)
            ->whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->first();

        if (!$currentMonthBalance) {
            return response()->json(['message' => 'No balance found for the current month'], 404);
        }

        //cheack if that balance remain is smaller than price request is yes return error
        if ($currentMonthBalance && $currentMonthBalance->remain < $request->price) {
            return response()->json(['message' => 'Balance is not enough for this request'], 400);
        }


        $currentMonthBalance->depreciated = $currentMonthBalance->depreciated + $request->price;
        $currentMonthBalance->remain = $currentMonthBalance->balance - $currentMonthBalance->depreciated;
        $currentMonthBalance->save();

        // Create a new detail record
        $detail = Detail::create($validatedData);

        return response()->json(['success' => 'Detail created successfully', 'data' => $detail], 201);
    }





    public function update(Request $request, $name, $id)
    {
        // Retrieve the company by name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Retrieve the Detail record by ID and company ID
        $detail = Detail::where('company_id', $company->id)->find($id);

        if (!$detail) {
            return response()->json(['message' => 'Detail not found'], 404);
        }

        // Check if price is being updated
        $originalPrice = $detail->price;
        $newPrice = $request->input('price', $originalPrice);

        // Validation rules
        $validator = Validator::make($request->all(), [
            'invoice_number' => 'required|string',
            'container' => 'required|string',
            'item' => 'required|string',
            'date' => 'required|date',
            'price' => 'required|numeric',
            'price_dollar' => 'nullable|numeric',
            'sender_company' => 'required|string',
            'receiver_company' => 'required|string',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            // Return only the first error without the message
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        // Update basic fields
        $fieldsToUpdate = [
            'invoice_number',
            'container',
            'item',
            'date',
            'price',
            'price_dollar',
            'sender_company',
            'receiver_company',
            'note',
        ];
        foreach ($fieldsToUpdate as $field) {
            if ($request->has($field)) {
                $detail->$field = $request->$field;
            }
        }

        $currentMonthBalance = Balance::where('company_id', $company->id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->first();

        if (!$currentMonthBalance) {
            return response()->json(['message' => 'No balance found for the current month'], 404);
        }

        //Handle balance update logic for price changes
        if ($newPrice != $originalPrice) {
            $priceDifference = $newPrice - $originalPrice;

            if ($priceDifference > 0) {
                // Check if there's enough balance to cover the increase
                if ($currentMonthBalance->remain < $priceDifference) {
                    return response()->json(['message' => 'Insufficient balance for the price update'], 400);
                }

                $currentMonthBalance->remain -= $priceDifference;
                $currentMonthBalance->depreciated += $priceDifference;
            } elseif ($priceDifference < 0) {
                // Refund the difference back to remain
                $currentMonthBalance->remain += abs($priceDifference);
                $currentMonthBalance->depreciated -= abs($priceDifference);
            }

            $currentMonthBalance->save();
        }


        // Save the updated detail record
        $detail->save();

        return response()->json(['message' => 'Detail updated successfully', 'data' => $detail->refresh()], 200);
    }






    // Delete a detail (DELETE /detail/{id})
    public function destroy($name, $id)
    {
        // Retrieve the company by name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Retrieve the detail record by ID and company ID
        $detail = Detail::where('company_id', $company->id)->find($id);

        if (!$detail) {
            return response()->json(['message' => 'Detail not found'], 404);
        }

        // Delete associated files
        foreach (['before_bank', 'after_bank'] as $field) {
            if ($detail->$field) {
                $files = json_decode($detail->$field, true);
                if (is_array($files)) {
                    foreach ($files as $file) {
                        $path = "{$file}";
                        Storage::disk('public')->delete($path);
                    }
                }
            }
        }

        $currentMonthBalance = Balance::where('company_id', $company->id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->first();

        if (!$currentMonthBalance) {
            return response()->json(['message' => 'No balance found for the current month'], 404);
        } else {
            $currentMonthBalance->depreciated -= $detail->price;
            $currentMonthBalance->remain += $detail->price;
            $currentMonthBalance->save();
        }
        // Delete the detail record
        $detail->delete();

        return response()->json(['message' => 'Detail deleted successfully'], 200);
    }
}
