import React, { useState } from 'react';

export const HostTrainingEstEvaluationForm = ({ internship, onSubmit, processing }) => {
  const [responses, setResponses] = useState({});
  const [generalComments, setGeneralComments] = useState('');

  const criteriaList = [
    "1. Training given by the Host Training Establishment (HTE) was course-related.",
    "2. Internship Supervisors were able to impart additional knowledge and skills related to the course of the student.",
    "3. The working environment was conducive to training and learning.",
    "4. The Host Training Establishment (HTE) provided complete facilities needed for the training of students.",
    "5. Training areas/activities specified in the Student Training Plan were successfully carried out.",
    "6. Interpersonal working relationship with employees of the company was positively maintained.",
    "7. Safety of the trainees in the workplace was ensured by the company, if applicable"
  ];

  const handleRatingChange = (key, value) => {
    setResponses(prev => ({ ...prev, [key]: value }));
  };

  const handleTextChange = (key, value) => {
    setResponses(prev => ({ ...prev, [key]: value }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (processing) return;

    // Ensure all criteria are rated
    const allFilled = criteriaList.every((_, i) => responses[`q${i + 1}`]);
    if (!allFilled) {
      alert('Please rate all criteria.');
      return;
    }

    onSubmit({
      evaluation_period: 'final', // Student evals are typically submitted at the end
      form_type: 'FO-22',
      responses,
      general_comments: generalComments
    });
  };

  const student = internship?.student?.student_profile || internship?.student?.studentProfile || {};
  const studentName = `${student.last_name || ''}, ${student.first_name || ''}`.trim() || 'Unavailable';
  const program = (typeof student.program === 'string' ? student.program : student.program?.name || student.program?.code) || 'Unavailable';
  const semStr = internship?.semester === 1 ? '1st Semester' : internship?.semester === 2 ? '2nd Semester' : internship?.semester === 3 ? 'Midyear' : 'Unavailable';
  const company = internship?.company || {};
  const hteName = company.company_name || company.name || 'Unavailable';
  const hteAddress = company.address || 'Unavailable';
  const supervisor = internship?.supervisor?.supervisorProfile || {};
  const supervisorName = `${supervisor.last_name || ''}, ${supervisor.first_name || ''}`.trim() || 'Unavailable';
  const supervisorPos = supervisor.position || supervisor.designation || 'Unavailable';

  return (
    <form onSubmit={handleSubmit} className="card shadow-sm mb-4 border-0">
      <div className="card-body p-4">
        <h3 className="card-title text-center mb-4 fw-bold">INTERNSHIP HOST TRAINING ESTABLISHMENT EVALUATION FORM</h3>

        <div className="row g-3 mb-4">
          <div className="col-md-12">
            <input type="text" className="form-control" placeholder="Semester" value={semStr} readOnly />
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
            <input type="text" className="form-control" placeholder="Company Address" value={hteAddress} readOnly />
          </div>
          <div className="col-md-6">
            <input type="text" className="form-control" placeholder="Internship Company Supervisor" value={supervisorName} readOnly />
          </div>
          <div className="col-md-6">
            <input type="text" className="form-control" placeholder="Position" value={supervisorPos} readOnly />
          </div>
        </div>

        <div className="alert alert-info text-center py-2 mb-4">
          <span className="fw-bold">Rating Scale:</span> 5=Outstanding | 4=Very Satisfactory | 3=Satisfactory | 2=Unsatisfactory | 1=Poor
        </div>

        <div className="table-responsive mb-4">
          <table className="table table-bordered align-middle">
            <thead className="table-light text-center">
              <tr>
                <th className="text-start">CRITERIA</th>
                <th style={{ width: '120px' }}>RATING</th>
                <th>COMMENTS/SUGGESTIONS</th>
              </tr>
            </thead>
            <tbody>
              {criteriaList.map((criteria, idx) => (
                <tr key={idx}>
                  <td className="text-start">{criteria}</td>
                  <td>
                    <select
                      className="form-select text-center"
                      value={responses[`q${idx + 1}`] || ''}
                      onChange={(e) => handleRatingChange(`q${idx + 1}`, e.target.value)}
                      required
                    >
                      <option value="" disabled>--</option>
                      <option value="5">5</option>
                      <option value="4">4</option>
                      <option value="3">3</option>
                      <option value="2">2</option>
                      <option value="1">1</option>
                    </select>
                  </td>
                  <td>
                    <input
                      type="text"
                      className="form-control"
                      value={responses[`q${idx + 1}_comment`] || ''}
                      onChange={(e) => handleTextChange(`q${idx + 1}_comment`, e.target.value)}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="mb-3">
          <label className="fw-bold form-label">Please list new training activities related to your program which you have experienced in the training/practicum but were not specified in the Student Training Plan:</label>
          <textarea className="form-control" rows="3" value={responses['new_activities'] || ''} onChange={(e) => handleTextChange('new_activities', e.target.value)}></textarea>
        </div>

        <div className="mb-3">
          <label className="fw-bold form-label d-block">Will you recommend your OJT host company to future OJT students?</label>
          <div className="d-flex align-items-center gap-3">
            <div className="form-check">
              <input type="radio" className="form-check-input" name="recommend_host" value="yes" onChange={(e) => handleRatingChange('recommend', e.target.value)} required />
              <label className="form-check-label">Yes</label>
            </div>
            <div className="form-check">
              <input type="radio" className="form-check-input" name="recommend_host" value="no" onChange={(e) => handleRatingChange('recommend', e.target.value)} />
              <label className="form-check-label">No</label>
            </div>
            <input type="text" className="form-control flex-grow-1" placeholder="Why?" value={responses['recommend_reason'] || ''} onChange={(e) => handleTextChange('recommend_reason', e.target.value)} />
          </div>
        </div>

        <div className="mb-4">
          <label className="fw-bold form-label">What specific curricular programs of students do you think will best fit with the training being provided by your host company?</label>
          <textarea className="form-control" rows="2" value={responses['fit_programs'] || ''} onChange={(e) => handleTextChange('fit_programs', e.target.value)}></textarea>
        </div>

        <div className="mb-4">
          <label className="fw-bold form-label">Other Comments / General Comments:</label>
          <textarea className="form-control" rows="2" value={generalComments} onChange={(e) => setGeneralComments(e.target.value)}></textarea>
        </div>

        <div className="card-footer bg-white border-top-0 px-0 pb-0">
          <div className="form-check">
            <input type="checkbox" className="form-check-input" id="dataPrivacy" required />
            <label className="form-check-label text-muted small" htmlFor="dataPrivacy">
              I agree to the collection and processing of my data for the purpose of processing the evaluation of host training establishment. I understand that my personal information is protected by RA 10173, Data Privacy Act of 2012, and that I am required to provide truthful information.
            </label>
          </div>
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
