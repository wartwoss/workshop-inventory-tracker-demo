import { describe, expect, it } from 'vitest'
import { componentBounds, getPartSliderRange, updateCustomParts } from './partGeometry'

const topBoard = (overrides = {}) => ({
  code: 'top',
  component_type: 'board',
  shape: 'rectangular',
  length_mm: 1200,
  width_mm: 600,
  height_mm: 20,
  pos_x_mm: 0,
  pos_y_mm: 700,
  pos_z_mm: 0,
  rot_x_deg: 0,
  rot_y_deg: 0,
  rot_z_deg: 0,
  ...overrides,
})

const leg = (overrides = {}) => ({
  code: 'leg',
  component_type: 'leg',
  shape: 'rectangular_leg',
  length_mm: 60,
  width_mm: 60,
  height_mm: 600,
  pos_x_mm: 560,
  pos_y_mm: 0,
  pos_z_mm: 260,
  rot_x_deg: 0,
  rot_y_deg: 0,
  rot_z_deg: 0,
  ...overrides,
})

describe('custom component geometry', () => {
  it('caps a leg at the underside of a tabletop when its height is increased', () => {
    const parts = [topBoard(), leg()]
    const updated = updateCustomParts(parts, 'leg', { height_mm: 900 })
    expect(updated.find((part) => part.code === 'leg').height_mm).toBe(700)
  })

  it('keeps a leg inside the tabletop footprint when the top is shortened', () => {
    const parts = [topBoard(), leg({ pos_x_mm: 560 })]
    const updated = updateCustomParts(parts, 'top', { length_mm: 800 })
    const updatedLeg = updated.find((part) => part.code === 'leg')
    const topBounds = componentBounds(updated.find((part) => part.code === 'top'))
    const legBounds = componentBounds(updatedLeg)
    expect(legBounds.minX).toBeGreaterThanOrEqual(topBounds.minX - 0.01)
    expect(legBounds.maxX).toBeLessThanOrEqual(topBounds.maxX + 0.01)
    expect(legBounds.minZ).toBeGreaterThanOrEqual(topBounds.minZ - 0.01)
    expect(legBounds.maxZ).toBeLessThanOrEqual(topBounds.maxZ + 0.01)
  })

  it('pushes a perpendicular board away when a board is lengthened into it', () => {
    const parts = [
      topBoard({ length_mm: 1000, width_mm: 20, height_mm: 700, pos_y_mm: 0 }),
      topBoard({
        code: 'side', length_mm: 20, width_mm: 500, height_mm: 700,
        pos_x_mm: 510, pos_y_mm: 0, pos_z_mm: 0,
      }),
    ]
    const updated = updateCustomParts(parts, 'top', { length_mm: 1100 })
    const mainBounds = componentBounds(updated.find((part) => part.code === 'top'))
    const sideBounds = componentBounds(updated.find((part) => part.code === 'side'))
    expect(sideBounds.minX).toBeGreaterThanOrEqual(mainBounds.maxX - 0.01)
  })

  it('limits the leg height slider to the gap under the tabletop', () => {
    const parts = [topBoard(), leg({ height_mm: 400 })]
    expect(getPartSliderRange(parts, parts[1], 'height_mm').max).toBe(700)
  })
})
