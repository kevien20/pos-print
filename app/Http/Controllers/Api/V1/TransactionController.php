<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetTransactionsRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\TransactionResource;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(GetTransactionsRequest $request)
    {
        $transactions = Transaction::with(['customer', 'items.product'])
            ->search($request->search)
            ->latest()
            ->paginate($request->limit ?? 10);

        return ApiResponse::success(
            new PaginatedResource($transactions, TransactionResource::class),
            'Transactions list'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request)
    {
        try {
            $result = DB::transaction(function () use ($request) {
                $validated = $request->validated();

                // Validate stock availability for each item
                $stockErrors = [];

                foreach ($validated['items'] as $index => $item) {
                    $product = Product::find($item['product_id']);

                    if ($product->stock < $item['quantity']) {
                        $stockErrors["items.{$index}.quantity"] = [
                            "Insufficient stock for {$product->name}. Available: {$product->stock}, Requested: {$item['quantity']}"
                        ];
                    }
                }

                if (!empty($stockErrors)) {
                    return ['errors' => $stockErrors];
                }

                // Calculate subtotal from items
                $subtotal = 0;
                $itemsData = [];

                foreach ($validated['items'] as $item) {
                    $product = Product::find($item['product_id']);
                    $itemSubtotal = $product->price * $item['quantity'];
                    $subtotal += $itemSubtotal;

                    $itemsData[] = [
                        'product_id' => $item['product_id'],
                        'price' => $product->price,
                        'quantity' => $item['quantity'],
                        'subtotal' => $itemSubtotal,
                    ];

                    // Decrement stock
                    $product->decrement('stock', $item['quantity']);
                }

                $tax = 0;
                $total = $subtotal + $tax;

                // Generate unique transaction code
                $code = 'TRX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

                // Create transaction
                $transaction = Transaction::create([
                    'code' => $code,
                    'customer_id' => $validated['customer_id'] ?? null,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                ]);

                // Create transaction items
                $transaction->items()->createMany($itemsData);

                return ['transaction' => $transaction->load(['customer', 'items.product'])];
            });

            // Check if stock validation failed
            if (isset($result['errors'])) {
                return ApiResponse::error(
                    'Insufficient stock',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $result['errors']
                );
            }

            return ApiResponse::success(
                new TransactionResource($result['transaction']),
                'Transaction Created Successfully',
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to create transaction: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $transaction = Transaction::with(['customer', 'items.product'])->find($id);

        if (!$transaction) {
            return ApiResponse::error(
                'Transaction not found',
                Response::HTTP_NOT_FOUND
            );
        }

        return ApiResponse::success(
            new TransactionResource($transaction),
            'Transaction details'
        );
    }
}
