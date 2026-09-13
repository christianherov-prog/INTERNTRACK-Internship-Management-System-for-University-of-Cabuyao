import React, { useRef, useState } from 'react';
import { useReactToPrint } from 'react-to-print';
import InternTrackLoader from '../InternTrackLoader';
import DailyTimeRecord from './DailyTimeRecord';
import WeeklyInternshipJournal from './WeeklyInternshipJournal';
import { PrintFO24, PrintFO03, PrintFO22, PrintFO23, PrintFacultyEval } from './EvaluationsPreview';
import { useConfirm } from '../../contexts/ConfirmContext';
import AsyncButton from '../AsyncButton';

function JournalReviewFooter({ review, onClose }) {
  const confirm = useConfirm();
  const [action, setAction] = useState('approved');
  const [feedback, setFeedback] = useState('');
  const journal = review.journal || {};
  const processing = !!review.processing;
  const needsFeedback = action === 'needs_revision' && !String(feedback).trim();

  const submit = async () => {
    if (needsFeedback || processing) return;
    const student = journal.student_name || review.studentName || 'this student';
    const week = journal.week_number ?? journal.entry_number ?? review.weekNumber ?? '—';
    const verb = action === 'approved' ? 'Approve' : 'Request revision for';
    await confirm({
      title: action === 'approved' ? 'Approve journal entry?' : 'Request journal revision?',
      message: `${verb} journal entry for ${student}, Week ${week}?`,
      confirmLabel: action === 'approved' ? 'Approve Journal' : 'Submit Needs Revision',
      variant: action === 'approved' ? 'primary' : 'danger',
      run: async () => {
        await review.onSubmit(action, feedback);
      },
    });
  };

  return (
    <div className="fpm-no-print" style={{ flexShrink: 0, borderTop: '1px solid #dee2e6', background: '#fff' }}>
      <div style={{ padding: '12px 20px' }}>
        {journal.faculty_feedback ? (
          <div className="alert alert-info py-2 mb-3">
            <strong>Previous Feedback:</strong> {journal.faculty_feedback}
          </div>
        ) : null}
        <div className="mb-3">
          <label className="form-label fw-semibold mb-1">Action</label>
          <div className="d-flex gap-3">
            <div className="form-check">
              <input type="radio" className="form-check-input" id="fac_preview_approve" checked={action === 'approved'} onChange={() => setAction('approved')} />
              <label className="form-check-label" htmlFor="fac_preview_approve">Approve</label>
            </div>
            <div className="form-check">
              <input type="radio" className="form-check-input" id="fac_preview_revise" checked={action === 'needs_revision'} onChange={() => setAction('needs_revision')} />
              <label className="form-check-label" htmlFor="fac_preview_revise">Needs Revision</label>
            </div>
          </div>
        </div>
        <div>
          <label className="form-label fw-semibold">
            Feedback {action === 'needs_revision' && <span className="text-danger">*</span>}
          </label>
          <textarea
            className="form-control"
            rows={2}
            value={feedback}
            onChange={(e) => setFeedback(e.target.value)}
            placeholder="Feedback"
          />
        </div>
      </div>
      <div className="d-flex justify-content-end gap-2 px-3 py-2" style={{ borderTop: '1px solid #eee', background: '#f8f9fa' }}>
        <button type="button" className="btn btn-secondary" onClick={onClose} disabled={processing}>Cancel</button>
        <AsyncButton
          type="button"
          className="btn btn-primary"
          busy={processing}
          busyLabel="Submitting…"
          disabled={needsFeedback}
          onClick={submit}
        >
          <i className="fa fa-check me-2"></i>Submit Review
        </AsyncButton>
      </div>
    </div>
  );
}

