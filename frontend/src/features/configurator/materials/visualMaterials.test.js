import { describe, expect, it } from 'vitest'
import { getMaterialProps } from '../materials/visualMaterials'

describe('getMaterialProps', () => {
  it('prefers color_hex when available', () => {
    const material = {
      color_hex: '#123456',
      visual_key: 'oak-natural',
      material_group: 'wood',
    }

    expect(getMaterialProps(material).color).toBe('#123456')
  })

  it('falls back to visual_key mapping', () => {
    const material = {
      visual_key: 'brass',
      material_group: 'metal',
    }

    expect(getMaterialProps(material).color).toBe('#b5a642')
    expect(getMaterialProps(material).metalness).toBe(0.8)
  })

  it('falls back to material group when no color or visual key', () => {
    const material = {
      material_group: 'wood',
    }

    expect(getMaterialProps(material).color).toBe('#c49a6c')
  })

  it('returns a default for missing material', () => {
    expect(getMaterialProps(null).color).toBe('#e5e5e5')
  })
})
