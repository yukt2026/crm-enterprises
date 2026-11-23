import React, { useState } from 'react';
import {
  Globe,
  Coins,
  Clock,
  Calendar,
  Languages,
  CheckCircle2,
  Building2,
  Smartphone,
  Info,
} from 'lucide-react';
import { useIndiaSettings } from '../../hooks/useAnalyticsBI';
import { api } from '../../api/client';

export const IndiaFirstTab: React.FC = () => {
  const { data: settings, isLoading } = useIndiaSettings();

  // Interactive amount tester
  const [testAmount, setTestAmount] = useState<number>(14500000);
  const [formattedResult, setFormattedResult] = useState<any>(null);
  const [isFormatting, setIsFormatting] = useState(false);

  // Selected language for dictionary preview
  const [selectedLang, setSelectedLang] = useState<string>('hi');
  const [langDict, setLangDict] = useState<Record<string, string> | null>(null);

  const supportedLanguages = [
    { code: 'en', label: 'English (Indian Business)' },
    { code: 'hi', label: 'हिन्दी (Hindi)' },
    { code: 'hinglish', label: 'Hinglish (Colloquial Business)' },
    { code: 'ta', label: 'தமிழ் (Tamil)' },
    { code: 'te', label: 'తెలుగు (Telugu)' },
    { code: 'mr', label: 'मराठी (Marathi)' },
    { code: 'gu', label: 'ગુજરાતી (Gujarati)' },
    { code: 'kn', label: 'ಕನ್ನಡ (Kannada)' },
    { code: 'bn', label: 'বাংলা (Bengali)' },
  ];

  const handleFormatTest = async () => {
    setIsFormatting(true);
    try {
      const res = await api.formatInrAmount(Number(testAmount));
      setFormattedResult(res.data);
    } catch (e) {
      console.error(e);
    } finally {
      setIsFormatting(false);
    }
  };

  const handleLoadLang = async (lang: string) => {
    setSelectedLang(lang);
    try {
      const res = await api.getIndiaTranslations(lang);
      setLangDict(res.data.translations);
    } catch (e) {
      console.error(e);
    }
  };

  React.useEffect(() => {
    handleLoadLang(selectedLang);
  }, [selectedLang]);

  return (
    <div className="space-y-6">
      {/* Top Banner */}
      <div className="bg-gradient-to-r from-orange-950/40 via-amber-900/30 to-emerald-950/40 border border-orange-500/30 rounded-xl p-5 shadow-lg">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="flex items-start gap-3">
            <div className="p-3 bg-orange-600/20 text-orange-400 rounded-lg border border-orange-500/30">
              <Globe className="w-6 h-6" />
            </div>
            <div>
              <h2 className="text-lg font-bold text-white flex items-center gap-2">
                India-First CRM Experience & Localization System
                <span className="text-xs px-2.5 py-0.5 rounded-full bg-orange-500/20 text-orange-300 border border-orange-500/30">
                  Asia/Kolkata (IST)
                </span>
              </h2>
              <p className="text-sm text-gray-300 mt-1 max-w-3xl">
                Native enterprise support for Indian numbering (Lakhs & Crores), INR currency
                representation, 2026 gazetted holidays, +91 mobile verification, and 9 regional
                language dictionaries.
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2 px-3.5 py-1.5 rounded-lg bg-gray-900/80 border border-gray-700 text-xs">
            <Clock className="w-4 h-4 text-emerald-400" />
            <span className="text-gray-300 font-mono">
              IST: {settings?.system_time_ist ? new Date(settings.system_time_ist).toLocaleTimeString('en-IN') : 'IST Active'}
            </span>
            <span
              className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                settings?.is_business_hours
                  ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                  : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'
              }`}
            >
              {settings?.is_business_hours ? 'Business Hours OPEN' : 'After Hours'}
            </span>
          </div>
        </div>
      </div>

      {/* Grid: Core Specs & Interactive Tester */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Core Localization Defaults */}
        <div className="bg-gray-800/90 border border-gray-700 rounded-xl p-5 shadow space-y-4">
          <h3 className="text-base font-bold text-white flex items-center gap-2">
            <Building2 className="w-4 h-4 text-orange-400" />
            Enterprise India Standards
          </h3>

          <div className="space-y-3 text-xs">
            <div className="flex items-center justify-between p-2.5 bg-gray-900/60 rounded-lg border border-gray-700/40">
              <span className="text-gray-400">Default Currency:</span>
              <span className="text-white font-semibold flex items-center gap-1">
                <Coins className="w-3.5 h-3.5 text-amber-400" />
                INR (₹ - Indian Rupee)
              </span>
            </div>

            <div className="flex items-center justify-between p-2.5 bg-gray-900/60 rounded-lg border border-gray-700/40">
              <span className="text-gray-400">Numbering Hierarchy:</span>
              <span className="text-white font-semibold">Lakhs (L) & Crores (Cr)</span>
            </div>

            <div className="flex items-center justify-between p-2.5 bg-gray-900/60 rounded-lg border border-gray-700/40">
              <span className="text-gray-400">Canonical Timezone:</span>
              <span className="text-white font-semibold">Asia/Kolkata (UTC +05:30)</span>
            </div>

            <div className="flex items-center justify-between p-2.5 bg-gray-900/60 rounded-lg border border-gray-700/40">
              <span className="text-gray-400">Date Display Standard:</span>
              <span className="text-white font-semibold">DD/MM/YYYY (Indian Standard)</span>
            </div>

            <div className="flex items-center justify-between p-2.5 bg-gray-900/60 rounded-lg border border-gray-700/40">
              <span className="text-gray-400">Mobile Validation:</span>
              <span className="text-white font-semibold flex items-center gap-1">
                <Smartphone className="w-3.5 h-3.5 text-blue-400" />
                +91 10-Digit Normalization
              </span>
            </div>

            <div className="flex items-center justify-between p-2.5 bg-gray-900/60 rounded-lg border border-gray-700/40">
              <span className="text-gray-400">Standard Office Hours:</span>
              <span className="text-white font-semibold">09:00 - 18:00 IST (Mon-Fri)</span>
            </div>
          </div>
        </div>

        {/* Live Indian Number Formatter Tool */}
        <div className="bg-gray-800/90 border border-gray-700 rounded-xl p-5 shadow space-y-4">
          <h3 className="text-base font-bold text-white flex items-center gap-2">
            <Coins className="w-4 h-4 text-emerald-400" />
            Lakhs & Crores Interactive Test
          </h3>

          <div className="space-y-3">
            <div>
              <label className="block text-xs font-semibold text-gray-300 mb-1">
                Enter Raw Decimal Amount
              </label>
              <div className="flex gap-2">
                <input
                  type="number"
                  value={testAmount}
                  onChange={(e) => setTestAmount(Number(e.target.value))}
                  className="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500 font-mono"
                />
                <button
                  onClick={handleFormatTest}
                  disabled={isFormatting}
                  className="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-medium transition shadow"
                >
                  Format
                </button>
              </div>
            </div>

            <div className="p-3 bg-gray-900/80 rounded-lg border border-gray-700 space-y-2 text-xs">
              <div className="text-gray-400">Formatted Indian Grouping:</div>
              <div className="text-lg font-bold text-emerald-400 font-mono">
                {formattedResult?.formatted_inr ?? `₹${testAmount.toLocaleString('en-IN')}`}
              </div>

              <div className="text-gray-400 mt-2">Executive Short Abbreviation:</div>
              <div className="text-base font-semibold text-blue-400 font-mono">
                {formattedResult?.formatted_short ??
                  (testAmount >= 10000000
                    ? `₹${(testAmount / 10000000).toFixed(2)} Cr`
                    : testAmount >= 100000
                    ? `₹${(testAmount / 100000).toFixed(2)} L`
                    : `₹${testAmount.toLocaleString('en-IN')}`)}
              </div>
            </div>

            <div className="text-[11px] text-gray-400 flex items-center gap-1.5">
              <Info className="w-3.5 h-3.5 text-gray-500 flex-shrink-0" />
              <span>Uses standard 2-digit interval grouping: 1,00,00,000 (1 Crore).</span>
            </div>
          </div>
        </div>

        {/* 2026 Gazetted Holidays */}
        <div className="bg-gray-800/90 border border-gray-700 rounded-xl p-5 shadow space-y-3">
          <h3 className="text-base font-bold text-white flex items-center gap-2">
            <Calendar className="w-4 h-4 text-red-400" />
            2026 Gazetted Holidays (India)
          </h3>

          <div className="h-60 overflow-y-auto space-y-2 pr-1">
            {settings?.gazetted_holidays?.map((h: any, idx: number) => (
              <div
                key={idx}
                className="p-2 bg-gray-900/60 rounded border border-gray-700/50 flex items-center justify-between text-xs"
              >
                <div>
                  <div className="text-white font-medium">{h.name}</div>
                  <div className="text-[10px] text-gray-400">{h.type}</div>
                </div>
                <div className="font-mono text-amber-300 font-semibold">{h.date}</div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Multi-Language Dictionaries Preview */}
      <div className="bg-gray-800/90 border border-gray-700 rounded-xl p-5 shadow space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <h3 className="text-base font-bold text-white flex items-center gap-2">
            <Languages className="w-5 h-5 text-indigo-400" />
            Regional Multi-Language Dictionaries
          </h3>
          <div className="flex items-center gap-2">
            <span className="text-xs text-gray-400">Select Language:</span>
            <select
              value={selectedLang}
              onChange={(e) => handleLoadLang(e.target.value)}
              className="bg-gray-900 border border-gray-700 text-white rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:border-indigo-500"
            >
              {supportedLanguages.map((l) => (
                <option key={l.code} value={l.code}>
                  {l.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        {langDict && Object.keys(langDict).length > 0 ? (
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            {Object.entries(langDict).map(([key, value]) => (
              <div key={key} className="bg-gray-900/60 p-3 rounded-lg border border-gray-700/50">
                <span className="text-[10px] uppercase text-gray-400 block font-mono">
                  {key.replace(/_/g, ' ')}
                </span>
                <span className="text-sm font-semibold text-white mt-1 block">{value}</span>
              </div>
            ))}
          </div>
        ) : (
          <div className="p-8 text-center text-gray-400 text-xs">
            Loading dictionary translations for {selectedLang}...
          </div>
        )}
      </div>
    </div>
  );
};
