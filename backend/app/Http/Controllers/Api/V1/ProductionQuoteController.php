<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteRequest;
use App\Services\ProductionQuoteService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ProductionQuoteController extends Controller
{
    public function __construct(private readonly ProductionQuoteService $quoteService)
    {
    }

    public function __invoke(QuoteRequest $request): JsonResponse
    {
        try {
            $quote = $this->quoteService->quote($request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json($quote);
    }
}
