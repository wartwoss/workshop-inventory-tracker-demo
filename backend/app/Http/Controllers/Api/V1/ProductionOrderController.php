<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmProductionOrderRequest;
use App\Services\ProductionConfirmationService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;

class ProductionOrderController extends Controller
{
    public function __construct(private readonly ProductionConfirmationService $confirmationService)
    {
    }

    public function confirm(ConfirmProductionOrderRequest $request): JsonResponse
    {
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));

        if ($idempotencyKey === '') {
            return response()->json([
                'message' => 'Idempotency-Key header is required.',
            ], 422);
        }

        try {
            $result = $this->confirmationService->confirm($request->validated(), $idempotencyKey);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (RuntimeException $e) {
            $status = $e->getCode() === 409 ? 409 : 422;

            return response()->json([
                'message' => $e->getMessage(),
            ], $status);
        }

        $order = $result['order'];
        $statusCode = $result['is_new'] ? 201 : 200;

        return response()->json([
            'id' => $order->id,
            'status' => $order->status,
            'confirmed_at' => $order->confirmed_at,
            'furniture_model' => $order->furnitureModel->only('id', 'slug', 'name'),
            'configuration' => $order->configuration_snapshot,
            'production_consumptions' => $order->productionConsumptions,
            'inventory_movements' => $order->inventoryMovements,
        ], $statusCode);
    }
}
