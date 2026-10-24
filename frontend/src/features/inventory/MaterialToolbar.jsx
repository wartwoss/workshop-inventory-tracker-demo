import { PER_PAGE_OPTIONS } from '../../lib/constants'

export function MaterialToolbar({
  search,
  onSearchChange,
  group,
  onGroupChange,
  groups,
  lowStockOnly,
  onLowStockChange,
  showArchived,
  onShowArchivedChange,
  perPage,
  onPerPageChange,
  onRefresh,
  refreshing,
}) {
  return (
    <div className="toolbar">
      <div className="toolbar-search">
        <span className="search-icon" aria-hidden="true">
          ⌕
        </span>
        <input
          type="search"
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
          placeholder="Search by material name or SKU"
          aria-label="Search materials"
        />
      </div>

      <div className="toolbar-controls">
        <label className="select-label">
          <span>Group</span>
          <select value={group} onChange={(event) => onGroupChange(event.target.value)}>
            <option value="">All groups</option>
            {groups.map((entry) => (
              <option key={entry.material_group} value={entry.material_group}>
                {entry.material_group} ({entry.materials_count})
              </option>
            ))}
          </select>
        </label>

        <label className="checkbox-label">
          <input
            type="checkbox"
            checked={lowStockOnly}
            onChange={(event) => onLowStockChange(event.target.checked)}
          />
          <span>Low stock only</span>
        </label>

        <label className="checkbox-label" title="Archived materials are hidden by default to keep the active inventory tidy.">
          <input
            type="checkbox"
            checked={showArchived}
            onChange={(event) => onShowArchivedChange(event.target.checked)}
          />
          <span>Include archived</span>
        </label>

        <label className="select-label">
          <span>Rows</span>
          <select value={perPage} onChange={(event) => onPerPageChange(Number(event.target.value))}>
            {PER_PAGE_OPTIONS.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
        </label>

        <button
          type="button"
          className="icon-button refresh-button"
          onClick={onRefresh}
          disabled={refreshing}
          aria-label="Refresh inventory"
          title="Refresh inventory"
        >
          <span aria-hidden="true" className={refreshing ? 'spin' : undefined}>
            ↻
          </span>
        </button>
      </div>
    </div>
  )
}
