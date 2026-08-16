import React, { useState, useEffect } from 'react';
import api from '../../../services/api';
import CCSPortfolioPreview from './CCSPortfolioPreview';
import COEPortfolioPreview from './COEPortfolioPreview';

const PortfolioPreview = () => {
  const [department, setDepartment] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Fetch the user's department to determine which preview to show
    api.get('/auth/user')
      .then(res => {
        const dept = (typeof res.data?.user?.program === 'string' ? res.data?.user?.program : res.data?.user?.program?.code || res.data?.user?.program?.name) || '';
        setDepartment(dept);
      })
      .catch(err => {
        console.error('Failed to fetch user department', err);
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
    return <div className="alert alert-warning m-4">Department information not found.</div>;
  }

  const isCOE = department.toLowerCase().includes('engineering') || department.toLowerCase().includes('coe');

  if (isCOE) {
    return <COEPortfolioPreview />;
  }

  return <CCSPortfolioPreview />;
};

export default PortfolioPreview;
