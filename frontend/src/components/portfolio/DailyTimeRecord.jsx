import React from 'react';
import '../../assets/css/portfolio-print.css';
import { PageHeader as DefaultPageHeader } from './WeeklyInternshipJournal';
import { AuthenticatedFileImage } from '../AuthenticatedFile';
import PortfolioSignature from './PortfolioSignature';
import { displayLabel } from '../../utils/displayLabel';

export function PageHeader({ companyLogoPath }) {
  const styles = {
    headerContainer: {
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center',
      textAlign: 'center',
      paddingBottom: '5px',
      marginBottom: '10px',
      fontFamily: 'Arial, sans-serif',
      pageBreakAfter: 'avoid',
      breakAfter: 'avoid',
      width: '100%',
      lineHeight: '1'
    },
    sideCol: {
      width: '85px',
      display: 'flex',
      justifyContent: 'center',
      alignItems: 'center',
      flexShrink: 0
    },
    centerCol: {
      flex: 1,
      padding: '0 10px',
      minWidth: 0
    },
    republic: {
      margin: 0,
      fontSize: '11pt',
      textIndent: 0,
      textAlign: 'center',
      lineHeight: '0'
    },
    university: {
      margin: '2px 0',
      fontFamily: "'Old English Text MT', 'Old English Five', 'UnifrakturCook', serif",
      fontSize: '22pt',
      color: '#0B5D2A',
      fontWeight: 'normal',
      textTransform: 'none',
      lineHeight: 1.1,
      textAlign: 'center'
    },
    pamantasan: {
      margin: 0,
      fontSize: '13pt',
      fontFamily: "'Copperplate Gothic Light', 'Copperplate Gothic', 'Copperplate', serif",
      textIndent: 0,
      textAlign: 'center'
    },
    department: {
      margin: '0px 0 0px 0',
      fontSize: '11pt',
      fontWeight: 'bold',
      fontFamily: "'Calibri', 'Arial', sans-serif",
      textAlign: 'center'
    },
    address: {
      margin: 0,
      fontSize: '10pt',
      textIndent: 0,
      textAlign: 'center'
    },
    logoBox: {
      width: '78px',
      height: '78px',
      border: '1px dashed #444',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      fontSize: '8pt',
      textAlign: 'center',
      fontFamily: 'Arial',
      margin: '0 auto',
      color: '#444'
    }
  };

  return (
    <div style={styles.headerContainer}>
      {/* Left Side: University Logo */}
      <div style={styles.sideCol}>
        <img
          src="/images/pnc-logo.png"
          alt="UC Logo"
          style={{
            width: "78px",
            height: "78px",
            objectFit: "contain",
          }}
        />
      </div>

      {/* Center: Main Institutional Information */}
      <div style={styles.centerCol}>
        <p style={styles.republic}>Republic of the Philippines</p>
        <h1 style={styles.university}>Pamantasan ng Cabuyao</h1>
        <p style={styles.pamantasan}>(UNIVERSITY OF CABUYAO)</p>
        <h2 style={styles.department}>Academic Affairs Division</h2>
        <p style={styles.address}>
          Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna 4025
        </p>
      </div>

      {/* Right Side: HTE Logo */}
      <div style={styles.sideCol}>
        {companyLogoPath ? (
          <AuthenticatedFileImage
            path={companyLogoPath}
            alt="HTE Logo"
            fallback={
              <div style={styles.logoBox}>
                Logo<br />of<br />HTE
              </div>
            }
            style={{
              width: "78px",
              height: "78px",
              objectFit: "contain",
              objectPosition: "center",
            }}
          />
        ) : (
          <div style={styles.logoBox}>
            Logo<br />of<br />HTE
          </div>
        )}
      </div>
    </div>
  );
}

