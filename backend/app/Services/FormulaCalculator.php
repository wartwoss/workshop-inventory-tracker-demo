<?php

namespace App\Services;

use InvalidArgumentException;

class FormulaCalculator
{
    /**
     * Supported inventory units.
     */
    public const SUPPORTED_FORMULAS = [
        'panel_area_m2',
        'cabinet_carcass_m2',
        'fixed_each',
    ];

    /**
     * Calculate consumption for a model part using decimal-safe arithmetic.
     *
     * @param string $formulaKey
     * @param array<string, int> $dimensions length/width/height in mm
     * @param array<string, mixed> $parameters
     * @return string Consumption in the part's canonical unit, rounded up to 6 dp.
     */
    public function calculate(string $formulaKey, array $dimensions, array $parameters): string
    {
        return match ($formulaKey) {
            'panel_area_m2' => $this->panelAreaM2($dimensions, $parameters),
            'cabinet_carcass_m2' => $this->cabinetCarcassM2($dimensions, $parameters),
            'fixed_each' => $this->fixedEach($parameters),
            default => throw new InvalidArgumentException("Unsupported formula key: {$formulaKey}"),
        };
    }

    /**
     * Panel area in m².
     *
     * Inputs:
     *   - dimension_axes: array of two axis keys from [length, width, height]
     *   - waste_factor: multiplier for offcuts/kerf
     *   - count_per_unit: number of identical panels consumed (defaults to 1)
     *   - dimension_subtractions_mm: optional per-axis offsets for panels fitted between other boards
     */
    private function panelAreaM2(array $dimensions, array $parameters): string
    {
        $axes = $parameters['dimension_axes'] ?? [];

        if (count($axes) !== 2 || ! isset($dimensions[$axes[0]], $dimensions[$axes[1]])) {
            throw new InvalidArgumentException('panel_area_m2 requires two valid dimension axes.');
        }

        $subtractions = $parameters['dimension_subtractions_mm'] ?? [];
        $firstDimension = (int) $dimensions[$axes[0]] - (int) ($subtractions[$axes[0]] ?? 0);
        $secondDimension = (int) $dimensions[$axes[1]] - (int) ($subtractions[$axes[1]] ?? 0);
        if ($firstDimension < 1 || $secondDimension < 1) {
            throw new InvalidArgumentException('panel_area_m2 adjustments must leave positive panel dimensions.');
        }

        $areaMm2 = bcmul((string) $firstDimension, (string) $secondDimension, 0);
        $areaM2 = bcdiv($areaMm2, '1000000', 12);
        $countPerUnit = (string) ($parameters['count_per_unit'] ?? 1);
        $areaM2 = bcmul($areaM2, $countPerUnit, 12);
        $wasteFactor = (string) ($parameters['waste_factor'] ?? 1);

        return $this->roundUp(bcmul($areaM2, $wasteFactor, 12), 6);
    }

    /**
     * Simplified five-panel cabinet carcass panel area in m².
     *
     * Inputs:
     *   - length_mm, width_mm, height_mm (model dimensions)
     *   - waste_factor
     *
     * Assumptions:
     *   - Five-panel box: two sides (H×W), top/bottom (L×W), back (L×H).
     *   - No shelves, drawers, or internal divisions.
     *   - This is a material estimate, not a manufacturing cut list.
     */
    private function cabinetCarcassM2(array $dimensions, array $parameters): string
    {
        $lengthMm = (string) ($dimensions['length'] ?? 0);
        $widthMm = (string) ($dimensions['width'] ?? 0);
        $heightMm = (string) ($dimensions['height'] ?? 0);
        $wasteFactor = (string) ($parameters['waste_factor'] ?? 1);

        // 2 sides + 2 top/bottom panels + back
        $panelAreaMm2 = bcadd(
            bcadd(
                bcmul('2', bcmul($heightMm, $widthMm, 0), 0),
                bcmul('2', bcmul($lengthMm, $widthMm, 0), 0),
                0
            ),
            bcmul($lengthMm, $heightMm, 0),
            0
        );

        $areaM2 = bcdiv($panelAreaMm2, '1000000', 12);

        return $this->roundUp(bcmul($areaM2, $wasteFactor, 12), 6);
    }

    /**
     * Fixed piece count.
     *
     * Inputs:
     *   - count_per_unit
     */
    private function fixedEach(array $parameters): string
    {
        $count = (string) ($parameters['count_per_unit'] ?? 0);

        return $this->roundUp($count, 6);
    }

    /**
     * Round a decimal string up to the given number of decimal places.
     */
    public function roundUp(string $value, int $scale): string
    {
        $factor = bcpow('10', (string) $scale, 0);
        $scaled = bcmul($value, $factor, $scale + 4);

        // Split into integer and fractional parts
        $dotPosition = strpos($scaled, '.');

        if ($dotPosition === false) {
            $integerPart = $scaled;
            $fractionalPart = '';
        } else {
            $integerPart = substr($scaled, 0, $dotPosition);
            $fractionalPart = substr($scaled, $dotPosition + 1);
        }

        // If there is any positive fractional remainder, add one to the integer part.
        if (rtrim($fractionalPart, '0') !== '') {
            $integerPart = bcadd($integerPart, '1', 0);
        }

        return bcdiv($integerPart, $factor, $scale);
    }
}
