<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $lowStockExpression = 'quantity_on_hand <= COALESCE(reorder_level, -1)';

        $materials = [
            'total' => Material::count(),
            'active' => Material::where('is_active', true)->count(),
            'inactive' => Material::where('is_active', false)->count(),
            'low_stock' => Material::where('is_active', true)->whereRaw($lowStockExpression)->count(),
            'out_of_stock' => Material::where('is_active', true)->whereRaw('quantity_on_hand <= 0')->count(),
        ];

        $groups = Material::query()
            ->select('material_group', DB::raw('COUNT(*) as materials_count'))
            ->groupBy('material_group')
            ->orderBy('material_group')
            ->get()
            ->map(fn ($row) => [
                'material_group' => $row->material_group,
                'materials_count' => (int) $row->materials_count,
            ]);

        // Only active inventory belongs in the live stock summary. Archived
        // legacy m3 materials remain available for historical reconciliation,
        // but they must not reintroduce m3 into the current dashboard.
        $stockByUnit = Material::query()
            ->where('is_active', true)
            ->select('stock_unit', DB::raw('SUM(quantity_on_hand) as quantity'))
            ->groupBy('stock_unit')
            ->orderBy('stock_unit')
            ->get()
            ->map(fn ($row) => [
                'stock_unit' => $row->stock_unit,
                'quantity' => (string) $row->quantity,
            ]);

        $recentMovements = InventoryMovement::query()
            ->with('material:id,name,sku,stock_unit')
            ->latest('created_at')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (InventoryMovement $movement) => [
                'id' => $movement->id,
                'material_id' => $movement->material_id,
                'material_name' => $movement->material?->name,
                'material_sku' => $movement->material?->sku,
                'stock_unit' => $movement->material?->stock_unit,
                'movement_type' => $movement->movement_type,
                'quantity_delta' => $movement->quantity_delta,
                'balance_after' => $movement->balance_after,
                'reason' => $movement->reason,
                'created_at' => $movement->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'materials' => $materials,
            'groups' => $groups,
            'stock_by_unit' => $stockByUnit,
            'recent_movements' => $recentMovements,
        ]);
    }
}
