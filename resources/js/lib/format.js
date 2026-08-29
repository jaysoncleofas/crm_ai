const currencyFormatters = new Map()

export function formatCurrency(amount, currency = 'USD') {
  if (amount === null || amount === undefined) return '—'

  if (!currencyFormatters.has(currency)) {
    currencyFormatters.set(
      currency,
      new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
      }),
    )
  }

  return currencyFormatters.get(currency).format(amount)
}

export function formatNumber(value) {
  return new Intl.NumberFormat().format(value ?? 0)
}

export function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

export function formatDateTime(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function formatRelative(value) {
  if (!value) return '—'

  const diff = Date.now() - new Date(value).getTime()
  const units = [
    ['year', 31_536_000_000],
    ['month', 2_592_000_000],
    ['day', 86_400_000],
    ['hour', 3_600_000],
    ['minute', 60_000],
  ]
  const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })

  for (const [unit, ms] of units) {
    if (Math.abs(diff) >= ms) {
      return rtf.format(-Math.round(diff / ms), unit)
    }
  }

  return 'just now'
}

/** "marketing_qualified_lead" -> "Marketing Qualified Lead" */
export function humanize(value) {
  if (!value) return '—'
  return String(value)
    .replace(/[_-]+/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}
