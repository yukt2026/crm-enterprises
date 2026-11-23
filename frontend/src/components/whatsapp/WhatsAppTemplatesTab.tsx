import React, { useState } from 'react';
import {
  FileText,
  Plus,
  RefreshCw,
  Send,
  Eye,
  CheckCircle2,
  Clock,
  AlertCircle,
  ExternalLink,
  MessageSquare,
} from 'lucide-react';
import {
  useWhatsAppTemplates,
  useCreateWhatsAppTemplate,
  useSyncWhatsAppTemplates,
  useTestWhatsAppTemplate,
  useWhatsAppAccounts,
} from '../../hooks/useWhatsApp';
import { WhatsAppTemplateItem } from '../../api/types';

interface Props {
  isDark: boolean;
}

export default function WhatsAppTemplatesTab({ isDark }: Props) {
  const [selectedTemplate, setSelectedTemplate] = useState<WhatsAppTemplateItem | null>(null);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isTestModalOpen, setIsTestModalOpen] = useState(false);
  const [testPhoneNumber, setTestPhoneNumber] = useState('');
  const [testParam1, setTestParam1] = useState('Rahul Sharma');
  const [testParam2, setTestParam2] = useState('CRM Enterprises');

  const { data: templates = [], isLoading, refetch } = useWhatsAppTemplates();
  const { data: accounts = [] } = useWhatsAppAccounts();

  const syncMutation = useSyncWhatsAppTemplates();
  const testMutation = useTestWhatsAppTemplate();
  const createMutation = useCreateWhatsAppTemplate();

  // New Template Form
  const [templateName, setTemplateName] = useState('');
  const [category, setCategory] = useState<'MARKETING' | 'UTILITY' | 'AUTHENTICATION'>('MARKETING');
  const [language, setLanguage] = useState('en_US');
  const [bodyText, setBodyText] = useState('');
  const [footerText, setFooterText] = useState('');

  const handleCreateTemplate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!templateName || !bodyText || accounts.length === 0) return;

    await createMutation.mutateAsync({
      whatsapp_account_id: accounts[0].id,
      template_name: templateName.toLowerCase().replace(/\s+/g, '_'),
      category,
      language,
      body_text: bodyText,
      footer_text: footerText || undefined,
    });

    setIsCreateModalOpen(false);
    setTemplateName('');
    setBodyText('');
    setFooterText('');
  };

  const handleSendTestMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTemplate || !testPhoneNumber) return;

    await testMutation.mutateAsync({
      id: selectedTemplate.id,
      data: {
        phone_number: testPhoneNumber,
        parameters: [testParam1, testParam2],
      },
    });

    setIsTestModalOpen(false);
  };

  return (
    <div className="flex-1 flex flex-col overflow-hidden p-6 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold flex items-center gap-2">
            <FileText className="w-5 h-5 text-emerald-500" />
            Meta WhatsApp Templates Directory
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Manage pre-approved WhatsApp message templates required for initiating outbound conversations outside the 24h window.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => syncMutation.mutate(accounts[0]?.id)}
            disabled={syncMutation.isPending}
            className="flex items-center gap-1.5 px-3 py-2 rounded-xl border dark:border-slate-800 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${syncMutation.isPending ? 'animate-spin' : ''}`} />
            Sync Meta WABA
          </button>
          <button
            onClick={() => setIsCreateModalOpen(true)}
            className="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-semibold shadow-xs transition"
          >
            <Plus className="w-4 h-4" />
            Register Template
          </button>
        </div>
      </div>

      {/* Templates Grid */}
      <div className="flex-1 overflow-y-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {isLoading ? (
          <div className="col-span-full p-12 text-center text-xs text-slate-500">Loading templates...</div>
        ) : templates.length === 0 ? (
          <div className="col-span-full p-12 text-center text-slate-500 space-y-2">
            <FileText className="w-10 h-10 mx-auto text-slate-400" />
            <div className="text-sm font-semibold text-slate-700 dark:text-slate-300">No message templates</div>
            <p className="text-xs">Click "Sync Meta WABA" to import your pre-approved templates from Meta.</p>
          </div>
        ) : (
          templates.map((tpl) => (
            <div
              key={tpl.id}
              className={`p-4 rounded-xl border flex flex-col justify-between transition ${
                isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'
              }`}
            >
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <span
                    className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded-full ${
                      tpl.category === 'MARKETING'
                        ? 'bg-purple-500/10 text-purple-500'
                        : tpl.category === 'UTILITY'
                        ? 'bg-blue-500/10 text-blue-500'
                        : 'bg-emerald-500/10 text-emerald-500'
                    }`}
                  >
                    {tpl.category}
                  </span>
                  <span className="flex items-center gap-1 text-[11px] font-semibold text-emerald-500">
                    <CheckCircle2 className="w-3.5 h-3.5" /> Approved
                  </span>
                </div>

                <div>
                  <h3 className="text-sm font-bold font-mono text-slate-900 dark:text-white">
                    {tpl.template_name}
                  </h3>
                  <span className="text-[10px] text-slate-400">Lang: {tpl.language}</span>
                </div>

                <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/40 text-xs font-sans text-slate-700 dark:text-slate-300 whitespace-pre-wrap leading-relaxed line-clamp-4">
                  {tpl.body_text}
                </div>

                {tpl.footer_text && (
                  <div className="text-[10px] text-slate-400 italic">Footer: {tpl.footer_text}</div>
                )}
              </div>

              <div className="pt-3 mt-3 border-t dark:border-slate-800 flex items-center justify-between text-xs">
                <span className="text-[10px] text-slate-400">
                  Sent: <strong>{tpl.sent_count || 0}</strong>
                </span>
                <div className="space-x-1">
                  <button
                    onClick={() => {
                      setSelectedTemplate(tpl);
                      setIsTestModalOpen(true);
                    }}
                    className="px-2 py-1 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-semibold text-[11px] hover:bg-emerald-500/20 inline-flex items-center gap-1"
                  >
                    <Send className="w-3 h-3" /> Test
                  </button>
                  <button
                    onClick={() => setSelectedTemplate(tpl)}
                    className="p-1 rounded text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                    title="Preview"
                  >
                    <Eye className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          ))
        )}
      </div>

      {/* Preview Modal */}
      {selectedTemplate && !isTestModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
          <div className="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border dark:border-slate-800 p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between border-b dark:border-slate-800 pb-3">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white font-mono">
                {selectedTemplate.template_name}
              </h3>
              <button
                onClick={() => setSelectedTemplate(null)}
                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                ✕
              </button>
            </div>

            {/* WhatsApp Phone Mock Preview */}
            <div className="p-4 rounded-xl bg-emerald-950/20 border border-emerald-500/20 space-y-2">
              <div className="text-[10px] text-emerald-400 uppercase font-bold tracking-wider">
                WhatsApp Preview Bubble
              </div>
              <div className="p-3.5 rounded-2xl rounded-tl-xs bg-white dark:bg-slate-800 text-xs shadow-sm space-y-2 text-slate-800 dark:text-slate-200 leading-relaxed">
                <div>{selectedTemplate.body_text}</div>
                {selectedTemplate.footer_text && (
                  <div className="text-[10px] text-slate-400">{selectedTemplate.footer_text}</div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Test Message Modal */}
      {isTestModalOpen && selectedTemplate && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
          <div className="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border dark:border-slate-800 p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between border-b dark:border-slate-800 pb-3">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Send className="w-4 h-4 text-emerald-500" />
                Dispatch Test Message
              </h3>
              <button
                onClick={() => setIsTestModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleSendTestMessage} className="space-y-3.5 text-xs">
              <div>
                <label className="block text-slate-500 mb-1">Recipient Phone Number (with Country Code)</label>
                <input
                  type="text"
                  required
                  placeholder="+91 98765 43210"
                  value={testPhoneNumber}
                  onChange={(e) => setTestPhoneNumber(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent font-mono"
                />
              </div>

              <div>
                <label className="block text-slate-500 mb-1">Variable {'{{1}}'} (Customer Name)</label>
                <input
                  type="text"
                  value={testParam1}
                  onChange={(e) => setTestParam1(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                />
              </div>

              <div>
                <label className="block text-slate-500 mb-1">Variable {'{{2}}'} (Company / Order)</label>
                <input
                  type="text"
                  value={testParam2}
                  onChange={(e) => setTestParam2(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsTestModalOpen(false)}
                  className="px-4 py-2 rounded-xl border dark:border-slate-700 font-medium"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={testMutation.isPending}
                  className="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-semibold transition"
                >
                  {testMutation.isPending ? 'Sending...' : 'Dispatch Test'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Create Template Modal */}
      {isCreateModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
          <div className="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border dark:border-slate-800 p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between border-b dark:border-slate-800 pb-3">
              <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Plus className="w-4 h-4 text-emerald-500" />
                Register WhatsApp Template
              </h3>
              <button
                onClick={() => setIsCreateModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleCreateTemplate} className="space-y-3.5 text-xs">
              <div>
                <label className="block text-slate-500 mb-1">Template Name (lowercase, alphanumeric, underscores)</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. order_dispatch_update"
                  value={templateName}
                  onChange={(e) => setTemplateName(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent font-mono"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-500 mb-1">Category</label>
                  <select
                    value={category}
                    onChange={(e) => setCategory(e.target.value as any)}
                    className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                  >
                    <option value="MARKETING">Marketing ($0.050/conv)</option>
                    <option value="UTILITY">Utility ($0.015/conv)</option>
                    <option value="AUTHENTICATION">Authentication ($0.030/conv)</option>
                  </select>
                </div>

                <div>
                  <label className="block text-slate-500 mb-1">Language</label>
                  <select
                    value={language}
                    onChange={(e) => setLanguage(e.target.value)}
                    className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                  >
                    <option value="en_US">English (US)</option>
                    <option value="hi">Hindi</option>
                    <option value="es">Spanish</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-slate-500 mb-1">
                  Body Text with Variable Tokens (e.g. {'{{1}}'}, {'{{2}}'})
                </label>
                <textarea
                  rows={4}
                  required
                  placeholder="Hi {{1}}, your order #{{2}} from {{3}} has been packed and dispatched!"
                  value={bodyText}
                  onChange={(e) => setBodyText(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                />
              </div>

              <div>
                <label className="block text-slate-500 mb-1">Footer Text (Optional)</label>
                <input
                  type="text"
                  placeholder="Reply STOP to unsubscribe."
                  value={footerText}
                  onChange={(e) => setFooterText(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsCreateModalOpen(false)}
                  className="px-4 py-2 rounded-xl border dark:border-slate-700 font-medium"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createMutation.isPending}
                  className="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-semibold transition"
                >
                  {createMutation.isPending ? 'Registering...' : 'Register Template'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
