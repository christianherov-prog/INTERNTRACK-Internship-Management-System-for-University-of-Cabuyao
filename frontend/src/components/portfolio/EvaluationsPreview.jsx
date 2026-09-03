import React from 'react';
import '../../assets/css/portfolio-print.css';

// ---------------------------------------------------------
// DATA CONSTANTS
// ---------------------------------------------------------
const FO24_CRITERIA = [
  { label: "1. Knowledge, Skills, and Abilities (exhibits the required level of work knowledge, skills, and values to perform the tasks assigned)", weight: "25" },
  { label: "2. Productivity (is able to accomplish the assigned tasks at a given time)", weight: "12.5" },
  { label: "3. Quality of Work (reflects accuracy and efficiency)", weight: "12.5" },
  { label: "4. Judgment (effectively analyzes problems; has sound decision-making skills)", weight: "10" },
  { label: "5. Communication (expresses ideas clearly; listens well and responds appropriately)", weight: "10" },
  { label: "6. Work Habits (complies with the company's work rules and office policies)", weight: "10" },
  { label: "7. Initiative (performs work voluntarily)", weight: "5" },
  { label: "8. Dependability (works well with less supervision)", weight: "5" },
  { label: "9. Attendance and Time Keeping (is consistent and punctual in office/virtual attendance)", weight: "5" },
  { label: "10. Social Adjustment to other people (is courteous, a team player, and helpful)", weight: "5" }
];

const FO03_CRITERIA = [
  "Interns' preparedness for work",
  "Relevance of academic training to job tasks",
  "Communication and support from the school",
  "Responsiveness to company feedback",
  "Overall effectiveness of the internship program"
];

const FO22_CRITERIA = [
  "1. Training given by the Host Training Establishment (HTE) was course-related.",
  "2. Internship Supervisors were able to impart additional knowledge and skills related to the course of the student.",
  "3. The working environment was conducive to training and learning.",
  "4. The Host Training Establishment (HTE) provided complete facilities needed for the training of students.",
  "5. Training areas/activities specified in the Student Training Plan were successfully carried out.",
  "6. Interpersonal working relationship with employees of the company was positively maintained.",
  "7. Safety of the trainees in the workplace was ensured by the company, if applicable"
];

const FO23_SECTIONS = [
  {
    title: "I. Internship Program Objectives",
    items: ["1. Clarity of Objectives", "2. Attainment of Objectives"],
  },
  {
    title: "II. Program Guidelines/Policies",
    items: ["1. Clarity of guidelines and policies", "2. Effective implementation of the guidelines and policies", "3. Relevance or importance of guidelines and policies to the student's training/internship"],
  },
  {
    title: "III. Internship Activities",
    items: ["1. Schedule of Activities (Orientation and Seminars)", "2. Venue/Platform of Internship Activities", "3. Adequacy of time allotted to meet the deadline for completion of activities"],
  },
  {
    title: "IV. Internship Teaching Personnel",
    items: ["1. Availability for consultation and inquiries", "2. Assistance in locating appropriate Internship Host Training Establishments (HTE)", "3. Professional dealing with the students", "4. Regular phone/virtual checking"],
  }
];

// ---------------------------------------------------------
// HELPER COMPONENTS
// ---------------------------------------------------------

const Checkbox = ({ checked }) => (
  <span style={{ fontSize: '10.5pt', lineHeight: '1', verticalAlign: 'middle' }}>
    {checked ? '☑' : '☐'}
  </span>
);

const PrintLine = ({ text, width = '100px', flex = 'none' }) => (
  <div style={{
    borderBottom: '1px solid #000',
    display: 'inline-block',
    width: flex === '1' ? 'auto' : width,
    flex: flex === '1' ? 1 : 'none',
    minHeight: '1.1em',
    padding: '0 3px',
    margin: '0 3px',
    fontFamily: 'Arial, sans-serif',
    wordBreak: 'break-word',
    fontSize: '9pt'
  }}>
    {text || ''}
  </div>
);

const MultilinePreview = ({ text, lines = 1 }) => (
  <div style={{ marginTop: '2px', marginBottom: '4px', color: text ? '#000' : 'transparent', display: 'block' }}>
    {text ? (
      <div style={{ borderBottom: '1px solid #e5e7eb', paddingBottom: '2px', minHeight: '18px', whiteSpace: 'pre-wrap', fontFamily: 'Arial, sans-serif', fontSize: '9pt', lineHeight: '1.2' }}>
        {text}
      </div>
    ) : (
      Array.from({ length: lines }).map((_, i) => (
        <div key={i} style={{ borderBottom: '1px solid #e5e7eb', height: '18px', width: '100%', marginBottom: '2px' }}></div>
      ))
    )}
  </div>
);

// ---------------------------------------------------------
// PRINT FORM COMPONENTS
// ---------------------------------------------------------

