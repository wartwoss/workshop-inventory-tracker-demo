import { useCallback, useEffect, useMemo, useState } from 'react'
import { listMaterials } from '../../api/materials'
import { createFurnitureModel } from '../../api/furniture'
import { Alert } from '../../components/Alert'
import { Button } from '../../components/Button'
import { LoadingState } from '../../components/LoadingState'
import { BuilderPreview } from './BuilderPreview'
import { ComponentEditor } from './ComponentEditor'

function toSlug(name) {
  return name
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

function emptyComponent(id) {
  return {
    id,
    type: 'board',
    label: '',
    material_id: '',
    length_mm: 1200,
    width_mm: 600,
    height_mm: 20,
    pos_x_mm: 0,
    pos_y_mm: 0,
    pos_z_mm: 0,
    rot_x_deg: 0,
    rot_y_deg: 0,
    rot_z_deg: 0,
    shape: 'rectangular',
  }
}

export function ModelBuilder({ onCancel, onSaved }) {
  const [name, setName] = useState('')

  const [components, setComponents] = useState(() => [emptyComponent(1)])
  const [expandedComponentId, setExpandedComponentId] = useState(1)
  const [nextId, setNextId] = useState(2)

  const [materials, setMaterials] = useState([])
  const [loadingMaterials, setLoadingMaterials] = useState(true)
  const [materialsError, setMaterialsError] = useState('')

  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState('')

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    listMaterials({ per_page: 100 }, controller.signal)
      .then((response) => {
        if (!active) {
          return
        }

        setMaterials(response.data ?? [])
      })
      .catch((error) => {
        if (!active || error.name === 'AbortError') {
          return
        }

        setMaterialsError(error.message || 'Could not load materials.')
      })
      .finally(() => {
        if (active) {
          setLoadingMaterials(false)
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [])

  const slugPreview = useMemo(() => toSlug(name), [name])

  const updateComponent = useCallback((id, updates) => {
    setComponents((prev) =>
      prev.map((component) => (component.id === id ? { ...component, ...updates } : component)),
    )
  }, [])

  const addComponent = useCallback(() => {
    setComponents((prev) => [...prev, emptyComponent(nextId)])
    setExpandedComponentId(nextId)
    setNextId((value) => value + 1)
  }, [nextId])

  const removeComponent = useCallback((id) => {
    const remaining = components.filter((component) => component.id !== id)
    setComponents(remaining)
    setExpandedComponentId((current) => current === id ? (remaining[0]?.id ?? null) : current)
  }, [components])

  const duplicateComponent = useCallback(
    (id) => {
      const source = components.find((component) => component.id === id)

      if (!source) {
        return
      }

      setComponents((prev) => [
        ...prev,
        {
          ...source,
          id: nextId,
          label: `${source.label.trim() || 'Component'} copy`,
          pos_x_mm: source.pos_x_mm + 50,
        },
      ])
      setExpandedComponentId(nextId)
      setNextId((value) => value + 1)
    },
    [components, nextId],
  )

  const toggleComponent = useCallback((id) => {
    setExpandedComponentId((current) => current === id ? null : id)
  }, [])

  const isValid = useMemo(() => {
    if (!name.trim()) {
      return false
    }

    return components.every((component) => {
      if (!component.label.trim()) {
        return false
      }

      if (!component.material_id) {
        return false
      }

      return (
        component.length_mm > 0 &&
        component.width_mm > 0 &&
        component.height_mm > 0
      )
    })
  }, [name, components])

  async function handleSave() {
    if (!isValid) {
      return
    }

    setSaving(true)
    setSaveError('')

    try {
      const payload = {
        name: name.trim(),
        components: components.map((component) => ({
          type: component.type,
          label: component.label.trim(),
          material_id: Number(component.material_id),
          length_mm: Number(component.length_mm),
          width_mm: Number(component.width_mm),
          height_mm: Number(component.height_mm),
          pos_x_mm: Number(component.pos_x_mm ?? 0),
          pos_y_mm: Number(component.pos_y_mm ?? 0),
          pos_z_mm: Number(component.pos_z_mm ?? 0),
          rot_x_deg: Number(component.rot_x_deg ?? 0),
          rot_y_deg: Number(component.rot_y_deg ?? 0),
          rot_z_deg: Number(component.rot_z_deg ?? 0),
          shape: component.shape || null,
        })),
      }

      const model = await createFurnitureModel(payload)
      onSaved?.(model)
    } catch (error) {
      setSaveError(error.message || 'Could not save the model.')
    } finally {
      setSaving(false)
    }
  }

  if (loadingMaterials) {
    return <LoadingState label="Loading materials…" />
  }

  return (
    <div className="configurator-page">
      <header className="configurator-header">
        <div>
          <p className="eyebrow">Model builder</p>
          <h1>Create a custom model</h1>
          <p className="muted">Define dimensions, add boards, legs and handles, then save.</p>
        </div>
        <div className="builder-actions">
          <Button variant="secondary" onClick={onCancel} disabled={saving}>
            Cancel
          </Button>
          <Button variant="primary" onClick={handleSave} disabled={!isValid || saving}>
            {saving ? 'Saving…' : 'Save model'}
          </Button>
        </div>
      </header>

      {materialsError ? (
        <Alert tone="error" title="Could not load materials" onDismiss={() => setMaterialsError('')}>
          {materialsError}
        </Alert>
      ) : null}

      {saveError ? (
        <Alert tone="error" title="Could not save model" onDismiss={() => setSaveError('')}>
          {saveError}
        </Alert>
      ) : null}

      <div className="configurator-layout-v2 model-builder-layout">
        <main className="configurator-workspace">
          <section className="configurator-preview panel" aria-label="3D preview">
            <BuilderPreview
              components={components}
              materials={materials}
              onComponentUpdate={updateComponent}
              onSelectionChange={setExpandedComponentId}
            />
          </section>
        </main>

        <aside className="configurator-side-panel panel" aria-label="Model definition">
          <div className="builder-field">
            <label htmlFor="model-name">Model name</label>
            <input
              id="model-name"
              type="text"
              value={name}
              onChange={(event) => setName(event.target.value)}
              placeholder="e.g. Console table"
            />
            {slugPreview ? (
              <span className="muted">Slug preview: {slugPreview}</span>
            ) : null}
          </div>

          <div className="builder-components">
            <div className="builder-components-header">
              <h2>Components</h2>
              <Button variant="secondary" onClick={addComponent}>
                Add component
              </Button>
            </div>

            {components.length === 0 ? (
              <p className="muted">Add at least one component to preview and save the model.</p>
            ) : (
              components.map((component, index) => (
                <ComponentEditor
                  key={component.id}
                  index={index}
                  component={component}
                  materials={materials}
                  onChange={updateComponent}
                  onRemove={removeComponent}
                  onDuplicate={duplicateComponent}
                  canRemove={components.length > 1}
                  expanded={expandedComponentId === component.id}
                  onToggle={toggleComponent}
                />
              ))
            )}
          </div>
        </aside>
      </div>
    </div>
  )
}