const FormPreviewModal = ({
  isOpen,
  onClose,
  type = 'dtr',
  data = {},
  onDownload = null,
  downloading = false,
  inline = false,
  loading = false,
  review = null,
}) => {
  const printRef = useRef(null);
  const handlePrint = useReactToPrint({
    contentRef: printRef,
    documentTitle: `Preview_${type}`,
  });

  if (!isOpen) return null;

  const documentNode = type === 'dtr' ? (
    <DailyTimeRecord
      studentName={data.studentName || data.name || ''}
      program={(typeof data.program === 'string' ? data.program : data.program?.name || data.program?.code) || ''}
      companyName={data.companyName || ''}
      companyLogoPath={data.companyLogoPath || ''}
      supervisorName={data.supervisorName || ''}
      studentSignaturePath={data.studentSignaturePath || ''}
      supervisorSignaturePath={data.supervisorSignaturePath || ''}
      logs={data.logs || data.attendance || []}
    />
  ) : type === 'FO-24' ? (
    <PrintFO24 evalData={data.evalData} internship={data.internship} user={data.user} identity={data.identity} />
  ) : type === 'FO-03' ? (
    <PrintFO03 evalData={data.evalData} internship={data.internship} user={data.user} identity={data.identity} />
  ) : type === 'FO-22' ? (
    <PrintFO22 evalData={data.evalData} internship={data.internship} user={data.user} identity={data.identity} />
  ) : type === 'FO-23' ? (
    <PrintFO23 evalData={data.evalData} internship={data.internship} user={data.user} identity={data.identity} />
  ) : type === 'faculty_eval' ? (
    <PrintFacultyEval evalData={data.evalData} internship={data.internship} user={data.user} identity={data.identity} />
  ) : (
    <WeeklyInternshipJournal
      studentName={data.studentName || data.name || ''}
      program={(typeof data.program === 'string' ? data.program : data.program?.name || data.program?.code) || ''}
      companyLogoPath={data.companyLogoPath || ''}
      studentSignaturePath={data.studentSignaturePath || ''}
      weekNumber={data.weekNumber || ''}
      date={data.date || ''}
      endDate={data.endDate || ''}
      accomplishment={data.accomplishment || ''}
      difficulties={data.difficulties || ''}
      insights={data.insights || ''}
      entries={data.entries || []}
    />
  );

  const printDownloadButtons = (
    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
      <button
        type="button"
        className="btn btn-sm btn-outline-secondary"
        onClick={handlePrint}
        style={{ display: 'flex', alignItems: 'center', gap: '5px' }}
      >
        <i className="fa fa-print"></i> Print Preview
      </button>
      {onDownload && (
        <button
          type="button"
          className="btn btn-sm btn-primary"
          onClick={onDownload}
          disabled={downloading}
          style={{ display: 'flex', alignItems: 'center', gap: '5px' }}
        >
          <i className={`fa fa-${downloading ? 'spinner fa-spin' : 'file-pdf'}`}></i>
          {downloading ? 'Generating...' : 'Download Official PDF'}
        </button>
      )}
    </div>
  );

  return (
    <>
      <style>{`
        @media print {
          body * {
            visibility: hidden;
          }
          #print-area, #print-area *,
          .fpm-print-area, .fpm-print-area * {
            visibility: visible;
          }
          #print-area, .fpm-print-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0;
            padding: 0;
          }
          .fpm-no-print { display: none !important; }
          .fpm-backdrop, .fpm-dialog, .fpm-body, .fpm-inline {
            position: static !important;
            background: none !important;
            box-shadow: none !important;
            height: auto !important;
            max-height: none !important;
            overflow: visible !important;
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
          }
        }
      `}</style>

      {inline ? (
        <div className="fpm-inline border rounded overflow-hidden mb-3">
          <div
            className="fpm-no-print d-flex align-items-center justify-content-between flex-wrap gap-2 px-3 py-2"
            style={{ background: '#f8f9fa', borderBottom: '1px solid #dee2e6' }}
          >
            <span className="small text-muted mb-0">
              <i className="fa fa-file-lines me-1"></i>
              Official FO-31 preview
            </span>
            {printDownloadButtons}
          </div>
          <div
            className="fpm-body"
            style={{
              overflowY: 'auto',
              overflowX: 'auto',
              background: '#525659',
              padding: '16px',
              display: 'flex',
              justifyContent: 'center',
              alignItems: 'flex-start',
              maxHeight: '70vh',
            }}
          >
            <div id="print-area-inline" className="fpm-print-area" ref={printRef}>
              {documentNode}
            </div>
          </div>
        </div>
      ) : (
        <div
          id="form-preview-root"
          className="fpm-backdrop"
          style={{
            position: 'fixed',
            inset: 0,
            zIndex: 1060,
            background: 'rgba(0,0,0,0.78)',
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '12px',
          }}
          onClick={onClose}
        >
          <div
            className="fpm-dialog"
            onClick={e => e.stopPropagation()}
            style={{
              width: '100%',
              maxWidth: '1400px',
              height: '95vh',
              display: 'flex',
              flexDirection: 'column',
              background: '#fff',
              borderRadius: '10px',
              boxShadow: '0 25px 80px rgba(0,0,0,0.55)',
              overflow: 'hidden',
            }}
          >
            <div
              className="fpm-no-print"
              style={{
                flexShrink: 0,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                padding: '10px 20px',
                background: '#f8f9fa',
                borderBottom: '1px solid #dee2e6',
              }}
            >
              <h5
                style={{
                  margin: 0,
                  fontSize: '1.05rem',
                  fontWeight: 700,
                  display: 'flex',
                  alignItems: 'center',
                  gap: '8px',
                  color: '#212529',
                }}
              >
                <i className={`fa fa-${type === 'dtr' ? 'clock' : type.startsWith('FO') ? 'star' : 'book'}`} style={{ color: '#0d6efd' }}></i>
                {type === 'dtr'
                  ? 'Preview: Daily Time Record (PNC:AA-FO-30)'
                  : type === 'FO-24' ? 'Preview: Student Intern Performance (PNC:AA-FO-24)'
                  : type === 'FO-03' ? 'Preview: HTE To University Evaluation (PNC:AA-FO-03)'
                  : type === 'FO-22' ? 'Preview: HTE Evaluation (PNC:AA-FO-22)'
                  : type === 'FO-23' ? 'Preview: Program Evaluation (PNC:AA-FO-23)'
                  : type === 'faculty_eval' ? 'Preview: Faculty Evaluation'
                  : 'Preview: Weekly Internship Journal (PNC:AA-FO-31)'}
              </h5>
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                {printDownloadButtons}
                <button
                  type="button"
                  className="btn-close"
                  onClick={onClose}
                  aria-label="Close"
                  style={{ marginLeft: '4px' }}
                />
              </div>
            </div>

            <div
              className="fpm-no-print"
              style={{
                flexShrink: 0,
                padding: '8px 20px',
                background: '#e7f0ff',
                borderBottom: '1px solid #c9d9f5',
                fontSize: '0.85rem',
                color: '#0d6efd',
                display: 'flex',
                alignItems: 'center',
                gap: '8px',
              }}
            >
              <i className="fa fa-circle-info"></i>
              <span>
                This is a live preview of the official document template. You can print this layout directly or download the server-generated official PDF.
              </span>
            </div>

            <div
              className="fpm-body"
              style={{
                flex: 1,
                overflowY: 'auto',
                overflowX: 'auto',
                background: '#525659',
                padding: '24px',
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'flex-start',
              }}
            >
              {loading ? (
                <div className="text-center py-5"><InternTrackLoader /></div>
              ) : (
                <div id="print-area" className="fpm-print-area" ref={printRef}>
                  {documentNode}
                </div>
              )}
            </div>

            {review ? (
              <JournalReviewFooter key={review.journal?.id || 'review'} review={review} onClose={onClose} />
            ) : (
              <div
                className="fpm-no-print"
                style={{
                  flexShrink: 0,
                  display: 'flex',
                  justifyContent: 'flex-end',
                  padding: '10px 20px',
                  background: '#f8f9fa',
                  borderTop: '1px solid #dee2e6',
                }}
              >
                <button type="button" className="btn btn-sm btn-secondary" onClick={onClose}>
                  Close
                </button>
              </div>
            )}
          </div>
        </div>
      )}
    </>
  );
};

export default FormPreviewModal;
