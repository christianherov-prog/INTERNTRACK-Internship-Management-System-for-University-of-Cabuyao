import React, { useEffect, useRef, useState } from 'react';
import { useReactToPrint } from 'react-to-print';
import InternTrackLoader from '../InternTrackLoader';
import DailyTimeRecord from './DailyTimeRecord';
import WeeklyInternshipJournal from './WeeklyInternshipJournal';
import { PrintFO24, PrintFO03, PrintFO22, PrintFO23, PrintFacultyEval } from './EvaluationsPreview';
import { useConfirm } from '../../contexts/ConfirmContext';
import AsyncButton from '../AsyncButton';
import AppModal from '../modals/AppModal';

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
          <textarea maxLength={1000}
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

/**
 * Screen-only fit for official documents: on screens narrower than the page
 * (794px A4), the page is scaled to the preview's exact width. The zoom sits
 * on this wrapper, outside the element Print Preview (react-to-print) copies,
 * so printed and PDF output keep the official dimensions. The observer only
 * reacts to real size changes (no polling, no feedback loop).
 */
function DocumentFit({ children }) {
  const ref = useRef(null);

  useEffect(() => {
    const wrapper = ref.current;
    const stage = wrapper?.parentElement;
    if (!wrapper || !stage || typeof ResizeObserver === 'undefined') return undefined;

    const fit = () => {
      const page = wrapper.firstElementChild;
      if (!page || !page.offsetWidth) return;
      const cs = getComputedStyle(stage);
      const available = stage.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
      const scale = Math.min(1, available / page.offsetWidth);
      wrapper.style.zoom = scale < 0.999 ? String(Math.max(0.2, Math.floor(scale * 1000) / 1000)) : '';
    };

    const observer = new ResizeObserver(fit);
    observer.observe(stage);
    if (wrapper.firstElementChild) observer.observe(wrapper.firstElementChild);
    fit();
    return () => observer.disconnect();
  }, []);

  return <div ref={ref} className="it-doc-fit">{children}</div>;
}

const PREVIEW_TITLES = {
  dtr: 'Preview: Daily Time Record (PNC:AA-FO-30)',
  'FO-24': 'Preview: Student Intern Performance (PNC:AA-FO-24)',
  'FO-03': 'Preview: HTE To University Evaluation (PNC:AA-FO-03)',
  'FO-22': 'Preview: HTE Evaluation (PNC:AA-FO-22)',
  'FO-23': 'Preview: Program Evaluation (PNC:AA-FO-23)',
  faculty_eval: 'Preview: Faculty Evaluation',
};

function previewTitle(type) {
  return PREVIEW_TITLES[type] || 'Preview: Weekly Internship Journal (PNC:AA-FO-31)';
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
    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '8px' }}>
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
          .it-doc-fit { zoom: 1 !important; }
          .fpm-backdrop, .fpm-dialog, .fpm-body, .fpm-inline,
          .it-modal-layer, .it-modal-viewport {
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
            className="fpm-body it-doc-stage"
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
            <DocumentFit key={type}>
              <div id="print-area-inline" className="fpm-print-area" ref={printRef}>
                {documentNode}
              </div>
            </DocumentFit>
          </div>
        </div>
      ) : (
        // Shared document dialog (AppModal): portaled to <body>, so a hover-lifted
        // page card can never re-anchor it (the old DTR preview flicker), plus
        // Escape, focus return and the shared scroll lock.
        <AppModal
          onClose={onClose}
          size="document"
          title={previewTitle(type)}
          icon={`fa-${type === 'dtr' ? 'clock' : type.startsWith('FO') ? 'star' : 'book'}`}
          className="fpm-dialog"
          bodyClassName="it-doc-stage fpm-body"
          headerActions={printDownloadButtons}
          closeOnBackdrop={!review}
          busy={!!review?.processing}
          testId="form-preview-modal"
          banner={(
            <p className="it-doc-notice fpm-no-print">
              <i className="fa fa-circle-info" aria-hidden="true"></i>
              <span>
                This is a live preview of the official document template. You can print this layout directly or download the server-generated official PDF.
              </span>
            </p>
          )}
          footerBare={!!review}
          footer={review ? (
            <JournalReviewFooter key={review.journal?.id || 'review'} review={review} onClose={onClose} />
          ) : (
            <button type="button" className="btn btn-sm btn-secondary" onClick={onClose}>
              Close
            </button>
          )}
        >
          {loading ? (
            <div className="text-center py-5"><InternTrackLoader /></div>
          ) : (
            <DocumentFit key={type}>
              <div id="print-area" className="fpm-print-area" ref={printRef}>
                {documentNode}
              </div>
            </DocumentFit>
          )}
        </AppModal>
      )}
    </>
  );
};

export default FormPreviewModal;
