<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;
use App\Models\Material;
use App\Models\ModelPart;
use App\Services\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MaterialController extends Controller
{
    public function __construct(private readonly StockAdjustmentService $stockAdjustmentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Material::query()
            ->when(! $request->boolean('include_inactive'), function ($q) {
                $q->where('is_active', true);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('material_group'), function ($q) use ($request) {
                $q->where('material_group', $request->input('material_group'));
            })
            ->when($request->boolean('low_stock_only'), function ($q) {
                $q->whereRaw('quantity_on_hand <= COALESCE(reorder_level, -1)');
            })
            ->orderBy('name');

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    public function store(StoreMaterialRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! isset($data['opening_quantity'])) {
            $data['opening_quantity'] = '0';
        }

        if ($request->hasFile('texture')) {
            $data['texture_path'] = $request->file('texture')->store('textures', 'public');
        }

        unset($data['texture']);

        $material = $this->stockAdjustmentService->createMaterial($data);

        return response()->json($material->load('inventoryMovements'), 201);
    }

    public function show(Material $material): JsonResponse
    {
        return response()->json($material->load('inventoryMovements'));
    }

    public function update(UpdateMaterialRequest $request, Material $material): JsonResponse
    {
        $data = $request->validated();

        // Prevent direct balance manipulation and unit changes after movements exist.
        unset($data['quantity_on_hand']);

        if (isset($data['stock_unit']) && $material->inventoryMovements()->exists()) {
            return response()->json([
                'message' => 'Cannot change stock unit after transactions exist.',
            ], 422);
        }

        $oldTexturePath = $material->texture_path;
        $newTexturePath = null;

        if ($request->hasFile('texture')) {
            $newTexturePath = $request->file('texture')->store('textures', 'public');
            $data['texture_path'] = $newTexturePath;
        } elseif ($request->boolean('remove_texture')) {
            $data['texture_path'] = null;
        }

        unset($data['texture']);

        try {
            DB::transaction(function () use ($material, $data) {
                $material->update($data);
            });
        } catch (\Throwable $e) {
            if ($newTexturePath) {
                Storage::disk('public')->delete($newTexturePath);
            }

            throw $e;
        }

        if (($request->hasFile('texture') || $request->boolean('remove_texture')) && $oldTexturePath) {
            Storage::disk('public')->delete($oldTexturePath);
        }

        return response()->json($material->fresh()->load('inventoryMovements'));
    }

    public function destroy(Material $material): JsonResponse
    {
        $result = DB::transaction(function () use ($material) {
            $locked = Material::query()->lockForUpdate()->findOrFail($material->id);
            $hasMovements = $locked->inventoryMovements()->exists();
            $hasConsumptions = $locked->productionConsumptions()->exists();
            $usedByModel = ModelPart::where('material_id', $locked->id)->exists();
            // The balance is stored as a fixed-scale decimal string. A numeric zero check
            // avoids requiring the optional BCMath PHP extension for deletion.
            $hasRemainingStock = (float) $locked->quantity_on_hand !== 0.0;

            if ($hasMovements || $hasConsumptions || $usedByModel || $hasRemainingStock) {
                $locked->update(['is_active' => false]);

                return [
                    'deleted' => false,
                    'archived' => true,
                    'message' => "{$locked->name} was archived so stock balances and historical references remain safe.",
                    'texture_path' => null,
                ];
            }

            $texturePath = $locked->texture_path;
            $name = $locked->name;
            $locked->delete();

            return [
                'deleted' => true,
                'archived' => false,
                'message' => "{$name} was permanently deleted.",
                'texture_path' => $texturePath,
            ];
        });

        if ($result['deleted'] && $result['texture_path']) {
            Storage::disk('public')->delete($result['texture_path']);
        }

        unset($result['texture_path']);

        return response()->json($result);
    }

    public function adjust(AdjustStockRequest $request, Material $material): JsonResponse
    {
        $data = $request->validated();

        try {
            $material = $this->stockAdjustmentService->adjustStock(
                $material,
                (string) $data['quantity'],
                $data['reason']
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'material_id' => $material->id,
                'quantity_on_hand' => $material->quantity_on_hand,
                'requested_quantity' => $data['quantity'],
            ], 422);
        }

        return response()->json($material->load('inventoryMovements'));
    }

    public function movements(Material $material, Request $request): JsonResponse
    {
        $query = $material->inventoryMovements()
            ->when($request->filled('type'), function ($q) use ($request) {
                $q->where('movement_type', $request->input('type'));
            });

        return response()->json($query->paginate($request->input('per_page', 15)));
    }
}
