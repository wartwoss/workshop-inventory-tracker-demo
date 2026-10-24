import { useCallback, useEffect, useState } from 'react'
import {
  adjustStock,
  createMaterial,
  deleteMaterial,
  getDashboard,
  listMaterials,
  updateMaterial,
} from '../../api/materials'
import { Alert } from '../../components/Alert'
import { Modal } from '../../components/Modal'
import { Button } from '../../components/Button'
import { Pagination } from '../../components/Pagination'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'
import { formatQuantity } from '../../lib/format'
import { AdjustStockModal } from './AdjustStockModal'
import { MaterialFormModal } from './MaterialFormModal'
import { MaterialTable } from './MaterialTable'
import { MaterialToolbar } from './MaterialToolbar'
import { MovementHistoryModal } from './MovementHistoryModal'
import { RecentActivity } from './RecentActivity'

export function InventoryPage({ refreshKey: externalRefreshKey = 0 }) {
  const [dashboard, setDashboard] = useState(null)
  const [summaryLoading, setSummaryLoading] = useState(true)
  const [dashboardError, setDashboardError] = useState('')

  const [materials, setMaterials] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [listError, setListError] = useState('')

  const [search, setSearch] = useState('')
  const debouncedSearch = useDebouncedValue(search, 350)
  const [group, setGroup] = useState('')
  const [lowStockOnly, setLowStockOnly] = useState(false)
  const [showArchived, setShowArchived] = useState(false)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(10)
  const [refreshKey, setRefreshKey] = useState(0)

  const [modal, setModal] = useState(null)
  const [notice, setNotice] = useState(null)
  const [deleteError, setDeleteError] = useState('')
  const [deletingMaterial, setDeletingMaterial] = useState(false)

  const refresh = useCallback(() => {
    setRefreshKey((value) => value + 1)
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    setSummaryLoading(true)
    setDashboardError('')

    getDashboard(controller.signal)
      .then((response) => {
        if (active) {
          setDashboard(response)
        }
      })
      .catch((error) => {
        if (!active || error.name === 'AbortError') {
          return
        }

        setDashboardError(error.message || 'Could not load the inventory summary.')
      })
      .finally(() => {
        if (active) {
          setSummaryLoading(false)
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [refreshKey, externalRefreshKey])

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    setLoading(true)
    setListError('')

    listMaterials(
      {
        search: debouncedSearch,
        material_group: group,
        low_stock_only: lowStockOnly,
        include_inactive: showArchived,
        page,
        per_page: perPage,
      },
      controller.signal,
    )
      .then((response) => {
        if (!active) {
          return
        }

        setMaterials(response.data)
        setMeta({
          current_page: response.current_page,
          last_page: response.last_page,
          total: response.total,
          from: response.from,
          to: response.to,
          per_page: response.per_page,
        })
      })
      .catch((error) => {
        if (!active || error.name === 'AbortError') {
          return
        }

        setListError(error.message || 'Could not load materials.')
      })
      .finally(() => {
        if (active) {
          setLoading(false)
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [debouncedSearch, group, lowStockOnly, showArchived, page, perPage, refreshKey, externalRefreshKey])

  useEffect(() => {
    if (!notice) {
      return undefined
    }

    const timer = window.setTimeout(() => setNotice(null), 6500)

    return () => window.clearTimeout(timer)
  }, [notice])

  const groups = dashboard?.groups ?? []
  const hasFilters = Boolean(search || group || lowStockOnly || showArchived)

  function handleSearchChange(value) {
    setSearch(value)
    setPage(1)
  }

  function handleGroupChange(value) {
    setGroup(value)
    setPage(1)
  }

  function handleLowStockChange(value) {
    setLowStockOnly(value)
    setPage(1)
  }

  function handlePerPageChange(value) {
    setPerPage(value)
    setPage(1)
  }

  function clearFilters() {
    setSearch('')
    setGroup('')
    setLowStockOnly(false)
    setShowArchived(false)
    setPage(1)
  }

  async function handleCreate(payload) {
    const material = await createMaterial(payload)
    setModal(null)
    setNotice({ tone: 'success', message: `Created ${material.name}.` })
    refresh()
  }

  async function handleUpdate(id, payload) {
    const material = await updateMaterial(id, payload)
    setModal(null)
    setNotice({ tone: 'success', message: `Updated ${material.name}.` })
    refresh()
  }

  async function handleDeleteMaterial() {
    if (modal?.type !== 'delete' || deletingMaterial) return

    setDeletingMaterial(true)
    setDeleteError('')

    try {
      const result = await deleteMaterial(modal.material.id)
      setModal(null)
      setNotice({ tone: 'success', message: result?.message || 'Material deletion request completed.' })
      refresh()
    } catch (error) {
      setDeleteError(error.message || 'Could not delete this material.')
    } finally {
      setDeletingMaterial(false)
    }
  }

  async function handleAdjust(material, payload) {
    const updated = await adjustStock(material.id, payload)
    setModal(null)
    setNotice({
      tone: 'success',
      message: `Stock for ${updated.name} is now ${formatQuantity(updated.quantity_on_hand, {
        unit: updated.stock_unit,
      })}.`,
    })
    refresh()
  }

  return (
    <div className="app-shell">
      <header className="app-header">
        <div className="brand">
          <div className="brand-mark" aria-hidden="true">
            <span />
            <span />
            <span />
          </div>
          <div>
            <p className="eyebrow">Furniture workshop</p>
            <h1>Inventory</h1>
            <p className="app-subtitle">
              Materials, balances, and an auditable movement trail.
            </p>
          </div>
        </div>
        <div className="header-actions">
          <Button variant="primary" onClick={() => setModal({ type: 'create' })}>
            Add material
          </Button>
        </div>
      </header>

      {notice ? (
        <Alert tone={notice.tone} onDismiss={() => setNotice(null)}>
          {notice.message}
        </Alert>
      ) : null}

      {dashboardError ? (
        <Alert
          tone="warning"
          title="Summary unavailable"
          onDismiss={() => setDashboardError('')}
        >
          {dashboardError}
        </Alert>
      ) : null}

      <div className="workspace">
        <section className="panel main-panel" aria-labelledby="materials-heading">
          <div className="panel-header">
            <div>
              <h2 id="materials-heading">Materials</h2>
              <p className="muted">
                {meta ? `${meta.total} material${meta.total === 1 ? '' : 's'}` : 'Loading…'}
              </p>
            </div>
          </div>

          <MaterialToolbar
            search={search}
            onSearchChange={handleSearchChange}
            group={group}
            onGroupChange={handleGroupChange}
            groups={groups}
            lowStockOnly={lowStockOnly}
            onLowStockChange={handleLowStockChange}
            showArchived={showArchived}
            onShowArchivedChange={(value) => { setShowArchived(value); setPage(1) }}
            perPage={perPage}
            onPerPageChange={handlePerPageChange}
            onRefresh={refresh}
            refreshing={loading || summaryLoading}
          />

          {listError ? (
            <Alert tone="error" title="Could not load materials">
              <div className="alert-row">
                <span>{listError}</span>
                <Button size="sm" onClick={refresh}>
                  Retry
                </Button>
              </div>
            </Alert>
          ) : null}

          <MaterialTable
            materials={materials}
            loading={loading}
            hasFilters={hasFilters}
            onClearFilters={clearFilters}
            onAddMaterial={() => setModal({ type: 'create' })}
            onAdjust={(material) => setModal({ type: 'adjust', material })}
            onHistory={(material) => setModal({ type: 'history', material })}
            onEdit={(material) => setModal({ type: 'edit', material })}
            onDelete={(material) => { setDeleteError(''); setModal({ type: 'delete', material }) }}
          />

          <div className="table-footer">
            <span className="muted">
              {meta && meta.total > 0
                ? `Showing ${meta.from}–${meta.to} of ${meta.total}`
                : 'No rows to display'}
            </span>
            <Pagination meta={meta} onPageChange={setPage} />
          </div>
        </section>

        <RecentActivity
          movements={dashboard?.recent_movements ?? []}
          stockByUnit={dashboard?.stock_by_unit ?? []}
        />
      </div>

      <footer className="app-footer">
        <span>Stage 4 — inventory + production orders</span>
        <span className="muted">
          Balances are authoritative from the Laravel API; this view never edits stock optimistically.
        </span>
      </footer>

      {modal?.type === 'create' ? (
        <MaterialFormModal
          key="create"
          mode="create"
          groups={groups}
          onClose={() => setModal(null)}
          onSubmit={handleCreate}
        />
      ) : null}

      {modal?.type === 'edit' ? (
        <MaterialFormModal
          key={`edit-${modal.material.id}`}
          mode="edit"
          material={modal.material}
          groups={groups}
          onClose={() => setModal(null)}
          onSubmit={(payload) => handleUpdate(modal.material.id, payload)}
        />
      ) : null}

      {modal?.type === 'adjust' ? (
        <AdjustStockModal
          key={`adjust-${modal.material.id}`}
          material={modal.material}
          onClose={() => setModal(null)}
          onSubmit={(payload) => handleAdjust(modal.material, payload)}
        />
      ) : null}

      {modal?.type === 'history' ? (
        <MovementHistoryModal
          key={`history-${modal.material.id}`}
          material={modal.material}
          onClose={() => setModal(null)}
        />
      ) : null}

      {modal?.type === 'delete' ? (
        <Modal
          title="Delete material?"
          description={`Remove “${modal.material.name}” from active inventory.`}
          onClose={() => { if (!deletingMaterial) setModal(null) }}
        >
          <div className="delete-confirm-content">
            <p>
              Current stock: <strong>{formatQuantity(modal.material.quantity_on_hand, { unit: modal.material.stock_unit })}</strong>.
            </p>
            <p>
              You do not need to enter a quantity. If this material has stock, movement history, model references, or production history, it will be archived instead of permanently deleted so existing records remain intact.
            </p>
            {deleteError ? <Alert tone="error" title="Could not delete material">{deleteError}</Alert> : null}
            <div className="modal-actions">
              <Button variant="secondary" onClick={() => setModal(null)} disabled={deletingMaterial}>Cancel</Button>
              <Button variant="danger" onClick={handleDeleteMaterial} disabled={deletingMaterial}>
                {deletingMaterial ? 'Deleting…' : 'Confirm delete'}
              </Button>
            </div>
          </div>
        </Modal>
      ) : null}
    </div>
  )
}
