import { useState } from 'react'
import { Alert } from '../../components/Alert'
import { Button } from '../../components/Button'
import { FormField } from '../../components/FormField'
import { Modal } from '../../components/Modal'
import { formatQuantity } from '../../lib/format'

export function AdjustStockModal({ material, onClose, onSubmit }) {
  const [direction, setDirection] = useState('add')
  const [quantity, setQuantity] = useState('')
  const [reason, setReason] = useState('')
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  function update(field, value) {
    if (field === 'quantity') {
      setQuantity(value)
    } else {
      setReason(value)
    }

    setErrors((previous) => ({ ...previous, [field]: undefined }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    const nextErrors = {}
    const amount = Number(quantity)

    if (quantity.trim() === '' || Number.isNaN(amount)) {
      nextErrors.quantity = 'Enter the quantity to adjust.'
    } else if (amount <= 0) {
      nextErrors.quantity = 'Quantity must be greater than zero.'
    } else if (material.stock_unit === 'ea' && !/^\d+$/.test(quantity.trim())) {
      nextErrors.quantity = 'Use whole numbers only for ea units.'
    } else if (material.stock_unit !== 'ea' && !/^\d+(\.\d{1,6})?$/.test(quantity.trim())) {
      nextErrors.quantity = 'Use a number with up to 6 decimal places.'
    }

    if (!reason.trim()) {
      nextErrors.reason = 'A reason is required for every stock adjustment.'
    }

    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors)
      return
    }

    setSubmitting(true)
    setFormError('')
    setErrors({})

    const signedQuantity = direction === 'remove' ? `-${quantity.trim()}` : quantity.trim()

    try {
      await onSubmit({ quantity: signedQuantity, reason: reason.trim() })
    } catch (error) {
      const fieldErrors = error.errors ?? {}
      const entries = Object.entries(fieldErrors)

      if (entries.length > 0) {
        setErrors(
          Object.fromEntries(
            entries.map(([field, messages]) => [
              field,
              Array.isArray(messages) ? messages[0] : String(messages),
            ]),
          ),
        )
      } else {
        setFormError(error.message || 'Could not adjust the stock balance.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal
      title="Adjust stock"
      description={`${material.name} — current balance ${formatQuantity(
        material.quantity_on_hand,
        { unit: material.stock_unit },
      )}`}
      onClose={onClose}
    >
      <form className="form" onSubmit={handleSubmit} noValidate>
        {formError ? (
          <Alert tone="error" onDismiss={() => setFormError('')}>
            {formError}
          </Alert>
        ) : null}

        <div className="segmented" role="group" aria-label="Adjustment direction">
          <button
            type="button"
            className={direction === 'add' ? 'is-active' : undefined}
            onClick={() => setDirection('add')}
          >
            Add stock
          </button>
          <button
            type="button"
            className={direction === 'remove' ? 'is-active' : undefined}
            onClick={() => setDirection('remove')}
          >
            Remove stock
          </button>
        </div>

        <FormField
          label="Quantity"
          required
          error={errors.quantity}
          hint={material.stock_unit === 'ea'
            ? 'Each (ea) quantities must be whole numbers. The API rejects negative balances.'
            : `Recorded in ${material.stock_unit}. The API rejects negative balances.`}
        >
          {(props) => (
            <input
              {...props}
              type="number"
              min="0"
              step={material.stock_unit === 'ea' ? '1' : '0.000001'}
              value={quantity}
              onChange={(event) => update('quantity', event.target.value)}
              placeholder={material.stock_unit === 'ea' ? '0' : '0.000000'}
              autoFocus
            />
          )}
        </FormField>

        <FormField
          label="Reason"
          required
          error={errors.reason}
          hint="Kept in the audit trail. Maximum 1,000 characters."
        >
          {(props) => (
            <textarea
              {...props}
              rows={3}
              maxLength={1000}
              value={reason}
              onChange={(event) => update('reason', event.target.value)}
              placeholder="e.g. Received supplier delivery, damaged board discarded"
            />
          )}
        </FormField>

        <div className="form-actions">
          <Button onClick={onClose} disabled={submitting}>
            Cancel
          </Button>
          <Button
            type="submit"
            variant={direction === 'remove' ? 'danger' : 'primary'}
            disabled={submitting}
          >
            {submitting
              ? 'Recording…'
              : direction === 'remove'
                ? 'Remove stock'
                : 'Add stock'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
