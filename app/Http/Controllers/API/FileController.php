<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Companies;
use App\Models\Detail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


class FileController extends Controller
{
    public function serveFile($name, $filetype, $filename)
    {
        // Get the company associated with the record
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Construct the file path
        $filePath = "uploads/bank_files/{$company->name}/{$filetype}/{$filename}";
        // Check if the file exists
        if (!Storage::disk('public')->exists($filePath)) {
            Log::info("File not found: " . $filePath);
            return response()->json(['message' => 'File not found'], 404);
        }

        // Retrieve the file
        $file = Storage::disk('public')->get($filePath);

        return response($file, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Access-Control-Allow-Origin', '*');
    }



    // Delete a file (DELETE /detail/{id}/{fileType}/{fileName})
    public function destroyFile(Request $request, $id, $fileType)
    {

        // Get the file name from the query string
        $fileName = $request->query('fileName');

        if (!$fileName) {
            return response()->json(['message' => 'File name is required'], 400);
        }

        // Retrieve the detail record by ID and company ID
        $detail = Detail::find($id);

        if (!$detail) {
            return response()->json(['message' => 'Detail not found'], 404);
        }

        // Ensure valid fileType field
        if (!in_array($fileType, ['before_bank', 'after_bank'])) {
            return response()->json(['message' => 'Invalid file type'], 400);
        }

        // Decode the file array
        $files = json_decode($detail->$fileType, true);
        if (!$files || !is_array($files)) {
            return response()->json(['message' => 'No files found in this record'], 404);
        }


        $company = Companies::find($detail->company_id);

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        // Define folder path
        $folder = $fileType === 'before_bank' ? 'before_bank' : 'after_bank';
        $companyFolder = str_replace(' ', '_', strtolower($company->name));
        $path = "uploads/bank_files/{$companyFolder}/{$folder}/{$fileName}";

        // Find the file in the array and remove it
        $key = array_search($path, $files);
        if ($key === false) {
            return response()->json(['message' => 'File not found in this record'], 404);
        }
        unset($files[$key]);


        // Check if the file exists in storage
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found in the folder'], 404);
        }

        // Delete the file from storage
        Storage::disk('public')->delete($path);

        // Update the database with the new file array
        $detail->$fileType = json_encode(array_values($files));
        $detail->save();

        return response()->json(['message' => 'File deleted successfully'], 200);
    }





    public function updatefile(Request $request, $id)
    {
        $detail = Detail::find($id);

        if (!$detail) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        $validatedData = $request->validate([
            'before_bank' => 'nullable|array',
            'before_bank.*' => 'nullable|file|mimes:pdf',
            'after_bank' => 'nullable|array',
            'after_bank.*' => 'nullable|file|mimes:pdf',
        ]);

        $company = Companies::find($detail->company_id);

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $companyFolder = str_replace(' ', '_', strtolower($company->name));
        $beforeBankPath = "uploads/bank_files/{$companyFolder}/before_bank";
        $afterBankPath = "uploads/bank_files/{$companyFolder}/after_bank";

        if (!Storage::disk('public')->exists($beforeBankPath)) {
            Storage::disk('public')->makeDirectory($beforeBankPath);
        }
        if (!Storage::disk('public')->exists($afterBankPath)) {
            Storage::disk('public')->makeDirectory($afterBankPath);
        }

        $normalizeFilename = function ($filename) {
            return strtolower(str_replace(' ', '_', $filename));
        };

        $existingBeforeFiles = $detail->before_bank ? array_unique(json_decode($detail->before_bank, true)) : [];
        $existingAfterFiles = $detail->after_bank ? array_unique(json_decode($detail->after_bank, true)) : [];

        $existingBeforeFiles = array_map($normalizeFilename, $existingBeforeFiles);
        $existingAfterFiles = array_map($normalizeFilename, $existingAfterFiles);

        $folderBeforeFiles = array_map(fn($file) => $normalizeFilename(basename($file)), Storage::disk('public')->files($beforeBankPath));
        $folderAfterFiles = array_map(fn($file) => $normalizeFilename(basename($file)), Storage::disk('public')->files($afterBankPath));

        $uploadedBeforeFiles = [];
        if ($request->hasFile('before_bank')) {
            foreach ($request->file('before_bank') as $file) {
                $normalizedFilename = $normalizeFilename($file->getClientOriginalName());
                $uploadedBeforeFiles[] = $file->storeAs($beforeBankPath, $normalizedFilename, 'public');
            }
        }
        $uploadedBeforeFiles = array_map($normalizeFilename, $uploadedBeforeFiles);

        $remainingBeforeFiles = array_unique(array_merge($existingBeforeFiles, $uploadedBeforeFiles));
        $filesToDeleteFromBefore = array_diff($existingBeforeFiles, $folderBeforeFiles, $uploadedBeforeFiles);

        foreach ($filesToDeleteFromBefore as $file) {
            Storage::disk('public')->delete($beforeBankPath . '/' . $file);
        }

        $uploadedAfterFiles = [];
        if ($request->hasFile('after_bank')) {
            foreach ($request->file('after_bank') as $file) {
                $normalizedFilename = $normalizeFilename($file->getClientOriginalName());
                $uploadedAfterFiles[] = $file->storeAs($afterBankPath, $normalizedFilename, 'public');
            }
        }
        $uploadedAfterFiles = array_map($normalizeFilename, $uploadedAfterFiles);

        $remainingAfterFiles = array_unique(array_merge($existingAfterFiles, $uploadedAfterFiles));
        $filesToDeleteFromAfter = array_diff($existingAfterFiles, $folderAfterFiles, $uploadedAfterFiles);

        foreach ($filesToDeleteFromAfter as $file) {
            Storage::disk('public')->delete($afterBankPath . '/' . $file);
        }

        $detail->update([
            'before_bank' => !empty($remainingBeforeFiles) ? json_encode(array_values($remainingBeforeFiles)) : null,
            'after_bank' => !empty($remainingAfterFiles) ? json_encode(array_values($remainingAfterFiles)) : null,
        ]);

        return response()->json(['success' => 'Record updated successfully', 'data' => $detail]);
    }
}
