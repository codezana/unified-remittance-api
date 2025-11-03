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

class HistoryController extends Controller
{

    public function index(Request $request, $name)
    {
        //retrive the company name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);

        $detail = Detail::query()
            ->where('company_id', $company->id)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->with('company')->get();

        if ($detail->isEmpty()) {
            return response()->json(['message' => 'No Details found'], 404);
        }

        return response()->json($detail);
    }


    public function show($name,$id)
    {
        //retrive the company name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        $detail = Detail::where('company_id', $company->id)->find($id);

        if (!$detail) {
            return response()->json(['message' => 'Detail not found'], 404);
        }

        return response()->json($detail);
    }



    public function update(Request $request, $name, $id)
    {
        // Retrieve the company by name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        $detail = Detail::where('company_id', $company->id)->find($id);

        if (!$detail) {
            return response()->json(['message' => 'Detail not found'], 404);
        }

        // Collect data for validation
        $data = [];
        $data['company_id'] = $company->id;

        if ($request->has('invoice_number')) {
            $data['invoice_number'] = $request->invoice_number;
        }
        if ($request->has('container')) {
            $data['container'] = $request->container;
        }
        if ($request->has('date')) {
            $data['date'] = $request->date;
        }
        if ($request->has('price')) {
            $data['price'] = $request->price;
        }
        if ($request->has('sender_company')) {
            $data['sender_company'] = $request->sender_company;
        }
        if ($request->has('receiver_company')) {
            $data['receiver_company'] = $request->receiver_company;
        }
        if ($request->has('note')) {
            $data['note'] = $request->note;
        }
        if ($request->hasFile('before_bank')) {
            $data['before_bank'] = $request->file('before_bank');
        }
        if ($request->hasFile('after_bank')) {
            $data['after_bank'] = $request->file('after_bank');
        }

        // Validation
        $validatedData = Validator::make($data, [
            'invoice_number' => [
                'sometimes',
                'required',
                'string',
                Rule::unique('details')->ignore($id)->where(function ($query) use ($company) {
                    return $query->where('company_id', $company->id);
                })
            ],
            'container' => 'sometimes|required|string',
            'date' => 'sometimes|required|date',
            'price' => 'sometimes|required|numeric',
            'sender_company' => 'sometimes|required|string',
            'receiver_company' => 'sometimes|required|string',
            'note' => 'nullable|string',
            'before_bank.*' => 'sometimes|file|mimes:pdf',
            'after_bank.*' => 'sometimes|file|mimes:pdf',
        ])->validate();

        // Handle file uploads
        if ($request->hasFile('before_bank')) {
            $folderPath = "uploads/bank_files/{$detail->company_id}/before_bank";
            $beforeBankFiles = [];
            foreach ($request->file('before_bank') as $file) {
                $beforeBankFiles[] = $file->store($folderPath, 'public');
            }
            $validatedData['before_bank'] = json_encode($beforeBankFiles);
        }

        if ($request->hasFile('after_bank')) {
            $folderPath = "uploads/bank_files/{$detail->company_id}/after_bank";
            $afterBankFiles = [];
            foreach ($request->file('after_bank') as $file) {
                $afterBankFiles[] = $file->store($folderPath, 'public');
            }
            $validatedData['after_bank'] = json_encode($afterBankFiles);
        }

        // Update the record
        if (!$detail->update($validatedData)) {
            return response()->json(['message' => 'Failed to update detail'], 500);
        }

        return response()->json([
            'success' => 'Detail updated successfully',
            'data' => $detail->refresh(),
        ], 200);
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
        if ($detail->before_bank) {
            $beforeBankFiles = json_decode($detail->before_bank, true);
            if (is_array($beforeBankFiles)) {
                Storage::disk('public')->delete($beforeBankFiles);
            }
        }
    
        if ($detail->after_bank) {
            $afterBankFiles = json_decode($detail->after_bank, true);
            if (is_array($afterBankFiles)) {
                Storage::disk('public')->delete($afterBankFiles);
            }
        }
    
        // Delete the detail record
        $detail->delete();
    
        return response()->json(['message' => 'Detail deleted successfully'], 200);
    }
    
}
