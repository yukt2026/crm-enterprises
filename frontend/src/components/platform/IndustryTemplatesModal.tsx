import React, { useState } from 'react';
import {
  Sparkles,
  Layers,
  CheckCircle2,
  Building,
  GraduationCap,
  Stethoscope,
  Code2,
  Megaphone,
  Car,
  Shield,
  Plane,
  Factory,
  ShoppingBag,
  Briefcase,
  BookOpen,
  ArrowRight,
  Filter,
  Check,
  AlertCircle,
  X,
  RefreshCw,
  Sliders,
  HelpCircle
} from 'lucide-react';
import {
  useIndustryTemplates,
  useCurrentIndustryTemplate,
  useApplyIndustryTemplate,
} from '../../hooks/useSaasPlatform';
import { IndustryTemplateItem } from '../../api/types';

interface IndustryTemplatesModalProps {
  isOpen: boolean;
  onClose: () => void;
  onApplied?: (template: IndustryTemplateItem) => void;
}

export const IndustryTemplatesModal: React.FC<IndustryTemplatesModalProps> = ({
  isOpen,
  onClose,
  onApplied,
}) => {
  const [selectedTemplateCode, setSelectedTemplateCode] = useState<string>('real_estate');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [replaceExisting, setReplaceExisting] = useState<boolean>(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const { data: templates = [], isLoading, refetch } = useIndustryTemplates();
  const { data: currentTemplateData, refetch: refetchCurrent } = useCurrentIndustryTemplate();
  const applyTemplateMutation = useApplyIndustryTemplate();

  if (!isOpen) return null;

  const getTemplateIcon = (iconName: string) => {
    switch (iconName) {
      case 'building': return <Building className="w-6 h-6 text-indigo-500" />;
      case 'graduation-cap': return <GraduationCap className="w-6 h-6 text-blue-500" />;
      case 'stethoscope': return <Stethoscope className="w-6 h-6 text-emerald-500" />;
      case 'code': return <Code2 className="w-6 h-6 text-purple-500" />;
      case 'megaphone': return <Megaphone className="w-6 h-6 text-pink-500" />;
      case 'car': return <Car className="w-6 h-6 text-amber-500" />;
      case 'shield': return <Shield className="w-6 h-6 text-cyan-500" />;
      case 'plane': return <Plane className="w-6 h-6 text-teal-500" />;
      case 'factory': return <Factory className="w-6 h-6 text-orange-500" />;
      case 'shopping-bag': return <ShoppingBag className="w-6 h-6 text-rose-500" />;
      case 'briefcase': return <Briefcase className="w-6 h-6 text-slate-500" />;
      case 'book-open': return <BookOpen className="w-6 h-6 text-violet-500" />;
      default: return <Sparkles className="w-6 h-6 text-indigo-500" />;
    }
  };

  const filteredTemplates = templates.filter(t =>
    t.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    t.industry_sector.toLowerCase().includes(searchQuery.toLowerCase()) ||
    t.description.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const selectedTemplate = templates.find(t => t.template_code === selectedTemplateCode) || templates[0];
  const currentActiveCode = currentTemplateData?.template?.template_code || null;

  const handleApply = async () => {
    if (!selectedTemplate) return;
    setSuccessMessage(null);
    try {
      await applyTemplateMutation.mutateAsync({
        templateCode: selectedTemplate.template_code,
        replaceExisting,
      });
      setSuccessMessage(`Successfully applied "${selectedTemplate.name}" template to your organization!`);
      refetchCurrent();
      if (onApplied) onApplied(selectedTemplate);
    } catch (err: any) {
      console.error('Failed to apply template:', err);
    }
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
      <div className="relative w-full max-w-6xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 flex flex-col max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="px-6 py-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
          <div className="flex items-center space-x-3">
            <div className="p-2.5 bg-indigo-600/10 text-indigo-600 dark:text-indigo-400 rounded-xl">
              <Sparkles className="w-6 h-6" />
            </div>
            <div>
              <h2 className="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                Industry-Specific CRM Starter Templates
                <span className="text-xs px-2.5 py-0.5 rounded-full font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">
                  12 Built-in Indian Blueprints
                </span>
              </h2>
              <p className="text-sm text-slate-500 dark:text-slate-400">
                Pre-configured sales pipelines, custom fields, dashboard widgets, and automation triggers tailored for your vertical.
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Search & Status Alert */}
        <div className="px-6 py-3 border-b border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-wrap items-center justify-between gap-4">
          <div className="relative flex-1 min-w-[280px]">
            <Filter className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
            <input
              type="text"
              placeholder="Search by industry (e.g. Real Estate, Healthcare, IT, Retail)..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-9 pr-4 py-2 text-sm bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          {currentActiveCode && (
            <div className="flex items-center gap-2 text-xs font-semibold px-3 py-1.5 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 rounded-lg border border-emerald-200 dark:border-emerald-800">
              <CheckCircle2 className="w-4 h-4 text-emerald-500" />
              <span>Active Organization Template: <strong>{currentTemplateData?.template?.name}</strong></span>
            </div>
          )}
        </div>

        {/* Content Body: Left Column List & Right Column Details */}
        <div className="flex-1 overflow-hidden grid grid-cols-1 lg:grid-cols-12">
          {/* Templates Grid List */}
          <div className="lg:col-span-5 p-4 overflow-y-auto border-r border-slate-200 dark:border-slate-800 space-y-2.5 max-h-[60vh]">
            {isLoading ? (
              <div className="flex items-center justify-center py-12 text-slate-400">
                <RefreshCw className="w-6 h-6 animate-spin mr-2" />
                <span>Loading starter templates...</span>
              </div>
            ) : filteredTemplates.length === 0 ? (
              <div className="text-center py-12 text-slate-400">
                No templates matched your search.
              </div>
            ) : (
              filteredTemplates.map((tmpl) => {
                const isSelected = selectedTemplateCode === tmpl.template_code;
                const isCurrent = currentActiveCode === tmpl.template_code;
                return (
                  <div
                    key={tmpl.template_code}
                    onClick={() => {
                      setSelectedTemplateCode(tmpl.template_code);
                      setSuccessMessage(null);
                    }}
                    className={`p-3.5 rounded-xl border transition-all cursor-pointer flex items-start gap-3.5 ${
                      isSelected
                        ? 'border-indigo-600 bg-indigo-50/60 dark:bg-indigo-950/30 dark:border-indigo-500 shadow-sm'
                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900'
                    }`}
                  >
                    <div className="p-2.5 bg-slate-100 dark:bg-slate-800 rounded-xl shrink-0">
                      {getTemplateIcon(tmpl.icon)}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between gap-1">
                        <h4 className="text-sm font-semibold text-slate-900 dark:text-white truncate">
                          {tmpl.name}
                        </h4>
                        {isCurrent && (
                          <span className="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">
                            CURRENT
                          </span>
                        )}
                      </div>
                      <p className="text-xs text-slate-500 dark:text-slate-400 line-clamp-1 mt-0.5">
                        {tmpl.description}
                      </p>
                      <div className="flex items-center gap-2 mt-2 text-[11px] text-slate-400">
                        <span className="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-800 rounded">
                          {tmpl.pipeline_stages?.length || 0} stages
                        </span>
                        <span>•</span>
                        <span className="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-800 rounded">
                          {tmpl.custom_fields?.length || 0} custom fields
                        </span>
                      </div>
                    </div>
                  </div>
                );
              })
            )}
          </div>

          {/* Template Preview Panel */}
          <div className="lg:col-span-7 p-6 overflow-y-auto max-h-[60vh] space-y-6 bg-slate-50/40 dark:bg-slate-900/40">
            {selectedTemplate ? (
              <>
                {/* Header info */}
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <span className="text-xs font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                      {selectedTemplate.industry_sector} Vertical
                    </span>
                    <h3 className="text-xl font-bold text-slate-900 dark:text-white mt-1">
                      {selectedTemplate.name}
                    </h3>
                    <p className="text-sm text-slate-600 dark:text-slate-400 mt-1">
                      {selectedTemplate.description}
                    </p>
                  </div>
                </div>

                {successMessage && (
                  <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-3">
                    <CheckCircle2 className="w-5 h-5 text-emerald-500 shrink-0" />
                    <span>{successMessage}</span>
                  </div>
                )}

                {/* Pipeline Stages Section */}
                <div className="bg-white dark:bg-slate-800/80 rounded-xl p-4 border border-slate-200 dark:border-slate-700 shadow-sm">
                  <h4 className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider flex items-center gap-2 mb-3">
                    <Layers className="w-4 h-4 text-indigo-500" />
                    Customized Sales Pipeline Stages
                  </h4>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    {selectedTemplate.pipeline_stages?.map((stage, idx) => (
                      <div
                        key={idx}
                        className="px-3 py-2 bg-slate-50 dark:bg-slate-900/60 rounded-lg border border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs"
                      >
                        <span className="font-medium text-slate-800 dark:text-slate-200">
                          {idx + 1}. {stage.name}
                        </span>
                        <span className="font-semibold px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                          {stage.probability}%
                        </span>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Industry Custom Fields */}
                <div className="bg-white dark:bg-slate-800/80 rounded-xl p-4 border border-slate-200 dark:border-slate-700 shadow-sm">
                  <h4 className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider flex items-center gap-2 mb-3">
                    <Sliders className="w-4 h-4 text-purple-500" />
                    Domain-Specific Custom Fields
                  </h4>
                  <div className="space-y-2">
                    {selectedTemplate.custom_fields?.map((field, idx) => (
                      <div
                        key={idx}
                        className="p-2.5 bg-slate-50 dark:bg-slate-900/60 rounded-lg border border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs"
                      >
                        <div>
                          <span className="font-semibold text-slate-800 dark:text-slate-200">
                            {field.label}
                          </span>
                          <span className="ml-2 text-[10px] text-slate-400 font-mono">
                            ({field.model}.{field.name})
                          </span>
                        </div>
                        <span className="px-2 py-0.5 uppercase text-[10px] rounded bg-purple-50 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300 font-medium">
                          {field.type}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Recommended Automations */}
                {selectedTemplate.recommended_automations && (
                  <div className="bg-white dark:bg-slate-800/80 rounded-xl p-4 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <h4 className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider flex items-center gap-2 mb-3">
                      <Sparkles className="w-4 h-4 text-amber-500" />
                      Pre-Packaged Workflow Automations
                    </h4>
                    <div className="space-y-2">
                      {selectedTemplate.recommended_automations.map((auto, idx) => (
                        <div
                          key={idx}
                          className="p-2.5 bg-slate-50 dark:bg-slate-900/60 rounded-lg border border-slate-100 dark:border-slate-800 text-xs"
                        >
                          <div className="font-semibold text-slate-800 dark:text-slate-200">
                            {auto.name}
                          </div>
                          <div className="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            Trigger: <span className="text-indigo-600 dark:text-indigo-400 font-medium">{auto.trigger}</span> → Action: <span className="text-emerald-600 dark:text-emerald-400 font-medium">{auto.action}</span>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </>
            ) : (
              <div className="text-center py-24 text-slate-400">
                Select an industry template from the left to preview its configuration.
              </div>
            )}
          </div>
        </div>

        {/* Footer Actions */}
        <div className="px-6 py-4 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-wrap items-center justify-between gap-4">
          <label className="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400 cursor-pointer">
            <input
              type="checkbox"
              checked={replaceExisting}
              onChange={(e) => setReplaceExisting(e.target.checked)}
              className="rounded text-indigo-600 focus:ring-indigo-500 dark:bg-slate-800 dark:border-slate-700"
            />
            <span>Replace existing stages & fields (otherwise safely merges new fields)</span>
          </label>

          <div className="flex items-center gap-3">
            <button
              onClick={onClose}
              className="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
            >
              Close
            </button>
            <button
              onClick={handleApply}
              disabled={applyTemplateMutation.isPending || !selectedTemplate}
              className="flex items-center gap-2 px-5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 rounded-lg shadow-sm shadow-indigo-600/30 transition-all"
            >
              {applyTemplateMutation.isPending ? (
                <>
                  <RefreshCw className="w-4 h-4 animate-spin" />
                  <span>Applying Template...</span>
                </>
              ) : (
                <>
                  <Sparkles className="w-4 h-4" />
                  <span>Apply {selectedTemplate?.name || 'Template'}</span>
                </>
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
