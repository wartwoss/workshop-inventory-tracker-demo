import { useEffect, useMemo, useState } from 'react'
import { Alert } from '../../components/Alert'
import { Button } from '../../components/Button'
import { FormField } from '../../components/FormField'
import { Modal } from '../../components/Modal'
import { listMovements } from '../../api/materials'
import { MATERIAL_GROUP_SUGGESTIONS, MATERIAL_PURPOSES, STOCK_UNITS } from '../../lib/constants'

const EMPTY_FORM = {
  name: '',
  sku: '',
  material_group: '',
  purpose: 'board',
  stock_unit: 'm2',
  opening_quantity: '',
  reorder_level: '',
  visual_key: '',
  color_hex: '#e5e5e5',
  texture_file: null,
  remove_texture: false,
  is_active: true,
}

function isValidHex(value) {
  return /^#[0-9A-Fa-f]{6}$/.test(value)
}

function normalizeHex(value) {
  const trimmed = String(value ?? '').trim()
  if (!trimmed.startsWith('#') && /^[0-9A-Fa-f]{6}$/.test(trimmed)) {
    return `#${trimmed}`
  }
  return trimmed
}

function toForm(material) {
  return {
    name: material.name ?? '',
    sku: material.sku ?? '',
    material_group: material.material_group ?? '',
    purpose: material.purpose ?? 'board',
    stock_unit: material.stock_unit ?? 'm2',
    opening_quantity: '',
    reorder_level:
      material.reorder_level === null || material.reorder_level === undefined
        ? ''
        : String(material.reorder_level),
    visual_key: material.visual_key ?? '',
    color_hex: material.color_hex || '#e5e5e5',
    texture_file: null,
    remove_texture: false,
    is_active: Boolean(material.is_active),
  }
}

function isValidQuantity(value, unit) {
  const trimmed = String(value).trim()
  return unit === 'ea'
    ? /^\d+$/.test(trimmed)
    : /^\d+(\.\d{1,6})?$/.test(trimmed)
}

