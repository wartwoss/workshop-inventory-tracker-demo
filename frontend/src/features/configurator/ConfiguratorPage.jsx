import { useCallback, useEffect, useMemo, useState } from 'react'
import { Alert } from '../../components/Alert'
import { Button } from '../../components/Button'
import { EmptyState } from '../../components/EmptyState'
import { LoadingState } from '../../components/LoadingState'
import { deleteFurnitureModel, getFurnitureModel, listFurnitureModels } from '../../api/furniture'
import { confirmProductionOrder, quoteConfiguration } from '../../api/production'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'
import { DimensionControls } from './DimensionControls'
import { MaterialSelectors } from './MaterialSelectors'
import { ModelSelector } from './ModelSelector'
import { QuotePanel } from './QuotePanel'
import { Scene } from './Scene'
import { Modal } from '../../components/Modal'
import { ModelBuilder } from '../modelBuilder/ModelBuilder'
import { getCustomModelDimensions } from './customModelScaling'

function buildDefaultSelections(parts) {
  const selections = {}

  parts.forEach((part) => {
    selections[part.code] =
      part.allowed_materials.find((material) => Number(material.id) === Number(part.material_id)) ??
      part.allowed_materials[0] ??
      null
  })

  return selections
}

function buildDefaultPartSpecs(parts = []) {
  return Object.fromEntries(parts.map((part) => [part.code, {
    length_mm: part.length_mm ?? 1,
    width_mm: part.width_mm ?? 1,
    height_mm: part.height_mm ?? 1,
    pos_x_mm: part.pos_x_mm ?? 0,
    pos_y_mm: part.pos_y_mm ?? 0,
    pos_z_mm: part.pos_z_mm ?? 0,
    rot_x_deg: part.rot_x_deg ?? 0,
    rot_y_deg: part.rot_y_deg ?? 0,
    rot_z_deg: part.rot_z_deg ?? 0,
    shape: part.shape ?? (part.component_type === 'board' ? 'rectangular' : undefined),
  }]))
}

const CUSTOM_SPEC_FIELDS = [
  'length_mm', 'width_mm', 'height_mm',
  'pos_x_mm', 'pos_y_mm', 'pos_z_mm',
  'rot_x_deg', 'rot_y_deg', 'rot_z_deg', 'shape',
]

function buildDefaultDimensions(dimensions) {
  return {
    length: dimensions.length.default,
    width: dimensions.width.default,
    height: dimensions.height.default,
  }
}

function buildQuotePayload(modelDetail, dimensions, quantity, partMaterials, partSpecs = {}) {
  return {
    furniture_model_slug: modelDetail.slug,
    length_mm: dimensions.length,
    width_mm: dimensions.width,
    height_mm: dimensions.height,
    quantity,
    parts: modelDetail.parts.map((part) => {
      const selected = {
        code: part.code,
        material_id: partMaterials[part.code]?.id,
      }

      if (modelDetail.is_custom) {
        const spec = { ...part, ...(partSpecs[part.code] ?? {}) }
        for (const field of CUSTOM_SPEC_FIELDS) {
          if (spec[field] !== undefined && spec[field] !== null) selected[field] = spec[field]
        }
      }

      return selected
    }),
  }
}

function generateIdempotencyKey() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `${Date.now()}-${Math.random().toString(36).slice(2)}`
}

function quoteMatchesConfig(quote, quoteConfig) {
  if (!quote || !quoteConfig) {
    return false
  }

  if (quote.furniture_model?.slug !== quoteConfig.slug) {
    return false
  }

  if (
    quote.configuration.length_mm !== quoteConfig.length ||
    quote.configuration.width_mm !== quoteConfig.width ||
    quote.configuration.height_mm !== quoteConfig.height ||
    quote.configuration.quantity !== quoteConfig.quantity
  ) {
    return false
  }

  const quotedParts = new Map((quote.configuration.parts ?? []).map((part) => [part.code, part]))
  const quoteMaterialIds = new Set(
    quote.components.map((component) => `${component.part_code}:${component.material_id}`),
  )

  for (const part of quoteConfig.parts) {
    if (!quoteMaterialIds.has(`${part.code}:${part.material_id}`)) return false
    const quoted = quotedParts.get(part.code)
    if (!quoted || Number(quoted.material_id) !== Number(part.material_id)) return false

    for (const field of CUSTOM_SPEC_FIELDS) {
      if (part[field] === undefined) continue
      if (field === 'shape') {
        if ((quoted[field] ?? null) !== (part[field] ?? null)) return false
      } else if (Number(quoted[field]) !== Number(part[field])) {
        return false
      }
    }
  }

  return true
}

