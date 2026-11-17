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

export default function DistributionInbox() {
  const [items, setItems] = useState<InboxItem[] | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busyId, setBusyId] = useState<number | null>(null)

  async function refresh() {
    const result = await authRequest('/distribution/inbox') as { data: InboxItem[] }
    setItems(result.data)
  }

  useEffect(() => {
    refresh().catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Could not load distributed versions.'))
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
    {error && <p className="registry-message registry-error" role="alert">{error}</p>}
    {notice && <p className="registry-message registry-success" role="status">{notice}</p>}
    <section className="panel registry-panel">
      <div className="panel-heading"><div><h2>Distributed to me</h2><p>Each item points to the exact released version issued to your account.</p></div></div>
      {items === null ? <p>Loading distributions…</p> : items.length === 0 ? <div className="registry-empty">No document versions have been distributed to you.</div> : <div className="distribution-inbox">
        {items.map((item) => <article className="version-card" key={item.recipient_id}>
          <strong>{item.document_title} · Rev {item.revision_label}</strong>
          <span className="registry-status">{item.status.replaceAll('_', ' ')}</span>
          <small>Issued by {item.issued_by ?? 'KMT'}{item.issued_at ? ` · ${new Date(item.issued_at).toLocaleString()}` : ''}</small>
          {item.due_at && <small>Acknowledgment due {item.due_at}</small>}
          {item.files.map((file) => <a key={file.id} href={`/api/document-files/${file.id}/download`}>
            {file.original_filename}<small>{(file.byte_size / 1048576).toFixed(2)} MiB · SHA-256 {file.sha256}</small>
          </a>)}
          {item.can_acknowledge && <button className="button button-primary" disabled={busyId === item.recipient_id} onClick={() => acknowledge(item)}>Acknowledge receipt</button>}
          {item.acknowledged_at && <small>Received {new Date(item.acknowledged_at).toLocaleString()}</small>}
        </article>)}
      </div>}
    </section>
  </div>
}
