import React from 'react';
import '../../assets/css/portfolio-print.css';
import { PageHeader as DefaultPageHeader } from './WeeklyInternshipJournal';
import { AuthenticatedFileImage } from '../AuthenticatedFile';
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
  logs = [],
  month = '',
  nextPg = null
}) => {
  // Map logs by YYYY-MM-DD
  const logMap = {};
  (logs || []).forEach(log => {
    if (log.date) {
      const dateStr = log.date.split('T')[0];
      logMap[dateStr] = log;
    }
  });

  const datesKeys = Object.keys(logMap).sort();
  let minDate = new Date();
  let maxDate = new Date();

  if (datesKeys.length > 0) {
    minDate = new Date(datesKeys[0]);
    maxDate = new Date(datesKeys[datesKeys.length - 1]);
  } else if (month) {
    minDate = new Date(`${month}-01T00:00:00`);
    maxDate = new Date(minDate.getFullYear(), minDate.getMonth() + 1, 0);
  }

  const daysInRange = [];
  let currDate = new Date(minDate);
  while (currDate <= maxDate) {
    daysInRange.push(new Date(currDate));
    currDate.setDate(currDate.getDate() + 1);
  }

  if (daysInRange.length === 0) {
    daysInRange.push(new Date());
  }



  const formatTime = (timeStr) => {
    if (!timeStr) return '';
    const [h, m] = timeStr.split(':');
    let hr = parseInt(h, 10);
    const ampm = hr >= 12 ? 'PM' : 'AM';
    hr = hr % 12 || 12;
    return `${hr}:${m} ${ampm}`;
  };

  const MAX_ROWS = 16;
  const numPages = Math.ceil(daysInRange.length / MAX_ROWS);
  const pages = [];

  for (let p = 0; p < numPages; p++) {
    const pageRows = [];
    const pageDays = daysInRange.slice(p * MAX_ROWS, (p + 1) * MAX_ROWS);

    pageDays.forEach(dateObj => {
      const year = dateObj.getFullYear();
      const monthIndex = dateObj.getMonth();
      const d = dateObj.getDate();
      const dateStr = `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      const log = logMap[dateStr];
      const isWeekend = dateObj.getDay() === 0 || dateObj.getDay() === 6;

      pageRows.push(
        <tr key={dateStr} style={isWeekend ? { backgroundColor: '#f9f9f9' } : {}}>
          <td style={styles.tdDTR}>{dateObj.toLocaleString('default', { month: 'short', day: '2-digit' })}, {dateObj.toLocaleString('default', { weekday: 'short' })}</td>
          <td style={styles.tdDTR}>{log?.am_time_in ? formatTime(log.am_time_in) : (isWeekend ? '—' : '')}</td>
          <td style={styles.tdDTR}>{log?.am_time_out ? formatTime(log.am_time_out) : (isWeekend ? '—' : '')}</td>
          <td style={styles.tdDTR}>{log?.pm_time_in ? formatTime(log.pm_time_in) : (isWeekend ? '—' : '')}</td>
          <td style={styles.tdDTR}>{log?.pm_time_out ? formatTime(log.pm_time_out) : (isWeekend ? '—' : '')}</td>
          <td style={styles.tdDTR}>{log?.hours_rendered ? parseFloat(log.hours_rendered).toFixed(2) : ''}</td>
          <td style={styles.tdDTR}></td>
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
              <div style={styles.sigMiddle}>{studentName}</div>
              <div style={styles.sigBottom}>Signature over printed name of Student Intern</div>
            </div>

            <div style={styles.signatureBox}>
              <div style={styles.sigHeader}>Verified by:</div>
              <div style={styles.sigMiddle}>{supervisorName}</div>
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
    paddingBottom: '2px',
    fontWeight: 'bold',
    fontSize: '10pt',
    textTransform: 'uppercase',
    minHeight: '20px'
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