export function ConfiguratorPage({ onRefreshInventory }) {
  const [models, setModels] = useState([])
  const [selectedSlug, setSelectedSlug] = useState(null)
  const [modelDetail, setModelDetail] = useState(null)
  const [dimensions, setDimensions] = useState({ length: 0, width: 0, height: 0 })
  const [quantity, setQuantity] = useState(1)
  const [partMaterials, setPartMaterials] = useState({})
  const [partSpecs, setPartSpecs] = useState({})
  const [loadingList, setLoadingList] = useState(true)
  const [loadingDetail, setLoadingDetail] = useState(false)
  const [error, setError] = useState('')

  const [quote, setQuote] = useState(null)
  const [quoteLoading, setQuoteLoading] = useState(false)
  const [quoteError, setQuoteError] = useState('')

  const [idempotencyKey, setIdempotencyKey] = useState(() => generateIdempotencyKey())
  const [isConfirming, setIsConfirming] = useState(false)
  const [confirmationSuccess, setConfirmationSuccess] = useState(null)
  const [confirmationError, setConfirmationError] = useState('')
  const [isBuilderOpen, setIsBuilderOpen] = useState(false)
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)
  const [deletingModel, setDeletingModel] = useState(false)
  const [deleteModelError, setDeleteModelError] = useState('')
  const [modelNotice, setModelNotice] = useState('')

  const refreshQuote = useCallback(
    async (payload) => {
      setQuoteLoading(true)
      setQuoteError('')

      try {
        const response = await quoteConfiguration(payload)
        setQuote(response)
      } catch (requestError) {
        if (requestError.name !== 'AbortError') {
          setQuoteError(requestError.message || 'Could not calculate quote.')
        }
      } finally {
        setQuoteLoading(false)
      }
    },
    [],
  )

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    setLoadingList(true)
    setError('')

    listFurnitureModels(controller.signal)
      .then((response) => {
        if (!active) {
          return
        }

        setModels(response)
      })
      .catch((requestError) => {
        if (!active || requestError.name === 'AbortError') {
          return
        }

        setError(requestError.message || 'Could not load furniture models.')
      })
      .finally(() => {
        if (active) {
          setLoadingList(false)
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [])

  useEffect(() => {
    if (models.length > 0 && selectedSlug === null) {
      setSelectedSlug(models[0].slug)
    }
  }, [models, selectedSlug])

  useEffect(() => {
    if (!selectedSlug) {
      return undefined
    }

    const controller = new AbortController()
    let active = true

    setLoadingDetail(true)
    setError('')
    setQuote(null)
    setQuoteError('')
    setConfirmationSuccess(null)
    setConfirmationError('')

    getFurnitureModel(selectedSlug, controller.signal)
      .then((response) => {
        if (!active) {
          return
        }

        setModelDetail(response)
        setDimensions(response.is_custom
          ? getCustomModelDimensions(response.parts)
          : buildDefaultDimensions(response.dimensions))
        setQuantity(1)
        setPartMaterials(buildDefaultSelections(response.parts))
        setPartSpecs(response.is_custom ? buildDefaultPartSpecs(response.parts) : {})
      })
      .catch((requestError) => {
        if (!active || requestError.name === 'AbortError') {
          return
        }

        setError(requestError.message || 'Could not load the selected model.')
      })
      .finally(() => {
        if (active) {
          setLoadingDetail(false)
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [selectedSlug])

  const quoteConfig = useMemo(() => {
    if (!modelDetail) {
      return null
    }

    return {
      slug: selectedSlug,
      length: dimensions.length,
      width: dimensions.width,
      height: dimensions.height,
      quantity,
      materials: Object.fromEntries(
        modelDetail.parts.map((part) => [part.code, partMaterials[part.code]?.id ?? null]),
      ),
      parts: modelDetail.parts.map((part) => {
        const selection = {
          code: part.code,
          material_id: partMaterials[part.code]?.id ?? null,
        }
        if (modelDetail.is_custom) {
          const spec = { ...part, ...(partSpecs[part.code] ?? {}) }
          for (const field of CUSTOM_SPEC_FIELDS) {
            if (spec[field] !== undefined && spec[field] !== null) selection[field] = spec[field]
          }
        }
        return selection
      }),
    }
  }, [modelDetail, selectedSlug, dimensions, quantity, partMaterials, partSpecs])

  const debouncedQuoteConfig = useDebouncedValue(quoteConfig, 350)

  useEffect(() => {
    if (!debouncedQuoteConfig || !modelDetail) {
      return undefined
    }

    const allPartsHaveMaterial = debouncedQuoteConfig.parts.every(
      (part) => part.material_id != null,
    )
    const isIntegerInRange = (value, min, max) =>
      value !== '' && value !== null && value !== undefined &&
      Number.isInteger(Number(value)) && Number(value) >= min && Number(value) <= max

    const specsAreValid = !modelDetail.is_custom || debouncedQuoteConfig.parts.every((part) =>
      ['length_mm', 'width_mm', 'height_mm'].every((field) => isIntegerInRange(part[field], 1, 5000)) &&
      ['pos_x_mm', 'pos_y_mm', 'pos_z_mm'].every((field) => isIntegerInRange(part[field], -5000, 5000)) &&
      ['rot_x_deg', 'rot_y_deg', 'rot_z_deg'].every((field) => isIntegerInRange(part[field], -360, 360))
    )

    if (!allPartsHaveMaterial || !specsAreValid) {
      setQuote(null)
      setQuoteError(specsAreValid ? '' : 'Enter valid positive component dimensions before quoting.')
      setQuoteLoading(false)
      return undefined
    }

    const controller = new AbortController()
    let active = true

    setQuoteLoading(true)
    setQuoteError('')

    const payload = {
      furniture_model_slug: modelDetail.slug,
      length_mm: debouncedQuoteConfig.length,
      width_mm: debouncedQuoteConfig.width,
      height_mm: debouncedQuoteConfig.height,
      quantity: debouncedQuoteConfig.quantity,
      parts: debouncedQuoteConfig.parts,
    }

    quoteConfiguration(payload)
      .then((response) => {
        if (!active) {
          return
        }

        setQuote(response)
      })
      .catch((requestError) => {
        if (!active || requestError.name === 'AbortError') {
          return
        }

        setQuoteError(requestError.message || 'Could not calculate quote.')
      })
      .finally(() => {
        if (active) {
          setQuoteLoading(false)
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [debouncedQuoteConfig, modelDetail])

  useEffect(() => {
    if (quoteConfig) {
      setIdempotencyKey(generateIdempotencyKey())
    }
  }, [quoteConfig])

  async function handleConfirm() {
    if (!modelDetail || !quote || quoteLoading || isConfirming) {
      return
    }

    if (!quote.is_sufficient || !quoteMatchesConfig(quote, quoteConfig)) {
      return
    }

    setIsConfirming(true)
    setConfirmationSuccess(null)
    setConfirmationError('')

    try {
      const payload = buildQuotePayload(modelDetail, dimensions, quantity, partMaterials, partSpecs)
      const result = await confirmProductionOrder(payload, idempotencyKey)

      setConfirmationSuccess(result)
      onRefreshInventory?.()

      const refreshedModel = await getFurnitureModel(selectedSlug)
      setModelDetail(refreshedModel)
      await refreshQuote(payload)
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setConfirmationError(requestError.message || 'Could not place production order.')
      }
    } finally {
      setIsConfirming(false)
    }
  }

  async function handleDeleteSelectedModel() {
    if (!modelDetail?.slug || deletingModel) return

    setDeletingModel(true)
    setDeleteModelError('')
    setModelNotice('')

    try {
      const result = await deleteFurnitureModel(modelDetail.slug)
      const refreshedModels = await listFurnitureModels()
      setModels(refreshedModels)
      setSelectedSlug(refreshedModels[0]?.slug ?? null)
      setModelDetail(null)
      setQuote(null)
      setPartSpecs({})
      setPartMaterials({})
      setDeleteDialogOpen(false)
      setModelNotice(result?.message || (result?.archived ? 'Model archived to preserve order history.' : 'Model deleted.'))
    } catch (requestError) {
      setDeleteModelError(requestError.message || 'Could not delete this model.')
    } finally {
      setDeletingModel(false)
    }
  }


  function handleDimensionsChange(nextDimensions) {
    // Custom models are ordered exactly as saved in the builder. Only preset
    // models expose adjustable overall dimensions.
    if (modelDetail?.is_custom) return
    setDimensions(nextDimensions)
  }

  function handleReset() {
    if (!modelDetail) {
      return
    }

    setDimensions(modelDetail.is_custom
      ? getCustomModelDimensions(modelDetail.parts)
      : buildDefaultDimensions(modelDetail.dimensions))
    setQuantity(1)
    setPartMaterials(buildDefaultSelections(modelDetail.parts))
    setPartSpecs(modelDetail.is_custom ? buildDefaultPartSpecs(modelDetail.parts) : {})
    setQuote(null)
    setQuoteError('')
    setConfirmationSuccess(null)
    setConfirmationError('')
  }

  if (isBuilderOpen) {
    return (
      <ModelBuilder
        onCancel={() => setIsBuilderOpen(false)}
        onSaved={async (model) => {
          setIsBuilderOpen(false)

          try {
            const refreshedModels = await listFurnitureModels()
            setModels(refreshedModels)
            setSelectedSlug(model.slug)
          } catch (requestError) {
            if (requestError.name !== 'AbortError') {
              setError(requestError.message || 'Could not refresh models.')
            }
          }
        }}
      />
    )
  }

  if (loadingList) {
    return <LoadingState label="Loading furniture models…" />
  }

  if (error && !modelDetail) {
    return (
      <Alert tone="error" title="Could not load configurator">
        {error}
      </Alert>
    )
  }

  if (models.length === 0) {
    return (
      <EmptyState
        title="No furniture models"
        description="Create a custom furniture model to start configuring components and generating quotes."
        action={
          <Button variant="primary" onClick={() => setIsBuilderOpen(true)}>
            Create model
          </Button>
        }
      />
    )
  }

  const constraints = modelDetail && !modelDetail.is_custom
    ? {
        length: modelDetail.dimensions.length,
        width: modelDetail.dimensions.width,
        height: modelDetail.dimensions.height,
      }
    : null

  const allPartsHaveMaterial = modelDetail
    ? modelDetail.parts.every((part) => partMaterials[part.code]?.id != null)
    : false
  const allCustomSpecsValid = !modelDetail?.is_custom || modelDetail.parts.every((part) => {
    const spec = { ...part, ...(partSpecs[part.code] ?? {}) }
    const isIntegerInRange = (value, min, max) =>
      value !== '' && value !== null && value !== undefined &&
      Number.isInteger(Number(value)) && Number(value) >= min && Number(value) <= max

    return ['length_mm', 'width_mm', 'height_mm'].every((field) => isIntegerInRange(spec[field], 1, 5000)) &&
      ['pos_x_mm', 'pos_y_mm', 'pos_z_mm'].every((field) => isIntegerInRange(spec[field], -5000, 5000)) &&
      ['rot_x_deg', 'rot_y_deg', 'rot_z_deg'].every((field) => isIntegerInRange(spec[field], -360, 360))
  })

  const quoteIsCurrent = quoteMatchesConfig(quote, quoteConfig)
  const canConfirm =
    !isConfirming &&
    !quoteLoading &&
    allPartsHaveMaterial &&
    allCustomSpecsValid &&
    quote != null &&
    quote.is_sufficient &&
    quoteIsCurrent

  return (
    <div className="configurator-page">
      <header className="configurator-header">
        <div>
          <p className="eyebrow">Furniture configurator</p>
          <h1>Build your piece</h1>
          <p className="muted">Adjust dimensions on preset models, or order a custom model exactly as it was created.</p>
        </div>
        <div className="configurator-header-actions">
          <Button variant="secondary" onClick={() => setIsBuilderOpen(true)}>
            New model
          </Button>
          <Button variant="secondary" onClick={handleReset} disabled={!modelDetail || loadingDetail}>
            Reset to defaults
          </Button>
        </div>
      </header>

      {modelNotice ? (
        <Alert tone="success" onDismiss={() => setModelNotice('')}>
          {modelNotice}
        </Alert>
      ) : null}

      {error ? (
        <Alert tone="warning" onDismiss={() => setError('')}>
          {error}
        </Alert>
      ) : null}

      {confirmationSuccess ? (
        <Alert tone="success" title="Production order confirmed" onDismiss={() => setConfirmationSuccess(null)}>
          Order #{confirmationSuccess.id} for {confirmationSuccess.configuration.quantity} unit
          {confirmationSuccess.configuration.quantity === 1 ? '' : 's'} of{' '}
          {confirmationSuccess.furniture_model.name} was created.
        </Alert>
      ) : null}

      {confirmationError ? (
        <Alert tone="error" title="Could not place order" onDismiss={() => setConfirmationError('')}>
          {confirmationError}
        </Alert>
      ) : null}

      <div className={`configurator-layout-v2${modelDetail?.is_custom ? ' custom-configurator-layout' : ''}`}>
        <main className="configurator-workspace">
          <section className="configurator-model-bar panel" aria-label="Model selection">
            <div className="configurator-model-bar-content">
              <ModelSelector
                models={models}
                selectedSlug={selectedSlug}
                onSelect={setSelectedSlug}
              />
              {modelDetail?.is_custom ? (
                <Button
                  variant="danger"
                  onClick={() => { setDeleteModelError(''); setDeleteDialogOpen(true) }}
                  disabled={deletingModel}
                >
                  Delete model
                </Button>
              ) : null}
            </div>
          </section>

          <section className="configurator-preview panel" aria-label="3D preview">
            {loadingDetail || !modelDetail ? (
              <LoadingState label="Preparing 3D preview…" />
            ) : (
              <Scene
                model={modelDetail}
                dimensions={dimensions}
                partMaterials={partMaterials}
                customParts={modelDetail.is_custom ? modelDetail.parts.map((part) => ({ ...part, ...(partSpecs[part.code] ?? {}) })) : undefined}
                selectedPartCode={null}
              />
            )}
          </section>

          <section className="configurator-dimensions panel" aria-label="Dimension controls">
            {loadingDetail || !modelDetail || (!modelDetail.is_custom && !constraints) ? (
              <LoadingState label="Loading dimensions…" />
            ) : (
              <>
                {modelDetail.is_custom ? (
                  <div className="fixed-custom-dimensions" aria-label="Saved custom model dimensions">
                    <h3>Saved model size</h3>
                    <p className="muted">Custom models are produced at the size you created. Dimensions cannot be changed here.</p>
                    <dl className="fixed-custom-dimensions-grid">
                      <div><dt>Length</dt><dd>{dimensions.length} mm</dd></div>
                      <div><dt>Width</dt><dd>{dimensions.width} mm</dd></div>
                      <div><dt>Height</dt><dd>{dimensions.height} mm</dd></div>
                    </dl>
                  </div>
                ) : (
                  <DimensionControls
                    dimensions={dimensions}
                    constraints={constraints}
                    onChange={handleDimensionsChange}
                  />
                )}
              </>
            )}
          </section>
        </main>

        <aside className="configurator-side-panel panel" aria-label="Configuration summary">
          {loadingDetail || !modelDetail ? (
            <LoadingState label="Loading configuration…" />
          ) : (
            <>
              <MaterialSelectors
                parts={modelDetail.parts}
                selections={partMaterials}
                onChange={setPartMaterials}
              />

              <div className="quantity-field">
                <label htmlFor="quantity">Quantity</label>
                <input
                  id="quantity"
                  type="number"
                  inputMode="numeric"
                  min={1}
                  step={1}
                  value={quantity}
                  onChange={(event) =>
                    setQuantity(Math.max(1, Math.round(Number(event.target.value)) || 1))
                  }
                />
              </div>

              {!allPartsHaveMaterial ? (
                <Alert tone="warning" title="Incomplete configuration">
                  One or more parts do not have a compatible material selected.
                </Alert>
              ) : !allCustomSpecsValid ? (
                <Alert tone="warning" title="Invalid component dimensions">
                  Enter valid measurements for every custom component to refresh the quote.
                </Alert>
              ) : null}

              <QuotePanel quote={quote} loading={quoteLoading} error={quoteError} />

              {allPartsHaveMaterial && quote && !quoteLoading ? (
                <div className="confirm-actions">
                  <Button
                    variant="primary"
                    onClick={handleConfirm}
                    disabled={!canConfirm || isConfirming}
                  >
                    {isConfirming ? 'Placing order…' : 'Place Production Order'}
                  </Button>
                  {!quote.is_sufficient ? (
                    <span className="muted">Insufficient stock to confirm this order.</span>
                  ) : !quoteIsCurrent ? (
                    <span className="muted">Quote is stale. Waiting for an updated quote.</span>
                  ) : null}
                </div>
              ) : null}
            </>
          )}
        </aside>
      </div>

      {deleteDialogOpen && modelDetail?.is_custom ? (
        <Modal
          title="Delete furniture model?"
          description={`Remove “${modelDetail.name}” from the active model catalogue.`}
          onClose={() => { if (!deletingModel) setDeleteDialogOpen(false) }}
        >
          <div className="delete-confirm-content">
            <p>If this model is referenced by production orders, it will be archived instead so historical records remain intact.</p>
            {deleteModelError ? <Alert tone="error" title="Could not delete model">{deleteModelError}</Alert> : null}
            <div className="modal-actions">
              <Button variant="secondary" onClick={() => setDeleteDialogOpen(false)} disabled={deletingModel}>Cancel</Button>
              <Button variant="danger" onClick={handleDeleteSelectedModel} disabled={deletingModel}>
                {deletingModel ? 'Deleting…' : 'Confirm delete'}
              </Button>
            </div>
          </div>
        </Modal>
      ) : null}
    </div>
  )
}
