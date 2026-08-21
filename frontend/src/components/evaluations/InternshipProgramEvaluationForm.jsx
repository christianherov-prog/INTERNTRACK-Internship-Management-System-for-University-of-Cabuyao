import React, { useState } from 'react';

export const InternshipProgramEvaluationForm = ({ internship, onSubmit, processing }) => {
  const [responses, setResponses] = useState({});
  const [generalComments, setGeneralComments] = useState('');

  const sections = [
    {
      title: "I. Internship Program Objectives",
      items: ["1. Clarity of Objectives", "2. Attainment of Objectives"],
      prefix: 'obj'
    },
    {
      title: "II. Program Guidelines/Policies",
      items: ["1. Clarity of guidelines and policies", "2. Effective implementation of the guidelines and policies", "3. Relevance or importance of guidelines and policies to the student's training/internship"],
      prefix: 'guide'
    },
    {
      title: "III. Internship Activities",
      items: ["1. Schedule of Activities (Orientation and Seminars)", "2. Venue/Platform of Internship Activities", "3. Adequacy of time allotted to meet the deadline for completion of activities"],
      prefix: 'act'
    },
    {
      title: "IV. Internship Teaching Personnel",
      items: ["1. Availability for consultation and inquiries", "2. Assistance in locating appropriate Internship Host Training Establishments (HTE)", "3. Professional dealing with the students", "4. Regular phone/virtual checking"],
      prefix: 'teach'
    }
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
    let allFilled = true;
    let count = 1;
    sections.forEach(sec => {
      sec.items.forEach(() => {
        if (!responses[`q${count}`]) allFilled = false;
        count++;
      });
    });

    if (!allFilled) {
      alert('Please rate all criteria.');
      return;
    }

    onSubmit({
      evaluation_period: 'final', // Typically submitted at the end
      form_type: 'FO-23',
      responses,
      general_comments: generalComments
    });
  };

  let globalIndex = 1;

  const student = internship?.student?.student_profile || internship?.student?.studentProfile || {};
  const studentName = `${student.first_name || ''} ${student.last_name || ''}`.trim() || 'Unavailable';
  const program = (typeof student.program === 'string' ? student.program : student.program?.code || student.program?.name) || 'Unavailable';
  const semStr = internship?.semester === 1 ? '1st Semester' : internship?.semester === 2 ? '2nd Semester' : internship?.semester === 3 ? 'Midyear' : 'Unavailable';
  const faculty = internship?.faculty?.facultyProfile || internship?.faculty?.faculty_profile || {};
  const facultyName = `${faculty.first_name || ''} ${faculty.last_name || ''}`.trim() || 'Unavailable';

  return (
    <form onSubmit={handleSubmit} className="card shadow-sm mb-4 border-0">
      <div className="card-body p-4">
        <h3 className="card-title text-center mb-4 fw-bold">INTERNSHIP PROGRAM EVALUATION FORM</h3>

        <div className="row g-3 mb-4">
          <div className="col-md-6">
            <input type="text" className="form-control" placeholder="Semester/Midyear" value={semStr} readOnly />
          </div>
          <div className="col-md-6">
            <input type="text" className="form-control" placeholder="Academic Year" value={internship?.academic_year || internship?.school_year || 'Unavailable'} readOnly />
          </div>
          <div className="col-md-6">
            <input type="text" className="form-control" placeholder="Student Name" value={studentName} readOnly />
          </div>
          <div className="col-md-6">
            <input type="text" className="form-control" placeholder="Program" value={program} readOnly />
          </div>
          <div className="col-md-12">
            <input type="text" className="form-control" placeholder="Internship Teaching Personnel" value={facultyName} readOnly />
          </div>
        </div>

        <div className="alert alert-info text-center py-2 mb-4">
          <span className="fw-bold">Rating Scale:</span> 5=Outstanding | 4=Very Satisfactory | 3=Satisfactory | 2=Unsatisfactory | 1=Poor
        </div>

        <div className="table-responsive mb-4">
          <table className="table table-bordered align-middle">
            <thead className="table-light text-center">
              <tr>
                <th className="text-start">Criteria</th>
                <th style={{ width: '120px' }}>Rating</th>
                <th>Comments/Suggestions</th>
              </tr>
            </thead>
            <tbody>
              {sections.map((section, sIdx) => (
                <React.Fragment key={sIdx}>
                  <tr>
                    <td colSpan="3" className="fw-bold bg-light">{section.title}</td>
                  </tr>
                  {section.items.map((item, i) => {
                    const qId = `q${globalIndex++}`;
                    return (
                      <tr key={qId}>
                        <td className="text-start ps-4">{item}</td>
                        <td>
                          <select
                            className="form-select text-center"
                            value={responses[qId] || ''}
                            onChange={(e) => handleRatingChange(qId, e.target.value)}
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
                            value={responses[`${qId}_comment`] || ''}
                            onChange={(e) => handleTextChange(`${qId}_comment`, e.target.value)}
                          />
                        </td>
                      </tr>
                    );
                  })}
                </React.Fragment>
              ))}
            </tbody>
          </table>
        </div>

        <div className="mb-4">
          <label className="fw-bold form-label">Other comments and suggestions:</label>
          <textarea className="form-control" rows="3" value={generalComments} onChange={(e) => setGeneralComments(e.target.value)}></textarea>
        </div>

        <div className="card-footer bg-white border-top-0 px-0 pb-0">
          <div className="form-check">
            <input type="checkbox" className="form-check-input" id="dataPrivacy23" required />
            <label className="form-check-label text-muted small" htmlFor="dataPrivacy23">
              I agree to the collection and processing of my data for the purpose of processing the evaluation of University's Internship Program. I understand that my personal information is protected by RA 10173, Data Privacy Act of 2012, and that I am required to provide truthful information.
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
