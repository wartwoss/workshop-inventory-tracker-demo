import { Button } from './Button'

function pageWindow(current, last) {
  const pages = []
  const start = Math.max(1, Math.min(current - 2, last - 4))
  const end = Math.min(last, start + 4)

  for (let page = start; page <= end; page += 1) {
    pages.push(page)
  }

  return pages
}

export function Pagination({ meta, onPageChange }) {
  if (!meta || meta.last_page <= 1) {
    return null
  }

  return (
    <nav className="pagination" aria-label="Material list pages">
      <Button
        size="sm"
        onClick={() => onPageChange(meta.current_page - 1)}
        disabled={meta.current_page <= 1}
      >
        Previous
      </Button>
      <div className="pagination-pages">
        {pageWindow(meta.current_page, meta.last_page).map((page) => (
          <button
            key={page}
            type="button"
            className={`page-button${page === meta.current_page ? ' is-current' : ''}`}
            onClick={() => onPageChange(page)}
            aria-current={page === meta.current_page ? 'page' : undefined}
          >
            {page}
          </button>
        ))}
      </div>
      <Button
        size="sm"
        onClick={() => onPageChange(meta.current_page + 1)}
        disabled={meta.current_page >= meta.last_page}
      >
        Next
      </Button>
    </nav>
  )
}
