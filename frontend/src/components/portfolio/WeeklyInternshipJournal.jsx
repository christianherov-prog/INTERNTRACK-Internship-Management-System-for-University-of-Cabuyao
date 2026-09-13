import React from 'react';
import '../../assets/css/portfolio-print.css';
import { AuthenticatedFileImage } from '../AuthenticatedFile';
import PortfolioSignature from './PortfolioSignature';
import { displayLabel } from '../../utils/displayLabel';
import { formatFo31DateRange, formatFo31WeekLabel } from '../../utils/fo31DateRange';

export function PageHeader({ companyLogoPath }) {
  const styles = {
    headerContainer: {
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center',
      textAlign: 'center',
      paddingBottom: '4px',
      marginBottom: '6px',
      fontFamily: 'Arial, sans-serif',
      pageBreakAfter: 'avoid',
      breakAfter: 'avoid',
      width: '100%',
      boxSizing: 'border-box',
    },
    sideCol: {
      width: '82px',
      height: '82px',
      display: 'flex',
      justifyContent: 'center',
      alignItems: 'center',
      flexShrink: 0,
    },
    centerCol: {
      flex: 1,
      padding: '0 8px',
      minWidth: 0,
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      justifyContent: 'center',
    },
    republic: {
      margin: 0,
      fontSize: '11pt',
      textIndent: 0,
      textAlign: 'center',
      lineHeight: 1.15,
    },
    university: {
      margin: '2px 0',
      fontFamily: "'Old English Text MT', 'Old English Five', 'UnifrakturCook', serif",
      fontSize: '22pt',
      color: '#0B5D2A',
      fontWeight: 'normal',
      textTransform: 'none',
      lineHeight: 1.1,
      textAlign: 'center',
    },
    pamantasan: {
      margin: 0,
      fontSize: '13pt',
      fontFamily: "'Copperplate Gothic Light', 'Copperplate Gothic', 'Copperplate', serif",
      textIndent: 0,
      textAlign: 'center',
      lineHeight: 1.15,
    },
    department: {
      margin: '2px 0 0 0',
      fontSize: '11pt',
      fontWeight: 'bold',
      fontFamily: "'Calibri', 'Arial', sans-serif",
      textAlign: 'center',
      lineHeight: 1.15,
    },
    address: {
      margin: 0,
      fontSize: '10pt',
      textIndent: 0,
      textAlign: 'center',
      lineHeight: 1.15,
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
      color: '#444',
      boxSizing: 'border-box',
    },
    logoImg: {
      width: '78px',
      height: '78px',
      objectFit: 'contain',
      display: 'block',
    },
  };

  return (
    <div className="fo31-header" style={styles.headerContainer}>
      <div style={styles.sideCol}>
        <img src="/images/pnc-logo.png" alt="UC Logo" style={styles.logoImg} />
      </div>

      <div style={styles.centerCol}>
        <p style={styles.republic}>Republic of the Philippines</p>
        <h1 style={styles.university}>Pamantasan ng Cabuyao</h1>
        <p style={styles.pamantasan}>(UNIVERSITY OF CABUYAO)</p>
        <h2 style={styles.department}>Academic Affairs Division</h2>
        <p style={styles.address}>
          Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna 4025
        </p>
      </div>

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
            style={styles.logoImg}
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

const WeeklyInternshipJournal = ({
  studentName = '',
  program = '',
  weekNumber = '',
  date = '',
  endDate = '',
  accomplishment = '',
  difficulties = '',
  insights = '',
  entries = [],
  nextPg = null,
  companyLogoPath = '',
  studentSignaturePath = '',
}) => {
  const displayDate = formatFo31DateRange(date, endDate);
  const displayWeek = formatFo31WeekLabel(weekNumber);

  const renderColumnData = (type) => {
    return [...Array(6)].map((_, i) => {
      let val = '';
      if (type === 'accomplishment') val = i === 0 ? accomplishment : (entries[i]?.accomplishment || entries[i]?.activities_summary || '');
      if (type === 'difficulties') val = i === 0 ? difficulties : (entries[i]?.difficulties || entries[i]?.challenges || '');
      if (type === 'insights') val = i === 0 ? insights : (entries[i]?.insights || entries[i]?.learnings || '');

      return val ? (
        <div key={i} className="fo31-cell-text" style={{ marginBottom: i < 5 ? '8px' : 0 }}>
          {val}
        </div>
      ) : null;
    });
  };

  return (
    <div
      className="a4-page page-break portfolio-document fo31-page"
      data-toc-id={weekNumber ? `week-${weekNumber}` : 'week-1'}
    >
      <div className="fo31-sheet">
        <div className="fo31-main">
          <div style={styles.docMeta}>
            <p style={styles.metaText}>PNC:AA-FO-31 rev.0 02012023</p>
          </div>

          <PageHeader companyLogoPath={companyLogoPath} />

          <div style={styles.formTitleContainer}>
            <div style={styles.formTitle}>WEEKLY STUDENT INTERNSHIP JOURNAL</div>
          </div>

          <div className="fo31-info-box" style={styles.infoBox}>
            <div style={styles.infoRowTop}>
              <div style={styles.infoCellLeft}>
                <span style={styles.label}>STUDENT INTERN:</span>
                <span style={styles.infoValue}>{studentName}</span>
              </div>
              <div style={styles.infoCellRight}>
                <span style={styles.label}>PROGRAM:</span>
                <span style={styles.infoValue}>{displayLabel(program)}</span>
              </div>
            </div>
            <div style={{ ...styles.infoRowTop, borderBottom: 'none' }}>
              <div style={styles.infoCellLeft}>
                <span style={styles.label}>DATE:</span>
                <span style={styles.infoValue}>{displayDate}</span>
              </div>
              <div style={styles.infoCellRight}>
                <span style={styles.label}>WEEK:</span>
                <span style={styles.infoValue}>{displayWeek}</span>
              </div>
            </div>
          </div>

          <table className="fo31-grid" style={styles.table}>
            <colgroup>
              <col style={{ width: '33.333%' }} />
              <col style={{ width: '33.333%' }} />
              <col style={{ width: '33.334%' }} />
            </colgroup>
            <thead>
              <tr>
                <th style={styles.th}>ACCOMPLISHMENT</th>
                <th style={styles.th}>DIFFICULTIES ENCOUNTERED</th>
                <th style={styles.th}>NEW LEARNING / INSIGHTS</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style={styles.tallTd}>{renderColumnData('accomplishment')}</td>
                <td style={styles.tallTd}>{renderColumnData('difficulties')}</td>
                <td style={styles.tallTd}>{renderColumnData('insights')}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div className="fo31-footer" style={styles.footerSection}>
          <div className="fo31-signature-box" style={styles.signatureBox}>
            <div style={styles.sigTop}>STUDENT-TRAINEE</div>
            <div style={styles.sigMiddle}>
              <PortfolioSignature path={studentSignaturePath} printedName={studentName} maxHeight={48} maxWidth={200} />
            </div>
            <div style={styles.sigBottom}>(signature over printed name)</div>
          </div>

          <div style={styles.privacyConsent}>
            <label style={styles.checkboxLabel}>
              <input type="checkbox" checked readOnly style={styles.checkbox} />
              <span>
                I agree to the collection and processing of my data for the purpose of facilitating my internship at Pamantasan ng Cabuyao. I understand that my personal information is protected by RA 10173, Data Privacy Act of 2012, that I am required to truthful information.
              </span>
            </label>
          </div>

          <div style={styles.mottoContainer}>
            <div style={styles.dangalText}>Dangal ng Bayan.</div>
            <div style={styles.bringingText}>bringing pride and honor to the nation.</div>
          </div>
        </div>
      </div>

      <div className="page-number">{nextPg ? nextPg() : ''}</div>
    </div>
  );
};

const styles = {
  docMeta: {
    display: 'flex',
    justifyContent: 'flex-end',
    alignItems: 'center',
    width: '100%',
    marginBottom: '2px',
    boxSizing: 'border-box',
  },
  metaText: {
    fontSize: '10pt',
    margin: 0,
    color: '#000',
    fontFamily: 'Arial, sans-serif',
    textAlign: 'right',
    textIndent: 0,
    lineHeight: 1.2,
  },

  formTitleContainer: {
    backgroundColor: '#cccccc',
    padding: '5px 8px',
    marginBottom: '8px',
    overflow: 'hidden',
    position: 'relative',
    WebkitPrintColorAdjust: 'exact',
    printColorAdjust: 'exact',
    boxSizing: 'border-box',
  },
  formTitle: {
    textAlign: 'center',
    fontSize: '11pt',
    fontWeight: 'bold',
    margin: 0,
    color: '#000',
    lineHeight: 1.2,
    textShadow: 'none',
    textIndent: 0,
  },

  infoBox: {
    border: '1px solid #000',
    marginBottom: '8px',
    display: 'flex',
    flexDirection: 'column',
    fontSize: '10pt',
    fontFamily: 'Arial, sans-serif',
    boxSizing: 'border-box',
  },
  infoRowTop: {
    display: 'flex',
    borderBottom: '1px solid #000',
    minHeight: '28px',
  },
  infoCellLeft: {
    width: '55%',
    borderRight: '1px solid #000',
    padding: '6px 8px',
    display: 'flex',
    alignItems: 'center',
    gap: '8px',
    boxSizing: 'border-box',
    minWidth: 0,
  },
  infoCellRight: {
    width: '45%',
    padding: '6px 8px',
    display: 'flex',
    alignItems: 'center',
    gap: '8px',
    boxSizing: 'border-box',
    minWidth: 0,
  },
  label: {
    whiteSpace: 'nowrap',
    fontWeight: 'normal',
    lineHeight: 1.2,
    margin: 0,
    flexShrink: 0,
    alignSelf: 'center',
  },
  infoValue: {
    flex: 1,
    minWidth: 0,
    textTransform: 'uppercase',
    lineHeight: 1.25,
    margin: 0,
    overflowWrap: 'break-word',
    wordBreak: 'break-word',
    alignSelf: 'center',
  },

  table: {
    width: '100%',
    borderCollapse: 'collapse',
    tableLayout: 'fixed',
    marginBottom: 0,
    boxSizing: 'border-box',
  },
  th: {
    border: '1px solid #000',
    padding: '6px 8px',
    textAlign: 'center',
    fontSize: '10pt',
    fontWeight: 'bold',
    backgroundColor: '#fff',
    verticalAlign: 'middle',
    width: '33.333%',
    boxSizing: 'border-box',
  },
  tallTd: {
    border: '1px solid #000',
    padding: '8px',
    verticalAlign: 'top',
    overflowWrap: 'break-word',
    wordBreak: 'break-word',
    whiteSpace: 'pre-wrap',
    fontSize: '9.5pt',
    width: '33.333%',
    boxSizing: 'border-box',
  },

  footerSection: {
    marginTop: '10px',
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    width: '100%',
    flexShrink: 0,
    boxSizing: 'border-box',
  },
  signatureBox: {
    border: '1px solid #000',
    width: '280px',
    maxWidth: '100%',
    display: 'flex',
    flexDirection: 'column',
    textAlign: 'center',
    marginBottom: '12px',
    background: 'transparent',
    boxSizing: 'border-box',
  },
  sigTop: {
    borderBottom: '1px solid #000',
    padding: '3px 0',
    fontWeight: 'bold',
    fontSize: '10pt',
  },
  sigMiddle: {
    minHeight: '62px',
    borderBottom: '1px solid #000',
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    padding: '4px 8px 2px',
    background: 'transparent',
    overflow: 'visible',
  },
  sigBottom: {
    padding: '3px 0',
    fontSize: '9pt',
    fontWeight: 'bold',
  },

  privacyConsent: {
    width: '100%',
    padding: '0 4px',
    marginTop: '4px',
    fontSize: '10.5pt',
    lineHeight: 1.2,
    fontFamily: 'Arial, sans-serif',
    boxSizing: 'border-box',
  },
  checkboxLabel: {
    display: 'flex',
    alignItems: 'flex-start',
    textAlign: 'justify',
    cursor: 'pointer',
    margin: 0,
  },
  checkbox: {
    width: '18px',
    height: '18px',
    marginRight: '8px',
    marginTop: '2px',
    flexShrink: 0,
  },
  mottoContainer: {
    marginTop: '14px',
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    textAlign: 'center',
    marginBottom: 0,
  },
  dangalText: {
    fontFamily: "'Edwardian Script ITC', 'Brush Script MT', 'Great Vibes', cursive",
    fontSize: '15pt',
    color: '#444',
    lineHeight: 1.1,
  },
  bringingText: {
    fontFamily: 'Arial, sans-serif',
    fontSize: '4pt',
    fontWeight: 'bold',
    color: '#555',
    lineHeight: 1,
  },
};

export default WeeklyInternshipJournal;
