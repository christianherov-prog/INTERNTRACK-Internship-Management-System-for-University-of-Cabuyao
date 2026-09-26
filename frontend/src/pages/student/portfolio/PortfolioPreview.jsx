import { useAuth } from '../../../contexts/AuthContext';
import CCSPortfolioPreview from './CCSPortfolioPreview';
import CBAAPortfolioPreview from './CBAAPortfolioPreview';
import COEPortfolioPreview from './COEPortfolioPreview';
import COEDPortfolioPreview from './COEDPortfolioPreview';
import PsychologyPortfolioPreview from './PsychologyPortfolioPreview';
import NursingPortfolioPreview from './NursingPortfolioPreview';
import { resolvePortfolioVariant } from '../../../utils/portfolioVariant';

const PortfolioPreview = () => {
  const { user } = useAuth()
  const department = user || { program: 'DEFAULT' };

  const variant = resolvePortfolioVariant(typeof department === 'string' ? { program: department } : department);

  if (variant === 'nursing') {
    return <NursingPortfolioPreview />;
  }
  if (variant === 'psychology') {
    return <PsychologyPortfolioPreview />;
  }
  if (variant === 'cbaa') {
    return <CBAAPortfolioPreview />;
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
