import React, { useState, useEffect } from 'react';
import api from '../../../services/api';
import CCSPortfolioBuilder from './CCSPortfolioBuilder';
import COEPortfolioBuilder from './COEPortfolioBuilder';
import COEDPortfolioBuilder from './COEDPortfolioBuilder';
import Layout from '../../../components/Layout';

const PortfolioBuilder = () => {
  // Initialize as an empty string to prevent null reference errors
  const [department, setDepartment] = useState('');

  useEffect(() => {
    api.get('/auth/user')
      .then(res => {
        // Fallback to a default string if the program is missing
        const dept = (typeof res.data?.user?.program === 'string' ? res.data?.user?.program : res.data?.user?.program?.code || res.data?.user?.program?.name) || 'DEFAULT';
        setDepartment(dept);
      })
      .catch(err => {
        console.error('Failed to fetch user department', err);
        // Fallback to a default string if the API fails
        setDepartment('DEFAULT');
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

  // Safely check the string 
  const safeDept = department.toLowerCase();
  const isCOE = safeDept.includes('engineering') || safeDept.includes('coe');
  const isCOED = safeDept.includes('education') || safeDept.includes('coed');

  if (isCOED) {
    return <COEDPortfolioBuilder />;
  } else if (isCOE) {
    return <COEPortfolioBuilder />;
  } else {
    return <CCSPortfolioBuilder />;
  }

};

export default PortfolioBuilder;