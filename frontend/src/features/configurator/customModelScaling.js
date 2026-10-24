import { componentBounds, constrainCustomLegsToBoards } from './partGeometry.js'

const MIN_DIMENSION_MM = 1
const MAX_DIMENSION_MM = 5000
const STATIC_FOOT_SHAPES = new Set(['cube_foot', 'angular_foot', 'round_foot'])
const ROUND_SHAPES = new Set(['round_foot', 'knob', 'bulb_knob'])
const STRUCTURAL_LEG_SHAPES = new Set(['rectangular_leg', 'cylinder_leg'])

const clamp = (value, min, max) => Math.max(min, Math.min(max, Number(value) || min))

function verticalExtent(part) {
  return ROUND_SHAPES.has(part.shape)
    ? Math.max(MIN_DIMENSION_MM, Number(part.length_mm) || MIN_DIMENSION_MM)
    : Math.max(MIN_DIMENSION_MM, Number(part.height_mm) || MIN_DIMENSION_MM)
}

function getBounds(parts) {
  const bounds = (parts ?? []).map((part) => componentBounds(part))
  if (!bounds.length) {
    return { minX: 0, maxX: 1000, minY: 0, maxY: 1000, minZ: 0, maxZ: 500 }
  }

  return {
    minX: Math.min(...bounds.map((boundsPart) => boundsPart.minX)),
    maxX: Math.max(...bounds.map((boundsPart) => boundsPart.maxX)),
    minY: Math.min(...bounds.map((boundsPart) => boundsPart.minY)),
    maxY: Math.max(...bounds.map((boundsPart) => boundsPart.maxY)),
    minZ: Math.min(...bounds.map((boundsPart) => boundsPart.minZ)),
    maxZ: Math.max(...bounds.map((boundsPart) => boundsPart.maxZ)),
  }
}

function getVerticalAnchor(parts, bounds) {
  const hasStructuralLegs = parts.some((part) =>
    part.component_type === 'leg' && STRUCTURAL_LEG_SHAPES.has(part.shape),
  )

  if (hasStructuralLegs) return bounds.minY

  // Short feet stay on the ground. The board assembly grows from the top of
  // the feet, so resizing a cabinet does not leave a gap beneath its carcass.
  const staticFeet = parts.filter((part) => {
    if (part.component_type !== 'leg' || !STATIC_FOOT_SHAPES.has(part.shape)) return false
    const footBounds = componentBounds(part)
    return footBounds.minY <= bounds.minY + 5
  })

  if (staticFeet.length) {
    return Math.max(...staticFeet.map((part) => componentBounds(part).maxY))
  }

  return bounds.minY
}

function getAxisScaleFactors(part, scaleX, scaleY, scaleZ) {
  const rx = (Number(part.rot_x_deg) || 0) * Math.PI / 180
  const ry = (Number(part.rot_y_deg) || 0) * Math.PI / 180
  const rz = (Number(part.rot_z_deg) || 0) * Math.PI / 180
  const sx = Math.sin(rx), cx = Math.cos(rx)
  const sy = Math.sin(ry), cy = Math.cos(ry)
  const sz = Math.sin(rz), cz = Math.cos(rz)

  // Local X/Y/Z axis directions after XYZ Euler rotation (Rz * Ry * Rx).
  // This keeps boards aligned with the furniture axes when they are rotated
  // by 90 degrees, instead of scaling a side board in the wrong direction.
  const localX = { x: cy * cz, y: cy * sz, z: -sy }
  const localY = { x: cz * sy * sx - sz * cx, y: sz * sy * sx + cz * cx, z: cy * sx }
  const localZ = { x: cz * sy * cx + sz * sx, y: sz * sy * cx - cz * sx, z: cy * cx }
  const factorFor = (axis) => Math.sqrt(
    (axis.x * scaleX) ** 2 + (axis.y * scaleY) ** 2 + (axis.z * scaleZ) ** 2,
  )

  return { localX: factorFor(localX), localY: factorFor(localY), localZ: factorFor(localZ) }
}

function getBoardDimensionRange(baseDimension, fixedOffset = 0) {
  const scalableBase = Math.max(1, baseDimension - fixedOffset)
  const min = Math.max(1, Math.round(fixedOffset + scalableBase * 0.5))
  const max = Math.min(MAX_DIMENSION_MM, Math.max(min, Math.round(fixedOffset + scalableBase * 2)))
  return { min: Math.min(min, MAX_DIMENSION_MM), max, default: clamp(baseDimension, 1, MAX_DIMENSION_MM) }
}

