import React, { useRef } from 'react';
import { useReactToPrint } from 'react-to-print';
import DailyTimeRecord from './DailyTimeRecord';
import WeeklyInternshipJournal from './WeeklyInternshipJournal';
import { PrintFO24, PrintFO03, PrintFO22, PrintFO23 } from './EvaluationsPreview';
const FormPreviewModal = ({
  isOpen,
  onClose,
  type = 'dtr',
  data = {},
  onDownload = null,
  downloading = false,
}) => {
  const printRef = useRef(null);
  const handlePrint = useReactToPrint({
    contentRef: printRef,
    documentTitle: `Preview_${type}`,
  });

  if (!isOpen) return null;

  return (
    <>
      <style>{`
        /* Print: show only the document */
        @media print {
          body * {
            visibility: hidden;
          }
          #print-area, #print-area * {
            visibility: visible;
          }
          #print-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0;
            padding: 0;
          }
          .fpm-no-print { display: none !important; }
          .fpm-backdrop, .fpm-dialog, .fpm-body {
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

      {/* Full-screen fixed overlay */}
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
        {/* Dialog box */}
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
          {/* Header */}
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
              <button
                type="button"
                className="btn btn-sm btn-outline-secondary"
                onClick={handlePrint}
                style={{ display: 'flex', alignItems: 'center', gap: '5px' }}
              >
                <i className="fa fa-print"></i> Print Preview
              </button>
              {onDownload && type !== 'dtr' && (
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
              <button
                type="button"
                className="btn-close"
                onClick={onClose}
                aria-label="Close"
                style={{ marginLeft: '4px' }}
              />
            </div>
          </div>

          {/* Info Banner */}
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

          {/* Scrollable Body */}
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
            <div id="print-area" ref={printRef}>
              {type === 'dtr' ? (
                <DailyTimeRecord
                  studentName={data.studentName || data.name || ''}
                  program={(typeof data.program === 'string' ? data.program : data.program?.code || data.program?.name) || ''}
                  companyName={data.companyName || ''}
                  companyLogoPath={data.companyLogoPath || ''}
                />
              ) : type === 'FO-24' ? (
                <PrintFO24 evalData={data.evalData} internship={data.internship} />
              ) : type === 'FO-03' ? (
                <PrintFO03 evalData={data.evalData} internship={data.internship} />
              ) : type === 'FO-22' ? (
                <PrintFO22 evalData={data.evalData} internship={data.internship} />
              ) : type === 'FO-23' ? (
                <PrintFO23 evalData={data.evalData} internship={data.internship} />
              ) : (
                <WeeklyInternshipJournal
                  studentName={data.studentName || data.name || ''}
                  program={(typeof data.program === 'string' ? data.program : data.program?.code || data.program?.name) || ''}
                  companyLogoPath={data.companyLogoPath || ''}
                  weekNumber={data.weekNumber || ''}
                  date={data.date || ''}
                  accomplishment={data.accomplishment || ''}
                  difficulties={data.difficulties || ''}
                  insights={data.insights || ''}
                  entries={data.entries || []}
                />
              )}
            </div>
          </div>

          {/* Footer */}
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
        </div>
      </div>
    </>
  );
};

export default FormPreviewModal;