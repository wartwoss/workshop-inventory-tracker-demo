import { fireEvent, render, within } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { MaterialFormModal } from './MaterialFormModal'

vi.mock('../../api/materials', () => ({
  listMovements: vi.fn().mockResolvedValue({ total: 0 }),
}))

afterEach(() => {
  vi.clearAllMocks()
})

describe('MaterialFormModal appearance controls', () => {
  const baseMaterial = {
    id: 1,
    name: 'Oak board',
    sku: 'OAK-020',
    material_group: 'wood',
    purpose: 'board',
    stock_unit: 'm2',
    reorder_level: null,
    visual_key: 'oak-natural',
    color_hex: '#c49a6c',
    texture_url: null,
    is_active: true,
  }

  function renderModal(props = {}) {
    return render(
      <MaterialFormModal
        mode="edit"
        material={baseMaterial}
        groups={[]}
        onClose={vi.fn()}
        onSubmit={vi.fn()}
        {...props}
      />,
    )
  }

  it('shows the stored color in the picker and hex input', () => {
    const { container } = renderModal()

    const picker = container.querySelector('input[type="color"]')
    const hexInput = container.querySelector('input[aria-label="Hex color"]')

    expect(picker).toHaveValue('#c49a6c')
    expect(hexInput).toHaveValue('#c49a6c')
  })

  it('updates the hex input when the color picker changes', () => {
    const { container } = renderModal()

    const picker = container.querySelector('input[type="color"]')
    const hexInput = container.querySelector('input[aria-label="Hex color"]')

    fireEvent.change(picker, { target: { value: '#123456' } })

    expect(hexInput).toHaveValue('#123456')
  })

  it('updates the color picker when a valid hex value is typed', () => {
    const { container } = renderModal()

    const picker = container.querySelector('input[type="color"]')
    const hexInput = container.querySelector('input[aria-label="Hex color"]')

    fireEvent.change(hexInput, { target: { value: '#654321' } })

    expect(picker).toHaveValue('#654321')
  })

  it('shows a preview after selecting a texture file', () => {
    const { container } = renderModal()

    const fileInput = container.querySelector('input[type="file"]')
    const file = new File(['pixels'], 'wood.png', { type: 'image/png' })

    global.URL.createObjectURL = vi.fn().mockReturnValue('blob:preview-url')
    global.URL.revokeObjectURL = vi.fn()

    fireEvent.change(fileInput, { target: { files: [file] } })

    const preview = container.querySelector('img[alt="Texture preview"]')
    expect(preview).toHaveAttribute('src', 'blob:preview-url')
  })

  it('clears the preview when remove texture is clicked', () => {
    const { container } = renderModal()

    const fileInput = container.querySelector('input[type="file"]')
    const file = new File(['pixels'], 'wood.png', { type: 'image/png' })

    global.URL.createObjectURL = vi.fn().mockReturnValue('blob:preview-url')
    global.URL.revokeObjectURL = vi.fn()

    fireEvent.change(fileInput, { target: { files: [file] } })
    expect(container.querySelector('img[alt="Texture preview"]')).toBeInTheDocument()

    const removeButton = within(container).getByRole('button', { name: /remove texture/i })
    fireEvent.click(removeButton)

    expect(container.querySelector('img[alt="Texture preview"]')).not.toBeInTheDocument()
  })

  it('shows the stored purpose in the purpose dropdown', () => {
    const { getByLabelText } = renderModal()

    const purposeSelect = getByLabelText(/purpose/i)
    expect(purposeSelect).toHaveValue('board')
  })

  it('changes the purpose value when a different purpose is selected', () => {
    const { getByLabelText } = renderModal()

    const purposeSelect = getByLabelText(/purpose/i)
    fireEvent.change(purposeSelect, { target: { value: 'handle' } })
    expect(purposeSelect).toHaveValue('handle')
  })
})
