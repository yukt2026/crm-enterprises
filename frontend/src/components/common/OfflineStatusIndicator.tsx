import React from 'react';
import { WifiOff, Wifi } from 'lucide-react';
import { useNetworkStatus } from '../../hooks/useNetworkStatus';

export const OfflineStatusIndicator: React.FC = () => {
  const { isOnline, wasOffline } = useNetworkStatus();

  if (isOnline && !wasOffline) return null;

  if (!isOnline) {
    return (
      <div
        role="alert"
        aria-live="assertive"
        className="bg-amber-600 text-white text-xs px-4 py-2 flex items-center justify-center space-x-2 shadow-inner z-50 transition-all duration-300"
      >
        <WifiOff className="w-4 h-4 animate-pulse flex-shrink-0" />
        <span className="font-medium">
          Offline Mode: Network disconnected. Sensitive customer records are protected and not cached locally.
        </span>
      </div>
    );
  }

  if (wasOffline) {
    return (
      <div
        role="status"
        aria-live="polite"
        className="bg-emerald-600 text-white text-xs px-4 py-2 flex items-center justify-center space-x-2 shadow-inner z-50 transition-all duration-300"
      >
        <Wifi className="w-4 h-4 flex-shrink-0" />
        <span className="font-medium">
          Connection restored! You are back online.
        </span>
      </div>
    );
  }

  return null;
};

export default OfflineStatusIndicator;
