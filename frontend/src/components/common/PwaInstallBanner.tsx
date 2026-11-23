import React from 'react';
import { Download, X } from 'lucide-react';
import { usePwaInstall } from '../../hooks/usePwaInstall';

export const PwaInstallBanner: React.FC = () => {
  const { isInstallable, promptInstall, dismissPrompt } = usePwaInstall();

  if (!isInstallable) return null;

  return (
    <div className="bg-gradient-to-r from-blue-600 to-indigo-700 text-white px-4 py-3 shadow-md flex items-center justify-between z-40 relative">
      <div className="flex items-center space-x-3">
        <div className="p-2 bg-white/20 rounded-lg backdrop-blur-sm">
          <Download className="w-5 h-5 text-white" />
        </div>
        <div>
          <p className="text-sm font-semibold">Install CRM App</p>
          <p className="text-xs text-blue-100">
            Add to your home screen for quick offline-ready access and native alerts.
          </p>
        </div>
      </div>
      <div className="flex items-center space-x-2">
        <button
          onClick={() => void promptInstall()}
          className="px-3 py-1.5 bg-white text-blue-700 hover:bg-blue-50 text-xs font-bold rounded shadow transition min-h-[36px] min-w-[70px] flex items-center justify-center"
        >
          Install
        </button>
        <button
          onClick={dismissPrompt}
          className="p-1.5 hover:bg-white/20 rounded text-blue-200 hover:text-white transition min-h-[36px] min-w-[36px] flex items-center justify-center"
          aria-label="Dismiss banner"
        >
          <X className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
};

export default PwaInstallBanner;
