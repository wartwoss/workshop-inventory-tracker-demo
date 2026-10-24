import { fireEvent, render } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { MaterialSelectors } from './MaterialSelectors'

describe('MaterialSelectors', () => {
  const parts = [
    {
      code: 'carcass',
      name: 'Carcass',
      consumption_unit: 'm3',
      allowed_materials: [
        { id: 1, name: 'Pine' },
        { id: 2, name: 'Oak' },
      ],
    },
    {
      code: 'door',
      name: 'Door',
      consumption_unit: 'm2',
      allowed_materials: [
        { id: 3, name: 'Walnut' },
      ],
    },
  ]

  it('displays the currently selected material in each selector', () => {
    const { container } = render(
      <MaterialSelectors
        parts={parts}
        selections={{
          carcass: { id: 2, name: 'Oak' },
          door: { id: 3, name: 'Walnut' },
        }}
        onChange={vi.fn()}
      />,
    )

    const selects = container.querySelectorAll('select')

    expect(selects[0]).toHaveValue('2')
    expect(selects[1]).toHaveValue('3')
  })

  it('calls onChange with the selected material object when a selection changes', () => {
    const handleChange = vi.fn()

    const { container } = render(
      <MaterialSelectors
        parts={parts}
        selections={{
          carcass: { id: 1, name: 'Pine' },
          door: { id: 3, name: 'Walnut' },
        }}
        onChange={handleChange}
      />,
    )

    const carcassSelect = container.querySelector('select#material-carcass')
    fireEvent.change(carcassSelect, { target: { value: '2' } })

    expect(handleChange).toHaveBeenCalledWith({
      carcass: { id: 2, name: 'Oak' },
      door: { id: 3, name: 'Walnut' },
    })
  })
})
