import { useEffect, useState } from 'react';
import { apiGet } from './api/client.js';
import ProLockedFeature from './components/ProLockedFeature.jsx';

const PRO_FEATURES = ['Products', 'Full Order Management', 'Customers', 'Analytics'];

export default function App() {
  const [status, setStatus] = useState('loading');

  useEffect(() => {
    apiGet('ping')
      .then(() => setStatus('connected'))
      .catch(() => setStatus('error'));
  }, []);

  return (
    <div className="min-h-screen bg-gray-50 p-8">
      <h1 className="text-2xl font-semibold text-gray-900">WooPilot Dashboard</h1>
      <p className="mt-2 text-gray-500">
        {status === 'loading' && 'Connecting to WooPilot...'}
        {status === 'connected' && 'Connected to the backend. More features are coming soon.'}
        {status === 'error' && 'Could not reach the WooPilot backend.'}
      </p>

      <nav className="mt-8 max-w-xs space-y-1">
        {PRO_FEATURES.map((feature) => (
          <ProLockedFeature key={feature} label={feature} />
        ))}
      </nav>
    </div>
  );
}