export function MaterialFormModal({ mode, material, groups, onClose, onSubmit }) {
  const isEdit = mode === 'edit'
  const [form, setForm] = useState(() => (isEdit ? toForm(material) : EMPTY_FORM))
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [unitState, setUnitState] = useState({
    loading: isEdit,
    hasMovements: false,
  })
  const [texturePreview, setTexturePreview] = useState(
    isEdit ? material.texture_url ?? null : null,
  )

  useEffect(() => {
    if (!isEdit) {
      return undefined
    }

    let active = true

    listMovements(material.id, { per_page: 1 })
      .then((response) => {
        if (active) {
          setUnitState({ loading: false, hasMovements: response.total > 0 })
        }
      })
      .catch(() => {
        if (active) {
          setUnitState({ loading: false, hasMovements: false })
        }
      })

    return () => {
      active = false
    }
  }, [isEdit, material])

  useEffect(() => {
    return () => {
      if (texturePreview && texturePreview.startsWith('blob:')) {
        URL.revokeObjectURL(texturePreview)
      }
    }
  }, [texturePreview])

  const groupSuggestions = useMemo(() => {
    const values = new Set([
      ...MATERIAL_GROUP_SUGGESTIONS,
      ...groups.map((entry) => entry.material_group),
      form.material_group,
    ])

    return [...values].filter(Boolean)
  }, [groups, form.material_group])

  function update(field, value) {
    setForm((previous) => ({ ...previous, [field]: value }))
    setErrors((previous) => ({ ...previous, [field]: undefined }))
  }

  useEffect(() => {
    const defaults = { board: 'm2', leg: 'ea', handle: 'ea', other: 'ea' }
    const defaultUnit = defaults[form.purpose]

    if (defaultUnit) {
      update('stock_unit', defaultUnit)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.purpose])

  function handleColorChange(value) {
    const hex = normalizeHex(value)
    update('color_hex', isValidHex(hex) ? hex : form.color_hex)
  }

  function handleTextureSelect(file) {
    if (!file) {
      return
    }

    if (texturePreview && texturePreview.startsWith('blob:')) {
      URL.revokeObjectURL(texturePreview)
    }

    update('texture_file', file)
    update('remove_texture', false)
    setTexturePreview(URL.createObjectURL(file))
  }

  function handleRemoveTexture() {
    if (texturePreview && texturePreview.startsWith('blob:')) {
      URL.revokeObjectURL(texturePreview)
    }

    update('texture_file', null)
    update('remove_texture', true)
    setTexturePreview(null)
  }

  function validate() {
    const next = {}

    if (!form.name.trim()) {
      next.name = 'Material name is required.'
    }

    if (!form.material_group.trim()) {
      next.material_group = 'Choose or enter a material group.'
    }

    if (!form.purpose) {
      next.purpose = 'Choose a material purpose.'
    }

    if (!form.stock_unit) {
      next.stock_unit = 'Choose a stock unit.'
    }

    if (form.opening_quantity !== '' && !isValidQuantity(form.opening_quantity, form.stock_unit)) {
      next.opening_quantity = form.stock_unit === 'ea'
        ? 'Use whole numbers only for ea units.'
        : 'Enter a non-negative number with up to 6 decimals.'
    }

    if (form.reorder_level !== '' && !isValidQuantity(form.reorder_level, form.stock_unit)) {
      next.reorder_level = form.stock_unit === 'ea'
        ? 'Use whole numbers only for ea units.'
        : 'Enter a non-negative number with up to 6 decimals.'
    }

    if (form.color_hex && !isValidHex(form.color_hex)) {
      next.color_hex = 'Enter a valid hex color such as #8B5A2B.'
    }

    return next
  }

  async function handleSubmit(event) {
    event.preventDefault()
    const nextErrors = validate()

    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors)
      return
    }

    setSubmitting(true)
    setFormError('')
    setErrors({})

    const payload = new FormData()
    payload.append('name', form.name.trim())
    payload.append('sku', form.sku.trim() || '')
    payload.append('material_group', form.material_group.trim())
    payload.append('purpose', form.purpose)
    payload.append('stock_unit', form.stock_unit)
    payload.append(
      'reorder_level',
      form.reorder_level === '' ? '' : String(form.reorder_level),
    )
    payload.append('visual_key', form.visual_key.trim())
    payload.append('color_hex', form.color_hex)
    payload.append('is_active', form.is_active ? '1' : '0')

    if (!isEdit) {
      payload.append(
        'opening_quantity',
        form.opening_quantity === '' ? '0' : String(form.opening_quantity),
      )
    }

    if (form.texture_file) {
      payload.append('texture', form.texture_file)
    }

    if (isEdit && form.remove_texture) {
      payload.append('remove_texture', '1')
    }

    try {
      await onSubmit(payload)
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
        setFormError(error.message || 'Could not save the material.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal
      title={isEdit ? `Edit ${material.name}` : 'Add material'}
      description={
        isEdit
          ? 'Update catalogue details. Stock balances change only through adjustments.'
          : 'Create a catalogue entry and optionally record its opening stock.'
      }
      onClose={onClose}
      size="lg"
    >
      <form className="form" onSubmit={handleSubmit} noValidate>
        {formError ? (
          <Alert tone="error" onDismiss={() => setFormError('')}>
            {formError}
          </Alert>
        ) : null}

        <div className="form-grid">
          <FormField label="Material name" required error={errors.name}>
            {(props) => (
              <input
                {...props}
                type="text"
                value={form.name}
                onChange={(event) => update('name', event.target.value)}
                maxLength={255}
                autoFocus
                placeholder="e.g. Oak board, 20 mm"
              />
            )}
          </FormField>

          <FormField label="SKU" error={errors.sku} hint="Optional internal stock code.">
            {(props) => (
              <input
                {...props}
                type="text"
                value={form.sku}
                onChange={(event) => update('sku', event.target.value)}
                maxLength={255}
                placeholder="e.g. OAK-020"
              />
            )}
          </FormField>

          <FormField label="Material group" required error={errors.material_group}>
            {(props) => (
              <>
                <input
                  {...props}
                  type="text"
                  list="material-group-options"
                  value={form.material_group}
                  onChange={(event) => update('material_group', event.target.value)}
                  maxLength={255}
                  placeholder="e.g. wood"
                />
                <datalist id="material-group-options">
                  {groupSuggestions.map((group) => (
                    <option key={group} value={group} />
                  ))}
                </datalist>
              </>
            )}
          </FormField>

          <FormField
            label="Purpose"
            required
            error={errors.purpose}
            hint="Controls which furniture components this material can be assigned to."
          >
            {(props) => (
              <select
                {...props}
                value={form.purpose}
                onChange={(event) => update('purpose', event.target.value)}
              >
                {MATERIAL_PURPOSES.map((purpose) => (
                  <option key={purpose.value} value={purpose.value}>
                    {purpose.label}
                  </option>
                ))}
              </select>
            )}
          </FormField>

          <FormField
            label="Stock unit"
            required
            error={errors.stock_unit}
            hint={
              unitState.loading
                ? 'Checking whether this material has movements…'
                : unitState.hasMovements
                  ? 'Locked because this material already has stock movements.'
                  : 'Canonical unit used for every balance and movement.'
            }
          >
            {(props) => (
              <select
                {...props}
                value={form.stock_unit}
                onChange={(event) => update('stock_unit', event.target.value)}
                disabled={isEdit && (unitState.loading || unitState.hasMovements)}
              >
                {STOCK_UNITS.map((unit) => (
                  <option key={unit.value} value={unit.value}>
                    {unit.label}
                  </option>
                ))}
              </select>
            )}
          </FormField>

          {isEdit ? null : (
            <FormField
              label="Opening quantity"
              error={errors.opening_quantity}
              hint={form.stock_unit === 'ea'
                ? 'Use a whole number. Creates an opening-stock movement when greater than zero.'
                : 'Creates an opening-stock movement when greater than zero.'}
            >
              {(props) => (
                <input
                  {...props}
                  type="number"
                  min="0"
                  step={form.stock_unit === 'ea' ? '1' : '0.000001'}
                  value={form.opening_quantity}
                  onChange={(event) => update('opening_quantity', event.target.value)}
                  placeholder="0"
                />
              )}
            </FormField>
          )}

          <FormField
            label="Reorder level"
            error={errors.reorder_level}
            hint={form.stock_unit === 'ea'
              ? 'Use a whole number. Low-stock warnings trigger at or below this quantity.'
              : 'Low-stock warnings trigger at or below this quantity.'}
          >
            {(props) => (
              <input
                {...props}
                type="number"
                min="0"
                step={form.stock_unit === 'ea' ? '1' : '0.000001'}
                value={form.reorder_level}
                onChange={(event) => update('reorder_level', event.target.value)}
                placeholder="Optional"
              />
            )}
          </FormField>

          <FormField
            label="Visual key"
            error={errors.visual_key}
            hint="Optional appearance reference for the 3D configurator."
          >
            {(props) => (
              <input
                {...props}
                type="text"
                value={form.visual_key}
                onChange={(event) => update('visual_key', event.target.value)}
                maxLength={255}
                placeholder="e.g. oak-natural"
              />
            )}
          </FormField>

          <FormField label="Availability">
            {(props) => (
              <label className="checkbox-label compact" htmlFor={props.id}>
                <input
                  {...props}
                  id={props.id}
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(event) => update('is_active', event.target.checked)}
                />
                <span>Available for selection and production</span>
              </label>
            )}
          </FormField>
        </div>

        <div className="form-section">
          <h3>Appearance</h3>
          <p className="field-hint">
            Choose a solid color. Optionally upload a texture image; the color is used as a fallback.
          </p>

          <div className="appearance-grid">
            <FormField
              label="Color"
              required
              error={errors.color_hex}
              hint="Solid color used when no texture is set."
            >
              {(props) => (
                <div className="color-field">
                  <input
                    {...props}
                    type="color"
                    value={isValidHex(form.color_hex) ? form.color_hex : '#e5e5e5'}
                    onChange={(event) => handleColorChange(event.target.value)}
                  />
                  <input
                    type="text"
                    value={form.color_hex}
                    onChange={(event) => handleColorChange(event.target.value)}
                    maxLength={7}
                    placeholder="#e5e5e5"
                    aria-label="Hex color"
                  />
                </div>
              )}
            </FormField>

            <FormField
              label="Texture image"
              error={errors.texture}
              hint="JPEG, PNG, or WebP up to 5 MB. Optional."
            >
              {(props) => (
                <div className="texture-field">
                  <input
                    {...props}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    onChange={(event) => handleTextureSelect(event.target.files?.[0] ?? null)}
                  />
                  {texturePreview ? (
                    <div className="texture-preview">
                      <img src={texturePreview} alt="Texture preview" />
                      <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        onClick={handleRemoveTexture}
                      >
                        Remove texture
                      </Button>
                    </div>
                  ) : null}
                </div>
              )}
            </FormField>
          </div>
        </div>

        <div className="form-actions">
          <Button onClick={onClose} disabled={submitting}>
            Cancel
          </Button>
          <Button type="submit" variant="primary" disabled={submitting}>
            {submitting ? 'Saving…' : isEdit ? 'Save changes' : 'Create material'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