export function getCustomModelDimensions(parts) {
  const bounds = getBounds(parts)
  return {
    length: Math.round(clamp(bounds.maxX - bounds.minX, MIN_DIMENSION_MM, MAX_DIMENSION_MM)),
    width: Math.round(clamp(bounds.maxZ - bounds.minZ, MIN_DIMENSION_MM, MAX_DIMENSION_MM)),
    height: Math.round(clamp(bounds.maxY - bounds.minY, MIN_DIMENSION_MM, MAX_DIMENSION_MM)),
  }
}

export function getCustomModelDimensionConstraints(parts) {
  const bounds = getBounds(parts)
  const verticalAnchor = getVerticalAnchor(parts ?? [], bounds)
  const fixedFootHeight = Math.max(0, verticalAnchor - bounds.minY)

  return {
    length: getBoardDimensionRange(bounds.maxX - bounds.minX),
    width: getBoardDimensionRange(bounds.maxZ - bounds.minZ),
    height: getBoardDimensionRange(bounds.maxY - bounds.minY, fixedFootHeight),
  }
}

/**
 * Scales a saved custom model as one assembly. Board dimensions and component
 * positions follow the three overall dimension controls; handles and short
 * feet keep their physical size. Structural legs keep their width/diameter,
 * while their vertical length follows the height control so they stay joined
 * to the tabletop instead of floating underneath it.
 *
 * This always derives from the saved model parts, not the previously scaled
 * result, so moving sliders back and forth does not accumulate rounding error
 * or overwrite the reusable model template.
 */
export function scaleCustomModelParts(baseParts, targetDimensions) {
  const parts = (baseParts ?? []).map((part) => ({ ...part }))
  if (!parts.length) return []

  const bounds = getBounds(parts)
  const baseLength = Math.max(1, bounds.maxX - bounds.minX)
  const baseWidth = Math.max(1, bounds.maxZ - bounds.minZ)
  const verticalAnchor = getVerticalAnchor(parts, bounds)
  const fixedFootHeight = Math.max(0, verticalAnchor - bounds.minY)
  const baseScalableHeight = Math.max(1, bounds.maxY - verticalAnchor)

  const targetLength = clamp(targetDimensions.length, 1, MAX_DIMENSION_MM)
  const targetWidth = clamp(targetDimensions.width, 1, MAX_DIMENSION_MM)
  const targetHeight = clamp(targetDimensions.height, 1, MAX_DIMENSION_MM)
  const scaleX = targetLength / baseLength
  const scaleZ = targetWidth / baseWidth
  const scaleY = Math.max(1 / baseScalableHeight, (targetHeight - fixedFootHeight) / baseScalableHeight)
  const centerX = (bounds.minX + bounds.maxX) / 2
  const centerZ = (bounds.minZ + bounds.maxZ) / 2

  const scaled = parts.map((part) => {
    const next = { ...part }
    const originalVerticalExtent = verticalExtent(part)
    const originalCenterY = (Number(part.pos_y_mm) || 0) + originalVerticalExtent / 2
    const isBoard = part.component_type === 'board'
    const isStructuralLeg = part.component_type === 'leg' && STRUCTURAL_LEG_SHAPES.has(part.shape)
    const isStaticFoot = part.component_type === 'leg' && STATIC_FOOT_SHAPES.has(part.shape)

    const axisScales = getAxisScaleFactors(part, scaleX, scaleY, scaleZ)

    if (isBoard) {
      if (part.shape === 'circular') {
        // Keep circular boards circular even when length and width scales differ.
        const diameterScale = Math.sqrt(axisScales.localX * axisScales.localZ)
        next.length_mm = Math.max(1, Math.round(Number(part.length_mm) * diameterScale))
        next.width_mm = next.length_mm
      } else {
        next.length_mm = Math.max(1, Math.round(Number(part.length_mm) * axisScales.localX))
        next.width_mm = Math.max(1, Math.round(Number(part.width_mm) * axisScales.localZ))
      }
      next.height_mm = Math.max(1, Math.round(Number(part.height_mm) * axisScales.localY))
    } else if (isStructuralLeg) {
      // Keep a leg's footprint intact, only changing its vertical reach.
      next.height_mm = Math.max(1, Math.round(Number(part.height_mm) * axisScales.localY))
    }

    const nextVerticalExtent = verticalExtent(next)
    next.pos_x_mm = Math.round(centerX + ((Number(part.pos_x_mm) || 0) - centerX) * scaleX)
    next.pos_z_mm = Math.round(centerZ + ((Number(part.pos_z_mm) || 0) - centerZ) * scaleZ)

    if (isStaticFoot) {
      // A short foot stays on the floor while the carcass scales above it.
      next.pos_y_mm = Number(part.pos_y_mm) || 0
    } else {
      const nextCenterY = verticalAnchor + (originalCenterY - verticalAnchor) * scaleY
      next.pos_y_mm = Math.round(nextCenterY - nextVerticalExtent / 2)
    }

    return next
  })

  return constrainCustomLegsToBoards(scaled)
}