export const PrintFO24 = ({ evalData, internship, tocId }) => {
  const responses = evalData?.responses || {};
  const student = internship?.student?.student_profile || internship?.student?.studentProfile || {};
  const studentName = `${student.last_name || ''}, ${student.first_name || ''}`.trim();
  const program = (typeof student.program === 'string' ? student.program : student.program?.name || student.program?.code) || '';
  const semStr = Number(internship?.semester) === 1 ? '1st' : Number(internship?.semester) === 2 ? '2nd' : Number(internship?.semester) === 3 ? 'Midyear' : '';
  const ayStr = internship?.academic_year || internship?.school_year || '';
  const supervisor = internship?.supervisor?.supervisorProfile || {};
  const supervisorName = `${supervisor.last_name || ''}, ${supervisor.first_name || ''}`.trim() || evalData?.supervisor_name || '';

  return (
    <div data-toc-id={tocId} className="a4-page portfolio-document position-relative" style={{ display: 'flex', flexDirection: 'column' }}>
      <div style={{ textAlign: 'right', fontSize: '8pt', fontFamily: 'Arial, sans-serif', marginBottom: '8px' }}>
        PNC:AA-FO-24 rev.0 02012023
      </div>

      <div style={{ position: 'relative', textAlign: 'center', marginBottom: '10px', fontFamily: 'Arial, sans-serif' }}>
        <div style={{ position: 'absolute', top: '50%', transform: 'translateY(-60%)', width: '75px', height: '75px', left: '0' }}>
          <img src="/images/pnc-logo.png" alt="University of Cabuyao Logo" style={{ objectFit: 'contain', width: '100%', height: '100%' }} />
        </div>
        <div style={{ fontSize: '9.5pt' }}>Republic of the Philippines</div>
        <div style={{ fontSize: '22pt', fontWeight: 'bold', fontFamily: '"Old English Text MT", serif', color: '#004d00', lineHeight: '1', margin: '0' }}>
          University of Cabuyao
        </div>
        <div style={{ fontSize: '11.5pt', fontFamily: 'Arial, sans-serif' }}>(PAMANTASAN NG CABUYAO)</div>
        <div style={{ fontSize: '10.5pt', fontWeight: 'bold', fontStyle: 'italic' }}>Academic Affairs Division</div>
        <div style={{ fontSize: '8.5pt' }}>Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna, Phillippines 4025</div>
      </div>

      <div style={{ backgroundColor: '#d1d5db', padding: '3px 0', textAlign: 'center', fontWeight: 'bold', fontSize: '10.5pt', marginTop: '5px', fontFamily: 'Arial, sans-serif' }}>
        STUDENT INTERN PERFORMANCE EVALUATION FORM
      </div>

      <div style={{ textAlign: 'center', fontSize: '9.5pt', margin: '6px 0 12px 0', fontFamily: 'Arial, sans-serif' }}>
        <span style={{ borderBottom: '1px solid black', minWidth: '70px', display: 'inline-block', textAlign: 'center' }}>{semStr}</span> Semester/Midyear
        <span style={{ borderBottom: '1px solid black', minWidth: '50px', display: 'inline-block', textAlign: 'center', margin: '0 5px' }}></span> / Academic Year
        <span style={{ borderBottom: '1px solid black', minWidth: '100px', display: 'inline-block', textAlign: 'center', marginLeft: '5px' }}>{ayStr}</span>
      </div>

      <div style={{ fontFamily: 'Arial, sans-serif', fontSize: '9pt', marginBottom: '12px', lineHeight: '1.4' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', marginBottom: '3px' }}>
          <span style={{ fontWeight: 'bold', width: '125px' }}>NAME OF TRAINEE:</span>
          <div style={{ flex: 1, borderBottom: '1px solid #000', paddingLeft: '8px' }}>{studentName}</div>
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end', marginBottom: '3px' }}>
          <span style={{ fontWeight: 'bold', width: '125px' }}>PROGRAM</span>
          <div style={{ width: '45%', borderBottom: '1px solid #000', paddingLeft: '8px' }}>{program}</div>
          <span style={{ fontWeight: 'bold', marginLeft: '12px', width: '125px' }}>TRAINING PERIOD:</span>
          <div style={{ flex: 1, borderBottom: '1px solid #000' }}></div>
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end' }}>
          <span style={{ fontWeight: 'bold', width: '125px' }}>ACADEMIC YEAR:</span>
          <div style={{ width: '45%', borderBottom: '1px solid #000', paddingLeft: '8px' }}>{ayStr}</div>
          <div style={{ marginLeft: '12px' }}></div>
          <div style={{ display: 'flex', gap: '8px', fontWeight: 'bold', fontSize: '8.5pt' }}>
            <label>[ {evalData?.evaluation_period === '1st' ? 'X' : ' '} ] 1st Sem.</label>
            <label>[ {evalData?.evaluation_period === '2nd' ? 'X' : ' '} ] 2nd Sem.</label>
            <label>[ {evalData?.evaluation_period === 'midyear' ? 'X' : ' '} ] Midyear</label>
          </div>
        </div>
      </div>

      <div style={{ fontSize: '8.5pt', fontFamily: 'Arial, sans-serif', marginBottom: '8px' }}>
        <p style={{ margin: '0 0 5px 0', fontWeight: 'bold', fontStyle: 'italic', textAlign: 'left', textIndent: '0' }}>
          Please provide a numerical grade for every criterion (100 highest, 65 lowest). Guide:
        </p>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '0 25px' }}>
          <div><div>96% - 100% - Excellent</div><div>80% - 84% - Fair</div></div>
          <div><div>90% - 95% - Very Good</div><div>75% - 79% - Passed</div></div>
          <div><div>85% - 89% - Good</div><div>below 75% - Failed</div></div>
        </div>
      </div>

      <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '8.5pt', fontFamily: 'Arial, sans-serif', border: '1px solid black', marginBottom: '10px' }}>
        <thead>
          <tr>
            <th rowSpan="2" style={{ border: '1px solid black', padding: '3px', textAlign: 'center', width: '65%' }}>CRITERIA FOR EVALUATION</th>
            <th rowSpan="2" style={{ border: '1px solid black', padding: '3px', textAlign: 'center', width: '10%' }}>RATING</th>
            <th colSpan="2" style={{ border: '1px solid black', padding: '3px', textAlign: 'center', backgroundColor: '#e5e7eb', fontSize: '7.5pt' }}>For OJT Moderator only</th>
          </tr>
          <tr>
            <th style={{ border: '1px solid black', padding: '3px', textAlign: 'center', backgroundColor: '#e5e7eb', fontSize: '7.5pt', fontWeight: 'normal', width: '12.5%' }}>Assigned<br />Weight (%)</th>
            <th style={{ border: '1px solid black', padding: '3px', textAlign: 'center', backgroundColor: '#e5e7eb', fontSize: '7.5pt', fontWeight: 'normal', width: '12.5%' }}>Equivalent</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colSpan="4" style={{ border: '1px solid black', padding: '2px 5px', fontWeight: 'bold', backgroundColor: '#e5e7eb' }}>INSTITUTIONAL</td></tr>
          {FO24_CRITERIA.map((crit, idx) => {
            const splitIdx = crit.label.indexOf('(');
            const title = splitIdx !== -1 ? crit.label.substring(0, splitIdx).trim() : crit.label;
            const desc = splitIdx !== -1 ? crit.label.substring(splitIdx).trim() : '';
            return (
              <tr key={idx}>
                <td style={{ border: '1px solid black', padding: '3px 5px', lineHeight: '1.1' }}>
                  <strong style={{ fontSize: '8.5pt' }}>{title}</strong>
                  {desc && <div style={{ fontSize: '8pt', marginTop: '1px', paddingLeft: '8px' }}>{desc}</div>}
                </td>
                <td style={{ border: '1px solid black', padding: '2px', textAlign: 'center', fontWeight: 'bold' }}>{responses[`c${idx + 1}`] || ''}</td>
                <td style={{ border: '1px solid black', padding: '2px', textAlign: 'center', backgroundColor: '#e5e7eb' }}>{crit.weight}</td>
                <td style={{ border: '1px solid black', padding: '2px', textAlign: 'center', backgroundColor: '#e5e7eb' }}>{responses[`eq${idx + 1}`] || ''}</td>
              </tr>
            )
          })}
          <tr>
            <td colSpan="2" style={{ border: '1px solid black', padding: '3px 15px', textAlign: 'right', fontWeight: 'bold' }}>TOTAL</td>
            <td style={{ border: '1px solid black', padding: '3px', textAlign: 'center', backgroundColor: '#e5e7eb' }}>100</td>
            <td style={{ border: '1px solid black', padding: '3px', textAlign: 'center', backgroundColor: '#e5e7eb' }}>{evalData?.average_score || ''}</td>
          </tr>
        </tbody>
      </table>

      <div style={{ border: '1px solid black', padding: '5px 8px', fontFamily: 'Arial, sans-serif', fontSize: '8.5pt', marginBottom: '8px', minHeight: '40px' }}>
        <strong style={{ display: 'block', marginBottom: '3px' }}>Other comments on work attitudes and behavior:</strong>
        <p style={{ margin: 0, whiteSpace: 'pre-wrap', lineHeight: '1.2' }}>{evalData?.general_comments || ''}</p>
      </div>
      <div style={{ border: '1px solid black', padding: '5px 8px', fontFamily: 'Arial, sans-serif', fontSize: '8.5pt', marginBottom: '12px', minHeight: '40px' }}>
        <strong style={{ display: 'block', marginBottom: '3px' }}>Recommendations for the trainee's further improvement in his/her work performance:</strong>
        <p style={{ margin: 0, whiteSpace: 'pre-wrap', lineHeight: '1.2' }}>{responses.recommendations || ''}</p>
      </div>

      <div style={{ display: 'flex', border: '1px solid black', minHeight: '40px', fontFamily: 'Arial, sans-serif', fontSize: '9pt', marginTop: 'auto' }}>
        <div style={{ width: '50%', borderRight: '1px solid black', padding: '4px 8px', display: 'flex', alignItems: 'flex-end', fontWeight: 'bold' }}>
          Name of Supervisor and Signature:
        </div>
        <div style={{ width: '50%', padding: '4px 8px', display: 'flex', alignItems: 'flex-end', justifyContent: 'center' }}>
          <span style={{ fontWeight: 'bold' }}>{supervisorName}</span>
        </div>
      </div>
    </div>
  );
};

