import { useEffect, useState } from 'react'
import { Alert } from '../../components/Alert'
import { EmptyState } from '../../components/EmptyState'
import { LoadingState } from '../../components/LoadingState'
import { Modal } from '../../components/Modal'
import { Pagination } from '../../components/Pagination'
import { StatusBadge } from '../../components/StatusBadge'
import { listMovements } from '../../api/materials'
import { MOVEMENT_TYPE_LABELS } from '../../lib/constants'
import { formatDate, formatQuantity } from '../../lib/format'

export function MovementHistoryModal({ material, onClose }) {
  const [movements, setMovements] = useState([])
  const [meta, setMeta] = useState(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    setLoading(true)
    setError('')

    listMovements(material.id, { page, per_page: 10 }, controller.signal)
      .then((response) => {
        if (!active) {
          return
        }

        setMovements(response.data)
        setMeta({
          current_page: response.current_page,
          last_page: response.last_page,
          total: response.total,
          from: response.from,
          to: response.to,
        })
      })
      .catch((requestError) => {
        if (!active || requestError.name === 'AbortError') {
          return
        }

        setError(requestError.message || 'Could not load movement history.')
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
  }, [material.id, page])

  return (
    <Modal
      title="Movement history"
      description={`${material.name} — signed changes with the balance after each movement.`}
      onClose={onClose}
      size="lg"
    >
      <div className="history-summary">
        <div>
          <span className="muted">Current balance</span>
          <strong>
            {formatQuantity(material.quantity_on_hand, { unit: material.stock_unit })}
          </strong>
        </div>
        <div>
          <span className="muted">Movements</span>
          <strong>{meta?.total ?? '—'}</strong>
        </div>
      </div>

      {error ? (
        <Alert tone="error" onDismiss={() => setError('')}>
          {error}
        </Alert>
      ) : null}

      {loading ? (
        <LoadingState label="Loading movements…" />
      ) : movements.length === 0 ? (
        <EmptyState
          title="No movements recorded"
          description="Stock changes will appear here after opening stock or an adjustment."
        />
      ) : (
        <div className="table-wrap">
          <table className="data-table compact-table">
            <thead>
              <tr>
                <th scope="col">Date</th>
                <th scope="col">Type</th>
                <th scope="col" className="align-right">
                  Change
                </th>
                <th scope="col" className="align-right">
                  Balance after
                </th>
                <th scope="col">Reason</th>
              </tr>
            </thead>
            <tbody>
              {movements.map((movement) => {
                const delta = Number(movement.quantity_delta)

                return (
                  <tr key={movement.id}>
                    <td data-label="Date">
                      <time dateTime={movement.created_at}>
                        {formatDate(movement.created_at)}
                      </time>
                    </td>
                    <td data-label="Type">
                      <StatusBadge tone={movement.movement_type === 'production' ? 'neutral' : 'info'}>
                        {MOVEMENT_TYPE_LABELS[movement.movement_type] || movement.movement_type}
                      </StatusBadge>
                    </td>
                    <td
                      data-label="Change"
                      className={`align-right delta${delta < 0 ? ' is-negative' : ' is-positive'}`}
                    >
                      {formatQuantity(movement.quantity_delta, { signed: true, unit: material.stock_unit })}
                    </td>
                    <td data-label="Balance after" className="align-right">
                      {formatQuantity(movement.balance_after, { unit: material.stock_unit })}
                    </td>
                    <td data-label="Reason" className="reason-cell">
                      {movement.reason || '—'}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}

      <div className="modal-footer">
        <Pagination meta={meta} onPageChange={setPage} />
      </div>
    </Modal>
  )
}
