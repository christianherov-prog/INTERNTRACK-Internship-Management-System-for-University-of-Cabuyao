import React, { useState } from 'react';

export const StudentInternPerformanceForm = ({ internship, onSubmit, processing }) => {
  const [responses, setResponses] = useState({});
  const [generalComments, setGeneralComments] = useState('');
  const [recommendations, setRecommendations] = useState('');
  const [period, setPeriod] = useState('1st');

  const criteriaList = [
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

  const handleRatingChange = (key, value) => {
    setResponses(prev => ({ ...prev, [key]: value }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (processing) return;

    // Ensure all criteria are rated
    const allFilled = criteriaList.every((_, i) => responses[`c${i + 1}`]);
    if (!allFilled) {
      alert('Please rate all criteria.');
      return;
    }

    onSubmit({
      evaluation_period: period, // Can be midterm, final etc. mapped to 1st, 2nd, midyear
      form_type: 'FO-24',
      responses,
      general_comments: generalComments,
      recommendations // Storing recommendations in backend if needed or add to general comments
    });
  };

  const student = internship?.student?.student_profile || internship?.student?.studentProfile || {};
  const studentName = `${student.last_name || ''}, ${student.first_name || ''}`.trim() || 'Unavailable';
  const program = (typeof student.program === 'string' ? student.program : student.program?.name || student.program?.code) || 'Unavailable';
  const semStr = internship?.semester === 1 ? '1st Semester' : internship?.semester === 2 ? '2nd Semester' : internship?.semester === 3 ? 'Midyear' : 'Unavailable';
  const ayStr = internship?.academic_year || internship?.school_year || 'Unavailable';
  const company = internship?.company || {};
  const hteName = company.company_name || company.name || 'Unavailable';
  const supervisor = internship?.supervisor?.supervisorProfile || {};
  const supervisorName = `${supervisor.last_name || ''}, ${supervisor.first_name || ''}`.trim() || 'Unavailable';

  return (
    <form onSubmit={handleSubmit} className="card shadow-sm mb-4 border-0">
      <div className="card-body p-4">
        <h3 className="card-title text-center fw-bold">STUDENT INTERN PERFORMANCE EVALUATION FORM</h3>
        <p className="text-center text-muted mb-4" style={{ fontSize: '0.9rem' }}>(PNC:AA-FO-24)</p>

        <div className="row g-3 mb-4">
          <div className="col-md-6">
            <input type="text" className="form-control" placeholder="Semester/Midyear" value={semStr} readOnly />
          </div>
          <div className="col-md-6">
            <input type="text" className="form-control" placeholder="Academic Year" value={ayStr} readOnly />
          </div>
          <div className="col-md-6">
            <input type="text" className="form-control" placeholder="Student Name" value={studentName} readOnly />
          </div>
          <div className="col-md-6">
            <input type="text" className="form-control" placeholder="Program" value={program} readOnly />
          </div>
          <div className="col-md-12">
            <input type="text" className="form-control" placeholder="Host Training Establishment (HTE)" value={hteName} readOnly />
          </div>
          <div className="col-md-12">
            <input type="text" className="form-control" placeholder="Evaluator/Supervisor Name" value={supervisorName} readOnly />
          </div>
        </div>

        <div className="alert alert-info py-2 mb-4">
          <p className="fw-bold mb-1">Grading Scale (65-100):</p>
          <div className="row text-sm">
            <div className="col-md-4">96%-100% - Excellent</div>
            <div className="col-md-4">90%-95% - Very Good</div>
            <div className="col-md-4">85%-89% - Good</div>
            <div className="col-md-4">80%-84% - Fair</div>
            <div className="col-md-4">75%-79% - Passed</div>
            <div className="col-md-4">below 75% - Failed</div>
          </div>
        </div>

        <div className="table-responsive mb-4">
          <table className="table table-bordered align-middle">
            <thead className="table-light text-center">
              <tr>
                <th className="text-start">CRITERIA FOR EVALUATION</th>
                <th style={{ width: '150px' }}>Assigned Weight (%)</th>
                <th style={{ width: '150px' }}>RATING (65-100)</th>
              </tr>
            </thead>
            <tbody>
              {criteriaList.map((item, idx) => (
                <tr key={idx}>
                  <td className="text-start">{item.label}</td>
                  <td className="text-center fw-bold">{item.weight}</td>
                  <td>
                    <input
                      type="number"
                      min="65"
                      max="100"
                      className="form-control text-center"
                      value={responses[`c${idx + 1}`] || ''}
                      onChange={(e) => {
                        let val = e.target.value;
                        if (val !== '') {
                          let num = parseInt(val, 10);
                          if (num > 100) val = '100';
                        }
                        handleRatingChange(`c${idx + 1}`, val);
                      }}
                      onBlur={(e) => {
                        let val = e.target.value;
                        if (val !== '') {
                          let num = parseInt(val, 10);
                          if (num < 65) val = '65';
                          handleRatingChange(`c${idx + 1}`, val);
                        }
                      }}
                      required
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="mb-3">
          <label className="fw-bold form-label">Other comments on work attitudes and behavior:</label>
          <textarea className="form-control" rows="3" value={generalComments} onChange={(e) => setGeneralComments(e.target.value)}></textarea>
        </div>

        <div className="mb-4">
          <label className="fw-bold form-label">Recommendations for the trainee's further improvement in his/her work performance:</label>
          <textarea className="form-control" rows="3" value={recommendations} onChange={(e) => setRecommendations(e.target.value)}></textarea>
        </div>

        <div className="d-flex justify-content-end mt-4">
          <button type="submit" className="btn btn-primary px-4 py-2 fw-semibold" disabled={processing}>
            {processing ? (
              <><i className="fa fa-spinner fa-spin me-2"></i> Submitting...</>
            ) : (
              <><i className="fa fa-paper-plane me-2"></i> Submit Evaluation</>
            )}
          </button>
        </div>
      </div>
    </form>
  );
};
