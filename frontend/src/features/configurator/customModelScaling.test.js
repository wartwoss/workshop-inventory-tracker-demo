import { describe, expect, it } from 'vitest'
import { componentBounds } from './partGeometry'
import {
  getCustomModelDimensionConstraints,
  getCustomModelDimensions,
  scaleCustomModelParts,
} from './customModelScaling'

const top = {
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
}

const legs = [
  { code: 'leg-1', component_type: 'leg', shape: 'rectangular_leg', length_mm: 60, width_mm: 60, height_mm: 700, pos_x_mm: -570, pos_y_mm: 0, pos_z_mm: -270, rot_x_deg: 0, rot_y_deg: 0, rot_z_deg: 0 },
  { code: 'leg-2', component_type: 'leg', shape: 'rectangular_leg', length_mm: 60, width_mm: 60, height_mm: 700, pos_x_mm: 570, pos_y_mm: 0, pos_z_mm: -270, rot_x_deg: 0, rot_y_deg: 0, rot_z_deg: 0 },
  { code: 'leg-3', component_type: 'leg', shape: 'rectangular_leg', length_mm: 60, width_mm: 60, height_mm: 700, pos_x_mm: -570, pos_y_mm: 0, pos_z_mm: 270, rot_x_deg: 0, rot_y_deg: 0, rot_z_deg: 0 },
  { code: 'leg-4', component_type: 'leg', shape: 'rectangular_leg', length_mm: 60, width_mm: 60, height_mm: 700, pos_x_mm: 570, pos_y_mm: 0, pos_z_mm: 270, rot_x_deg: 0, rot_y_deg: 0, rot_z_deg: 0 },
]

const handle = {
  code: 'handle', component_type: 'handle', shape: 'bar',
  length_mm: 150, width_mm: 30, height_mm: 30,
  pos_x_mm: 0, pos_y_mm: 500, pos_z_mm: 285,
  rot_x_deg: 0, rot_y_deg: 0, rot_z_deg: 0,
}

const baseParts = [top, ...legs, handle]

function boundsFor(parts) {
  const bounds = parts.map(componentBounds)
  return {
    length: Math.round(Math.max(...bounds.map((item) => item.maxX)) - Math.min(...bounds.map((item) => item.minX))),
    width: Math.round(Math.max(...bounds.map((item) => item.maxZ)) - Math.min(...bounds.map((item) => item.minZ))),
    height: Math.round(Math.max(...bounds.map((item) => item.maxY)) - Math.min(...bounds.map((item) => item.minY))),
  }
}

describe('custom model overall scaling', () => {
  it('derives initial dimensions from the assembled component bounds', () => {
    expect(getCustomModelDimensions([top, ...legs])).toEqual({ length: 1200, width: 600, height: 720 })
  })

  it('scales the board assembly and keeps hardware sizes fixed', () => {
    const scaled = scaleCustomModelParts(baseParts, { length: 1800, width: 900, height: 1080 })
    const scaledTop = scaled.find((part) => part.code === 'top')
    const scaledLeg = scaled.find((part) => part.code === 'leg-1')
    const scaledHandle = scaled.find((part) => part.code === 'handle')

    expect(scaledTop.length_mm).toBe(1800)
    expect(scaledTop.width_mm).toBe(900)
    expect(scaledTop.height_mm).toBe(30)
    expect(scaledLeg.length_mm).toBe(60)
    expect(scaledLeg.width_mm).toBe(60)
    expect(scaledLeg.height_mm).toBe(1050)
    expect(scaledHandle.length_mm).toBe(150)
    expect(scaledHandle.width_mm).toBe(30)
    expect(scaledHandle.height_mm).toBe(30)
    expect(boundsFor(scaled)).toEqual({ length: 1800, width: 900, height: 1080 })
  })

  it('always rescales from the base layout without compounding slider changes', () => {
    const small = scaleCustomModelParts(baseParts, { length: 800, width: 400, height: 500 })
    const restored = scaleCustomModelParts(baseParts, getCustomModelDimensions(baseParts))
    expect(small.find((part) => part.code === 'top').length_mm).toBe(800)
    expect(restored).toEqual(baseParts)
  })

  it('provides a practical range around each custom model size', () => {
    const constraints = getCustomModelDimensionConstraints([top, ...legs])
    expect(constraints.length.min).toBeLessThan(1200)
    expect(constraints.length.max).toBeGreaterThan(1200)
    expect(constraints.height.min).toBeLessThan(720)
    expect(constraints.height.max).toBeGreaterThan(720)
  })

  it('scales a perpendicular board along world axes rather than its original local axis', () => {
    const side = {
      ...top,
      code: 'side',
      length_mm: 700,
      width_mm: 20,
      height_mm: 600,
      pos_x_mm: 590,
      pos_y_mm: 0,
      rot_y_deg: 90,
    }
    const scaled = scaleCustomModelParts([top, side], { length: 2400, width: 1200, height: 1440 })
    const scaledSide = scaled.find((part) => part.code === 'side')
    // Local X points along world Z after a 90-degree yaw.
    expect(scaledSide.length_mm).toBe(1200)
    expect(scaledSide.width_mm).toBe(40)
  })
})
