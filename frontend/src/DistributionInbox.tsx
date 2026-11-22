import { useEffect, useState } from 'react'
import { authRequest } from './api'

type InboxItem = {
  recipient_id: number
  distribution_id: number
  document_title: string
  revision_label: string
  document_version_id: number
  issued_at: string | null
  issued_by: string | null
  acknowledgment_required: boolean
  status: string
  due_at: string | null
  acknowledged_at: string | null
  can_acknowledge: boolean
  files: { id: number; original_filename: string; byte_size: number; sha256: string }[]
}

const testIds = {
  loading: 'distribution-loading',
  empty: 'distribution-empty',
  table: 'distribution-table',
}

function formatDate(value: string | null) {
  if (!value) return '—'

  return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

function formatDateTime(value: string | null) {
  return value ? new Date(value).toLocaleString() : 'Not recorded'
}

function readableStatus(value: string) {
  const label = value.replaceAll('_', ' ')

  return label.charAt(0).toUpperCase() + label.slice(1)
}

function statusTone(value: string) {
  if (['acknowledged', 'issued', 'released'].includes(value)) return 'released'
  if (['pending', 'pending_acknowledgment'].includes(value)) return 'pending'
  if (['expired', 'returned', 'rejected'].includes(value)) return 'returned'
  return 'neutral'
}

export default function DistributionInbox() {
  const [items, setItems] = useState<InboxItem[] | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busyId, setBusyId] = useState<number | null>(null)

  async function refresh() {
    setError('')
    try {
      const result = await authRequest('/distribution/inbox') as { data: InboxItem[] }
      setItems(result.data)
    } catch (reason) {
      setItems(null)
      setError(reason instanceof Error ? reason.message : 'Could not load distributed versions.')
    }
  }

  useEffect(() => {
    void refresh()
  }, [])

  async function acknowledge(item: InboxItem) {
    setBusyId(item.recipient_id); setError(''); setNotice('')
    try {
      await authRequest(`/distribution-recipients/${item.recipient_id}/acknowledge`, 'POST', {})
      await refresh()
      setNotice(`Receipt acknowledged for ${item.document_title}, revision ${item.revision_label}.`)
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not acknowledge receipt.') }
    finally { setBusyId(null) }
  }

  return <div className="registry-layout">
    {error && <p className="registry-message registry-error" role="alert">{error} {items === null && <button className="text-button" type="button" onClick={() => void refresh()}>Retry</button>}</p>}
    {notice && <p className="registry-message registry-success" role="status">{notice}</p>}

    <section className="panel registry-panel" aria-busy={items === null && !error}>
      <div className="panel-heading"><div><h2>Distributed to me</h2><p>Each row points to the exact released version issued to your account.</p></div></div>

      {items === null ? (error ? <div className="registry-empty" data-testid={testIds.empty}>Distributions could not be loaded. Use Retry above.</div> : <p className="registry-loading" data-testid={testIds.loading}>Loading distributions…</p>)
        : items.length === 0 ? <div className="registry-empty" data-testid={testIds.empty}>No document versions have been distributed to you.</div>
        : <div className="registry-table-wrap distribution-table-wrap" role="region" tabIndex={0} aria-labelledby="distribution-table-caption">
          <table className="registry-table distribution-table" data-testid={testIds.table}>
            <caption className="sr-only" id="distribution-table-caption">Distributed to me — released document versions issued to your account, with the acknowledgment status of each.</caption>
            <thead>
              <tr>
                <th scope="col">Document</th>
                <th scope="col">Status</th>
                <th scope="col" className="is-date">Issued</th>
                <th scope="col" className="is-date">Due</th>
                <th scope="col">Files</th>
                <th scope="col" className="is-date">Receipt</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => <tr key={item.recipient_id}>
                <th scope="row">
                  <strong>{item.document_title}</strong>
                  <small>Rev {item.revision_label} · issued by {item.issued_by ?? 'KMT'}</small>
                </th>
                <td><span className={`stamp stamp-${statusTone(item.status)}`}>{readableStatus(item.status)}</span></td>
                <td className="is-date"><time dateTime={item.issued_at ?? undefined} title={formatDateTime(item.issued_at)}>{formatDate(item.issued_at)}</time></td>
                <td className="is-date">{item.due_at ? <time dateTime={item.due_at} title={formatDateTime(item.due_at)}>{formatDate(item.due_at)}</time> : '—'}</td>
                <td>
                  {item.files.length === 0 ? <span className="distribution-none">No files attached</span> : <div className="distribution-files">
                    {item.files.map((file) => <a key={file.id} href={`/api/document-files/${file.id}/download`}>
                      {file.original_filename}
                      <small>{(file.byte_size / 1048576).toFixed(2)} MiB · SHA-256 {file.sha256}</small>
                    </a>)}
                  </div>}
                </td>
                <td className="registry-row-actions">
                  {item.acknowledged_at
                    ? <div className="distribution-receipt"><span className="stamp stamp-released">Acknowledged</span><small><time dateTime={item.acknowledged_at} title={formatDateTime(item.acknowledged_at)}>{formatDate(item.acknowledged_at)}</time></small></div>
                    : item.can_acknowledge
                      ? <button className="button button-primary" disabled={busyId === item.recipient_id} onClick={() => acknowledge(item)}>{busyId === item.recipient_id ? 'Acknowledging…' : 'Acknowledge receipt'}</button>
                      : <span className="distribution-none">{item.acknowledgment_required ? 'Awaiting acknowledgment' : 'No receipt needed'}</span>}
                </td>
              </tr>)}
            </tbody>
          </table>
        </div>}
    </section>
  </div>
}
