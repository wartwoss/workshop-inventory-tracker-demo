import { Button } from '../../components/Button'
import { EmptyState } from '../../components/EmptyState'
import { LoadingState } from '../../components/LoadingState'
import { StatusBadge } from '../../components/StatusBadge'
import { materialStatus, formatQuantity } from '../../lib/format'
import { MATERIAL_PURPOSE_LABELS } from '../../lib/constants'

export function MaterialTable({
  materials,
  loading,
  hasFilters,
  onClearFilters,
  onAddMaterial,
  onAdjust,
  onHistory,
  onEdit,
  onDelete,
}) {
  if (loading && materials.length === 0) {
    return <LoadingState label="Loading materials…" />
  }

  if (!loading && materials.length === 0) {
    return hasFilters ? (
      <EmptyState
        title="No materials match these filters"
        description="Try a different search term or clear the filters to see the full catalogue."
        action={
          <Button variant="secondary" onClick={onClearFilters}>
            Clear filters
          </Button>
        }
      />
    ) : (
      <EmptyState
        title="No materials yet"
        description="Add your first material to start tracking stock levels and adjustments."
        action={
          <Button variant="primary" onClick={onAddMaterial}>
            Add material
          </Button>
        }
      />
    )
  }

  return (
    <div className={`table-wrap${loading ? ' is-refreshing' : ''}`} aria-busy={loading}>
      <table className="data-table">
        <thead>
          <tr>
            <th scope="col">Material</th>
            <th scope="col">Group</th>
            <th scope="col">Purpose</th>
            <th scope="col">Unit</th>
            <th scope="col" className="align-right">
              On hand
            </th>
            <th scope="col" className="align-right">
              Reorder level
            </th>
            <th scope="col">Status</th>
            <th scope="col" className="align-right material-actions-header">
              <span className="visually-hidden">Actions</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {materials.map((material) => {
            const status = materialStatus(material)

            return (
              <tr key={material.id}>
                <td data-label="Material">
                  <div className="material-name">
                    <span>{material.name}</span>
                    {material.is_active ? null : (
                      <StatusBadge tone="neutral">Inactive</StatusBadge>
                    )}
                  </div>
                  <span className="material-sku">{material.sku || 'No SKU'}</span>
                </td>
                <td data-label="Group">
                  <span className="group-tag">{material.material_group}</span>
                </td>
                <td data-label="Purpose">
                  <span className="purpose-tag">
                    {MATERIAL_PURPOSE_LABELS[material.purpose] ?? material.purpose ?? '—'}
                  </span>
                </td>
                <td data-label="Unit">{material.stock_unit}</td>
                <td data-label="On hand" className="align-right">
                  <strong>{formatQuantity(material.quantity_on_hand, { unit: material.stock_unit, includeUnit: false })}</strong>
                </td>
                <td data-label="Reorder level" className="align-right muted">
                  {material.reorder_level === null
                    ? '—'
                    : formatQuantity(material.reorder_level, { unit: material.stock_unit, includeUnit: false })}
                </td>
                <td data-label="Status">
                  <StatusBadge tone={status.tone}>{status.label}</StatusBadge>
                </td>
                <td data-label="Actions" className="align-right material-actions-cell">
                  <div className="row-actions">
                    <Button size="sm" onClick={() => onAdjust(material)}>
                      Adjust
                    </Button>
                    <Button size="sm" onClick={() => onHistory(material)}>
                      History
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => onEdit(material)}>
                      Edit
                    </Button>
                    <Button size="sm" variant="danger" onClick={() => onDelete(material)}>
                      Delete
                    </Button>
                  </div>
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
