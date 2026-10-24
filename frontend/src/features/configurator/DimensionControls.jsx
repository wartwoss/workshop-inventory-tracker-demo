function clamp(value, min, max) {
  const number = Math.round(Number(value))

  if (Number.isNaN(number)) {
    return min
  }

  return Math.max(min, Math.min(max, number))
}

export function DimensionControls({ dimensions, constraints, onChange }) {
  function update(field, value) {
    const constraint = constraints[field]
    const next = clamp(value, constraint.min, constraint.max)

    onChange({ ...dimensions, [field]: next })
  }

  const fields = [
    { key: 'length', label: 'Length' },
    { key: 'width', label: 'Width' },
    { key: 'height', label: 'Height' },
  ]

  return (
    <fieldset className="dimension-controls">
      <legend>Dimensions (mm)</legend>
      <div className="dimension-grid">
        {fields.map((field) => {
          const constraint = constraints[field.key]

          return (
            <div className="dimension-field" key={field.key}>
              <div className="dimension-label-row">
                <label htmlFor={`dim-${field.key}`}>{field.label}</label>
                <span className="dim-range muted">
                  {constraint.min}–{constraint.max} mm
                </span>
              </div>
              <input
                id={`dim-${field.key}-slider`}
                type="range"
                min={constraint.min}
                max={constraint.max}
                step={1}
                value={dimensions[field.key]}
                onChange={(event) => update(field.key, event.target.value)}
                aria-label={`${field.label} slider`}
              />
              <input
                id={`dim-${field.key}`}
                type="number"
                inputMode="numeric"
                min={constraint.min}
                max={constraint.max}
                step={1}
                value={dimensions[field.key]}
                onChange={(event) => update(field.key, event.target.value)}
                aria-label={`${field.label} value`}
              />
            </div>
          )
        })}
      </div>
    </fieldset>
  )
}
