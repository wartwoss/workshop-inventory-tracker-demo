import { SummaryCard } from '../../components/SummaryCard'

export function SummaryCards({ summary, groups, loading, lowStockActive, onShowLowStock }) {
  if (loading && !summary) {
    return (
      <section className="summary-grid" aria-label="Inventory summary">
        {[0, 1, 2, 3].map((item) => (
          <div className="summary-card skeleton" key={item}>
            <span className="skeleton-line skeleton-line-sm" />
            <span className="skeleton-line skeleton-line-lg" />
          </div>
        ))}
      </section>
    )
  }

  const materials = summary?.materials ?? {}

  return (
    <section className="summary-grid" aria-label="Inventory summary">
      <SummaryCard
        label="Materials tracked"
        value={materials.total ?? '—'}
        detail={
          groups?.length
            ? `${groups.length} material ${groups.length === 1 ? 'group' : 'groups'}`
            : 'No groups yet'
        }
      />
      <SummaryCard
        label="Active materials"
        value={materials.active ?? '—'}
        detail={`${materials.inactive ?? 0} inactive`}
      />
      <SummaryCard
        label="Low stock"
        value={materials.low_stock ?? '—'}
        detail="At or below reorder level"
        tone="warning"
        onClick={onShowLowStock}
        active={lowStockActive}
      />
      <SummaryCard
        label="Out of stock"
        value={materials.out_of_stock ?? '—'}
        detail="Requires replenishment"
        tone="danger"
      />
    </section>
  )
}
