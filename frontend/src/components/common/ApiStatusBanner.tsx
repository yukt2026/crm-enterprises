import React from 'react';
import { useHealthCheck } from '../../hooks/useCrmQuery';
import { AlertTriangle, RefreshCw, Wifi, WifiOff } from 'lucide-react';

export default function ApiStatusBanner() {
  const { data, isError, isLoading, refetch, isFetching } = useHealthCheck();

  if (isLoading) return null;

  const isDegraded = isError || !data?.success || data?.data?.status !== 'healthy';

  if (!isDegraded) {
    return null;
  }

  return (
    <div className="bg-amber-500/15 border-b border-amber-500/30 px-4 py-2.5 text-xs text-amber-300 flex items-center justify-between gap-3 backdrop-blur-md sticky top-0 z-50">
      <div className="flex items-center gap-2 min-w-0">
        <WifiOff className="h-4 w-4 text-amber-400 shrink-0 animate-pulse" />
        <span className="font-semibold truncate">
          Laravel Backend API Connection Degraded / Disconnected
        </span>
        <span className="hidden sm:inline text-amber-400/80">
          (Running in local mode or server offline. Live database sync may be interrupted.)
        </span>
      </div>

      <button
        onClick={() => refetch()}
        disabled={isFetching}
        className="px-2.5 py-1 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 font-semibold text-amber-200 transition-colors flex items-center gap-1.5 shrink-0 disabled:opacity-50"
      >
        <RefreshCw className={`h-3 w-3 ${isFetching ? 'animate-spin' : ''}`} />
        <span>{isFetching ? 'Reconnecting...' : 'Retry Connection'}</span>
      </button>
    </div>
  );
}