const DailyTimeRecord = ({
  studentName = '',
  program = '',
  companyName = '',
  companyLogoPath = '',
  supervisorName = '',
  studentSignaturePath = '',
  supervisorSignaturePath = '',
  logs = [],
  month = '',
  nextPg = null
}) => {
  const logMap = {};
  (logs || []).forEach(log => {
    if (log.date) {
      const dateStr = String(log.date).split('T')[0];
      logMap[dateStr] = log;
    }
  });

  const datesKeys = Object.keys(logMap).sort();
  const daysInRange = [];

  const ymdFromUtc = (ms) => {
    const x = new Date(ms);
    return `${x.getUTCFullYear()}-${String(x.getUTCMonth() + 1).padStart(2, '0')}-${String(x.getUTCDate()).padStart(2, '0')}`;
  };

  const parseYmdUtc = (ymd) => {
    const [y, m, d] = ymd.split('-').map(Number);
    return Date.UTC(y, m - 1, d);
  };

  if (datesKeys.length > 0) {
    let cursor = parseYmdUtc(datesKeys[0]);
    const end = parseYmdUtc(datesKeys[datesKeys.length - 1]);
    while (cursor <= end) {
      daysInRange.push(ymdFromUtc(cursor));
      cursor += 24 * 60 * 60 * 1000;
    }
  } else if (month) {
    const [y, m] = month.split('-').map(Number);
    const last = new Date(Date.UTC(y, m, 0)).getUTCDate();
    for (let d = 1; d <= last; d++) {
      daysInRange.push(`${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`);
    }
  }

  const formatTime = (timeStr) => {
    if (!timeStr) return '';
    const raw = String(timeStr).trim();
    if (/[ap]m/i.test(raw)) return raw;
    const [h, m] = raw.split(':');
    let hr = parseInt(h, 10);
    if (Number.isNaN(hr)) return '';
    const ampm = hr >= 12 ? 'PM' : 'AM';
    hr = hr % 12 || 12;
    return `${hr}:${(m || '00').slice(0, 2)} ${ampm}`;
  };

  const formatDateLabel = (ymd) => {
    const [y, m, d] = ymd.split('-').map(Number);
    const dt = new Date(Date.UTC(y, m - 1, d));
    const monthName = dt.toLocaleString('en-US', { month: 'short', timeZone: 'UTC' });
    const weekday = dt.toLocaleString('en-US', { weekday: 'short', timeZone: 'UTC' });
    return `${monthName} ${String(d).padStart(2, '0')}, ${weekday}`;
  };

  const weekdayUtc = (ymd) => {
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(Date.UTC(y, m - 1, d)).getUTCDay();
  };

  const anyValidated = (logs || []).some(log => log.validated || log.status === 'validated');
  const verifiedSignaturePath = anyValidated ? supervisorSignaturePath : '';

  const SignatureMark = ({ path, printedName }) => (
    <PortfolioSignature path={path} printedName={printedName} maxHeight={40} maxWidth={160} />
  );

  const MAX_ROWS = 16;
  const numPages = Math.max(1, Math.ceil(daysInRange.length / MAX_ROWS));
  const pages = [];

  for (let p = 0; p < numPages; p++) {
    const pageRows = [];
    const pageDays = daysInRange.slice(p * MAX_ROWS, (p + 1) * MAX_ROWS);

    pageDays.forEach(dateStr => {
      const log = logMap[dateStr];
      const isWeekend = weekdayUtc(dateStr) === 0 || weekdayUtc(dateStr) === 6;
      const isValidated = log?.validated === true || log?.status === 'validated';
      const rowSig = (isValidated && (log.hte_signature_path || supervisorSignaturePath))
        ? (log.hte_signature_path || supervisorSignaturePath)
        : '';

      pageRows.push(
        <tr key={dateStr} style={isWeekend ? { backgroundColor: '#f9f9f9' } : {}}>
          <td style={styles.tdDTR}>{formatDateLabel(dateStr)}</td>
          <td style={styles.tdDTR}>{log?.am_time_in ? formatTime(log.am_time_in) : (isWeekend ? '—' : '')}</td>
          <td style={styles.tdDTR}>{log?.am_time_out ? formatTime(log.am_time_out) : (isWeekend ? '—' : '')}</td>
          <td style={styles.tdDTR}>{log?.pm_time_in ? formatTime(log.pm_time_in) : (isWeekend ? '—' : '')}</td>
          <td style={styles.tdDTR}>{log?.pm_time_out ? formatTime(log.pm_time_out) : (isWeekend ? '—' : '')}</td>
          <td style={styles.tdDTR}>{log?.hours_rendered != null && log?.hours_rendered !== '' ? parseFloat(log.hours_rendered).toFixed(2) : ''}</td>
          <td style={styles.tdDTR}>
            {rowSig ? (
              <AuthenticatedFileImage path={rowSig} alt="" className="portfolio-signature-img" style={{ height: '18px', maxWidth: '70px', objectFit: 'contain', background: 'transparent' }} />
            ) : ''}
          </td>
        </tr>
      );
    });

    pages.push(
      <div key={p} className="a4-page page-break position-relative" style={{ display: 'flex', flexDirection: 'column', height: '100%', justifyContent: 'space-between' }}>
        <div style={{ width: '100%' }}>

          <div style={styles.docMeta}>
            <p style={styles.metaText}>PNC:AA-FO-30 rev.1 09022025</p>
          </div>

          <PageHeader companyLogoPath={companyLogoPath} />

          <div style={styles.formTitleContainer}>
            <h3 style={styles.formTitle}>Student Internship Daily Time Record (DTR) Form</h3>
          </div>

          <div style={styles.infoBox}>
            <div style={styles.infoRowTop}>
              <span style={styles.label}>Name of Student:</span>
              <span style={styles.infoValueBold}>{studentName}</span>
            </div>
            <div style={styles.infoRowTop}>
              <span style={styles.label}>Program:</span>
              <span style={styles.infoValue}>{displayLabel(program)}</span>
            </div>
            <div style={styles.infoRowBottom}>
              <span style={styles.label}>Company/School:</span>
              <span style={styles.infoValue}>{companyName}</span>
            </div>
          </div>

          <table style={styles.table}>
            <thead>
              <tr>
                <th rowSpan="2" style={styles.th}>Date</th>
                <th colSpan="2" style={styles.th}>AM</th>
                <th colSpan="2" style={styles.th}>PM</th>
                <th rowSpan="2" style={styles.th}>Daily<br />Hours</th>
                <th rowSpan="2" style={styles.th}>HTE<br />Signature</th>
              </tr>
              <tr>
                <th style={styles.thInner}>Time in</th>
                <th style={styles.thInner}>Time Out</th>
                <th style={styles.thInner}>Time in</th>
                <th style={styles.thInner}>Time Out</th>
              </tr>
            </thead>
            <tbody>
              {pageRows}
            </tbody>
          </table>
        </div>

        <div style={styles.footerSection}>
          <div style={styles.signatureContainer}>
            <div style={styles.signatureBox}>
              <div style={styles.sigHeader}>Prepared by:</div>
              <div style={styles.sigMiddle}>
                <SignatureMark path={studentSignaturePath} printedName={studentName} />
              </div>
              <div style={styles.sigBottom}>Signature over printed name of Student Intern</div>
            </div>

            <div style={styles.signatureBox}>
              <div style={styles.sigHeader}>Verified by:</div>
              <div style={styles.sigMiddle}>
                <SignatureMark path={verifiedSignaturePath} printedName={supervisorName} />
              </div>
              <div style={styles.sigBottom}>Signature over printed name</div>
              <div style={styles.sigRole}>HTE IN-CHARGE/HEAD/SUPERVISOR</div>
            </div>
          </div>

          <div style={styles.privacyConsent}>
            <label style={styles.checkboxLabel}>
              <input type="checkbox" checked readOnly style={styles.checkbox} />
              <span>
                I agree to the collection and processing of my data for the purpose of recording my daily time record to satisfy the requirements of the Internship Program. I understand that my personal information is protected by RA 10173, Data Privacy Act of 2012, and that I am required to provide truthful information.
              </span>
            </label>
          </div>
        </div>

        <div className="page-number">{nextPg ? nextPg() : ''}</div>
      </div>
    );
  }

  return (
    <div className="portfolio-document">
      {pages}
    </div>
  );
};

