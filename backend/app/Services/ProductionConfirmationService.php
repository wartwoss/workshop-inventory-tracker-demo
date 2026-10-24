<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\ProductionConsumption;
use App\Models\ProductionOrder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ProductionConfirmationService
{
    public function __construct(
        private readonly ProductionQuoteService $quoteService,
        private readonly StockAdjustmentService $stockService
    ) {
    }

    /**
     * Confirm a production order atomically.
     *
     * @param array<string, mixed> $input
     * @param string $idempotencyKey
     * @return array{order: ProductionOrder, is_new: bool}
     */
    public function confirm(array $input, string $idempotencyKey): array
    {
        $snapshot = $this->normalizeSnapshot($input);

        try {
            return DB::transaction(function () use ($input, $idempotencyKey, $snapshot) {
                $existing = ProductionOrder::where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->configuration_snapshot === $snapshot) {
                        return [
                            'order' => $existing->load('productionConsumptions', 'inventoryMovements'),
                            'is_new' => false,
                        ];
                    }

                    throw new RuntimeException(
                        'Idempotency key conflict: the provided key was already used with a different request.',
                        409
                    );
                }

                $quote = $this->quoteService->quote($input);

                if (! $quote['is_sufficient']) {
                    throw new RuntimeException('Insufficient stock to confirm this production order.');
                }

                $materialIds = collect($quote['materials'])->pluck('material_id')->sort()->values()->all();

                /** @var \Illuminate\Database\Eloquent\Collection<int, Material> $lockedMaterials */
                $lockedMaterials = Material::lockForUpdate()
                    ->whereIn('id', $materialIds)
                    ->orderBy('id')
                    ->get()
                    ->keyBy('id');

                foreach ($quote['materials'] as $materialRequirement) {
                    $materialId = $materialRequirement['material_id'];
                    $locked = $lockedMaterials->get($materialId);

                    if (! $locked) {
                        throw new RuntimeException("Material {$materialId} disappeared during confirmation.");
                    }

                    if (bccomp($locked->quantity_on_hand, $materialRequirement['required_quantity'], 6) < 0) {
                        throw new RuntimeException(
                            "Insufficient stock for material '{$locked->name}' after locking."
                        );
                    }
                }

                $order = ProductionOrder::create([
                    'furniture_model_id' => $quote['furniture_model']['id'],
                    'length_mm' => $snapshot['length_mm'],
                    'width_mm' => $snapshot['width_mm'],
                    'height_mm' => $snapshot['height_mm'],
                    'quantity' => $snapshot['quantity'],
                    'configuration_snapshot' => $snapshot,
                    'status' => 'confirmed',
                    'idempotency_key' => $idempotencyKey,
                    'confirmed_at' => now(),
                ]);

                foreach ($quote['components'] as $component) {
                    ProductionConsumption::create([
                        'production_order_id' => $order->id,
                        'model_part_id' => $component['part_id'],
                        'material_id' => $component['material_id'],
                        'quantity_consumed' => $component['total_quantity'],
                        'stock_unit' => $component['unit'],
                    ]);
                }

                foreach ($quote['materials'] as $materialRequirement) {
                    $locked = $lockedMaterials->get($materialRequirement['material_id']);
                    $required = $materialRequirement['required_quantity'];
                    $newBalance = bcsub($locked->quantity_on_hand, $required, 6);

                    $locked->quantity_on_hand = $newBalance;
                    $locked->save();

                    $this->stockService->recordMovement(
                        $locked,
                        InventoryMovement::TYPE_PRODUCTION,
                        '-'.$required,
                        $newBalance,
                        null,
                        $order->id
                    );
                }

                return [
                    'order' => $order->load('productionConsumptions', 'inventoryMovements'),
                    'is_new' => true,
                ];
            });
        } catch (QueryException $e) {
            $message = strtolower($e->getMessage());

            if (str_contains($message, 'idempotency') || str_contains($message, 'unique')) {
                $existing = ProductionOrder::where('idempotency_key', $idempotencyKey)->first();

                if ($existing && $existing->configuration_snapshot === $snapshot) {
                    return [
                        'order' => $existing->load('productionConsumptions', 'inventoryMovements'),
                        'is_new' => false,
                    ];
                }

                throw new RuntimeException(
                    'Idempotency key conflict: the provided key was already used with a different request.',
                    409
                );
            }

            throw $e;
        }
    }

    /**
     * Build a deterministic configuration snapshot for idempotency comparison.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizeSnapshot(array $input): array
    {
        $parts = collect($input['parts'] ?? [])
            ->sortBy('code')
            ->values()
            ->map(function ($part) {
                $normalized = [
                    'code' => $part['code'],
                    'material_id' => (int) $part['material_id'],
                ];

                foreach ([
                    'length_mm', 'width_mm', 'height_mm',
                    'pos_x_mm', 'pos_y_mm', 'pos_z_mm',
                    'rot_x_deg', 'rot_y_deg', 'rot_z_deg',
                ] as $key) {
                    if (array_key_exists($key, $part) && $part[$key] !== null) {
                        $normalized[$key] = (int) $part[$key];
                    }
                }

                if (array_key_exists('shape', $part) && $part['shape'] !== null) {
                    $normalized['shape'] = (string) $part['shape'];
                }

                return $normalized;
            })
            ->all();

        return [
            'furniture_model_slug' => $input['furniture_model_slug'],
            'length_mm' => (int) $input['length_mm'],
            'width_mm' => (int) $input['width_mm'],
            'height_mm' => (int) $input['height_mm'],
            'quantity' => (int) $input['quantity'],
            'parts' => $parts,
        ];
    }
}
