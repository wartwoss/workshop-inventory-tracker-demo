const ROUND_SHAPES = new Set(['round_foot', 'knob', 'bulb_knob'])
const ROUND_BASE_SHAPES = new Set(['circular', 'cylinder_leg', 'angular_foot'])
const SIZE_FIELDS = new Set(['length_mm', 'width_mm', 'height_mm'])
const MIN_SIZE_MM = 1
const MAX_SIZE_MM = 5000

const clamp = (value, min, max) => Math.max(min, Math.min(max, Number(value) || min))
const radians = (degrees = 0) => (Number(degrees) || 0) * Math.PI / 180

function baseHalfExtents(part) {
  const length = Math.max(MIN_SIZE_MM, Number(part.length_mm) || MIN_SIZE_MM) / 2
  const width = Math.max(MIN_SIZE_MM, Number(part.width_mm) || MIN_SIZE_MM) / 2
  const height = Math.max(MIN_SIZE_MM, Number(part.height_mm) || MIN_SIZE_MM) / 2
  const shape = part.shape || 'rectangular'

  if (ROUND_SHAPES.has(shape)) return { x: length, y: length, z: length }
  if (ROUND_BASE_SHAPES.has(shape)) return { x: length, y: height, z: length }
  return { x: length, y: height, z: width }
}

function transformedHalfExtents(part) {
  const half = baseHalfExtents(part)
  const x = radians(part.rot_x_deg)
  const y = radians(part.rot_y_deg)
  const z = radians(part.rot_z_deg)
  const cx = Math.cos(x), sx = Math.sin(x)
  const cy = Math.cos(y), sy = Math.sin(y)
  const cz = Math.cos(z), sz = Math.sin(z)

  // Absolute rotation-matrix multiplication gives the enclosing axis-aligned bounds.
  const r00 = cy * cz
  const r01 = -cy * sz
  const r02 = sy
  const r10 = sx * sy * cz + cx * sz
  const r11 = -sx * sy * sz + cx * cz
  const r12 = -sx * cy
  const r20 = -cx * sy * cz + sx * sz
  const r21 = cx * sy * sz + sx * cz
  const r22 = cx * cy

  return {
    x: Math.abs(r00) * half.x + Math.abs(r01) * half.y + Math.abs(r02) * half.z,
    y: Math.abs(r10) * half.x + Math.abs(r11) * half.y + Math.abs(r12) * half.z,
    z: Math.abs(r20) * half.x + Math.abs(r21) * half.y + Math.abs(r22) * half.z,
  }
}

export function componentBounds(part) {
  const half = transformedHalfExtents(part)
  const verticalExtent = ROUND_SHAPES.has(part.shape)
    ? Math.max(1, Number(part.length_mm) || 1)
    : Math.max(1, Number(part.height_mm) || 1)
  const center = {
    x: Number(part.pos_x_mm) || 0,
    y: (Number(part.pos_y_mm) || 0) + verticalExtent / 2,
    z: Number(part.pos_z_mm) || 0,
  }
  return {
    minX: center.x - half.x, maxX: center.x + half.x,
    minY: center.y - half.y, maxY: center.y + half.y,
    minZ: center.z - half.z, maxZ: center.z + half.z,
    center,
  }
}

function overlaps(a, b, epsilon = 0.5) {
  return a.minX < b.maxX - epsilon && a.maxX > b.minX + epsilon &&
    a.minY < b.maxY - epsilon && a.maxY > b.minY + epsilon &&
    a.minZ < b.maxZ - epsilon && a.maxZ > b.minZ + epsilon
}

function isHorizontalBoard(part) {
  const length = Number(part.length_mm) || 0
  const width = Number(part.width_mm) || 0
  const height = Number(part.height_mm) || 0
  return part.component_type === 'board' &&
    Math.abs(Number(part.rot_x_deg) || 0) < 1 &&
    Math.abs(Number(part.rot_z_deg) || 0) < 1 &&
    !ROUND_SHAPES.has(part.shape) &&
    height < Math.min(length, width) * 0.75
}

function getTableTop(parts, excludeCode = null) {
  return parts
    .filter((part) => part.code !== excludeCode && isHorizontalBoard(part))
    .filter((part) => Number(part.height_mm) > 0)
    .sort((a, b) => (Number(b.length_mm) * Number(b.width_mm)) - (Number(a.length_mm) * Number(a.width_mm)))[0] ?? null
}

