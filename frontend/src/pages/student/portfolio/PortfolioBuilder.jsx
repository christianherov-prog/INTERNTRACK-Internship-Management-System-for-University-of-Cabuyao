import React, { useState, useEffect } from 'react';
import api from '../../../services/api';
import CCSPortfolioBuilder from './CCSPortfolioBuilder';
import COEPortfolioBuilder from './COEPortfolioBuilder';
import COEDPortfolioBuilder from './COEDPortfolioBuilder';
import PsychologyPortfolioBuilder from './PsychologyPortfolioBuilder';
import NursingPortfolioBuilder from './NursingPortfolioBuilder';
import Layout from '../../../components/Layout';
import { resolvePortfolioVariant } from '../../../utils/portfolioVariant';

const PortfolioBuilder = () => {
  // Initialize as an empty string to prevent null reference errors
  const [department, setDepartment] = useState('');

  useEffect(() => {
    api.get('/auth/user')
      .then(res => {
        setDepartment(res.data?.user || { program: 'DEFAULT' });
      })
      .catch(err => {
        console.error('Failed to fetch user department', err);
        setDepartment({ program: 'DEFAULT' });
      });
  }, []);

  // Show a layout loader initially so the screen doesn't go blank
  if (!department) {
    return (
      <Layout title="My Portfolio" subtitle="Loading…" icon="fa-folder" bodyClass="student-page">
        <div className="text-center py-5 mt-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      </Layout>
    );
  }

  const variant = resolvePortfolioVariant(typeof department === 'string' ? { program: department } : department);

  if (variant === 'nursing') {
    return <NursingPortfolioBuilder />;
  }
  if (variant === 'psychology') {
    return <PsychologyPortfolioBuilder />;
  }
  if (variant === 'coed') {
    return <COEDPortfolioBuilder />;
  }
  if (variant === 'coe') {
    return <COEPortfolioBuilder />;
  }
  return <CCSPortfolioBuilder />;

};

export default PortfolioBuilder;