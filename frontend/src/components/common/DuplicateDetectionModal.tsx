import React, { useState } from 'react';
import {
  Copy,
  GitMerge,
  X,
  AlertTriangle,
  CheckCircle2,
  Loader2,
  ArrowRight,
  ShieldCheck,
} from 'lucide-react';
import { useMergeLead } from '../../hooks/useCrmCore';
import { api } from '../../api/client';
import { DuplicateMatch, LeadItem } from '../../api/types';

interface DuplicateDetectionModalProps {
  masterLead: any;
  isOpen: boolean;
  onClose: () => void;
  theme?: 'light' | 'dark';
}

export default function DuplicateDetectionModal({
  masterLead,
  isOpen,
  onClose,
  theme = 'dark',
}: DuplicateDetectionModalProps) {
  const [duplicateMatches, setDuplicateMatches] = useState<DuplicateMatch[]>([]);
  const [selectedDuplicate, setSelectedDuplicate] = useState<DuplicateMatch | null>(null);
  const [fieldOverrides, setFieldOverrides] = useState<Record<string, any>>({});
  const [isSearching, setIsSearching] = useState(false);

  const mergeMutation = useMergeLead();

  React.useEffect(() => {
    if (isOpen && masterLead) {
      setIsSearching(true);
      api
        .checkLeadDuplicates(
          {
            email: masterLead.email,
            phone: masterLead.phone,
            company_name: masterLead.company_name || masterLead.company,
          },
          masterLead.id
        )
        .then((res) => {
          setDuplicateMatches(res.data?.duplicates || []);
          if (res.data?.duplicates?.length > 0) {
            setSelectedDuplicate(res.data.duplicates[0]);
          }
        })
        .finally(() => setIsSearching(false));
    }
  }, [isOpen, masterLead]);

  if (!isOpen || !masterLead) return null;

  const handleMergeSubmit = () => {
    if (!selectedDuplicate) return;

    mergeMutation.mutate(
      {
        masterId: masterLead.id,
        duplicateId: selectedDuplicate.id,
        overrides: fieldOverrides,
      },
      {
        onSuccess: () => {
          onClose();
        },
      }
    );
  };

  return (
    <div className="fixed inset-0 bg-black/75 backdrop-blur-xs z-50 flex items-center justify-center p-4">
      <div
        className={`w-full max-w-2xl rounded-2xl border shadow-2xl overflow-hidden p-6 max-h-[85vh] flex flex-col ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
        }`}
      >
        {/* Header */}
        <div className="flex items-center justify-between border-b border-inherit pb-3 mb-4">
          <div className="flex items-center gap-2">
            <GitMerge className="h-5 w-5 text-emerald-400" />
            <div>
              <h3 className="font-bold text-sm">Duplicate Resolution & Controlled Merge</h3>
              <p className="text-[11px] text-slate-400">
                Consolidates duplicate CRM records, activities, and attachments without data loss.
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-slate-200"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto space-y-4 pr-1 text-xs">
          {isSearching ? (
            <div className="py-16 text-center text-xs text-slate-400 flex flex-col items-center gap-2">
              <Loader2 className="h-6 w-6 text-emerald-500 animate-spin" />
              <span>Scanning tenant records for matching phone or email...</span>
            </div>
          ) : duplicateMatches.length === 0 ? (
            <div className="py-16 text-center text-xs text-slate-400 flex flex-col items-center gap-2">
              <ShieldCheck className="h-8 w-8 text-emerald-400" />
              <span className="font-semibold text-slate-200">No duplicate records detected</span>
              <span>Lead email and phone numbers are unique in this workspace.</span>
            </div>
          ) : (
            <>
              {/* Duplicate Match Selector */}
              <div>
                <label className="block text-slate-400 font-semibold mb-1.5">
                  Select Duplicate Record to Merge ({duplicateMatches.length} detected)
                </label>
                <div className="space-y-1.5">
                  {duplicateMatches.map((dup) => (
                    <div
                      key={dup.id}
                      onClick={() => setSelectedDuplicate(dup)}
                      className={`p-3 rounded-xl border cursor-pointer flex items-center justify-between transition-all ${
                        selectedDuplicate?.id === dup.id
                          ? 'border-emerald-500 bg-emerald-950/20'
                          : 'border-slate-800 hover:bg-slate-800/40'
                      }`}
                    >
                      <div>
                        <div className="flex items-center gap-2">
                          <span className="font-semibold text-slate-200">{dup.name}</span>
                          <span className="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-slate-800 text-amber-400 border border-slate-700">
                            {dup.confidence} match
                          </span>
                        </div>
                        <p className="text-[11px] text-slate-400 mt-0.5 font-mono">
                          {dup.email || 'No email'} • {dup.phone || 'No phone'}
                        </p>
                      </div>
                      <div className="text-[10px] text-slate-500 font-mono">
                        {dup.match_reasons.join(', ')}
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Side by side comparison */}
              {selectedDuplicate && (
                <div className="grid grid-cols-2 gap-3 pt-2">
                  <div className="p-3 rounded-xl border border-slate-800 bg-slate-950/40">
                    <span className="font-semibold text-emerald-400 block mb-2">
                      Master Record (#{masterLead.id})
                    </span>
                    <p className="font-medium text-slate-200">{masterLead.name}</p>
                    <p className="text-slate-400 font-mono text-[11px]">{masterLead.email}</p>
                    <p className="text-slate-400 font-mono text-[11px]">{masterLead.phone}</p>
                  </div>

                  <div className="p-3 rounded-xl border border-slate-800 bg-slate-950/40">
                    <span className="font-semibold text-rose-400 block mb-2">
                      Duplicate Record (#{selectedDuplicate.id})
                    </span>
                    <p className="font-medium text-slate-200">{selectedDuplicate.name}</p>
                    <p className="text-slate-400 font-mono text-[11px]">{selectedDuplicate.email}</p>
                    <p className="text-slate-400 font-mono text-[11px]">{selectedDuplicate.phone}</p>
                  </div>
                </div>
              )}

              <div className="p-3 rounded-xl border border-slate-800 bg-slate-950/60 text-[11px] text-slate-400 flex items-center gap-2">
                <AlertTriangle className="h-4 w-4 text-amber-400 shrink-0" />
                <span>
                  Merging will transfer all notes, chronological activities, and files to the master record. The duplicate will be archived/soft-deleted.
                </span>
              </div>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="pt-3 border-t border-inherit flex items-center justify-end gap-2 mt-4">
          <button
            type="button"
            onClick={onClose}
            className="px-3.5 py-2 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 transition-colors"
          >
            Cancel
          </button>
          {selectedDuplicate && (
            <button
              onClick={handleMergeSubmit}
              disabled={mergeMutation.isPending}
              className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold flex items-center gap-1.5 shadow-xs transition-colors"
            >
              {mergeMutation.isPending ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <GitMerge className="h-4 w-4" />
              )}
              <span>Confirm & Merge Records</span>
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
