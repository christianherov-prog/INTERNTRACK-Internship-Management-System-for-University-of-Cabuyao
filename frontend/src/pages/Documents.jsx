import { useCallback, useEffect, useRef, useState } from 'react';
import client from '../api/client';
import LoadingCard from '../components/LoadingCard.jsx';

const STATUS_BADGES = {
  approved: 'badge-active',
  pending: 'badge-pending',
  rejected: 'badge-overdue',
  missing: 'badge-overdue',
};

function formatDate(value) {
  if (!value) return 'Not uploaded';
  return new Date(value).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

export default function Documents() {
  const [requirements, setRequirements] = useState([]);
  const [requiredTypes, setRequiredTypes] = useState([]);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [docType, setDocType] = useState('');
  const [remarks, setRemarks] = useState('');
  const [file, setFile] = useState(null);
  const [busy, setBusy] = useState(false);
  const [formError, setFormError] = useState('');
  const [notice, setNotice] = useState('');
  const fileInputRef = useRef(null);

  const load = useCallback(async () => {
    try {
      const { data } = await client.get('/student/documents');
      setRequirements(data.requirements);
      setRequiredTypes(data.required_types);
      setStats(data.stats);
      setError('');
    } catch {
      setError('Unable to load documents.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const handleUpload = async (e) => {
    e.preventDefault();
    setFormError('');
    setNotice('');

    if (!file) {
      setFormError('Select a file to upload.');
      return;
    }
    if (!docType) {
      setFormError('Choose a document type.');
      return;
    }

    setBusy(true);
    try {
      const payload = new FormData();
      payload.append('document_type', docType);
      payload.append('file', file);
      if (remarks) payload.append('remarks', remarks);

      await client.post('/student/documents', payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      setNotice('Document uploaded. It is now pending review.');
      setDocType('');
      setRemarks('');
      setFile(null);
      if (fileInputRef.current) fileInputRef.current.value = '';
      await load();
    } catch (err) {
      const errors = err.response?.data?.errors;
      setFormError(
        errors ? Object.values(errors)[0][0] : err.response?.data?.message || 'Upload failed. Please try again.'
      );
    } finally {
      setBusy(false);
    }
  };

  if (loading) {
    return (
      <main className="main-content">
        <LoadingCard label="Loading documents..." />
      </main>
    );
  }

  const submitted = stats?.submitted ?? 0;
  const required = stats?.required ?? 0;

  return (
    <main className="main-content">
      {error && <div className="alert-interntrack mb-3"><i className="fa fa-circle-info me-2"></i>{error}</div>}

      <div className="content-card mb-4 it-inline-013">
        <div className="row g-3 align-items-center">
          <div className="col-md-8">
            <div className="content-card-header" style={{ borderBottom: 'none', paddingBottom: 0, marginBottom: '.5rem' }}><i className="fa fa-folder-tree"></i><h6>Document Compliance Center</h6></div>
            <p className="it-inline-063">Manage internship requirements, upload missing files, and monitor approval status.</p>
          </div>
          <div className="col-md-4 text-md-end"><span className="badge-status badge-active">{submitted} of {required} complete</span></div>
        </div>
      </div>

      <div className="row g-3 mb-4">
        <div className="col-sm-6 col-xl-3"><div className="stat-card"><div className="stat-icon green"><i className="fa fa-file-circle-check"></i></div><div><div className="stat-value">{stats?.approved ?? 0}</div><div className="stat-label">Approved</div></div></div></div>
        <div className="col-sm-6 col-xl-3"><div className="stat-card it-inline-026"><div className="stat-icon amber"><i className="fa fa-file-circle-exclamation"></i></div><div><div className="stat-value">{stats?.pending ?? 0}</div><div className="stat-label">Pending Review</div></div></div></div>
        <div className="col-sm-6 col-xl-3"><div className="stat-card it-inline-025"><div className="stat-icon it-inline-005"><i className="fa fa-file-circle-xmark"></i></div><div><div className="stat-value">{stats?.missing ?? 0}</div><div className="stat-label">Missing</div></div></div></div>
        <div className="col-sm-6 col-xl-3"><div className="stat-card it-inline-023"><div className="stat-icon blue"><i className="fa fa-download"></i></div><div><div className="stat-value">{required}</div><div className="stat-label">Required Files</div></div></div></div>
      </div>

      <div className="row g-3 mb-4">
        <div className="col-lg-8">
          <div className="content-card h-100">
            <div className="content-card-header"><i className="fa fa-file-lines"></i><h6>Required Documents</h6></div>
            <div className="table-card">
              <table className="table table-hover">
                <thead><tr><th>Requirement</th><th>Last Update</th><th>Status</th></tr></thead>
                <tbody>
                  {requirements.map((req) => (
                    <tr key={req.document_type}>
                      <td>{req.document_type}</td>
                      <td>{formatDate(req.last_update)}</td>
                      <td>
                        <span className={`badge-status ${STATUS_BADGES[req.status] || 'badge-pending'}`}>
                          {req.status.charAt(0).toUpperCase() + req.status.slice(1)}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div className="col-lg-4">
          <div className="content-card h-100">
            <div className="content-card-header"><i className="fa fa-cloud-arrow-up"></i><h6>Quick Upload</h6></div>
            <form onSubmit={handleUpload}>
              <div
                className="upload-dropzone mb-3"
                onClick={() => fileInputRef.current?.click()}
                style={{ cursor: 'pointer' }}
              >
                <i className="fa fa-file-arrow-up"></i>
                <strong>{file ? file.name : 'Select a supporting document'}</strong>
                <span>PDF, DOCX, JPG, PNG</span>
              </div>
              <input
                ref={fileInputRef}
                type="file"
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                style={{ display: 'none' }}
                onChange={(e) => setFile(e.target.files?.[0] || null)}
              />
              <div className="mb-3">
                <label className="form-label form-label-subtle">Document Type</label>
                <select className="form-select" value={docType} onChange={(e) => setDocType(e.target.value)}>
                  <option value="">Select document type...</option>
                  {requiredTypes.map((type) => (
                    <option key={type} value={type}>{type}</option>
                  ))}
                </select>
              </div>
              <div className="mb-3">
                <label className="form-label form-label-subtle">Remarks</label>
                <textarea className="form-control" rows="4" placeholder="Optional note for coordinator" value={remarks} onChange={(e) => setRemarks(e.target.value)}></textarea>
              </div>
              <button className="btn-green w-100" type="submit" disabled={busy}>
                <i className="fa fa-upload me-1"></i>{busy ? 'Uploading...' : 'Upload File'}
              </button>
            </form>
            {formError && <div className="alert-interntrack mt-3"><i className="fa fa-circle-info me-2"></i>{formError}</div>}
            {notice && !formError && <div className="alert-interntrack mt-3"><i className="fa fa-circle-check me-2"></i>{notice}</div>}
          </div>
        </div>
      </div>

      <footer className="app-footer">&copy; 2024-2025 INTERNTRACK <span>AY 2024-2025 | 50m2</span></footer>
    </main>
  );
}
