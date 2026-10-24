import { Alert } from '../../components/Alert'
import { LoadingState } from '../../components/LoadingState'
import { formatQuantity } from '../../lib/format'

export function QuotePanel({ quote, loading, error }) {
  if (error) {
    return (
      <Alert tone="warning" title="Quote unavailable">
        {error}
      </Alert>
    )
  }

  if (loading) {
    return <LoadingState label="Calculating quote…" />
  }

  if (!quote) {
    return null
  }

  return (
    <div className="quote-panel">
      <div
        className={`quote-status quote-status-${quote.is_sufficient ? 'ok' : 'short'}`}
      >
        <strong>{quote.is_sufficient ? 'Sufficient stock' : 'Insufficient stock'}</strong>
        <span className="muted">
          {quote.is_sufficient
            ? 'This configuration can be produced with current inventory.'
            : 'One or more materials do not have enough stock for this configuration.'}
        </span>
      </div>

      <section className="quote-section">
        <h3>Material requirements</h3>
        <ul className="quote-materials">
          {quote.materials.map((material) => (
            <li
              key={material.material_id}
              className={material.is_sufficient ? 'is-sufficient' : 'is-insufficient'}
            >
              <div className="quote-material-main">
                <span className="quote-material-name">{material.material_name}</span>
                <span className="quote-material-required">
                  {formatQuantity(material.required_quantity, { unit: material.unit })}
                </span>
              </div>
              <div className="quote-material-meta muted">
                <span>
                  Available: {formatQuantity(material.available_quantity, { unit: material.unit })}
                </span>
                {!material.is_sufficient ? (
                  <span className="shortfall">
                    Short:{' '}
                    {formatQuantity(
                      Math.max(
                        0,
                        Number(material.required_quantity) - Number(material.available_quantity),
                      ),
                      { unit: material.unit },
                    )}
                  </span>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      </section>

      <section className="quote-section">
        <h3>Component breakdown</h3>
        <table className="quote-components">
          <thead>
            <tr>
              <th>Part</th>
              <th>Material</th>
              <th>Per unit</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            {quote.components.map((component) => (
              <tr key={component.part_code}>
                <td>{component.part_name}</td>
                <td>{component.material_name}</td>
                <td>{formatQuantity(component.per_unit_quantity, { unit: component.unit })}</td>
                <td>{formatQuantity(component.total_quantity, { unit: component.unit })}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>
    </div>
  )
}
