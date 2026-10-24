import { StatusBadge } from '../../components/StatusBadge'
import { MOVEMENT_TYPE_LABELS } from '../../lib/constants'
import { formatDate, formatQuantity } from '../../lib/format'

export function RecentActivity({ movements = [], stockByUnit = [] }) {
  return (
    <aside className="activity-panel">
      <section className="panel">
        <header className="panel-header">
          <h2>Stock by unit</h2>
        </header>
        {stockByUnit.length === 0 ? (
          <p className="muted">No stock recorded yet.</p>
        ) : (
          <ul className="unit-list">
            {stockByUnit.map((entry) => (
              <li key={entry.stock_unit}>
                <span className="unit-chip">{entry.stock_unit}</span>
                <span className="unit-quantity">{formatQuantity(entry.quantity, { unit: entry.stock_unit, includeUnit: false })}</span>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="panel">
        <header className="panel-header">
          <h2>Recent activity</h2>
        </header>
        {movements.length === 0 ? (
          <p className="muted">No stock movements yet.</p>
        ) : (
          <ol className="activity-list">
            {movements.slice(0, 6).map((movement) => {
              const delta = Number(movement.quantity_delta)

              return (
                <li key={movement.id}>
                  <div className="activity-main">
                    <span className="activity-material">
                      {movement.material_name || `Material #${movement.material_id}`}
                    </span>
                    <span
                      className={`activity-delta${delta < 0 ? ' is-negative' : ' is-positive'}`}
                    >
                      {formatQuantity(movement.quantity_delta, {
                        signed: true,
                        unit: movement.stock_unit,
                      })}
                    </span>
                  </div>
                  <div className="activity-meta">
                    <StatusBadge tone={movement.movement_type === 'production' ? 'neutral' : 'info'}>
                      {MOVEMENT_TYPE_LABELS[movement.movement_type] || movement.movement_type}
                    </StatusBadge>
                    <time dateTime={movement.created_at}>{formatDate(movement.created_at)}</time>
                  </div>
                  {movement.reason ? (
                    <p className="activity-reason">{movement.reason}</p>
                  ) : null}
                </li>
              )
            })}
          </ol>
        )}
      </section>
    </aside>
  )
}
