export function SummaryCard({ label, value, detail, tone = 'neutral', onClick, active }) {
  const Element = onClick ? 'button' : 'div'

  return (
    <Element
      type={onClick ? 'button' : undefined}
      className={`summary-card summary-card-${tone}${active ? ' is-active' : ''}`}
      onClick={onClick}
      aria-pressed={onClick ? Boolean(active) : undefined}
    >
      <span className="summary-label">{label}</span>
      <span className="summary-value">{value}</span>
      {detail ? <span className="summary-detail">{detail}</span> : null}
    </Element>
  )
}
