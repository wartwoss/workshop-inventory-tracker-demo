const TYPE_OPTIONS = [
  { value: 'board', label: 'Board' },
  { value: 'leg', label: 'Leg / foot' },
  { value: 'handle', label: 'Handle' },
]

const SHAPE_OPTIONS = {
  board: [
    { value: 'rectangular', label: 'Rectangular board' },
    { value: 'circular', label: 'Circular board' },
  ],
  leg: [
    { value: 'cube_foot', label: 'Cubic foot' },
    { value: 'angular_foot', label: 'Angular foot' },
    { value: 'round_foot', label: 'Round foot' },
    { value: 'rectangular_leg', label: 'Rectangular leg' },
    { value: 'cylinder_leg', label: 'Cylinder leg' },
  ],
  handle: [
    { value: 'knob', label: 'Circular knob' },
    { value: 'bulb_knob', label: 'Bulky round knob' },
    { value: 'bar', label: 'Bar handle' },
  ],
}

const DEFAULTS_BY_TYPE = {
  board: { shape: 'rectangular', length_mm: 1200, width_mm: 600, height_mm: 20 },
  leg: { shape: 'rectangular_leg', length_mm: 60, width_mm: 60, height_mm: 700 },
  handle: { shape: 'bar', length_mm: 150, width_mm: 30, height_mm: 30 },
}

function NumberInput({ label, value, onChange, min = 0, step = 1 }) {
  return (
    <label className="builder-number-field">
      <span>{label}</span>
      <input
        type="number"
        inputMode="numeric"
        min={min}
        step={step}
        value={value}
        onChange={(event) => {
          const raw = event.target.value
          onChange(raw === '' ? '' : Number(raw))
        }}
      />
    </label>
  )
}

