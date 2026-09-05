import React, { useState } from 'react';
import { useFormIdentity } from '../../hooks/useFormIdentity';
import { FORM_IDENTITY_FIELDS } from '../../utils/formIdentity';
import FormIdentityFields from './FormIdentityFields';

export const HTEToUniversityEvaluationForm = ({ internship, onSubmit, processing }) => {
  const identity = useFormIdentity(internship);
  const [responses, setResponses] = useState({});
  const [generalComments, setGeneralComments] = useState('');

  const criteriaList = [
    "Interns' preparedness for work",
    "Relevance of academic training to job tasks",
    "Communication and support from the school",
    "Responsiveness to company feedback",
    "Overall effectiveness of the internship program"
  ];

  const handleRadioChange = (key, value) => {
    setResponses(prev => ({ ...prev, [key]: value }));
  };

  const handleTextChange = (key, value) => {
    setResponses(prev => ({ ...prev, [key]: value }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (processing) return;

    // Ensure all 5 criteria are filled
    const allFilled = criteriaList.every((_, i) => responses[`q${i + 1}`]);
    if (!allFilled) {
      alert('Please rate all criteria.');
      return;
    }

    onSubmit({
      evaluation_period: 'final',
      form_type: 'FO-03',
      responses,
      general_comments: generalComments
    });
  };

  

  return (
    <form onSubmit={handleSubmit} className="card shadow-sm mb-4 border-0">
      <div className="card-body p-4">
        <h3 className="card-title text-center mb-4 fw-bold">HTE Evaluation to the University Internship Program</h3>

        <FormIdentityFields identity={identity} fields={FORM_IDENTITY_FIELDS['FO-03']} />
             
        <p className="fw-bold mb-2">Please rate the school's internship program based on your experience and observation:</p>
        <div className="table-responsive mb-4">
          <table className="table table-bordered text-center align-middle">
            <thead className="table-light">
              <tr>
                <th className="text-start">Criteria</th>
                <th>Excellent (5)</th>
                <th>Good (4)</th>
                <th>Satisfactory (3)</th>
                <th>Needs Improvement (2)</th>
                <th>Poor (1)</th>
              </tr>
            </thead>
            <tbody>
              {criteriaList.map((criteria, i) => (
                <tr key={i}>
                  <td className="text-start">{criteria}</td>
                  {[5, 4, 3, 2, 1].map(val => (
                    <td key={val}>
                      <input
                        type="radio"
                        name={`q${i + 1}`}
                        className="form-check-input"
                        checked={responses[`q${i + 1}`] === String(val)}
                        onChange={() => handleRadioChange(`q${i + 1}`, String(val))}
                        required
                      />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="mb-3">
          <label className="fw-bold form-label">1. Observations on the School's Internship Program: (Describe any positive aspects and challenges faced during the collaboration with the school.)</label>
          <textarea className="form-control" rows="3" value={responses['observations'] || ''} onChange={(e) => handleTextChange('observations', e.target.value)} required></textarea>
        </div>

        <div className="mb-3">
          <label className="fw-bold form-label">2. Suggestions for Improvement: (Provide recommendations on how the school can improve the internship experience.)</label>
          <textarea className="form-control" rows="3" value={responses['suggestions'] || ''} onChange={(e) => handleTextChange('suggestions', e.target.value)} required></textarea>
        </div>

        <div className="mb-3">
          <label className="fw-bold form-label">3. Impact of the Internship Program on the Company. Did the interns contribute positively to the company's operations? (Explain how.)</label>
          <textarea className="form-control" rows="3" value={responses['impact'] || ''} onChange={(e) => handleTextChange('impact', e.target.value)} required></textarea>
        </div>

        <div className="mb-3">
          <label className="fw-bold form-label d-block">4. Would the company consider hiring any of the interns in the future?</label>
          <div className="d-flex align-items-center gap-3">
            <div className="form-check">
              <input type="radio" className="form-check-input" name="hire" value="yes" onChange={(e) => handleRadioChange('would_hire', e.target.value)} required />
              <label className="form-check-label">Yes</label>
            </div>
            <div className="form-check">
              <input type="radio" className="form-check-input" name="hire" value="no" onChange={(e) => handleRadioChange('would_hire', e.target.value)} />
              <label className="form-check-label">No</label>
            </div>
            <input type="text" className="form-control flex-grow-1" placeholder="(Provide reasons)" value={responses['hire_reasons'] || ''} onChange={(e) => handleTextChange('hire_reasons', e.target.value)} />
          </div>
        </div>

        <div className="mb-4">
          <label className="fw-bold form-label d-block">5. Final Recommendation: Would you recommend continuing the partnership with this school for future internship programs?</label>
          <div className="d-flex align-items-center gap-3">
            <div className="form-check">
              <input type="radio" className="form-check-input" name="continue_partner" value="yes" onChange={(e) => handleRadioChange('recommend_partner', e.target.value)} required />
              <label className="form-check-label">Yes</label>
            </div>
            <div className="form-check">
              <input type="radio" className="form-check-input" name="continue_partner" value="no" onChange={(e) => handleRadioChange('recommend_partner', e.target.value)} />
              <label className="form-check-label">No</label>
            </div>
          </div>
        </div>

        <div className="mb-4">
          <label className="fw-bold form-label">Other Comments / General Comments:</label>
          <textarea className="form-control" rows="2" value={generalComments} onChange={(e) => setGeneralComments(e.target.value)}></textarea>
        </div>

        <div className="d-flex justify-content-end">
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
