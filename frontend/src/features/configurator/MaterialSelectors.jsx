export function MaterialSelectors({ parts, selections, onChange }) {
  return (
    <div className="material-selectors">
      {parts.map((part) => {
        const selectedMaterial = selections[part.code]
        const selectedId = selectedMaterial?.id ?? ''
        const hasMaterials = part.allowed_materials.length > 0

        return (
          <div className="material-field" key={part.code}>
            <label htmlFor={`material-${part.code}`}>
              {part.name}
              <span className="unit-hint muted">({part.consumption_unit})</span>
            </label>

            {hasMaterials ? (
              <select
                id={`material-${part.code}`}
                value={selectedId}
                onChange={(event) =>
                  onChange({
                    ...selections,
                    [part.code]: part.allowed_materials.find(
                      (material) => String(material.id) === event.target.value,
                    ),
                  })
                }
              >
                {part.allowed_materials.map((material) => (
                  <option key={material.id} value={material.id}>
                    {material.name}
                  </option>
                ))}
              </select>
            ) : (
              <div className="material-empty">No compatible materials in stock</div>
            )}

            {!selectedMaterial && hasMaterials ? (
              <span className="field-error">Please select a material</span>
            ) : null}
          </div>
        )
      })}
    </div>
  )
}
