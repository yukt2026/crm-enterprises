import React, { useState, useMemo } from 'react';
import {
  Globe,
  Layout,
  Plus,
  Trash2,
  RefreshCw,
  Search,
  CheckCircle2,
  XCircle,
  Clock,
  Layers,
  ArrowRight,
  Sliders,
  Sparkles,
  Zap,
  Activity,
  History,
  FileText,
  AlertTriangle,
  ChevronRight,
  ExternalLink,
  HelpCircle,
  Copy,
  Settings,
  Send,
  Eye,
  Check,
  X,
  RotateCcw,
  ShieldCheck,
  Smartphone,
  Tablet,
  Monitor,
  Share2,
  Link,
  FormInput,
  CheckSquare,
  BarChart3,
  PieChart,
  MessageSquare,
  Lock,
} from 'lucide-react';
import {
  useLandingPages,
  useLandingPage,
  useCreateLandingPage,
  useUpdateLandingPage,
  useDeleteLandingPage,
  usePublishLandingPage,
  useUnpublishLandingPage,
  useRollbackLandingPage,
  useLandingPageTemplates,
  useCreateLandingPageFromTemplate,
  useLandingPageAnalytics,
  useForms,
  useForm,
  useCreateForm,
  useUpdateForm,
  useDeleteForm,
  useFormSubmissions,
  useCustomDomains,
  useAddCustomDomain,
  useVerifyCustomDomain,
  useDeleteCustomDomain,
} from '../../hooks/useLandingPageBuilder';
import {
  LandingPageItem,
  LandingPageSection,
  LandingPageTemplateItem,
  FormItem,
  FormField,
  CustomDomainItem,
} from '../../api/types';

interface LandingPageBuilderViewProps {
  theme?: 'light' | 'dark';
}