const styles = {
  docMeta: {
    display: 'flex',
    justifyContent: 'flex-end',
    width: '100%',
    marginBottom: '5px'
  },
  metaText: {
    fontSize: '10pt',
    margin: 0,
    color: '#000',
    fontFamily: 'Arial, sans-serif'
  },

  // Title Styles
  formTitleContainer: {
    backgroundColor: '#cccccc',
    padding: '6px 0',
    marginBottom: '10px',
    WebkitPrintColorAdjust: 'exact',
    printColorAdjust: 'exact'
  },
  formTitle: {
    textAlign: 'center',
    fontSize: '11pt',
    fontWeight: 'bold',
    margin: '0',
    color: '#000',
    textTransform: 'uppercase'
  },

  // Info Box Styles (Reused perfectly aligned setup)
  infoBox: {

    marginBottom: '15px',
    display: 'flex',
    flexDirection: 'column',
    fontSize: '10pt',
    fontFamily: 'Arial, sans-serif'
  },
  infoRowTop: {
    display: 'flex',
    padding: '8px 8px',
    alignItems: 'center',
    gap: '6px',

  },
  infoRowBottom: {
    padding: '8px 8px',
    display: 'flex',
    alignItems: 'center',
    gap: '6px'
  },
  label: {
    whiteSpace: 'nowrap',
    fontWeight: 'normal',
    lineHeight: '1',
    margin: 0
  },
  infoValue: {
    borderBottom: '1px solid #000',
    flex: 1
  },
  infoValueBold: {
    borderBottom: '1px solid #000',
    flex: 1,
    fontWeight: 'bold',
    textTransform: 'uppercase'
  },

  // Table Styles
  table: {
    width: '100%',
    borderCollapse: 'collapse',
    marginBottom: '15px'
  },
  th: {
    border: '1px solid #000',
    padding: '4px',
    textAlign: 'center',
    fontSize: '9pt',
    fontWeight: 'bold',
    backgroundColor: '#fff'
  },
  thInner: {
    border: '1px solid #000',
    padding: '3px',
    textAlign: 'center',
    fontSize: '8.5pt',
    fontWeight: 'normal'
  },
  tdDTR: {
    border: '1px solid #000',
    padding: '1px 3px',
    height: '16px',
    fontSize: '8.5pt',
    textAlign: 'center'
  },

  // Footer & Signature Styles
  footerSection: {
    marginTop: '10px',
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    width: '100%',
    paddingBottom: '20px'
  },
  signatureContainer: {
    display: 'flex',
    justifyContent: 'space-between',
    width: '100%',
    marginBottom: '10px'
  },
  signatureBox: {
    width: '45%',
    display: 'flex',
    flexDirection: 'column',
    textAlign: 'center',
  },
  sigHeader: {
    fontWeight: 'bold',
    fontSize: '10pt',
    textAlign: 'left',
    marginBottom: '20px'
  },
  sigMiddle: {
    borderBottom: '1px solid #000',
    padding: '4px 6px 2px',
    fontWeight: 'bold',
    fontSize: '10pt',
    textTransform: 'uppercase',
    minHeight: '58px',
    background: 'transparent'
  },
  sigBottom: {
    fontSize: '9pt',
    marginTop: '3px'
  },
  sigRole: {
    fontSize: '8.5pt',
    marginTop: '1px'
  },

  // Privacy Consent
  privacyConsent: {
    paddingTop: '5px',
    marginTop: '5px',
    width: '100%',

  },
  checkboxLabel: {
    display: 'flex',
    alignItems: 'flex-start',
    gap: '8px',
    fontSize: '7pt',
    lineHeight: '1.2',
    textAlign: 'justify',
    color: '#333'
  },
  checkbox: {
    height: '18px',
    marginRight: '8px',
    marginTop: '2px',
    flexShrink: 0
  }
};

export default DailyTimeRecord;