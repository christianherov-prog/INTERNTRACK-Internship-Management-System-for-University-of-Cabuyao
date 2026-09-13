import React from 'react';
import CCSPortfolioBuilder from './CCSPortfolioBuilder';
import COEPortfolioBuilder from './COEPortfolioBuilder';
import COEDPortfolioBuilder from './COEDPortfolioBuilder';
import PsychologyPortfolioBuilder from './PsychologyPortfolioBuilder';
import NursingPortfolioBuilder from './NursingPortfolioBuilder';
import Layout from '../../../components/Layout';
import { resolvePortfolioVariant } from '../../../utils/portfolioVariant';
import { useAuth } from '../../../contexts/AuthContext';
import InternTrackLoader from '../../../components/InternTrackLoader'

const PortfolioBuilder = () => {
  const { user } = useAuth()
  const department = user || { program: 'DEFAULT' }

  if (!user) {
    return (
      <Layout title="My Portfolio" subtitle="Loading…" icon="fa-folder" bodyClass="student-page">
        <div className="text-center py-5 mt-5"><InternTrackLoader /></div>
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