export const LandingPageBuilderView: React.FC<LandingPageBuilderViewProps> = ({ theme = 'dark' }) => {
  const isDark = theme === 'dark';

  // Navigation Tabs
  const [activeTab, setActiveTab] = useState<'pages' | 'editor' | 'forms' | 'submissions' | 'domains' | 'analytics'>('pages');
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [selectedPageId, setSelectedPageId] = useState<number | null>(null);
  const [selectedFormId, setSelectedFormId] = useState<number | null>(null);

  // Responsive device view switcher for live preview
  const [previewDevice, setPreviewDevice] = useState<'desktop' | 'tablet' | 'mobile'>('desktop');

  // Page Editor state
  const [editorPageName, setEditorPageName] = useState('');
  const [editorSlug, setEditorSlug] = useState('');
  const [editorTitle, setEditorTitle] = useState('');
  const [editorDesc, setEditorDesc] = useState('');
  const [editorSections, setEditorSections] = useState<LandingPageSection[]>([]);
  const [editorPrimaryColor, setEditorPrimaryColor] = useState('#4f46e5');
  const [editorFontFamily, setEditorFontFamily] = useState('Inter, sans-serif');
  const [editorMetaTitle, setEditorMetaTitle] = useState('');
  const [editorMetaDesc, setEditorMetaDesc] = useState('');
  const [selectedSectionId, setSelectedSectionId] = useState<string | null>(null);
  const [editorCustomDomainId, setEditorCustomDomainId] = useState<number | null>(null);

  // Modals / Drawers
  const [showTemplatesModal, setShowTemplatesModal] = useState(false);
  const [showNewPageModal, setShowNewPageModal] = useState(false);
  const [newPageForm, setNewPageForm] = useState({ name: '', slug: '', title: '' });
  const [showRevisionsDrawer, setShowRevisionsDrawer] = useState(false);
  const [showSeoDrawer, setShowSeoDrawer] = useState(false);
  const [publishChangelog, setPublishChangelog] = useState('');
  const [showPublishModal, setShowPublishModal] = useState(false);

  // Form Builder state
  const [showNewFormModal, setShowNewFormModal] = useState(false);
  const [formBuilderData, setFormBuilderData] = useState<Partial<FormItem>>({
    name: '',
    slug: '',
    title: 'Get In Touch',
    schema: {
      fields: [
        { name: 'name', label: 'Full Name', type: 'text', required: true, placeholder: 'e.g. Rahul Verma' },
        { name: 'email', label: 'Business Email', type: 'email', required: true, placeholder: 'name@company.com' },
        { name: 'phone', label: 'Phone Number', type: 'phone', required: false, placeholder: '+91 98765 43210' },
      ],
    },
    settings: {
      submit_button_text: 'Submit Inquiry',
      success_type: 'message',
      success_message: 'Thank you! We will get in touch shortly.',
      require_consent: true,
      consent_text: 'I agree to the privacy policy and consent to receiving communications.',
    },
  });

  // Custom Domains state
  const [newDomainInput, setNewDomainInput] = useState('');
  const [showAddDomainModal, setShowAddDomainModal] = useState(false);

  // Data Queries
  const { data: pages = [], isLoading: pagesLoading, refetch: refetchPages } = useLandingPages({
    status: statusFilter === 'all' ? undefined : statusFilter,
    search: searchQuery || undefined,
  });

  const { data: activePage, refetch: refetchActivePage } = useLandingPage(selectedPageId);
  const { data: templates = [] } = useLandingPageTemplates();
  const { data: forms = [], isLoading: formsLoading, refetch: refetchForms } = useForms();
  const { data: submissionsData } = useFormSubmissions(selectedFormId);
  const { data: domains = [], refetch: refetchDomains } = useCustomDomains();
  const { data: analyticsData } = useLandingPageAnalytics(selectedPageId);

  // Mutations
  const createPageMut = useCreateLandingPage();
  const updatePageMut = useUpdateLandingPage();
  const deletePageMut = useDeleteLandingPage();
  const publishPageMut = usePublishLandingPage();
  const unpublishPageMut = useUnpublishLandingPage();
  const rollbackPageMut = useRollbackLandingPage();
  const createFromTemplateMut = useCreateLandingPageFromTemplate();

  const createFormMut = useCreateForm();
  const updateFormMut = useUpdateForm();
  const deleteFormMut = useDeleteForm();

  const addDomainMut = useAddCustomDomain();
  const verifyDomainMut = useVerifyCustomDomain();
  const deleteDomainMut = useDeleteCustomDomain();

  // Load page into editor
  const handleOpenEditor = (page: LandingPageItem) => {
    setSelectedPageId(page.id);
    setEditorPageName(page.name);
    setEditorSlug(page.slug);
    setEditorTitle(page.title || page.name);
    setEditorDesc(page.description || '');
    setEditorSections(page.sections || []);
    setEditorPrimaryColor(page.branding?.primary_color || '#4f46e5');
    setEditorFontFamily(page.branding?.font_family || 'Inter, sans-serif');
    setEditorMetaTitle(page.seo_metadata?.meta_title || page.name);
    setEditorMetaDesc(page.seo_metadata?.meta_description || '');
    setEditorCustomDomainId(page.custom_domain_id || null);
    setSelectedSectionId(page.sections?.[0]?.id || null);
    setActiveTab('editor');
  };

  // Save current editor state
  const handleSavePage = async () => {
    if (!selectedPageId) return;
    await updatePageMut.mutateAsync({
      id: selectedPageId,
      data: {
        name: editorPageName,
        slug: editorSlug,
        title: editorTitle,
        description: editorDesc,
        custom_domain_id: editorCustomDomainId,
        sections: editorSections,
        branding: {
          primary_color: editorPrimaryColor,
          font_family: editorFontFamily,
        },
        seo_metadata: {
          meta_title: editorMetaTitle,
          meta_description: editorMetaDesc,
        },
      },
    });
    refetchActivePage();
    refetchPages();
  };

  // Add new section to page
  const handleAddSection = (type: LandingPageSection['type']) => {
    const newId = `sec_${type}_${Date.now()}`;
    const newSection: LandingPageSection = {
      id: newId,
      type,
      headline:
        type === 'hero'
          ? 'Next-Generation Revenue Acceleration'
          : type === 'features'
          ? 'Key Product Capabilities'
          : type === 'form_embed'
          ? 'Contact Our Strategy Team'
          : type === 'faq'
          ? 'Frequently Asked Questions'
          : 'Transform Your Growth Today',
      subheadline: 'Designed for enterprise performance, lead capture, and instant qualification.',
      cta_text: 'Get Started Today',
      is_visible: true,
      features:
        type === 'features'
          ? [
              { title: 'Unified Data Model', desc: 'Sync omnichannel chats, telephony, and CRM records.' },
              { title: 'Zero Code Automation', desc: 'Deterministic visual workflows with instant trigger evaluation.' },
            ]
          : undefined,
      faqs:
        type === 'faq'
          ? [
              { q: 'How fast is deployment?', a: 'You can connect domains and publish responsive pages in seconds.' },
              { q: 'Is lead ingestion automated?', a: 'Yes, every submission feeds directly into CRM pipeline with UTM tags.' },
            ]
          : undefined,
    };

    setEditorSections((prev) => [...prev, newSection]);
    setSelectedSectionId(newId);
  };

  // Active editing section
  const activeSection = useMemo(
    () => editorSections.find((s) => s.id === selectedSectionId) || null,
    [editorSections, selectedSectionId]
  );

  // Update section data
  const updateActiveSection = (updates: Partial<LandingPageSection>) => {
    if (!selectedSectionId) return;
    setEditorSections((prev) =>
      prev.map((s) => (s.id === selectedSectionId ? { ...s, ...updates } : s))
    );
  };

  // Delete section
  const handleDeleteSection = (secId: string) => {
    setEditorSections((prev) => prev.filter((s) => s.id !== secId));
    if (selectedSectionId === secId) {
      setSelectedSectionId(null);
    }
  };

  // KPI Metrics
  const metrics = useMemo(() => {
    const total = pages.length;
    const published = pages.filter((p) => p.status === 'published').length;
    const totalSubmissions = pages.reduce((acc, p) => acc + (p.submission_count || 0), 0);
    const totalViews = pages.reduce((acc, p) => acc + (p.view_count || 0), 0);
    const avgConv = totalViews > 0 ? ((totalSubmissions / totalViews) * 100).toFixed(1) : '0.0';
    return { total, published, totalSubmissions, totalViews, avgConv };
  }, [pages]);

  return (
    <div className={`h-full flex flex-col ${isDark ? 'bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-800'}`}>
      {/* Top Header */}
      <div className={`px-6 py-4 border-b flex items-center justify-between ${isDark ? 'border-slate-800 bg-slate-900/60' : 'border-slate-200 bg-white'}`}>
        <div className="flex items-center space-x-3">
          <div className="p-2.5 rounded-xl bg-gradient-to-tr from-cyan-600 to-indigo-600 text-white shadow-md shadow-cyan-500/20">
            <Globe className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center space-x-2">
              <h1 className="text-xl font-bold tracking-tight">Landing Page & Form Builder</h1>
              <span className="px-2 py-0.5 text-xs font-semibold rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                Lead Acquisition
              </span>
            </div>
            <p className="text-xs text-slate-400 mt-0.5">
              High-converting responsive pages, custom forms, DNS-verified domains & UTM capture
            </p>
          </div>
        </div>

        {/* Tab Switcher & Action Buttons */}
        <div className="flex items-center space-x-3">
          <div className={`flex rounded-lg p-1 border ${isDark ? 'bg-slate-900 border-slate-800' : 'bg-slate-100 border-slate-200'}`}>
            <button
              onClick={() => setActiveTab('pages')}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-all flex items-center space-x-1.5 ${
                activeTab === 'pages'
                  ? isDark
                    ? 'bg-slate-800 text-white shadow'
                    : 'bg-white text-slate-900 shadow'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <Layout className="w-3.5 h-3.5" />
              <span>Landing Pages</span>
            </button>
            <button
              onClick={() => setActiveTab('editor')}
              disabled={!selectedPageId}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-all flex items-center space-x-1.5 ${
                activeTab === 'editor'
                  ? isDark
                    ? 'bg-slate-800 text-white shadow'
                    : 'bg-white text-slate-900 shadow'
                  : selectedPageId
                  ? 'text-slate-400 hover:text-slate-200'
                  : 'text-slate-600 cursor-not-allowed opacity-50'
              }`}
            >
              <Sliders className="w-3.5 h-3.5" />
              <span>Section Editor</span>
            </button>
            <button
              onClick={() => setActiveTab('forms')}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-all flex items-center space-x-1.5 ${
                activeTab === 'forms'
                  ? isDark
                    ? 'bg-slate-800 text-white shadow'
                    : 'bg-white text-slate-900 shadow'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <FormInput className="w-3.5 h-3.5" />
              <span>Form Builder</span>
            </button>
            <button
              onClick={() => setActiveTab('submissions')}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-all flex items-center space-x-1.5 ${
                activeTab === 'submissions'
                  ? isDark
                    ? 'bg-slate-800 text-white shadow'
                    : 'bg-white text-slate-900 shadow'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <FileText className="w-3.5 h-3.5" />
              <span>Submissions</span>
            </button>
            <button
              onClick={() => setActiveTab('domains')}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-all flex items-center space-x-1.5 ${
                activeTab === 'domains'
                  ? isDark
                    ? 'bg-slate-800 text-white shadow'
                    : 'bg-white text-slate-900 shadow'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <Link className="w-3.5 h-3.5" />
              <span>Custom Domains</span>
            </button>
            <button
              onClick={() => setActiveTab('analytics')}
              disabled={!selectedPageId}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-all flex items-center space-x-1.5 ${
                activeTab === 'analytics'
                  ? isDark
                    ? 'bg-slate-800 text-white shadow'
                    : 'bg-white text-slate-900 shadow'
                  : selectedPageId
                  ? 'text-slate-400 hover:text-slate-200'
                  : 'text-slate-600 cursor-not-allowed opacity-50'
              }`}
            >
              <BarChart3 className="w-3.5 h-3.5" />
              <span>Analytics</span>
            </button>
          </div>

          <button
            onClick={() => setShowTemplatesModal(true)}
            className={`px-3 py-1.5 text-xs font-medium rounded-lg border flex items-center space-x-1.5 transition-all ${
              isDark
                ? 'border-indigo-500/30 text-indigo-300 hover:bg-indigo-500/10'
                : 'border-indigo-200 text-indigo-700 hover:bg-indigo-50'
            }`}
          >
            <Sparkles className="w-3.5 h-3.5" />
            <span>Templates</span>
          </button>

          <button
            onClick={() => setShowNewPageModal(true)}
            className="px-3.5 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white flex items-center space-x-1.5 shadow-sm transition-all"
          >
            <Plus className="w-4 h-4" />
            <span>Create Page</span>
          </button>
        </div>
      </div>

      {/* -------------------------------------------------------------------- */}
      {/* TAB 1: LANDING PAGES DIRECTORY */}
      {/* -------------------------------------------------------------------- */}
      {activeTab === 'pages' && (
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {/* Quick Metrics */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="text-xs text-slate-400 font-medium">Total Landing Pages</div>
              <div className="text-2xl font-bold mt-1 text-indigo-400">{metrics.total}</div>
              <div className="text-xs text-slate-500 mt-1">Configured in workspace</div>
            </div>
            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="text-xs text-slate-400 font-medium">Live & Published</div>
              <div className="text-2xl font-bold mt-1 text-emerald-400">{metrics.published}</div>
              <div className="text-xs text-slate-500 mt-1">Receiving web traffic</div>
            </div>
            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="text-xs text-slate-400 font-medium">Total Form Submissions</div>
              <div className="text-2xl font-bold mt-1 text-cyan-400">{metrics.totalSubmissions}</div>
              <div className="text-xs text-slate-500 mt-1">Direct to CRM pipeline</div>
            </div>
            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="text-xs text-slate-400 font-medium">Avg Conversion Rate</div>
              <div className="text-2xl font-bold mt-1 text-amber-400">{metrics.avgConv}%</div>
              <div className="text-xs text-slate-500 mt-1">Submissions / Page Views</div>
            </div>
          </div>

          {/* Filter Bar */}
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center space-x-2">
              {['all', 'published', 'draft', 'unpublished'].map((st) => (
                <button
                  key={st}
                  onClick={() => setStatusFilter(st)}
                  className={`px-3 py-1.5 rounded-lg text-xs font-medium uppercase tracking-wider transition-all ${
                    statusFilter === st
                      ? 'bg-indigo-600 text-white'
                      : isDark
                      ? 'bg-slate-900 text-slate-400 hover:text-slate-200 border border-slate-800'
                      : 'bg-white text-slate-600 hover:text-slate-900 border border-slate-200'
                  }`}
                >
                  {st}
                </button>
              ))}
            </div>

            <div className="relative w-72">
              <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
              <input
                type="text"
                placeholder="Search landing pages..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className={`w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border outline-none transition-all ${
                  isDark
                    ? 'bg-slate-900 border-slate-800 focus:border-indigo-500 text-white'
                    : 'bg-white border-slate-200 focus:border-indigo-500 text-slate-900'
                }`}
              />
            </div>
          </div>

          {/* Landing Pages Cards Grid */}
          {pagesLoading ? (
            <div className="text-center py-12 text-slate-400 text-xs">Loading landing pages...</div>
          ) : pages.length === 0 ? (
            <div className={`p-12 text-center rounded-2xl border ${isDark ? 'bg-slate-900/30 border-slate-800' : 'bg-white border-slate-200'}`}>
              <Globe className="w-12 h-12 text-slate-500 mx-auto mb-3 opacity-40" />
              <h3 className="text-sm font-semibold">No Landing Pages Found</h3>
              <p className="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                Create modular lead-generation landing pages, connect custom domains, and capture verified CRM prospects.
              </p>
              <div className="flex justify-center space-x-3 mt-4">
                <button
                  onClick={() => setShowTemplatesModal(true)}
                  className="px-3.5 py-1.5 text-xs font-medium rounded-lg border border-indigo-500/30 text-indigo-400 hover:bg-indigo-500/10"
                >
                  Browse Templates
                </button>
                <button
                  onClick={() => setShowNewPageModal(true)}
                  className="px-3.5 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white"
                >
                  Create Page
                </button>
              </div>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {pages.map((p) => {
                const isPublished = p.status === 'published';
                return (
                  <div
                    key={p.id}
                    className={`rounded-xl border p-5 transition-all hover:border-indigo-500/50 flex flex-col justify-between ${
                      isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'
                    }`}
                  >
                    <div>
                      <div className="flex items-center justify-between mb-2">
                        <span
                          className={`px-2 py-0.5 text-xs rounded-full font-medium ${
                            isPublished
                              ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                              : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                          }`}
                        >
                          {p.status.toUpperCase()}
                        </span>
                        <span className="text-[10px] font-mono uppercase text-slate-400">
                          v{p.current_version} • {p.sections?.length || 0} sections
                        </span>
                      </div>

                      <h3 className="font-semibold text-sm line-clamp-1">{p.name}</h3>
                      <p className="text-xs text-slate-400 mt-1 font-mono flex items-center space-x-1">
                        <span className="text-indigo-400">/p/{p.slug}</span>
                      </p>

                      <div className={`mt-3 p-2.5 rounded-lg border text-xs space-y-1 ${isDark ? 'bg-slate-950/50 border-slate-800/80' : 'bg-slate-50 border-slate-200'}`}>
                        <div className="flex items-center justify-between text-slate-400">
                          <span>Page Views:</span>
                          <span className="text-slate-200 font-semibold">{p.view_count}</span>
                        </div>
                        <div className="flex items-center justify-between text-slate-400">
                          <span>Form Submissions:</span>
                          <span className="text-cyan-400 font-semibold">{p.submission_count}</span>
                        </div>
                        {p.custom_domain && (
                          <div className="flex items-center justify-between text-slate-400 pt-1 border-t border-slate-800">
                            <span>Domain:</span>
                            <span className="text-emerald-400 font-mono text-[11px] truncate max-w-[160px]">
                              {p.custom_domain.domain}
                            </span>
                          </div>
                        )}
                      </div>
                    </div>

                    <div className="mt-4 pt-3 border-t flex items-center justify-between border-slate-800/60">
                      <div className="flex items-center space-x-1.5">
                        <button
                          title="View Analytics"
                          onClick={() => {
                            setSelectedPageId(p.id);
                            setActiveTab('analytics');
                          }}
                          className="p-1.5 text-xs rounded-md bg-cyan-500/10 text-cyan-400 hover:bg-cyan-500/20 transition-all"
                        >
                          <BarChart3 className="w-3.5 h-3.5" />
                        </button>

                        {isPublished ? (
                          <button
                            title="Unpublish"
                            onClick={() => unpublishPageMut.mutate(p.id)}
                            className="p-1.5 text-xs rounded-md bg-amber-500/10 text-amber-400 hover:bg-amber-500/20 transition-all"
                          >
                            <Clock className="w-3.5 h-3.5" />
                          </button>
                        ) : (
                          <button
                            title="Publish"
                            onClick={() => publishPageMut.mutate({ id: p.id })}
                            className="p-1.5 text-xs rounded-md bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 transition-all"
                          >
                            <CheckCircle2 className="w-3.5 h-3.5" />
                          </button>
                        )}

                        <button
                          title="Delete Page"
                          onClick={() => {
                            if (confirm(`Are you sure you want to delete "${p.name}"?`)) {
                              deletePageMut.mutate(p.id);
                            }
                          }}
                          className="p-1.5 text-xs rounded-md bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-all"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>

                      <button
                        onClick={() => handleOpenEditor(p)}
                        className="px-3 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white flex items-center space-x-1 transition-all"
                      >
                        <span>Edit Page</span>
                        <ChevronRight className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* TAB 2: SECTION EDITOR & LIVE PREVIEW */}
      {/* -------------------------------------------------------------------- */}
      {activeTab === 'editor' && (
        <div className="flex-1 flex overflow-hidden">
          {/* Left Panel: Sections List & Configurations */}
          <div className={`w-96 border-r flex flex-col ${isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'}`}>
            <div className="p-4 border-b flex items-center justify-between border-slate-800">
              <div className="flex items-center space-x-2">
                <Sliders className="w-4 h-4 text-indigo-400" />
                <h3 className="font-bold text-sm">Sections & Styling</h3>
              </div>
              <div className="flex items-center space-x-1.5">
                <button
                  onClick={() => setShowSeoDrawer(true)}
                  className="p-1 text-slate-400 hover:text-white"
                  title="SEO & Branding"
                >
                  <Share2 className="w-4 h-4" />
                </button>
                <button
                  onClick={() => setShowRevisionsDrawer(true)}
                  className="p-1 text-slate-400 hover:text-white"
                  title="Revision History"
                >
                  <History className="w-4 h-4" />
                </button>
              </div>
            </div>

            {/* Quick Section Add Toolbar */}
            <div className={`p-3 border-b flex items-center space-x-1.5 overflow-x-auto ${isDark ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'}`}>
              <button
                onClick={() => handleAddSection('hero')}
                className="px-2 py-1 text-[11px] font-semibold rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 hover:bg-indigo-500/20"
              >
                + Hero
              </button>
              <button
                onClick={() => handleAddSection('features')}
                className="px-2 py-1 text-[11px] font-semibold rounded bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 hover:bg-cyan-500/20"
              >
                + Features
              </button>
              <button
                onClick={() => handleAddSection('form_embed')}
                className="px-2 py-1 text-[11px] font-semibold rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20"
              >
                + Form
              </button>
              <button
                onClick={() => handleAddSection('faq')}
                className="px-2 py-1 text-[11px] font-semibold rounded bg-purple-500/10 text-purple-400 border border-purple-500/20 hover:bg-purple-500/20"
              >
                + FAQ
              </button>
            </div>

            {/* Sections Accordion */}
            <div className="flex-1 overflow-y-auto p-4 space-y-3">
              {editorSections.map((sec, idx) => (
                <div
                  key={sec.id}
                  onClick={() => setSelectedSectionId(sec.id)}
                  className={`p-3 rounded-xl border cursor-pointer transition-all ${
                    selectedSectionId === sec.id
                      ? 'border-indigo-500 bg-indigo-500/10 ring-1 ring-indigo-500'
                      : isDark
                      ? 'border-slate-800 bg-slate-950/40 hover:border-slate-700'
                      : 'border-slate-200 bg-white hover:border-slate-300'
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <span className="text-[10px] font-bold uppercase tracking-wider text-indigo-400 font-mono">
                      #{idx + 1} • {sec.type}
                    </span>
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        handleDeleteSection(sec.id);
                      }}
                      className="text-slate-500 hover:text-red-400 p-0.5"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                  <div className="text-xs font-semibold mt-1 truncate">{sec.headline || 'Untitled Section'}</div>
                </div>
              ))}

              {/* Active Section Config Fields */}
              {activeSection && (
                <div className={`mt-4 p-4 rounded-xl border space-y-3 ${isDark ? 'bg-slate-950 border-slate-800' : 'bg-slate-100 border-slate-200'}`}>
                  <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">Configure Section</h4>
                  <div>
                    <label className="text-[11px] font-semibold text-slate-400">Headline</label>
                    <input
                      type="text"
                      value={activeSection.headline || ''}
                      onChange={(e) => updateActiveSection({ headline: e.target.value })}
                      className="w-full mt-1 px-2.5 py-1 text-xs rounded bg-slate-900 border border-slate-800 text-white outline-none"
                    />
                  </div>
                  <div>
                    <label className="text-[11px] font-semibold text-slate-400">Subheadline</label>
                    <textarea
                      rows={2}
                      value={activeSection.subheadline || ''}
                      onChange={(e) => updateActiveSection({ subheadline: e.target.value })}
                      className="w-full mt-1 px-2.5 py-1 text-xs rounded bg-slate-900 border border-slate-800 text-white outline-none"
                    />
                  </div>
                  {activeSection.type === 'hero' && (
                    <div>
                      <label className="text-[11px] font-semibold text-slate-400">CTA Button Text</label>
                      <input
                        type="text"
                        value={activeSection.cta_text || ''}
                        onChange={(e) => updateActiveSection({ cta_text: e.target.value })}
                        className="w-full mt-1 px-2.5 py-1 text-xs rounded bg-slate-900 border border-slate-800 text-white outline-none"
                      />
                    </div>
                  )}
                  {activeSection.type === 'form_embed' && (
                    <div>
                      <label className="text-[11px] font-semibold text-slate-400">Embed Custom Form</label>
                      <select
                        value={activeSection.form_slug || ''}
                        onChange={(e) => updateActiveSection({ form_slug: e.target.value })}
                        className="w-full mt-1 px-2.5 py-1 text-xs rounded bg-slate-900 border border-slate-800 text-white outline-none"
                      >
                        <option value="">Select form...</option>
                        {forms.map((f) => (
                          <option key={f.id} value={f.slug}>
                            {f.name} ({f.slug})
                          </option>
                        ))}
                      </select>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Right Panel: Interactive Device Live Preview */}
          <div className="flex-1 flex flex-col overflow-hidden bg-slate-950">
            {/* Sub-header / Device toggles */}
            <div className="p-3 border-b flex items-center justify-between border-slate-800 bg-slate-900/80">
              <div className="flex items-center space-x-2">
                <button
                  onClick={() => setPreviewDevice('desktop')}
                  className={`p-1.5 rounded ${previewDevice === 'desktop' ? 'bg-indigo-600 text-white' : 'text-slate-400'}`}
                >
                  <Monitor className="w-4 h-4" />
                </button>
                <button
                  onClick={() => setPreviewDevice('tablet')}
                  className={`p-1.5 rounded ${previewDevice === 'tablet' ? 'bg-indigo-600 text-white' : 'text-slate-400'}`}
                >
                  <Tablet className="w-4 h-4" />
                </button>
                <button
                  onClick={() => setPreviewDevice('mobile')}
                  className={`p-1.5 rounded ${previewDevice === 'mobile' ? 'bg-indigo-600 text-white' : 'text-slate-400'}`}
                >
                  <Smartphone className="w-4 h-4" />
                </button>
                <span className="text-xs text-slate-400 ml-2 font-mono">
                  {previewDevice.toUpperCase()} PREVIEW • /p/{editorSlug}
                </span>
              </div>

              <div className="flex items-center space-x-2">
                <button
                  onClick={handleSavePage}
                  disabled={updatePageMut.isPending}
                  className="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-800 hover:bg-slate-700 text-white border border-slate-700"
                >
                  Save Draft
                </button>
                <button
                  onClick={() => setShowPublishModal(true)}
                  className="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white flex items-center space-x-1"
                >
                  <CheckCircle2 className="w-3.5 h-3.5" />
                  <span>Publish</span>
                </button>
              </div>
            </div>

            {/* Live Canvas Screen Frame */}
            <div className="flex-1 overflow-y-auto p-6 flex justify-center bg-slate-950">
              <div
                className={`transition-all duration-300 rounded-2xl border border-slate-800 shadow-2xl bg-white text-slate-900 overflow-hidden flex flex-col ${
                  previewDevice === 'mobile'
                    ? 'w-[375px]'
                    : previewDevice === 'tablet'
                    ? 'w-[768px]'
                    : 'w-full max-w-4xl'
                }`}
                style={{ fontFamily: editorFontFamily }}
              >
                {/* Simulated Header */}
                <header className="px-6 py-4 border-b flex items-center justify-between bg-slate-50">
                  <div className="font-bold text-base tracking-tight" style={{ color: editorPrimaryColor }}>
                    {editorPageName || 'Brand Logo'}
                  </div>
                  <nav className="hidden md:flex space-x-4 text-xs font-medium text-slate-600">
                    <span>Solutions</span>
                    <span>Platform</span>
                    <span>Resources</span>
                  </nav>
                </header>

                {/* Rendered Sections */}
                <div className="divide-y divide-slate-100">
                  {editorSections.map((sec) => (
                    <div key={sec.id} className="p-8">
                      {sec.type === 'hero' && (
                        <div className="text-center py-8 max-w-2xl mx-auto space-y-4">
                          <span
                            className="text-[10px] uppercase font-bold tracking-widest px-3 py-1 rounded-full text-white"
                            style={{ backgroundColor: editorPrimaryColor }}
                          >
                            {sec.badge || 'Enterprise AI CRM'}
                          </span>
                          <h2 className="text-3xl font-extrabold tracking-tight text-slate-900 leading-tight">
                            {sec.headline}
                          </h2>
                          <p className="text-sm text-slate-600 max-w-lg mx-auto">{sec.subheadline}</p>
                          <div className="pt-2">
                            <button
                              className="px-6 py-2.5 text-xs font-bold rounded-lg text-white shadow-lg transition-transform hover:scale-105"
                              style={{ backgroundColor: editorPrimaryColor }}
                            >
                              {sec.cta_text || 'Get Started Now'}
                            </button>
                          </div>
                        </div>
                      )}

                      {sec.type === 'features' && (
                        <div className="space-y-6">
                          <h3 className="text-xl font-bold text-center">{sec.headline}</h3>
                          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {(sec.features || []).map((f, i) => (
                              <div key={i} className="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-1">
                                <h4 className="font-bold text-sm" style={{ color: editorPrimaryColor }}>
                                  {f.title}
                                </h4>
                                <p className="text-xs text-slate-600">{f.desc}</p>
                              </div>
                            ))}
                          </div>
                        </div>
                      )}

                      {sec.type === 'form_embed' && (
                        <div className="max-w-md mx-auto p-6 rounded-2xl border border-slate-200 bg-slate-50 space-y-3">
                          <h3 className="font-bold text-base text-center">{sec.headline}</h3>
                          <p className="text-xs text-slate-500 text-center">{sec.subheadline}</p>
                          <div className="space-y-2 pt-2">
                            <input
                              type="text"
                              disabled
                              placeholder="Full Name"
                              className="w-full px-3 py-2 text-xs rounded-lg border bg-white"
                            />
                            <input
                              type="email"
                              disabled
                              placeholder="Business Email"
                              className="w-full px-3 py-2 text-xs rounded-lg border bg-white"
                            />
                            <button
                              disabled
                              className="w-full py-2 text-xs font-bold text-white rounded-lg opacity-90"
                              style={{ backgroundColor: editorPrimaryColor }}
                            >
                              Submit Inquiry
                            </button>
                          </div>
                        </div>
                      )}

                      {sec.type === 'faq' && (
                        <div className="space-y-4 max-w-xl mx-auto">
                          <h3 className="text-lg font-bold text-center">{sec.headline}</h3>
                          {(sec.faqs || []).map((faq, i) => (
                            <div key={i} className="border-b pb-2">
                              <h5 className="font-semibold text-xs text-slate-800">{faq.q}</h5>
                              <p className="text-xs text-slate-600 mt-1">{faq.a}</p>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* TAB 3: CUSTOM FORM BUILDER */}
      {/* -------------------------------------------------------------------- */}
      {activeTab === 'forms' && (
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-base font-bold">Custom Lead Forms</h2>
              <p className="text-xs text-slate-400">Embeddable schemas with validation rules, consent checkboxes & honeypot spam defense</p>
            </div>
            <button
              onClick={() => setShowNewFormModal(true)}
              className="px-3.5 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white flex items-center space-x-1.5"
            >
              <Plus className="w-4 h-4" />
              <span>Create Form</span>
            </button>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {forms.map((f) => (
              <div
                key={f.id}
                className={`p-5 rounded-xl border flex flex-col justify-between ${
                  isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'
                }`}
              >
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 font-bold">
                      {f.status.toUpperCase()}
                    </span>
                    <span className="text-xs text-slate-400 font-mono">slug: {f.slug}</span>
                  </div>
                  <h4 className="font-semibold text-sm">{f.name}</h4>
                  <p className="text-xs text-slate-400 mt-1 line-clamp-2">{f.description || 'Custom capture form.'}</p>

                  <div className="mt-3 p-2.5 rounded-lg border border-slate-800 bg-slate-950/40 text-xs space-y-1">
                    <div className="flex justify-between text-slate-400">
                      <span>Fields:</span>
                      <span className="font-semibold text-white">{f.schema?.fields?.length || 0} fields</span>
                    </div>
                    <div className="flex justify-between text-slate-400">
                      <span>Submissions:</span>
                      <span className="font-semibold text-cyan-400">{f.submissions_count || 0} leads</span>
                    </div>
                  </div>
                </div>

                <div className="mt-4 pt-3 border-t border-slate-800 flex justify-between items-center">
                  <button
                    onClick={() => {
                      setSelectedFormId(f.id);
                      setActiveTab('submissions');
                    }}
                    className="text-xs text-indigo-400 hover:text-indigo-300 font-semibold"
                  >
                    View Submissions
                  </button>
                  <button
                    onClick={() => {
                      if (confirm(`Delete form "${f.name}"?`)) deleteFormMut.mutate(f.id);
                    }}
                    className="text-slate-500 hover:text-red-400 p-1"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* TAB 4: SUBMISSIONS LEDGER */}
      {/* -------------------------------------------------------------------- */}
      {activeTab === 'submissions' && (
        <div className="flex-1 overflow-y-auto p-6 space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-base font-bold">Form Submissions Audit Trail</h2>
              <p className="text-xs text-slate-400">Captured UTM attributes, explicit consent, and automatic CRM Lead linking</p>
            </div>
            <select
              value={selectedFormId || ''}
              onChange={(e) => setSelectedFormId(parseInt(e.target.value) || null)}
              className="px-3 py-1.5 text-xs rounded-lg bg-slate-900 border border-slate-800 text-white"
            >
              <option value="">Select form to view submissions...</option>
              {forms.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.name} ({f.submissions_count} submissions)
                </option>
              ))}
            </select>
          </div>

          <div className={`rounded-xl border overflow-hidden ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
            <table className="w-full text-xs text-left">
              <thead className="border-b border-slate-800 bg-slate-900 text-slate-400 uppercase font-semibold">
                <tr>
                  <th className="px-4 py-3">ID</th>
                  <th className="px-4 py-3">Linked Lead</th>
                  <th className="px-4 py-3">Campaign / UTM</th>
                  <th className="px-4 py-3">Consent</th>
                  <th className="px-4 py-3">Spam Score</th>
                  <th className="px-4 py-3">Submitted At</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800">
                {(submissionsData?.data || []).map((sub) => (
                  <tr key={sub.id} className="hover:bg-slate-800/30">
                    <td className="px-4 py-3 font-mono text-slate-400">#{sub.id}</td>
                    <td className="px-4 py-3">
                      {sub.lead ? (
                        <div>
                          <div className="font-semibold text-white">{sub.lead.name}</div>
                          <div className="text-[10px] text-slate-400">{sub.lead.email || sub.lead.phone}</div>
                        </div>
                      ) : (
                        <span className="text-slate-500">Unlinked</span>
                      )}
                    </td>
                    <td className="px-4 py-3 font-mono text-indigo-400">
                      {sub.utm_data?.utm_source || 'organic'} / {sub.utm_data?.utm_campaign || 'direct'}
                    </td>
                    <td className="px-4 py-3">
                      {sub.consent_given ? (
                        <span className="text-emerald-400 flex items-center space-x-1">
                          <CheckCircle2 className="w-3.5 h-3.5" />
                          <span>Consented</span>
                        </span>
                      ) : (
                        <span className="text-slate-500">No consent</span>
                      )}
                    </td>
                    <td className="px-4 py-3 font-mono">
                      {sub.spam_score > 0 ? (
                        <span className="text-red-400 font-bold">{sub.spam_score} (Spam)</span>
                      ) : (
                        <span className="text-emerald-400">0.00 (Clean)</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-slate-400">{new Date(sub.created_at).toLocaleString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* TAB 5: CUSTOM DOMAINS */}
      {/* -------------------------------------------------------------------- */}
      {activeTab === 'domains' && (
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-base font-bold">Custom Domain Mapping</h2>
              <p className="text-xs text-slate-400">Host landing pages under your branded domain with automatic SSL and DNS verification</p>
            </div>
            <button
              onClick={() => setShowAddDomainModal(true)}
              className="px-3.5 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white flex items-center space-x-1.5"
            >
              <Plus className="w-4 h-4" />
              <span>Connect Domain</span>
            </button>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {domains.map((d) => (
              <div key={d.id} className="p-5 rounded-xl border border-slate-800 bg-slate-900/60 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-2">
                    <Globe className="w-4 h-4 text-indigo-400" />
                    <span className="font-bold text-sm text-white">{d.domain}</span>
                  </div>
                  <span
                    className={`px-2 py-0.5 text-xs rounded-full font-bold uppercase ${
                      d.verification_status === 'verified'
                        ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                        : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                    }`}
                  >
                    {d.verification_status}
                  </span>
                </div>

                <div className="p-3 rounded-lg bg-slate-950 border border-slate-800 text-xs font-mono space-y-1">
                  <div className="text-slate-400">DNS TXT Record:</div>
                  <div className="text-indigo-300 break-all">{d.verification_token}</div>
                </div>

                <div className="flex items-center justify-between pt-2">
                  <div className="text-[11px] text-slate-400 flex items-center space-x-1">
                    <Lock className="w-3 h-3 text-emerald-400" />
                    <span>SSL Status: {d.ssl_status.toUpperCase()}</span>
                  </div>

                  <div className="flex items-center space-x-2">
                    <button
                      onClick={() => verifyDomainMut.mutate(d.id)}
                      disabled={verifyDomainMut.isPending}
                      className="px-3 py-1 text-xs rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white"
                    >
                      Verify DNS
                    </button>
                    <button
                      onClick={() => deleteDomainMut.mutate(d.id)}
                      className="text-slate-500 hover:text-red-400 p-1"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* TAB 6: ANALYTICS DASHBOARD */}
      {/* -------------------------------------------------------------------- */}
      {activeTab === 'analytics' && activePage && (
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          <div>
            <h2 className="text-base font-bold">{activePage.name} • Conversion Funnel</h2>
            <p className="text-xs text-slate-400">Consent-aware page views, CTA interactions, and UTM attribution breakdown</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/60">
              <div className="text-xs text-slate-400">Page Views</div>
              <div className="text-2xl font-bold mt-1 text-white">{analyticsData?.metrics?.views || activePage.view_count}</div>
            </div>
            <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/60">
              <div className="text-xs text-slate-400">CTA Button Clicks</div>
              <div className="text-2xl font-bold mt-1 text-indigo-400">{analyticsData?.metrics?.cta_clicks || 0}</div>
            </div>
            <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/60">
              <div className="text-xs text-slate-400">Form Submissions</div>
              <div className="text-2xl font-bold mt-1 text-cyan-400">{analyticsData?.metrics?.submissions || activePage.submission_count}</div>
            </div>
            <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/60">
              <div className="text-xs text-slate-400">Conversion Rate</div>
              <div className="text-2xl font-bold mt-1 text-emerald-400">{analyticsData?.metrics?.conversion_rate || 0}%</div>
            </div>
          </div>
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* MODAL: CONNECT CUSTOM DOMAIN */}
      {/* -------------------------------------------------------------------- */}
      {showAddDomainModal && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50">
          <div className="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-2xl">
            <h3 className="text-base font-bold mb-3">Connect Custom Domain</h3>
            <p className="text-xs text-slate-400 mb-4">
              Enter your domain name (e.g. <code>get.yourcompany.com</code>). You will be given a TXT verification record.
            </p>
            <input
              type="text"
              placeholder="e.g. promo.acmecorp.com"
              value={newDomainInput}
              onChange={(e) => setNewDomainInput(e.target.value)}
              className="w-full px-3 py-2 text-xs rounded-lg bg-slate-950 border border-slate-800 text-white outline-none"
            />
            <div className="flex justify-end space-x-3 mt-6">
              <button onClick={() => setShowAddDomainModal(false)} className="px-3 py-1.5 text-xs text-slate-400">
                Cancel
              </button>
              <button
                onClick={async () => {
                  if (!newDomainInput.trim()) return;
                  await addDomainMut.mutateAsync(newDomainInput.trim());
                  setShowAddDomainModal(false);
                  setNewDomainInput('');
                }}
                disabled={addDomainMut.isPending}
                className="px-4 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white"
              >
                Add Domain
              </button>
            </div>
          </div>
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* MODAL: CREATE PAGE */}
      {/* -------------------------------------------------------------------- */}
      {showNewPageModal && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50">
          <div className="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-2xl">
            <h3 className="text-base font-bold mb-4">Create Landing Page</h3>
            <div className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-slate-400">Page Name</label>
                <input
                  type="text"
                  placeholder="e.g. Enterprise Consultation Launch"
                  value={newPageForm.name}
                  onChange={(e) => setNewPageForm({ ...newPageForm, name: e.target.value })}
                  className="w-full mt-1 px-3 py-1.5 text-xs rounded-lg bg-slate-950 border border-slate-800 text-white outline-none"
                />
              </div>
              <div>
                <label className="text-xs font-semibold text-slate-400">Page Title</label>
                <input
                  type="text"
                  placeholder="e.g. Scale Your B2B Operations"
                  value={newPageForm.title}
                  onChange={(e) => setNewPageForm({ ...newPageForm, title: e.target.value })}
                  className="w-full mt-1 px-3 py-1.5 text-xs rounded-lg bg-slate-950 border border-slate-800 text-white outline-none"
                />
              </div>
            </div>
            <div className="flex justify-end space-x-3 mt-6">
              <button onClick={() => setShowNewPageModal(false)} className="px-3.5 py-1.5 text-xs text-slate-400">
                Cancel
              </button>
              <button
                onClick={async () => {
                  if (!newPageForm.name.trim()) return;
                  const created = await createPageMut.mutateAsync({
                    name: newPageForm.name,
                    title: newPageForm.title || newPageForm.name,
                  });
                  setShowNewPageModal(false);
                  handleOpenEditor(created as any);
                }}
                disabled={createPageMut.isPending}
                className="px-4 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white"
              >
                Create & Open Editor
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
