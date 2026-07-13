export default function LoadingCard({ label = 'Loading...' }) {
  return (
    <div className="content-card mb-4 text-center" style={{ padding: '2rem' }}>
      <i className="fa fa-spinner fa-spin me-2"></i>
      <span>{label}</span>
    </div>
  );
}
