import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { AdjustStockModal } from './AdjustStockModal'

describe('AdjustStockModal', () => {
  it('rejects fractional quantities for ea materials', async () => {
    const onSubmit = vi.fn()

    render(
      <AdjustStockModal
        material={{ id: 1, name: 'Cabinet handle', stock_unit: 'ea', quantity_on_hand: '10.000000' }}
        onClose={vi.fn()}
        onSubmit={onSubmit}
      />,
    )

    fireEvent.change(screen.getByLabelText(/quantity/i), { target: { value: '2.5' } })
    fireEvent.change(screen.getByLabelText(/reason/i), { target: { value: 'Received shipment' } })
    fireEvent.click(screen.getByRole('button', { name: /add stock/i }))

    expect(await screen.findByText(/whole numbers only for ea units/i)).toBeInTheDocument()
    expect(onSubmit).not.toHaveBeenCalled()
  })
})