function clampLegToTop(parts, legCode) {
  const legIndex = parts.findIndex((part) => part.code === legCode)
  if (legIndex < 0 || parts[legIndex].component_type !== 'leg') return
  const top = getTableTop(parts, legCode)
  if (!top) return

  let leg = parts[legIndex]
  const topBounds = componentBounds(top)
  const topWidthX = Math.max(1, topBounds.maxX - topBounds.minX)
  const topWidthZ = Math.max(1, topBounds.maxZ - topBounds.minZ)
  const roundLeg = ROUND_SHAPES.has(leg.shape) || ROUND_BASE_SHAPES.has(leg.shape)

  // Keep the leg's footprint within the tabletop. Cylinders, cones, and spheres
  // use one diameter on both horizontal axes; rectangular legs have independent X/Z sizes.
  const maxLegLength = Math.max(1, Math.floor(topWidthX))
  const maxLegWidth = Math.max(1, Math.floor(topWidthZ))
  const maxRoundDiameter = Math.max(1, Math.floor(Math.min(topWidthX, topWidthZ)))
  leg = {
    ...leg,
    length_mm: Math.min(Number(leg.length_mm) || 1, roundLeg ? maxRoundDiameter : maxLegLength),
    width_mm: roundLeg
      ? Math.min(Number(leg.width_mm) || Number(leg.length_mm) || 1, maxRoundDiameter)
      : Math.min(Number(leg.width_mm) || 1, maxLegWidth),
  }

  // A rotated rectangular foot has a larger axis-aligned footprint. Scale it
  // down only if needed to keep all corners inside the usable tabletop area.
  for (let attempt = 0; attempt < 4; attempt += 1) {
    const half = transformedHalfExtents(leg)
    const scale = Math.min(1, topWidthX / (2 * Math.max(half.x, 0.5)), topWidthZ / (2 * Math.max(half.z, 0.5)))
    if (scale >= 0.999) break
    leg.length_mm = Math.max(1, Math.floor(Number(leg.length_mm) * scale))
    if (!roundLeg) leg.width_mm = Math.max(1, Math.floor(Number(leg.width_mm) * scale))
  }

  const half = transformedHalfExtents(leg)
  const minCenterX = topBounds.minX + half.x
  const maxCenterX = topBounds.maxX - half.x
  const minCenterZ = topBounds.minZ + half.z
  const maxCenterZ = topBounds.maxZ - half.z
  const oldX = Number(leg.pos_x_mm) || 0
  const oldZ = Number(leg.pos_z_mm) || 0
  leg.pos_x_mm = minCenterX <= maxCenterX ? clamp(oldX, minCenterX, maxCenterX) : (topBounds.minX + topBounds.maxX) / 2
  leg.pos_z_mm = minCenterZ <= maxCenterZ ? clamp(oldZ, minCenterZ, maxCenterZ) : (topBounds.minZ + topBounds.maxZ) / 2

  // The foot stays on the floor; cap its height at the underside of the tabletop.
  const topBottom = topBounds.minY
  const availableHeight = Math.floor(topBottom - (Number(leg.pos_y_mm) || 0))
  if (availableHeight >= 1) {
    if (ROUND_SHAPES.has(leg.shape) && Number(leg.length_mm) > availableHeight) {
      leg.length_mm = availableHeight
      leg.width_mm = Math.min(Number(leg.width_mm) || availableHeight, availableHeight)
    } else if (Number(leg.height_mm) > availableHeight) {
      leg.height_mm = availableHeight
    }
  }
  parts[legIndex] = leg
}

