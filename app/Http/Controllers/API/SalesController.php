<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Companies, Sale, Product};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PgSql\Lob;

class SalesController extends Controller
{

    public function index($name)
    {
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $sales = Sale::query()
            ->where('company_id', $company->id)
            ->with('company', 'customer', 'items.product')->get();

        if ($sales->isEmpty()) {
            return response()->json(['message' => 'No Sales found'], 404);
        }

        $transformedSale = $sales->map(function ($sale) {
            return [
                'id' => $sale->id,
                'company_id' => $sale->company->name,
                'customer_name' => $sale->customer->name,
                'customer_id' => str_pad($sale->customer->id, 3, '0', STR_PAD_LEFT),
                'invoice_number' => str_pad($sale->invoice_number, 5, '0', STR_PAD_LEFT),
                'address' => $sale->customer->address,
                'phone' => $sale->customer->phone,
                'discount' => $sale->discount,
                'total_amount' => $sale->total_amount,
                'total_after_discount' => $sale->total_after_discount,
                'date' => $sale->date,
                'items' => $sale->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product->id,
                        'product_name' => $item->product->name,
                        'sell_price' => $item->product->sell_price,
                        'pcs' => $item->pcs,
                        'qty' => $item->qty,
                        'meter' => $item->meter,
                        'yard' => $item->yard,
                        'cm' => $item->cm,
                        'carton' => $item->carton,
                        'amount' => $item->amount,
                    ];
                }),
            ];
        });

        return response()->json(['data' => $transformedSale]);
    }




    public function show($name, $id)
    {
        // Retrieve the company by name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Retrieve the Sale record by ID and company ID
        $sale = Sale::query()
            ->where('company_id', $company->id)
            ->with('company', 'customer', 'items.product')->find($id);
        if (!$sale) {
            return response()->json(['message' => 'Sale not found'], 404);
        }

        $transformedSale = [
            'id' => $sale->id,
            'company_name' => $sale->company->name,
            'customer_name' => $sale->customer->name,
            'customer_id' => str_pad($sale->customer->id, 3, '0', STR_PAD_LEFT),
            'invoice_number' => str_pad($sale->invoice_number, 5, '0', STR_PAD_LEFT),
            'address' => $sale->customer->address,
            'phone' => $sale->customer->phone,
            'discount' => $sale->discount,
            'total_amount' => $sale->total_amount,
            'total_after_discount' => $sale->total_after_discount,
            'date' => $sale->date,
            'items' => $sale->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product->id,
                    'product_name' => $item->product->name,
                    'sell_price' => $item->product->sell_price,
                    'pcs' => $item->pcs,
                    'qty' => $item->qty,
                    'meter' => $item->meter,
                    'yard' => $item->yard,
                    'cm' => $item->cm,
                    'carton' => $item->carton,
                    'amount' => $item->amount,
                ];
            }),
        ];

        return response()->json(['data' => $transformedSale]);
    }


    public function store(Request $request, $name)
    {
        $company = Companies::where('name', $name)->first() ?? response()->json(['message' => 'Company not found'], 404);


        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'date' => 'required|date',
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.pcs' => 'nullable|numeric|min:0',
            'products.*.qty' => 'nullable|numeric|min:0',
            'products.*.meter' => 'nullable|numeric|min:0',
            'products.*.yard' => 'nullable|numeric|min:0',
            'products.*.cm' => 'nullable|numeric|min:0',
            'products.*.carton' => 'nullable|numeric|min:0',
        ]);
        if ($validator->fails()) {
            // Return only the first error without the message
            return response()->json(['message' => $validator->errors()->first()], 422);
        }
        $validatedData = $validator->validated();




        DB::beginTransaction();

        try {
            $lastSale = Sale::where('company_id', $company->id)->orderBy('id', 'desc')->first();
            $invoiceNumber = $lastSale ? $lastSale->invoice_number + 1 : 1;

            $sale = Sale::create([
                'company_id' => $company->id,
                'customer_id' => $validatedData['customer_id'],
                'date' => $validatedData['date'],
                'invoice_number' => str_pad($invoiceNumber, 5, '0', STR_PAD_LEFT),
                'total_amount' => 0,
            ]);

            $totalAmount = 0;

            foreach ($validatedData['products'] as $productData) {
                $product = Product::find($productData['product_id']) ?: throw new \Exception('Product not found');
                $amount = 0;

                // Check if 'meter' is provided and perform calculations based on 'meter'
                if ($product->meter !== null && isset($productData['meter']) && $productData['meter'] > 0) {
                    if ($product->meter !== null && $product->meter < $productData['meter']) {
                        throw new \Exception("You can't send more meters than available.");
                    }

                    $amount = $productData['meter'] * $product->sell_price;
                    $product->sold_meter += $productData['meter']; // Update sold_meter
                    $product->meter -= $productData['meter']; // Deduct meter stock

                    // Update financials specific to meter
                    $product->sold += $productData['meter'] * $product->sell_price;
                    $product->profit = ($product->sold_meter * $product->sell_price) - ($product->sold_meter * $product->unit_price);
                    $product->save();
                }
                // Check if 'qty' is provided and perform calculations based on 'qty'
                elseif ($product->qty !== null && isset($productData['qty']) && $productData['qty'] > 0) {
                    if ($product->qty !== null && $product->qty < $productData['qty']) {
                        throw new \Exception("You can't send more quantity than available.");
                    }

                    $amount = $productData['qty'] * $product->sell_price;
                    $product->sold_qty += $productData['qty']; // Update sold_qty
                    $product->qty -= $productData['qty']; // Deduct quantity stock

                    // Update financials specific to qty
                    $product->sold += $productData['qty'] * $product->sell_price;
                    $product->profit = ($product->sold_qty * $product->sell_price) - ($product->sold_qty * $product->unit_price);
                    $product->save();
                } else {
                    throw new \Exception("Either meter or quantity must be provided.");
                }


                // Create SaleItem record
                $sale->items()->create([
                    'product_id' => $product->id,
                    'pcs' => $productData['pcs'] ?? null,
                    'qty' => $productData['qty'] ?? null,
                    'meter' => $productData['meter'] ?? null,
                    'yard' => $productData['yard'] ?? null,
                    'cm' => $productData['cm'] ?? null,
                    'carton' => $productData['carton'] ?? null,
                    'amount' => $amount,
                ]);

                $totalAmount += $amount;
            }



            $sale->update(['total_amount' => $totalAmount]);
            DB::commit();

            $transformedSale = [
                'id' => $sale->id,
                'company_name' => $sale->company->name,
                'customer_id' => str_pad($sale->customer->id, 3, '0', STR_PAD_LEFT),
                'customer_name' => $sale->customer->name,
                'invoice_number' => $sale->invoice_number,
                'address' => $sale->customer->address,
                'phone' => $sale->customer->phone,
                'discount' => $sale->discount,
                'total_amount' => $sale->total_amount,
                'date' => $sale->date,
                'items' => $sale->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product->id,
                        'product_name' => $item->product->name,
                        'sell_price' => $item->product->sell_price,
                        'pcs' => $item->pcs,
                        'qty' => $item->qty,
                        'meter' => $item->meter,
                        'yard' => $item->yard,
                        'cm' => $item->cm,
                        'carton' => $item->carton,
                        'amount' => $item->amount,
                    ];
                }),
            ];

            return response()->json(['success' => 'Sale stored successfully', 'data' => $transformedSale]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }



    // Update a Sale (PUT /Sale/{id})
    public function update(Request $request, $name, $id)
    {
        // Find the sale record
        $sale = Sale::find($id);

        if (!$sale) {
            return response()->json(['message' => 'Sale not found'], 404);
        }


        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
            'discount' => 'nullable',
            'date' => 'nullable|date',
            'total_amount' => 'nullable',
            'total_after_discount' => 'nullable',
            'products' => 'nullable|array',
            'products.*.product_id' => 'nullable|exists:products,id',
            'products.*.pcs' => 'sometimes|nullable|numeric|min:0',
            'products.*.qty' => 'sometimes|nullable|numeric|min:0',
            'products.*.meter' => 'sometimes|nullable|numeric|min:0',
            'products.*.yard' => 'sometimes|nullable|numeric|min:0',
            'products.*.cm' => 'sometimes|nullable|numeric|min:0',
            'products.*.carton' => 'sometimes|nullable|numeric|min:0',
        ]);
        if ($validator->fails()) {
            // Return only the first error without the message
            return response()->json(['message' => $validator->errors()->first()], 422);
        }
        $validatedData = $validator->validated();


        DB::beginTransaction();

        try {
            $totalAmount = 0;

            if (isset($validatedData['products'])) {

                foreach ($validatedData['products'] as $productData) {
                    $product = Product::find($productData['product_id']);

                    if (!$product) {
                        throw new \Exception('Product not found');
                    }

                    $saleItem = $sale->items()->where('product_id', $product->id)->first();

                    // Handle new products being added to the sale
                    if (!$saleItem) {
                        // Check inventory availability
                        if ($product->meter != null && isset($productData['meter']) && $product->meter < $productData['meter']) {
                            throw new \Exception('Not enough meter for this product');
                        } elseif ($product->meter == null && isset($productData['qty']) && $product->qty < $productData['qty']) {
                            throw new \Exception('Not enough qty for this product');
                        }

                        // Deduct inventory
                        if ($product->meter != null && isset($productData['meter'])) {
                            $product->meter -= $productData['meter'];
                            $product->sold_meter += $productData['meter'];

                            // Update financials specific to meter
                            $product->sold += $productData['meter'] * $product->sell_price;
                            $product->profit = ($product->sold_meter * $product->sell_price) - ($product->sold_meter * $product->unit_price);
                            $product->save();
                        } else {
                            $product->qty -= $productData['qty'];
                            $product->sold_qty += $productData['qty'];

                            // Update financials specific to qty
                            $product->sold += $productData['qty'] * $product->sell_price;
                            $product->profit = ($product->sold_qty * $product->sell_price) - ($product->sold_qty * $product->unit_price);
                            $product->save();
                        }

                        // Calculate the amount
                        $amount = isset($productData['meter']) && $product->meter != null
                            ? $productData['meter'] * $product->sell_price
                            : $productData['qty'] * $product->sell_price;

                        // Create a new sale item
                        $sale->items()->create([
                            'product_id' => $product->id,
                            'meter' => $productData['meter'] ?? null,
                            'pcs' => $productData['pcs'] ?? null,
                            'qty' => $productData['qty'] ?? null,
                            'yard' => $productData['yard'] ?? null,
                            'cm' => $productData['cm'] ?? null,
                            'carton' => $productData['carton'] ?? null,
                            'amount' => $amount,
                        ]);

                        $totalAmount += $amount;
                        continue;
                    }








                    // Skip update if data is the same
                    if (
                        isset($productData['meter']) && $saleItem->meter == $productData['meter'] &&
                        isset($productData['qty']) && $saleItem->qty == $productData['qty'] &&
                        isset($productData['pcs']) && $saleItem->pcs == $productData['pcs'] &&
                        isset($productData['yard']) && $saleItem->yard == $productData['yard'] &&
                        isset($productData['cm']) && $saleItem->cm == $productData['cm'] &&
                        isset($productData['carton']) && $saleItem->carton == $productData['carton'] &&
                        isset($validatedData['discount']) && $sale->discount == $validatedData['discount']
                    ) {
                        continue;
                    }





                    // Existing product logic
                    $meterDifference = isset($productData['meter']) ? ($saleItem->meter - $productData['meter']) : null;
                    $qtyDifference = isset($productData['qty']) ? ($saleItem->qty - $productData['qty']) : null;

                    //part meter
                    if ($product->meter != null && isset($meterDifference)) {
                        if ($meterDifference > 0) {
                            $product->meter += $meterDifference;
                            $product->sold_meter -= $meterDifference;

                            $product->sold = $product->sold_meter * $product->sell_price;
                            $product->profit = ($product->sold_meter * $product->sell_price) - ($product->sold_meter * $product->unit_price);
                        } elseif ($product->meter + $meterDifference < 0) {
                            throw new \Exception('Not enough inventory for this product');
                        } else {
                            $product->meter += $meterDifference;
                            $product->sold_meter -= $meterDifference;

                            $product->sold = $product->sold_meter * $product->sell_price;
                            $product->profit = ($product->sold_meter * $product->sell_price) - ($product->sold_meter * $product->unit_price);
                        }
                    } else {


                        if ($qtyDifference > 0) {
                            $product->qty += $qtyDifference;
                            $product->sold_qty -= $qtyDifference;

                            $product->sold = $product->sold_qty * $product->sell_price;
                            $product->profit = ($product->sold_qty * $product->sell_price) - ($product->sold_qty * $product->unit_price);
                        } elseif ($product->qty + $qtyDifference < 0) {
                            throw new \Exception('Not enough inventory for this product');
                        } else {
                            $product->qty += $qtyDifference;
                            $product->sold_qty -= $qtyDifference;

                            $product->sold = $product->sold_qty * $product->sell_price;
                            $product->profit = ($product->sold_qty * $product->sell_price) - ($product->sold_qty * $product->unit_price);
                        }
                    }

                    $amount = isset($productData['meter']) && $product->meter != null
                        ? $productData['meter'] * $product->sell_price
                        : $productData['qty'] * $product->sell_price;

                    $saleItem->update([
                        'meter' => $productData['meter'] ?? null,
                        'pcs' => $productData['pcs'] ?? null,
                        'qty' => $productData['qty'] ?? null,
                        'yard' => $productData['yard'] ?? null,
                        'cm' => $productData['cm'] ?? null,
                        'carton' => $productData['carton'] ?? null,
                        'amount' => $amount,
                    ]);


                    $product->save();
                    $totalAmount += $amount;
                }
            }
            $totalAmount = $sale->items->sum('amount');

            $saleData = [
                'total_amount' => $totalAmount,
                'date' => $validatedData['date'] ?? $sale->date,
                'customer_id' => $validatedData['customer_id'] ?? $sale->customer_id,
                'updated_at' => now(),
            ];

            if (isset($validatedData['discount']) && $validatedData['discount'] !== null) {


                if (isset($sale->total_after_discount) && $sale->total_after_discount > 0) {
                    $saleData['discount'] = $validatedData['discount'];
                    $saleData['total_after_discount'] = $sale->total_amount;
                    $saleData['total_after_discount'] -= $saleData['discount']; // ex 5000 * 10 / 100 = 500
                } else {
                    $saleData['discount'] = $validatedData['discount'];
                    $saleData['total_after_discount'] = $totalAmount - $saleData['discount'];
                }


            }

            $sale->update($saleData);

            DB::commit();

            return response()->json(['success' => 'Sale updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }



    // Delete a Sale (DELETE /Sale/{id})
    public function destroy($name, $id)
    {
        // Retrieve the company by name
        $company = Companies::where('name', $name)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Retrieve the Sale record by ID and company ID
        $sale = Sale::where('company_id', $company->id)->find($id);

        if (!$sale) {
            return response()->json(['message' => 'Sale not found'], 404);
        }

        // Iterate over the sale items
        foreach ($sale->items as $item) {
            $product = Product::find($item->product_id);


            if ($product) {
                // Adjust product inventory and metrics

                if ($product->sold_meter != null) {

                    $product->sold_meter -= $item->meter;
                    $product->meter += $item->meter;

                    $product->sold = $product->sold_meter * $product->sell_price;
                    $product->profit = ($product->sold_meter * $product->sell_price) - ($product->sold_meter * $product->unit_price);
                } else {
                    $product->qty += $item->qty;
                    $product->sold_qty -= $item->qty;

                    $product->sold = $product->sold_qty * $product->sell_price;
                    $product->profit = ($product->sold_qty * $product->sell_price) - ($product->sold_qty * $product->unit_price);
                }
                $product->save();
            }
        }

        // Delete the Sale record and its associated items
        $sale->items()->delete();
        $sale->delete();

        // Reorder the invoice numbers for all sales in the company
        $sales = Sale::where('company_id', $company->id)->orderBy('created_at')->get();

        $invoiceNumber = 1;
        foreach ($sales as $sale) {
            $sale->invoice_number = str_pad($invoiceNumber, 5, '0', STR_PAD_LEFT); // Format as 5-digit number
            $sale->save();
            $invoiceNumber++;
        }


        return response()->json(['message' => 'Sale deleted successfully'], 200);
    }



    // Handle the deletion of a specific item from a sale
    public function deleteItem($id, $itemid)
    {
        // Retrieve the Sale record by ID
        $sale = Sale::find($id);

        if (!$sale) {
            return response()->json(['message' => 'Sale not found'], 404);
        }

        // Find the specific item in the sale
        $item = $sale->items()->find($itemid);

        if (!$item) {
            return response()->json(['message' => 'Item not found in the sale'], 404);
        }

        // Retrieve the associated product
        $product = Product::find($item->product_id);

        if ($product) {
            // Adjust product inventory and metrics

            if ($product->sold_meter != null) {

                $product->sold_meter -= $item->meter;
                $product->meter += $item->meter;

                $product->sold = $product->sold_meter * $product->sell_price;
                $product->profit = ($product->sold_meter * $product->sell_price) - ($product->sold_meter * $product->unit_price);
            } else {
                $product->qty += $item->qty;
                $product->sold_qty -= $item->qty;

                $product->sold = $product->sold_qty * $product->sell_price;
                $product->profit = ($product->sold_qty * $product->sell_price) - ($product->sold_qty * $product->unit_price);
            }
            $product->save();
        }

        // Delete the specific item
        $item->delete();

        // Recalculate the total amount of the sale
        $totalAmount = $sale->items->sum(function ($item) {
            return $item->amount;
        });
        $sale->total_amount = $totalAmount;
        $sale->save();


        return response()->json(['message' => 'Item deleted successfully'], 200);
    }
}
