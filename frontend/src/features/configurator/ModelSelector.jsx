export function ModelSelector({ models, selectedSlug, onSelect }) {
  return (
    <div className="model-selector">
      {models.map((model) => (
        <button
          key={model.slug}
          type="button"
          className={model.slug === selectedSlug ? 'is-active' : undefined}
          onClick={() => onSelect(model.slug)}
        >
          <span className="model-name">{model.name}</span>
          <span className="model-dims muted">
            {model.is_custom
              ? 'Custom model · fixed size'
              : `${model.default_length_mm} × ${model.default_width_mm} × ${model.default_height_mm} mm`}
          </span>
        </button>
      ))}
    </div>
  )
}
