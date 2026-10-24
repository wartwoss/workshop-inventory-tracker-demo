import { useEffect, useState } from 'react'
import { getPartSliderRange } from './partGeometry'

function NumberField({ label, value, onChange, min = 1, max = 5000, slider = false }) {
  const numericValue = Number(value)
  const controlledValue = Number.isFinite(numericValue) ? numericValue : min
  const sliderMax = Math.max(min, Number(max) || 5000)

  return (
    <label className={`custom-part-number-field${slider ? ' has-slider' : ''}`}>
      <span className="custom-part-dimension-label">
        <span>{label}</span>
        {slider ? <output>{Math.max(min, Math.min(sliderMax, controlledValue)).toLocaleString()} mm</output> : null}
      </span>
      {slider ? (
        <input
          className="custom-part-range"
          type="range"
          min={min}
          max={sliderMax}
          step={1}
          value={Math.max(min, Math.min(sliderMax, controlledValue))}
          onChange={(event) => onChange(Number(event.target.value))}
          aria-label={`${label} slider`}
        />
      ) : null}
      <input
        type="number"
        min={min}
        max={sliderMax}
        step={1}
        value={value ?? 0}
        onChange={(event) => {
          const raw = event.target.value
          onChange(raw === '' ? '' : Number(raw))
        }}
      />
    </label>
  )
}

function labelForShape(part) {
  const shape = part.shape || 'rectangular'
  const labels = {
    rectangular: 'Rectangular board',
    circular: 'Circular board',
    cube_foot: 'Cubic foot',
    angular_foot: 'Angular foot',
    round_foot: 'Round foot',
    rectangular_leg: 'Rectangular leg',
    cylinder_leg: 'Cylinder leg',
    knob: 'Circular knob',
    bulb_knob: 'Bulky round knob',
    bar: 'Bar handle',
  }
  return labels[shape] || shape.replaceAll('_', ' ')
}

export function CustomPartControls({ parts, specs, selections, selectedCode, onSelect, onChange }) {
  const [expandedCode, setExpandedCode] = useState(selectedCode ?? null)

  useEffect(() => {
    if (selectedCode && parts.some((part) => part.code === selectedCode)) {
      setExpandedCode(selectedCode)
    } else if (!selectedCode) {
      setExpandedCode(null)
    } else if (expandedCode && !parts.some((part) => part.code === expandedCode)) {
      setExpandedCode(null)
    }
  }, [parts, expandedCode, selectedCode])

  if (!parts?.length) return null

  function update(code, field, value) {
    const changes = field && typeof field === 'object' ? field : { [field]: value }
    onChange(code, changes)
  }

  return (
    <section className="custom-part-controls" aria-labelledby="custom-part-controls-title">
      <div className="custom-part-controls-heading">
        <div>
          <h3 id="custom-part-controls-title">Component specifications</h3>
          <p className="muted">Select a part in the 3D preview or open it here. Changes apply to this configuration only.</p>
        </div>
        <span className="component-count">{parts.length} parts</span>
      </div>

      <div className="custom-part-accordion">
        {parts.map((part, index) => {
          const current = { ...part, ...(specs[part.code] ?? {}) }
          const expanded = expandedCode === part.code
          const shape = current.shape || 'rectangular'
          const circularBoard = current.component_type === 'board' && shape === 'circular'
          const cylinder = shape === 'cylinder_leg'
          const roundOnly = ['round_foot', 'knob', 'bulb_knob'].includes(shape)
          const angular = shape === 'angular_foot'
          const material = selections[part.code]
          const lengthRange = getPartSliderRange(parts.map((item) => ({ ...item, ...(specs[item.code] ?? {}) })), current, 'length_mm')
          const widthRange = getPartSliderRange(parts.map((item) => ({ ...item, ...(specs[item.code] ?? {}) })), current, 'width_mm')
          const heightRange = getPartSliderRange(parts.map((item) => ({ ...item, ...(specs[item.code] ?? {}) })), current, 'height_mm')

          return (
            <article className={`custom-part-card${expanded ? ' is-expanded' : ''}${selectedCode === part.code ? ' is-selected' : ''}`} key={part.code}>
              <div className="custom-part-card-header">
                <button
                  className="custom-part-toggle"
                  type="button"
                  aria-expanded={expanded}
                  onClick={() => {
                    const nextCode = expanded ? null : part.code
                    setExpandedCode(nextCode)
                    onSelect?.(nextCode)
                  }}
                >
                  <span className="custom-part-number">{index + 1}</span>
                  <span className="custom-part-summary">
                    <strong>{part.label || part.name || `Component ${index + 1}`}</strong>
                    <small>{labelForShape(current)}{material?.name ? ` · ${material.name}` : ''}</small>
                  </span>
                  <span className="custom-part-chevron" aria-hidden="true">{expanded ? '−' : '+'}</span>
                </button>
              </div>

              {expanded ? (
                <div className="custom-part-card-body">
                  <div className="custom-part-spec-grid">
                    {circularBoard ? (
                      <NumberField label="Diameter (mm)" value={current.length_mm} max={lengthRange.max} slider onChange={(value) => update(part.code, { length_mm: value, width_mm: value })} />
                    ) : (
                      <NumberField label={cylinder ? 'Diameter (mm)' : roundOnly || angular ? 'Diameter / width (mm)' : 'Length (mm)'} value={current.length_mm} max={lengthRange.max} slider onChange={(value) => {
                          if (circularBoard || cylinder || roundOnly || angular) {
                            update(part.code, 'length_mm', value)
                            if (circularBoard || roundOnly || angular) update(part.code, 'width_mm', value)
                          } else update(part.code, 'length_mm', value)
                        }} />
                    )}

                    {!circularBoard && !cylinder && !roundOnly && !angular ? (
                      <NumberField label="Width (mm)" value={current.width_mm} max={widthRange.max} slider onChange={(value) => update(part.code, 'width_mm', value)} />
                    ) : null}

                    {!roundOnly ? (
                      <NumberField
                        label={current.component_type === 'board' ? 'Thickness (mm)' : 'Height (mm)'}
                        value={current.height_mm}
                        max={heightRange.max}
                        slider
                        onChange={(value) => update(part.code, 'height_mm', value)}
                      />
                    ) : null}
                  </div>

                  <h4>Position (mm)</h4>
                  <div className="custom-part-spec-grid custom-part-spec-grid-3">
                    <NumberField label="X" min={-5000} max={5000} value={current.pos_x_mm} onChange={(value) => update(part.code, 'pos_x_mm', value)} />
                    <NumberField label="Y" min={-5000} max={5000} value={current.pos_y_mm} onChange={(value) => update(part.code, 'pos_y_mm', value)} />
                    <NumberField label="Z" min={-5000} max={5000} value={current.pos_z_mm} onChange={(value) => update(part.code, 'pos_z_mm', value)} />
                  </div>

                  <h4>Rotation (°)</h4>
                  <div className="custom-part-spec-grid custom-part-spec-grid-3">
                    <NumberField label="X" min={-360} max={360} value={current.rot_x_deg} onChange={(value) => update(part.code, 'rot_x_deg', value)} />
                    <NumberField label="Y" min={-360} max={360} value={current.rot_y_deg} onChange={(value) => update(part.code, 'rot_y_deg', value)} />
                    <NumberField label="Z" min={-360} max={360} value={current.rot_z_deg} onChange={(value) => update(part.code, 'rot_z_deg', value)} />
                  </div>
                </div>
              ) : null}
            </article>
          )
        })}
      </div>
    </section>
  )
}