export function ComponentEditor({
  index,
  component,
  materials,
  onChange,
  onRemove,
  onDuplicate,
  canRemove,
  expanded,
  onToggle,
}) {
  const requiredUnit = component.type === 'board' ? 'm2' : 'ea'
  const compatibleMaterials = materials.filter((material) =>
    material.is_active !== false &&
    material.purpose === component.type &&
    material.stock_unit === requiredUnit
  )
  const selectedMaterial = compatibleMaterials.find((material) => String(material.id) === String(component.material_id))
  const isCircular = component.shape === 'circular'
  const isCylinderLeg = component.shape === 'cylinder_leg'

  function handleChange(updates) {
    onChange(component.id, updates)
  }

  function handleTypeChange(type) {
    handleChange({ type, material_id: '', ...DEFAULTS_BY_TYPE[type] })
  }

  function handleShapeChange(shape) {
    const shapeDefaults = {
      rectangular: { length_mm: 1200, width_mm: 600, height_mm: 20 },
      circular: { length_mm: 600, width_mm: 600, height_mm: 20 },
      cube_foot: { length_mm: 60, width_mm: 60, height_mm: 60 },
      angular_foot: { length_mm: 60, width_mm: 60, height_mm: 80 },
      round_foot: { length_mm: 60, width_mm: 60, height_mm: 60 },
      rectangular_leg: { length_mm: 60, width_mm: 60, height_mm: 700 },
      cylinder_leg: { length_mm: 60, width_mm: 60, height_mm: 700 },
      knob: { length_mm: 35, width_mm: 35, height_mm: 35 },
      bulb_knob: { length_mm: 55, width_mm: 55, height_mm: 55 },
      bar: { length_mm: 150, width_mm: 30, height_mm: 30 },
    }

    // Selecting a new shape should create a usable starting size, rather than
    // carrying a leg's 700 mm height into a spherical foot or a bar into a knob.
    handleChange({ shape, ...(shapeDefaults[shape] ?? {}) })
  }

  function handleDiameterChange(value) {
    const diameter = value === '' ? '' : Number(value)
    handleChange({ length_mm: diameter, width_mm: diameter })
  }

  return (
    <div className={`builder-component${expanded ? ' is-expanded' : ''}`}>
      <div className="builder-component-header">
        <button
          type="button"
          className="builder-component-toggle"
          aria-expanded={expanded}
          onClick={() => onToggle(component.id)}
        >
          <span className="builder-component-toggle-main">
            <strong>Component {index + 1}{component.label.trim() ? ` · ${component.label.trim()}` : ''}</strong>
            <small>{component.type} · {component.shape || 'default shape'}{selectedMaterial ? ` · ${selectedMaterial.name}` : ''}</small>
          </span>
          <span className="builder-component-chevron" aria-hidden="true">{expanded ? '−' : '+'}</span>
        </button>
        <div className="builder-component-actions">
          <button
            type="button"
            className="builder-duplicate"
            onClick={() => onDuplicate(component.id)}
            aria-label={`Duplicate component ${index + 1}`}
          >
            Duplicate
          </button>
          {canRemove ? (
            <button
              type="button"
              className="builder-remove"
              onClick={() => onRemove(component.id)}
              aria-label={`Remove component ${index + 1}`}
            >
              Remove
            </button>
          ) : null}
        </div>
      </div>

      {expanded ? <div className="builder-component-body">
      <div className="builder-section">
        <h4 className="builder-section-title">Identity</h4>
        <div className="builder-row builder-row-2">
          <label className="builder-select-field">
            <span>Type</span>
            <select value={component.type} onChange={(event) => handleTypeChange(event.target.value)}>
              {TYPE_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          <label className="builder-text-field">
            <span>Label</span>
            <input
              type="text"
              value={component.label}
              onChange={(event) => handleChange({ label: event.target.value })}
              placeholder="e.g. Table top"
            />
          </label>
        </div>
      </div>

      <div className="builder-section">
        <h4 className="builder-section-title">Shape & Material</h4>
        <div className="builder-row builder-row-2">
          <label className="builder-select-field">
            <span>Shape</span>
            <select value={component.shape} onChange={(event) => handleShapeChange(event.target.value)}>
              {SHAPE_OPTIONS[component.type]?.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          <label className="builder-select-field">
            <span>Material</span>
            <select
              value={component.material_id}
              onChange={(event) => handleChange({ material_id: event.target.value })}
            >
              <option value="">Choose a material</option>
              {compatibleMaterials.map((material) => (
                <option key={material.id} value={material.id}>
                  {material.name} ({material.stock_unit})
                </option>
              ))}
            </select>
          </label>
        </div>
      </div>

      <div className="builder-section">
        <h4 className="builder-section-title">Dimensions</h4>
        <div className="builder-row builder-row-3">
          {isCircular ? (
            <NumberInput
              label="Diameter (mm)"
              value={component.length_mm}
              onChange={handleDiameterChange}
            />
          ) : (
            <>
              <NumberInput
                label={isCylinderLeg ? 'Diameter (mm)' : 'Length (mm)'}
                value={component.length_mm}
                onChange={(value) => handleChange({ length_mm: value })}
              />
              {!isCylinderLeg ? (
                <NumberInput
                  label="Width (mm)"
                  value={component.width_mm}
                  onChange={(value) => handleChange({ width_mm: value })}
                />
              ) : null}
            </>
          )}
          <NumberInput
            label={component.type === 'board' ? 'Thickness (mm)' : 'Height (mm)'}
            value={component.height_mm}
            onChange={(value) => handleChange({ height_mm: value })}
          />
        </div>
      </div>

      <div className="builder-section">
        <h4 className="builder-section-title">Position & Orientation</h4>
        <div className="builder-row builder-row-3">
          <NumberInput
            label="Pos X (mm)"
            value={component.pos_x_mm}
            onChange={(value) => handleChange({ pos_x_mm: value })}
            min={-5000}
          />
          <NumberInput
            label="Pos Y (mm)"
            value={component.pos_y_mm}
            onChange={(value) => handleChange({ pos_y_mm: value })}
            min={-5000}
          />
          <NumberInput
            label="Pos Z (mm)"
            value={component.pos_z_mm}
            onChange={(value) => handleChange({ pos_z_mm: value })}
            min={-5000}
          />
        </div>

        <div className="builder-row builder-row-3">
          <NumberInput
            label="Rot X (°)"
            value={component.rot_x_deg}
            onChange={(value) => handleChange({ rot_x_deg: value })}
            min={-360}
            step={5}
          />
          <NumberInput
            label="Rot Y (°)"
            value={component.rot_y_deg}
            onChange={(value) => handleChange({ rot_y_deg: value })}
            min={-360}
            step={5}
          />
          <NumberInput
            label="Rot Z (°)"
            value={component.rot_z_deg}
            onChange={(value) => handleChange({ rot_z_deg: value })}
            min={-360}
            step={5}
          />
        </div>
      </div>
      </div> : null}
    </div>
  )
}
