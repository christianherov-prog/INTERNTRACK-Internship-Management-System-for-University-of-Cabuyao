import React from 'react';
import '../../assets/css/portfolio-print.css';
import { AuthenticatedFileImage } from '../AuthenticatedFile';

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
      width: '100%'
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
      textAlign: 'center'
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
          src="/images/ccs-logo.png"
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
  companyLogoPath = '' 
}) => {
  const formatDate = (d) => {
    if (!d) return '';
    try {
      const parsed = new Date(d);
      if (isNaN(parsed.getTime())) return d; // fallback if invalid date string
      return parsed.toLocaleDateString('default', { month: 'long', day: 'numeric', year: 'numeric' });
    } catch {
      return d;
    }
  };

  const formattedStart = formatDate(date);
  const formattedEnd = formatDate(endDate);
  const displayDateRange = (formattedStart && formattedEnd) ? `${formattedStart} to ${formattedEnd}` : formattedStart;
  
  const displayDate = displayDateRange ? displayDateRange : '';

  const renderColumnData = (type) => {
    return [...Array(6)].map((_, i) => {
      let val = '';
      if (type === 'accomplishment') val = i === 0 ? accomplishment : (entries[i]?.accomplishment || entries[i]?.activities_summary || '');
      if (type === 'difficulties') val = i === 0 ? difficulties : (entries[i]?.difficulties || entries[i]?.challenges || '');
      if (type === 'insights') val = i === 0 ? insights : (entries[i]?.insights || entries[i]?.learnings || '');
      
      return val ? (
        <div key={i} style={{ marginBottom: '8px' }}>
          {val}
        </div>
      ) : null;
    });
  };

  return (
    <div className="portfolio-document">
      <div className="a4-page page-break position-relative" style={{ display: 'flex', flexDirection: 'column', height: '100%', justifyContent: 'space-between' }}>
        
        <div style={{ width: '100%' }}>
          
          <div style={styles.docMeta}>
            <p style={styles.metaText}>PNC:AA-FO-31 rev.0 02012023</p>
          </div>

          <PageHeader companyLogoPath={companyLogoPath} />

          {/* Gray Background Title */}
          <div style={styles.formTitleContainer}>
            <h3 style={styles.formTitle}>WEEKLY STUDENT INTERNSHIP JOURNAL</h3>
          </div>

          {/* Structured Information Box */}
          <div style={styles.infoBox}>
            <div style={styles.infoRowTop}>
              <div style={styles.infoCellLeft}>
                <span style={styles.label}>STUDENT INTERN:</span>
                <span style={styles.infoValue}>{studentName}</span>
              </div>
              <div style={styles.infoCellRight}>
                <span style={styles.label}>PROGRAM:</span>
                <span style={styles.infoValue}>{program || 'BSIT / BSCS'}</span>
              </div>
            </div>
            <div style={styles.infoRowBottom}>
              <span style={styles.label}>DATE:</span>
              <span style={styles.infoValue}>{displayDate}</span>
            </div>
          </div>

          {/* Unified Single-Row Table */}
          <table style={styles.table}>
            <thead>
              <tr>
                <th style={styles.th}>ACCOMPLISHMENT</th>
                <th style={styles.th}>DIFFICULTIES ENCOUNTERED</th>
                <th style={styles.th}>NEW LEARNING/ INSIGHTS</th>
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

        <div style={styles.footerSection}>
          {/* Structured Signature Box */}
          <div style={styles.signatureBox}>
            <div style={styles.sigTop}>STUDENT-TRAINEE</div>
            <div style={styles.sigMiddle}>
              {studentName}
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

        <div className="page-number">{nextPg ? nextPg() : ''}</div>
      </div>
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
    color: '#000' 
  },
  
  // Info Box Styles
  infoBox: {
    border: '1px solid #000',
    marginBottom: '15px',
    display: 'flex',
    flexDirection: 'column',
    fontSize: '10pt',
    fontFamily: 'Arial, sans-serif'
  },
  infoRowTop: {
    display: 'flex',
    borderBottom: '1px solid #000'
  },
  infoCellLeft: {
    width: '55%', 
    borderRight: '1px solid #000',
    padding: '8px 8px', 
    display: 'flex',
    alignItems: 'center', 
    gap: '6px'
  },
  infoCellRight: {
    width: '45%',
    padding: '8px 8px',
    display: 'flex',
    alignItems: 'center', 
    gap: '6px'
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
    flex: 1, 
    textTransform: 'uppercase',
    lineHeight: '1.2',
    margin: 0
  },
  
  // Table Styles
  table: { 
    width: '100%', 
    borderCollapse: 'collapse', 
    marginBottom: '20px' 
  },
  th: { 
    border: '1px solid #000', 
    padding: '6px', 
    textAlign: 'center', 
    fontSize: '10pt', 
    fontWeight: 'bold',
    backgroundColor: '#fff' 
  },
  tallTd: { 
    border: '1px solid #000', 
    padding: '8px', 
    height: '380px', 
    verticalAlign: 'top', 
    wordBreak: 'break-word', 
    whiteSpace: 'pre-wrap', 
    fontSize: '9.5pt' 
  },
  
  // Footer & Signature Box Styles
  footerSection: { 
    marginTop: '70px', 
    display: 'flex', 
    flexDirection: 'column', 
    alignItems: 'center',
    width: '100%'
    
  },
  signatureBox: {
    border: '1px solid #000',
    width: '280px',
    display: 'flex',
    flexDirection: 'column',
    textAlign: 'center',
    marginBottom: '20px'
  },
  sigTop: {
    borderBottom: '1px solid #000',
    padding: '3px 0',
    fontWeight: 'bold',
    fontSize: '10pt'
  },
  sigMiddle: {
    height: '45px', 
    borderBottom: '1px solid #000',
    display: 'flex',
    alignItems: 'flex-end',
    justifyContent: 'center',
    paddingBottom: '2px',
    fontWeight: 'bold',
    fontSize: '10pt',
    textTransform: 'uppercase'
  },
  sigBottom: {
    padding: '3px 0',
    fontSize: '9pt',
    fontWeight: 'bold'
  },
  
  // Privacy Consent & Motto
  privacyConsent: { 
    width: '100%',
    padding: '0 10px', 
    marginTop: '10px', 
    fontSize: '10.5pt', 
    lineHeight: '1.2',
    fontFamily: 'Arial, sans-serif'
  },
  checkboxLabel: { 
    display: 'flex', 
    alignItems: 'flex-start', 
    textAlign: 'justify',
    cursor: 'pointer', 
    margin: 0 
  },
  checkbox: {
    width: '18px',
    height: '18px',
    marginRight: '8px',
    marginTop: '2px',
    flexShrink: 0
  },
  mottoContainer: {
    marginTop: '35px', /* INCREASED: Pushed down further from the privacy consent text */
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    textAlign: 'center',
    marginBottom: '10px' 
  },
  dangalText: {
    fontFamily: "'Edwardian Script ITC', 'Brush Script MT', 'Great Vibes', cursive",
    fontSize: '15pt',
    color: '#444',
    lineHeight: '1 ',
    
    
  },
  bringingText: {
    fontFamily: 'Arial, sans-serif',
    fontSize: '4pt',
    fontWeight: 'bold',
    color: '#555',
    lineHeight: '1'
  }
};

export default WeeklyInternshipJournal;