export const PrintFO03 = ({ evalData, internship, tocId }) => {
  const responses = evalData?.responses || {};
  const student = internship?.student?.student_profile || internship?.student?.studentProfile || {};
  const studentName = `${student.last_name || ''}, ${student.first_name || ''}`.trim();
  const program = (typeof student.program === 'string' ? student.program : student.program?.name || student.program?.code) || '';
  const semStr = Number(internship?.semester) === 1 ? '1st' : Number(internship?.semester) === 2 ? '2nd' : Number(internship?.semester) === 3 ? 'Midyear' : '';
  const ayStr = internship?.academic_year || internship?.school_year || '';
  const company = internship?.company || {};
  const hteName = company.company_name || company.name || '';
  const hteAddress = company.address || '';
  const supervisor = internship?.supervisor?.supervisorProfile || {};
  const supervisorName = `${supervisor.last_name || ''}, ${supervisor.first_name || ''}`.trim() || evalData?.evaluator_name || '';
  const supervisorPos = supervisor.position || supervisor.designation || '';

  return (
    <div data-toc-id={tocId} className="a4-page portfolio-document position-relative" style={{ display: 'flex', flexDirection: 'column' }}>
      <div style={{ textAlign: 'right', fontSize: '8.5pt', fontFamily: 'Arial, sans-serif', marginBottom: '8px' }}>
        PNC:PALD-FO-03 rev.1 06202025
      </div>

      <div style={{ position: 'relative', textAlign: 'center', marginBottom: '12px', fontFamily: 'Arial, sans-serif' }}>
        <div style={{ position: 'absolute', top: '50%', left: '0px', transform: 'translateY(-50%)', width: '75px', height: '75px' }}>
          <img src="/images/pnc-logo.png" alt="University of Cabuyao Logo" style={{ width: '100%', height: '100%', objectFit: 'contain' }} />
        </div>
        <div style={{ fontSize: '9.5pt', lineHeight: '1' }}>Republic of the Philippines</div>
        <div style={{ fontSize: '22pt', fontWeight: 'bold', fontFamily: '"Old English Text MT", serif', color: '#004d00', lineHeight: '1', margin: '2px 0' }}>
          University of Cabuyao
        </div>
        <div style={{ fontSize: '11.5pt', fontFamily: 'Arial, sans-serif', margin: '2px 0', lineHeight: '1' }}>(PAMANTASAN NG CABUYAO)</div>
        <div style={{ fontSize: '10.5pt', fontWeight: 'bold', fontStyle: 'italic', margin: '2px 0', lineHeight: '1' }}>Placement, Alumni, & Linkages Department</div>
        <div style={{ fontSize: '8.5pt', lineHeight: '1' }}>Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna, Phillippines 4025</div>
      </div>

      <h3 style={{ textAlign: 'center', fontSize: '12pt', fontWeight: 'bold', margin: '12px 0', fontFamily: 'Arial, sans-serif' }}>
        HTE Evaluation to the<br />University Internship Program
      </h3>

      <div style={{ fontSize: '9pt', fontFamily: 'Arial, sans-serif', lineHeight: '1.4', marginBottom: '12px' }}>
        <div style={{ marginBottom: '4px', fontWeight: 'bold' }}>Company Details:</div>
        <div style={{ display: 'flex', alignItems: 'flex-end', marginLeft: '15px', marginBottom: '4px' }}>
          <span style={{ marginRight: '6px' }}>• Company Name:</span><PrintLine text={internship?.company?.company_name} width="300px" />
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end', marginLeft: '15px', marginBottom: '8px' }}>
          <span style={{ marginRight: '6px' }}>• Department:</span><PrintLine text={internship?.company?.department} width="300px" />
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end', marginBottom: '4px' }}>
          <span style={{ marginRight: '6px' }}>Intern's Name:</span><PrintLine text={studentName} flex="1" />
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end', marginBottom: '4px' }}>
          <span style={{ marginRight: '6px' }}>Program/Section:</span><PrintLine text={(typeof (internship?.student?.student_profile || internship?.student?.studentProfile)?.program === 'string' ? (internship?.student?.student_profile || internship?.student?.studentProfile)?.program : (internship?.student?.student_profile || internship?.student?.studentProfile)?.program?.code || (internship?.student?.student_profile || internship?.student?.studentProfile)?.program?.name) || ''} width="250px" />
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end', marginBottom: '4px' }}>
          <span style={{ marginRight: '6px' }}>Internship Period: From //</span><PrintLine text="" width="90px" />
          <span style={{ margin: '0 6px' }}>to //</span><PrintLine text="" width="90px" />
        </div>
      </div>

      <div style={{ fontWeight: 'bold', fontSize: '9pt', marginBottom: '6px', fontFamily: 'Arial, sans-serif' }}>
        Please rate the school's internship program based on your experience and observation:
      </div>

      <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '8.5pt', fontFamily: 'Arial, sans-serif', border: '1px solid #000', marginBottom: '12px' }}>
        <thead>
          <tr style={{ textAlign: 'left', backgroundColor: '#f3f4f6' }}>
            <th style={{ border: '1px solid #000', padding: '3px 4px' }}>Criteria</th>
            <th style={{ border: '1px solid #000', padding: '3px 4px', width: '55px', fontWeight: 'bold', textAlign: 'center' }}>Excellent<br />(5)</th>
            <th style={{ border: '1px solid #000', padding: '3px 4px', width: '55px', fontWeight: 'bold', textAlign: 'center' }}>Good<br />(4)</th>
            <th style={{ border: '1px solid #000', padding: '3px 4px', width: '75px', fontWeight: 'bold', textAlign: 'center' }}>Satisfactory<br />(3)</th>
            <th style={{ border: '1px solid #000', padding: '3px 4px', width: '90px', fontWeight: 'bold', textAlign: 'center' }}>Needs<br />Improvement (2)</th>
            <th style={{ border: '1px solid #000', padding: '3px 4px', width: '55px', fontWeight: 'bold', textAlign: 'center' }}>Poor (1)</th>
          </tr>
        </thead>
        <tbody>
          {FO03_CRITERIA.map((criteria, i) => {
            const score = parseInt(responses[`crit_${i}`] || responses[`q${i + 1}`] || 0);
            return (
              <tr key={i}>
                <td style={{ border: '1px solid #000', padding: '3px 4px', fontWeight: 'bold', lineHeight: '1.2' }}>{criteria}</td>
                <td style={{ border: '1px solid #000', padding: '2px', textAlign: 'center' }}><Checkbox checked={score === 5} /></td>
                <td style={{ border: '1px solid #000', padding: '2px', textAlign: 'center' }}><Checkbox checked={score === 4} /></td>
                <td style={{ border: '1px solid #000', padding: '2px', textAlign: 'center' }}><Checkbox checked={score === 3} /></td>
                <td style={{ border: '1px solid #000', padding: '2px', textAlign: 'center' }}><Checkbox checked={score === 2} /></td>
                <td style={{ border: '1px solid #000', padding: '2px', textAlign: 'center' }}><Checkbox checked={score === 1} /></td>
              </tr>
            );
          })}
        </tbody>
      </table>

      <div style={{ fontSize: '9pt', fontFamily: 'Arial, sans-serif' }}>
        <div style={{ marginBottom: '10px' }}>
          <div style={{ display: 'flex', fontWeight: 'bold', paddingLeft: '5px', marginBottom: '2px' }}>
            <span style={{ marginRight: '6px' }}>1.</span><span>Observations on the School's Internship Program: (Describe any positive aspects and challenges...)</span>
          </div>
          <div style={{ paddingLeft: '15px' }}><MultilinePreview text={responses.observations} lines={2} /></div>
        </div>

        <div style={{ marginBottom: '10px' }}>
          <div style={{ display: 'flex', fontWeight: 'bold', paddingLeft: '5px', marginBottom: '2px' }}>
            <span style={{ marginRight: '6px' }}>2.</span><span>Suggestions for Improvement: (Provide recommendations on how the school can improve...)</span>
          </div>
          <div style={{ paddingLeft: '15px' }}><MultilinePreview text={responses.suggestions} lines={2} /></div>
        </div>

        <div style={{ marginBottom: '10px' }}>
          <div style={{ display: 'flex', fontWeight: 'bold', paddingLeft: '5px' }}>
            <span style={{ marginRight: '6px' }}>3.</span><span>Impact of the Internship Program on the Company</span>
          </div>
          <div style={{ display: 'flex', fontWeight: 'bold', paddingLeft: '20px', marginBottom: '2px' }}>
            <span style={{ marginRight: '6px' }}>•</span><span>Did the interns contribute positively to the company's operations? (Explain how.)</span>
          </div>
          <div style={{ paddingLeft: '25px' }}><MultilinePreview text={responses.impact} lines={2} /></div>
        </div>

        <div style={{ marginBottom: '10px' }}>
          <div style={{ display: 'flex', fontWeight: 'bold', paddingLeft: '5px', marginBottom: '2px' }}>
            <span style={{ marginRight: '6px' }}>4.</span><span>Would the company consider hiring any of the interns in the future?</span>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px', fontWeight: 'bold', paddingLeft: '20px', marginBottom: '2px' }}>
            <label style={{ display: 'flex', alignItems: 'center', gap: '4px' }}><Checkbox checked={responses.would_hire === 'yes'} /> Yes</label>
            <label style={{ display: 'flex', alignItems: 'center', gap: '4px' }}><Checkbox checked={responses.would_hire === 'no'} /> No</label>
            <span>(Provide reasons)</span>
          </div>
          <div style={{ paddingLeft: '20px' }}><MultilinePreview text={responses.hire_reasons} lines={2} /></div>
        </div>

        <div style={{ marginBottom: '15px' }}>
          <div style={{ display: 'flex', fontWeight: 'bold', paddingLeft: '5px' }}>
            <span style={{ marginRight: '6px' }}>5.</span>
            <span style={{ lineHeight: '1.2' }}>
              Final Recommendation: Would you recommend continuing the partnership with this school for future internship programs?
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: '10px', marginLeft: '10px' }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: '4px' }}><Checkbox checked={responses.recommend_partner === 'yes'} /> Yes</label>
                <label style={{ display: 'flex', alignItems: 'center', gap: '4px' }}><Checkbox checked={responses.recommend_partner === 'no'} /> No</label>
              </span>
            </span>
          </div>
        </div>
      </div>

      <div style={{ fontSize: '9pt', fontFamily: 'Arial, sans-serif', fontWeight: 'bold', marginTop: 'auto' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', marginBottom: '4px' }}>
          <span style={{ marginRight: '6px', width: '120px' }}>Evaluator's Name:</span><PrintLine text={evalData?.signer_name} width="220px" />
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end', marginBottom: '4px' }}>
          <span style={{ marginRight: '6px', width: '120px' }}>Signature:</span>
          {evalData?.signature_url || evalData?.signature_path ? (
            <div style={{ width: '220px', borderBottom: '1px solid #000', margin: '0 4px', paddingBottom: '2px' }}>
              <img src={evalData.signature_url || `http://localhost:8000/storage/${evalData.signature_path}`} alt="Signature" style={{ height: '30px', display: 'block' }} />
            </div>
          ) : <PrintLine text="" width="220px" />}
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end', marginBottom: '10px' }}>
          <span style={{ marginRight: '6px', width: '120px' }}>Date:</span>
          <PrintLine text={evalData?.signed_at ? new Date(evalData.signed_at).toLocaleDateString('en-PH') : ''} width="220px" />
        </div>
        <div style={{ fontSize: '8pt', lineHeight: '1.2', textAlign: 'justify', paddingRight: '10px' }}>
          This report serves as valuable feedback for improving the collaboration between the school and the company, ensuring a more effective internship program for future students. Thank you.
        </div>
      </div>

      <div style={{ textAlign: 'center', marginTop: '10px' }}>
        <img src="/images/dangal.png" alt="Dangal ng Bayan" style={{ height: '35px', objectFit: 'contain' }} />
      </div>
    </div>
  );
};