function pushOverlappingParts(previousParts, nextParts, changedCode, updates) {
  const changedPart = nextParts.find((part) => part.code === changedCode)
  if (!changedPart) return
  const dimensionFields = [...SIZE_FIELDS].filter((field) => updates[field] !== undefined)
  if (dimensionFields.length === 0) return
  if (changedPart.component_type === 'leg' && dimensionFields.includes('height_mm')) return

  const previousChanged = previousParts.find((part) => part.code === changedCode)
  const previousBounds = previousChanged ? componentBounds(previousChanged) : null
  const changedBounds = componentBounds(changedPart)

  let preferredAxes = []
  if (dimensionFields.includes('length_mm')) preferredAxes.push('x')
  if (dimensionFields.includes('width_mm')) preferredAxes.push('z')
  if (dimensionFields.includes('height_mm')) preferredAxes.push('y')
  if (changedPart.shape === 'circular' || changedPart.shape === 'cylinder_leg') {
    preferredAxes = ['x', 'z', ...preferredAxes.filter((axis) => !['x', 'z'].includes(axis))]
  }

  for (const candidate of nextParts) {
    if (candidate.code === changedCode) continue
    const previousCandidate = previousParts.find((part) => part.code === candidate.code)
    const candidateBoundsBefore = previousCandidate ? componentBounds(previousCandidate) : null
    let candidateBounds = componentBounds(candidate)
    if (!overlaps(changedBounds, candidateBounds)) continue
    if (previousBounds && candidateBoundsBefore && overlaps(previousBounds, candidateBoundsBefore)) continue

    const axes = [...preferredAxes].sort((a, b) => {
      const overlapFor = (axis) => Math.min(changedBounds[`max${axis.toUpperCase()}`], candidateBounds[`max${axis.toUpperCase()}`]) -
        Math.max(changedBounds[`min${axis.toUpperCase()}`], candidateBounds[`min${axis.toUpperCase()}`])
      return overlapFor(a) - overlapFor(b)
    })

    for (const axis of axes) {
      const cap = axis.toUpperCase()
      const direction = candidateBounds.center[axis] >= changedBounds.center[axis] ? 1 : -1
      const delta = direction > 0
        ? changedBounds[`max${cap}`] - candidateBounds[`min${cap}`] + 1
        : candidateBounds[`max${cap}`] - changedBounds[`min${cap}`] + 1
      const field = axis === 'x' ? 'pos_x_mm' : axis === 'y' ? 'pos_y_mm' : 'pos_z_mm'
      candidate[field] = Math.round((Number(candidate[field]) || 0) + delta * direction)
      candidateBounds = componentBounds(candidate)
      if (!overlaps(changedBounds, candidateBounds)) break
    }
  }
}

export function updateCustomParts(parts, changedCode, updates) {
  const previousParts = parts.map((part) => ({ ...part }))
  const nextParts = parts.map((part) => part.code === changedCode ? { ...part, ...updates } : { ...part })
  const changedPart = nextParts.find((part) => part.code === changedCode)
  const dimensionChanged = Object.keys(updates).some((field) => SIZE_FIELDS.has(field))

  if (dimensionChanged && changedPart?.component_type === 'leg') clampLegToTop(nextParts, changedCode)
  pushOverlappingParts(previousParts, nextParts, changedCode, updates)

  // Any tabletop resize can leave legs outside its new footprint; pull them back in.
  if (dimensionChanged && changedPart?.component_type === 'board') {
    for (const part of [...nextParts]) {
      if (part.component_type === 'leg') clampLegToTop(nextParts, part.code)
    }
  }

  return nextParts
}

export function maxDimensionForPart(parts, part, field) {
  const controlsVerticalSize = field === 'height_mm' ||
    (field === 'length_mm' && part.component_type === 'leg' && ROUND_SHAPES.has(part.shape))
  if (!controlsVerticalSize || part.component_type !== 'leg') return MAX_SIZE_MM
  const top = getTableTop(parts, part.code)
  if (!top) return MAX_SIZE_MM
  const topBounds = componentBounds(top)
  const legBottom = Number(part.pos_y_mm) || 0
  return Math.max(1, Math.min(MAX_SIZE_MM, Math.floor(topBounds.minY - legBottom)))
}

export function getPartSliderRange(parts, part, field) {
  const current = Math.max(MIN_SIZE_MM, Number(part[field]) || MIN_SIZE_MM)
  const max = maxDimensionForPart(parts, part, field)
  const adaptiveMax = Math.min(max, Math.max(600, current * 2))
  return { min: MIN_SIZE_MM, max: Math.max(MIN_SIZE_MM, adaptiveMax) }
}

/** Keep configurable legs/feet within the largest horizontal board after a global resize. */
export function constrainCustomLegsToBoards(parts) {
  const constrained = (parts ?? []).map((part) => ({ ...part }))
  for (const part of constrained) {
    if (part.component_type === 'leg') {
      clampLegToTop(constrained, part.code)
    }
  }
  return constrained
}
