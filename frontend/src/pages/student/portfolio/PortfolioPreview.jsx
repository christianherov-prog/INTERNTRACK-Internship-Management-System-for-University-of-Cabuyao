import React, { useState, useEffect } from 'react';
import api from '../../../services/api';
import CCSPortfolioPreview from './CCSPortfolioPreview';
import COEPortfolioPreview from './COEPortfolioPreview';
import COEDPortfolioPreview from './COEDPortfolioPreview';
import PsychologyPortfolioPreview from './PsychologyPortfolioPreview';
import NursingPortfolioPreview from './NursingPortfolioPreview';
import { resolvePortfolioVariant } from '../../../utils/portfolioVariant';

const PortfolioPreview = () => {
  const [department, setDepartment] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Fetch the user's department to determine which preview to show
    api.get('/auth/user')
      .then(res => {
        setDepartment(res.data?.user || { program: '' });
      })
      .catch(err => {
        console.error('Failed to fetch user department', err);
        setDepartment({ program: 'DEFAULT' });
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  if (loading) {
    return (
      <div className="d-flex flex-column align-items-center justify-content-center min-vh-100 text-muted">
        <i className="fa fa-spinner fa-spin fa-2x mb-3" aria-hidden="true" />
        <div className="small">Checking your session…</div>
      </div>
    );
  }

  if (!department && department !== '') {
    // Show a loader or fallback layout
    return (
      <div className="d-flex flex-column align-items-center justify-content-center min-vh-100 text-muted">
        <i className="fa fa-spinner fa-spin fa-2x mb-3" aria-hidden="true" />
        <div className="small">Checking your session…</div>
      </div>
    );
  }

  const variant = resolvePortfolioVariant(typeof department === 'string' ? { program: department } : department);

  if (variant === 'nursing') {
    return <NursingPortfolioPreview />;
  }
  if (variant === 'psychology') {
    return <PsychologyPortfolioPreview />;
  }
  if (variant === 'coed') {
    return <COEDPortfolioPreview />;
  }
  if (variant === 'coe') {
    return <COEPortfolioPreview />;
  }

  return <CCSPortfolioPreview />;
};

export default PortfolioPreview;