export const PrintFO22 = ({ evalData, internship, tocId }) => {
  const responses = evalData?.responses || {};
  const student = internship?.student?.student_profile || internship?.student?.studentProfile || {};
  const studentName = `${student.last_name || ''}, ${student.first_name || ''}`.trim();
  const program = (typeof student.program === 'string' ? student.program : student.program?.name || student.program?.code) || '';
  const semStr = Number(internship?.semester) === 1 ? '1st' : Number(internship?.semester) === 2 ? '2nd' : Number(internship?.semester) === 3 ? 'Midyear' : '';
  const ayStr = internship?.academic_year || internship?.school_year || '';
  const company = internship?.company || {};
  const hteName = company.company_name || company.name || '';
  const hteAddress = company.address || '';
  const supervisor = internship?.supervisor?.supervisorProfile || {};
  const supervisorName = `${supervisor.last_name || ''}, ${supervisor.first_name || ''}`.trim() || evalData?.supervisor_name || '';
  const supervisorPos = supervisor.position || supervisor.designation || '';

  return (
    <div data-toc-id={tocId} className="a4-page portfolio-document position-relative" style={{ display: 'flex', flexDirection: 'column' }}>
      <div style={{ textAlign: 'right', fontSize: '8.5pt', fontFamily: 'Arial, sans-serif', marginBottom: '8px' }}>
        PNC:AA-FO-22 rev.0 02012023
      </div>

      <div style={{ position: 'relative', textAlign: 'center', marginBottom: '12px', fontFamily: 'Arial, sans-serif' }}>
        <div style={{ position: 'absolute', top: '50%', transform: 'translateY(-50%)', left: '0', width: '75px', height: '75px' }}>
          <img src="/images/pnc-logo.png" alt="University of Cabuyao Logo" style={{ width: '100%', height: '100%', objectFit: 'contain' }} />
        </div>
        <div style={{ fontSize: '9.5pt', lineHeight: '1' }}>Republic of the Philippines</div>
        <div style={{ fontSize: '22pt', fontWeight: 'bold', fontFamily: '"Old English Text MT", serif', color: '#004d00', lineHeight: '1', margin: '2px 0' }}>Pamantasan ng Cabuyao</div>
        <div style={{ fontSize: '11.5pt', fontFamily: 'Arial, sans-serif', margin: '2px 0', lineHeight: '1' }}>(UNIVERSITY OF CABUYAO)</div>
        <div style={{ fontSize: '10.5pt', fontWeight: 'bold', fontStyle: 'italic', margin: '2px 0', lineHeight: '1' }}>Academic Affairs Division</div>
        <div style={{ fontSize: '8.5pt', lineHeight: '1' }}>Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna 4025</div>
      </div>

      <div style={{ backgroundColor: '#9499a3', padding: '3px 0', textAlign: 'center', fontWeight: 'bold', fontSize: '10.5pt', marginTop: '4px', fontFamily: 'Arial, sans-serif' }}>
        INTERNSHIP HOST TRAINING ESTABLISHMENT EVALUATION FORM
      </div>

      <div style={{ textAlign: 'center', fontSize: '9.5pt', margin: '6px 0 15px 0', fontFamily: 'Arial, sans-serif' }}>
        <span style={{ borderBottom: '1px solid black', minWidth: '70px', display: 'inline-block', textAlign: 'center' }}>{semStr}</span> Semester/Midyear
        <span style={{ borderBottom: '1px solid black', minWidth: '50px', display: 'inline-block', textAlign: 'center', margin: '0 5px' }}></span> / Academic Year
        <span style={{ borderBottom: '1px solid black', minWidth: '100px', display: 'inline-block', textAlign: 'center', marginLeft: '5px' }}>{ayStr}</span>
      </div>

      <div style={{ fontFamily: 'Arial, sans-serif', fontSize: '9pt', marginBottom: '12px', display: 'flex', flexDirection: 'column', gap: '5px' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end' }}>
          <span style={{ fontWeight: 'bold', marginRight: '6px' }}>Student Name:</span>
          <div style={{ flex: 1, borderBottom: '1px solid black', minHeight: '1.2em', paddingLeft: '5px' }}>{studentName}</div>
          <span style={{ fontWeight: 'bold', marginLeft: '10px', marginRight: '6px' }}>Program:</span>
          <div style={{ flex: 0.8, borderBottom: '1px solid black', minHeight: '1.2em', paddingLeft: '5px' }}>{program}</div>
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end' }}>
          <span style={{ fontWeight: 'bold', marginRight: '6px' }}>Host Training Establishment (HTE):</span>
          <div style={{ flex: 1, borderBottom: '1px solid black', minHeight: '1.2em', paddingLeft: '5px' }}>{hteName}</div>
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end' }}>
          <span style={{ fontWeight: 'bold', marginRight: '6px' }}>Company Address:</span>
          <div style={{ flex: 1, borderBottom: '1px solid black', minHeight: '1.2em', paddingLeft: '5px' }}>{hteAddress}</div>
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end' }}>
          <span style={{ fontWeight: 'bold', marginRight: '6px' }}>Internship Company Supervisor:</span>
          <div style={{ flex: 1, borderBottom: '1px solid black', minHeight: '1.2em', paddingLeft: '5px' }}>{supervisorName}</div>
          <span style={{ fontWeight: 'bold', marginLeft: '10px', marginRight: '6px' }}>Position:</span>
          <div style={{ flex: 0.8, borderBottom: '1px solid black', minHeight: '1.2em', paddingLeft: '5px' }}>{supervisorPos}</div>
        </div>
      </div>

      <div style={{ fontSize: '9pt', fontFamily: 'Arial, sans-serif', marginBottom: '10px' }}>
        <p style={{ margin: 0, fontStyle: 'italic', textAlign: 'justify', lineHeight: '1.2', textIndent: '0' }}>
          Please evaluate your internship host-company to help gauge the appropriateness of the company training/practicum
          to the curricular program, using the following scale. Be assured that any information written on this evaluation form will
          be treated with utmost confidentiality:
        </p>
        <div style={{ display: 'flex', justifyContent: 'space-between', fontWeight: 'bold', padding: '8px 15px 4px 15px' }}>
          <span>5=Outstanding</span><span>4=Very Satisfactory</span><span>3=Satisfactory</span><span>2=Unsatisfactory</span><span>1=Poor</span>
        </div>
      </div>

      <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '9pt', fontFamily: 'Arial, sans-serif', border: '1px solid black', marginBottom: '10px' }}>
        <thead>
          <tr style={{ backgroundColor: '#e5e7eb', textAlign: 'center' }}>
            <th style={{ border: '1px solid black', padding: '4px 6px', width: '55%' }}>CRITERIA</th>
            <th style={{ border: '1px solid black', padding: '4px 6px', width: '12%' }}>RATING</th>
            <th style={{ border: '1px solid black', padding: '4px 6px', width: '33%' }}>COMMENTS/SUGGESTIONS</th>
          </tr>
        </thead>
        <tbody>
          {FO22_CRITERIA.map((crit, idx) => (
            <tr key={idx}>
              <td style={{ border: '1px solid black', padding: '3px 6px', lineHeight: '1.1' }}>{crit}</td>
              <td style={{ border: '1px solid black', padding: '3px 4px', textAlign: 'center', fontWeight: 'bold' }}>{responses[`q${idx + 1}`] || ''}</td>
              <td style={{ border: '1px solid black', padding: '3px 6px' }}>{responses[`q${idx + 1}_comment`] || ''}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <table style={{ width: '100%', fontSize: '8.5pt', fontFamily: 'Arial, sans-serif', borderCollapse: 'collapse', marginBottom: '10px' }}>
        <tbody>
          <tr>
            <td style={{ border: '1px solid black', padding: '3px 6px', width: '15%' }}>Rating:</td>
            <td style={{ border: '1px solid black', padding: '3px 6px', width: '35%', textAlign: 'center', fontWeight: 'bold' }}>{evalData?.average_score || ''}</td>
            <td style={{ border: '1px solid black', padding: '3px 6px', width: '15%' }}>Interpretation:</td>
            <td style={{ border: '1px solid black', padding: '3px 6px', width: '35%', textAlign: 'center' }}></td>
          </tr>
          <tr>
            <td colSpan="4" style={{ border: '1px solid black', padding: '3px 6px' }}>
              <div style={{ marginBottom: '2px' }}>Guide to Interpretation:</div>
              <div style={{ display: 'flex', gap: '30px' }}>
                <div><div>4.50 – 5.00 Outstanding</div><div>2.00 – 2.74 Unsatisfactory</div></div>
                <div><div>3.50 – 4.49 Very Satisfactory</div><div>1.00 – 1.99 Poor</div></div>
                <div><div>2.75 – 3.49 Satisfactory</div></div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <table style={{ width: '100%', fontSize: '8.5pt', fontFamily: 'Arial, sans-serif', borderCollapse: 'collapse', marginBottom: '10px' }}>
        <tbody>
          <tr>
            <td style={{ border: '1px solid black', padding: '4px 6px', width: '45%', verticalAlign: 'top', lineHeight: '1.2' }}>
              Please list new training activities related to your program which you have experienced in the training/practicum but were not specified in the Student Training Plan:
            </td>
            <td style={{ border: '1px solid black', padding: '4px 6px', width: '55%', verticalAlign: 'top' }}>
              <div style={{ minHeight: '35px', whiteSpace: 'pre-wrap' }}>{responses.new_activities || ''}</div>
            </td>
          </tr>
          <tr>
            <td style={{ border: '1px solid black', padding: '4px 6px', verticalAlign: 'top', lineHeight: '1.2' }}>
              Will you recommend your OJT host company to future OJT students?
            </td>
            <td style={{ border: '1px solid black', padding: '4px 6px', verticalAlign: 'top' }}>
              <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: '3px' }}><Checkbox checked={responses.recommend === 'yes'} /> Yes</label>
                <label style={{ display: 'flex', alignItems: 'center', gap: '3px' }}><Checkbox checked={responses.recommend === 'no'} /> No</label>
                <span style={{ marginLeft: '6px' }}>Why?</span>
              </div>
              <div style={{ minHeight: '25px', whiteSpace: 'pre-wrap', marginTop: '2px' }}>{responses.recommend_reason || ''}</div>
            </td>
          </tr>
          <tr>
            <td style={{ border: '1px solid black', padding: '4px 6px', verticalAlign: 'top', lineHeight: '1.2' }}>
              What specific curricular programs of students do you think will best fit with the training being provided by your host company?
            </td>
            <td style={{ border: '1px solid black', padding: '4px 6px', verticalAlign: 'top' }}>
              <div style={{ minHeight: '35px', whiteSpace: 'pre-wrap' }}>{responses.fit_programs || ''}</div>
            </td>
          </tr>
        </tbody>
      </table>

      <div style={{ display: 'flex', alignItems: 'flex-start', fontSize: '8pt', fontFamily: 'Arial, sans-serif', marginBottom: '10px', textAlign: 'justify', lineHeight: '1.2' }}>
        <div style={{ marginRight: '6px', marginTop: '1px' }}><Checkbox checked={true} /></div>
        <div>
          I agree to the collection and processing of my data for the purpose of processing the evaluation of host training
          establishment. I understand that my personal information is protected by RA 10173, Data Privacy Act of 2012, and that I
          am required to provide truthful information
        </div>
      </div>
      <div style={{ textAlign: 'center', marginTop: 'auto', paddingBottom: '10px' }}>
        <img src="/images/dangal.png" alt="Dangal ng Bayan" style={{ height: '35px', objectFit: 'contain' }} />
      </div>
    </div>
  );
};

export const PrintFO23 = ({ evalData, internship, tocId }) => {
  const responses = evalData?.responses || {};
  const student = internship?.student?.student_profile || internship?.student?.studentProfile || {};
  const studentName = `${student.last_name || ''}, ${student.first_name || ''}`.trim();
  const program = (typeof student.program === 'string' ? student.program : student.program?.name || student.program?.code) || '';
  const semStr = Number(internship?.semester) === 1 ? '1st' : Number(internship?.semester) === 2 ? '2nd' : Number(internship?.semester) === 3 ? 'Midyear' : '';
  const ayStr = internship?.academic_year || internship?.school_year || '';
  const faculty = internship?.faculty?.facultyProfile || internship?.faculty?.faculty_profile || {};
  const facultyName = `${faculty.last_name || ''}, ${faculty.first_name || ''}`.trim() || internship?.faculty?.name || '';
  let globalIndex = 1;

  return (
    <div data-toc-id={tocId} className="a4-page portfolio-document position-relative" style={{ display: 'flex', flexDirection: 'column' }}>
      <div style={{ textAlign: 'right', fontSize: '8.5pt', fontFamily: 'Arial, sans-serif', marginBottom: '8px' }}>
        PNC:AA-FO-23 rev.0 02012023
      </div>
      <div style={{ position: 'relative', textAlign: 'center', marginBottom: '12px', fontFamily: 'Arial, sans-serif' }}>
        <div style={{ position: 'absolute', top: '50%', transform: 'translateY(-60%)', width: '75px', height: '75px', left: '0' }}>
          <img src="/images/pnc-logo.png" alt="University of Cabuyao Logo" style={{ objectFit: 'contain', width: '100%', height: '100%' }} />
        </div>
        <div style={{ fontSize: '9.5pt', lineHeight: '1' }}>Republic of the Philippines</div>
        <div style={{ fontSize: '22pt', fontWeight: 'bold', fontFamily: '"Old English Text MT", serif', color: '#004d00', lineHeight: '1', margin: '2px 0' }}>University of Cabuyao</div>
        <div style={{ fontSize: '11.5pt', fontFamily: 'Arial, sans-serif', lineHeight: '1.1' }}>(PAMANTASAN NG CABUYAO)</div>
        <div style={{ fontSize: '10.5pt', fontWeight: 'bold', fontStyle: 'italic', lineHeight: '1.1' }}>Academic Affairs Division</div>
        <div style={{ fontSize: '8.5pt' }}>Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna, Phillippines 4025</div>
      </div>

      <div style={{ backgroundColor: '#9499a3', padding: '3px 0', textAlign: 'center', fontWeight: 'bold', fontSize: '10.5pt', marginTop: '4px', fontFamily: 'Arial, sans-serif' }}>
        INTERNSHIP PROGRAM EVALUATION FORM
      </div>

      <div style={{ textAlign: 'center', fontSize: '9.5pt', margin: '6px 0 15px 0', fontFamily: 'Arial, sans-serif' }}>
        <span style={{ borderBottom: '1px solid black', minWidth: '70px', display: 'inline-block', textAlign: 'center' }}>{semStr}</span> Semester/Midyear
        <span style={{ borderBottom: '1px solid black', minWidth: '40px', display: 'inline-block', textAlign: 'center', margin: '0 5px' }}></span> / Academic Year
        <span style={{ borderBottom: '1px solid black', minWidth: '100px', display: 'inline-block', textAlign: 'center', marginLeft: '5px' }}>{ayStr}</span>
      </div>

      <div style={{ fontFamily: 'Arial, sans-serif', fontSize: '9.5pt', marginBottom: '15px', display: 'flex', flexDirection: 'column', gap: '6px', lineHeight: '1.1' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end' }}>
          <span style={{ marginRight: '6px' }}>Student Name:</span>
          <div style={{ flex: 1, borderBottom: '1px solid black', minHeight: '1.1em', paddingLeft: '5px' }}>{studentName}</div>
          <span style={{ marginLeft: '10px', marginRight: '6px' }}>Program:</span>
          <div style={{ flex: 1, borderBottom: '1px solid black', minHeight: '1.1em', paddingLeft: '5px' }}>{program}</div>
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end' }}>
          <span style={{ marginRight: '8px' }}>Internship Teaching Personnel:</span>
          <div style={{ flex: 1, borderBottom: '1px solid black', minHeight: '1.1em', paddingLeft: '5px' }}>{facultyName}</div>
        </div>
      </div>

      <div style={{ fontSize: '9.5pt', fontFamily: 'Arial, sans-serif', marginBottom: '12px' }}>
        <p style={{ margin: '0 0 8px 0', textIndent: '0', lineHeight: '1.2' }}>
          <strong>Directions:</strong> Based on your experience, please rate the <strong>Internship Program</strong> using the following scale:
        </p>
        <div style={{ display: 'flex', justifyContent: 'space-between', fontWeight: 'bold', padding: '0 5px', fontSize: '9pt' }}>
          <span>5=Outstanding</span><span>4=Very Satisfactory</span><span>3=Satisfactory</span><span>2=Unsatisfactory</span><span>1=Poor</span>
        </div>
      </div>

      <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '9pt', fontFamily: 'Arial, sans-serif', marginBottom: '12px' }}>
        <thead>
          <tr style={{ backgroundColor: '#d1d5db', textAlign: 'center', fontStyle: 'italic' }}>
            <th style={{ border: '1px solid black', padding: '4px', width: '45%' }}>Criteria</th>
            <th style={{ border: '1px solid black', padding: '4px', width: '15%' }}>Rating</th>
            <th style={{ border: '1px solid black', padding: '4px', width: '40%' }}>Comments/Suggestions</th>
          </tr>
        </thead>
        <tbody>
          {FO23_SECTIONS.map((sec, sIdx) => (
            <React.Fragment key={sIdx}>
              <tr>
                <td colSpan="3" style={{ border: 'none', padding: '6px 0 2px 0', fontWeight: 'bold', fontStyle: 'italic', fontSize: '9pt' }}>{sec.title}</td>
              </tr>
              {sec.items.map((item) => {
                const qId = `q${globalIndex++}`;
                return (
                  <tr key={qId}>
                    <td style={{ border: '1px solid black', padding: '3px 4px', lineHeight: '1.1' }}>{item}</td>
                    <td style={{ border: '1px solid black', padding: '3px', textAlign: 'center', fontWeight: 'bold' }}>{responses[qId] || ''}</td>
                    <td style={{ border: '1px solid black', padding: '3px' }}>{responses[`${qId}_comment`] || ''}</td>
                  </tr>
                );
              })}
            </React.Fragment>
          ))}
        </tbody>
      </table>

      <div style={{ fontSize: '9.5pt', fontFamily: 'Arial, sans-serif', marginBottom: '12px' }}>
        <strong style={{ display: 'block', marginBottom: '6px' }}>Other comments and suggestions:</strong>
        {evalData?.general_comments ? (
          <div style={{ whiteSpace: 'pre-wrap', borderBottom: '1px solid black', paddingBottom: '3px', minHeight: '35px' }}>
            {evalData.general_comments}
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-evenly', minHeight: '35px', marginTop: '2px' }}>
            <div style={{ borderBottom: '1px solid black', width: '100%' }}></div>
            <div style={{ borderBottom: '1px solid black', width: '100%' }}></div>
          </div>
        )}
      </div>

      <div style={{ display: 'flex', alignItems: 'flex-start', fontSize: '8pt', fontFamily: 'Arial, sans-serif', marginBottom: '10px', textAlign: 'justify', lineHeight: '1.2' }}>
        <div style={{ marginRight: '6px', marginTop: '1px' }}><Checkbox checked={true} /></div>
        <div>
          I agree to the collection and processing of my data for the purpose of processing the evaluation of University's
          Internship Program. I understand that my personal information is protected by RA 10173, Data Privacy Act of 2012,
          and that I am required to provide truthful information
        </div>
      </div>

      <div style={{ textAlign: 'center', marginTop: 'auto', paddingBottom: '10px' }}>
        <img src="/images/dangal.png" alt="Dangal ng Bayan" style={{ height: '35px', objectFit: 'contain' }} />
      </div>
    </div>
  );
};

export const PrintFacultyEval = ({ evalData, internship, tocId }) => {
  return (
    <div data-toc-id={tocId} className="a4-page portfolio-document position-relative" style={{ display: 'flex', flexDirection: 'column' }}>
      <div style={{ textAlign: 'center', marginBottom: '15px' }}>
        <h4 style={{ margin: 0, fontWeight: 'bold', fontSize: '15pt' }}>FACULTY EVALUATION</h4>
        <p style={{ margin: 0, color: '#666', fontSize: '10.5pt' }}>Performance Evaluation</p>
      </div>
      <div style={{ marginBottom: '15px', fontSize: '10.5pt' }}>
        <strong>Student:</strong> {(internship?.student?.student_profile || internship?.student?.studentProfile)?.first_name} {(internship?.student?.student_profile || internship?.student?.studentProfile)?.last_name}
      </div>
      <table style={{ width: '100%', borderCollapse: 'collapse', marginBottom: '15px', fontSize: '9.5pt' }}>
        <thead>
          <tr style={{ backgroundColor: '#f3f4f6' }}>
            <th style={{ border: '1px solid #ccc', padding: '6px' }}>Criteria</th>
            <th style={{ border: '1px solid #ccc', padding: '6px', width: '110px', textAlign: 'center' }}>Rating</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style={{ border: '1px solid #ccc', padding: '6px' }}>Overall Performance</td>
            <td style={{ border: '1px solid #ccc', padding: '6px', textAlign: 'center', fontWeight: 'bold' }}>{evalData?.average_score}</td>
          </tr>
        </tbody>
      </table>
      <div style={{ marginBottom: '30px', fontSize: '9.5pt' }}>
        <strong>General Comments:</strong>
        <p style={{ borderBottom: '1px solid #ccc', minHeight: '50px', marginTop: '6px' }}>{evalData?.general_comments || 'None'}</p>
      </div>
      {evalData?.signature_path && (
        <div style={{ marginTop: 'auto', width: '220px', marginBottom: '15px' }}>
          <div style={{ borderBottom: '1px solid #000', textAlign: 'center', paddingBottom: '4px' }}>
            <img src={`http://localhost:8000/storage/${evalData.signature_path}`} alt="Signature" style={{ height: '45px' }} />
          </div>
          <div style={{ textAlign: 'center', paddingTop: '4px', fontSize: '9pt' }}>
            <strong>{evalData.signer_name}</strong><br />Evaluating Faculty
          </div>
        </div>
      )}
    </div>
  );
};