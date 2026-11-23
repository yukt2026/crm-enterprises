import React, { useState } from 'react';
import {
  Sliders,
  Plus,
  Trash2,
  CheckCircle2,
  HelpCircle,
  Loader2,
  Tag,
  Hash,
  Calendar,
  Layers,
  FileText,
  DollarSign,
  Mail,
  Phone,
  Link,
  Paperclip,
  CheckSquare,
  ListFilter,
  X,
  AlertCircle,
} from 'lucide-react';
import {
  useCustomFieldDefinitions,
  useCreateCustomFieldDefinition,
  useDeleteCustomFieldDefinition,
} from '../../hooks/useSharedServices';
import { CustomFieldDefinition } from '../../api/types';

interface CustomFieldsBuilderProps {
  theme?: 'light' | 'dark';
}

const SUPPORTED_ENTITIES = [
  { id: 'lead', label: 'Sales Leads' },
  { id: 'deal', label: 'Deals & Opportunities' },
  { id: 'contact', label: 'Customer Contacts' },
  { id: 'company', label: 'Companies & Accounts' },
  { id: 'customer', label: 'Client Accounts' },
  { id: 'project', label: 'Client Projects' },
  { id: 'task', label: 'Tasks' },
  { id: 'ticket', label: 'Support Tickets' },
  { id: 'employee', label: 'Employees & Staff' },
  { id: 'invoice', label: 'Invoices & Billing' },
];

const FIELD_TYPES = [
  { id: 'text', label: 'Text (Single Line)', icon: FileText },
  { id: 'long_text', label: 'Long Text (Textarea)', icon: FileText },
  { id: 'number', label: 'Number / Quantity', icon: Hash },
  { id: 'date', label: 'Date Picker', icon: Calendar },
  { id: 'select', label: 'Dropdown Select', icon: ListFilter },
  { id: 'multi_select', label: 'Multi-Select Tags', icon: Layers },
  { id: 'checkbox', label: 'Boolean Checkbox', icon: CheckSquare },
  { id: 'email', label: 'Email Address', icon: Mail },
  { id: 'phone', label: 'Phone Number', icon: Phone },
  { id: 'url', label: 'Website / Link URL', icon: Link },
  { id: 'currency', label: 'Monetary Currency', icon: DollarSign },
  { id: 'file', label: 'File Attachment Reference', icon: Paperclip },
];

export default function CustomFieldsBuilder({ theme = 'dark' }: CustomFieldsBuilderProps) {
  const [selectedEntity, setSelectedEntity] = useState<string>('lead');
  const [isModalOpen, setIsModalOpen] = useState(false);

  // Form State for creating a new definition
  const [name, setName] = useState('');
  const [fieldKey, setFieldKey] = useState('');
  const [type, setType] = useState<any>('text');
  const [optionsStr, setOptionsStr] = useState('');
  const [isRequired, setIsRequired] = useState(false);
  const [defaultValue, setDefaultValue] = useState('');
  const [displayOrder, setDisplayOrder] = useState(1);

  const { data: fields = [], isLoading } = useCustomFieldDefinitions(selectedEntity);
  const createMutation = useCreateCustomFieldDefinition();
  const deleteMutation = useDeleteCustomFieldDefinition();

  const handleNameChange = (val: string) => {
    setName(val);
    setFieldKey(val.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, ''));
  };

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;

    const options = (type === 'select' || type === 'multi_select')
      ? optionsStr.split(',').map((s) => s.trim()).filter(Boolean)
      : undefined;

    createMutation.mutate(
      {
        entity_type: selectedEntity as any,
        name: name.trim(),
        field_key: fieldKey.trim() || undefined,
        type,
        options,
        is_required: isRequired,
        default_value: defaultValue.trim() || undefined,
        display_order: Number(displayOrder) || 1,
      },
      {
        onSuccess: () => {
          setIsModalOpen(false);
          setName('');
          setFieldKey('');
          setType('text');
          setOptionsStr('');
          setIsRequired(false);
          setDefaultValue('');
          setDisplayOrder(fields.length + 1);
        },
      }
    );
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <Sliders className="h-5 w-5 text-emerald-500" />
            Custom Fields & Layout Metadata
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Extend CRM entities with business-specific attributes using scalable metadata schemas without raw SQL schema changes.
          </p>
        </div>

        <button
          onClick={() => setIsModalOpen(true)}
          className="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold flex items-center gap-2 shadow-xs transition-colors self-start sm:self-auto"
        >
          <Plus className="h-4 w-4" />
          <span>Add Custom Field</span>
        </button>
      </div>

      {/* Entity Selector Tabs */}
      <div
        className={`p-1 rounded-2xl border flex items-center gap-1 overflow-x-auto ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
        }`}
      >
        {SUPPORTED_ENTITIES.map((ent) => (
          <button
            key={ent.id}
            onClick={() => setSelectedEntity(ent.id)}
            className={`px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all ${
              selectedEntity === ent.id
                ? 'bg-emerald-600 text-white shadow-xs'
                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50'
            }`}
          >
            {ent.label}
          </button>
        ))}
      </div>

      {/* Field Definitions Table */}
      {isLoading ? (
        <div className="py-24 text-center text-xs text-slate-400 flex flex-col items-center gap-2">
          <Loader2 className="h-6 w-6 text-emerald-500 animate-spin" />
          <span>Loading custom fields for {selectedEntity}...</span>
        </div>
      ) : fields.length === 0 ? (
        <div
          className={`py-16 rounded-2xl border text-center flex flex-col items-center justify-center p-6 ${
            theme === 'dark' ? 'bg-slate-900/40 border-slate-800' : 'bg-white border-slate-200'
          }`}
        >
          <Tag className="h-8 w-8 text-slate-600 mb-2" />
          <h4 className="font-semibold text-xs text-slate-200">No custom fields defined</h4>
          <p className="text-xs text-slate-400 mt-1 max-w-sm">
            Add custom metadata fields for {selectedEntity} records to capture unique company requirements.
          </p>
          <button
            onClick={() => setIsModalOpen(true)}
            className="mt-4 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium flex items-center gap-1.5 transition-colors"
          >
            <Plus className="h-3.5 w-3.5 text-emerald-400" />
            <span>Create First Field</span>
          </button>
        </div>
      ) : (
        <div
          className={`rounded-2xl border overflow-hidden ${
            theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          }`}
        >
          <table className="w-full text-left text-xs">
            <thead
              className={`border-b ${
                theme === 'dark' ? 'bg-slate-950/60 border-slate-800 text-slate-400' : 'bg-slate-50 border-slate-200 text-slate-600'
              }`}
            >
              <tr>
                <th className="py-3 px-4 font-semibold">Field Name & Key</th>
                <th className="py-3 px-4 font-semibold">Type</th>
                <th className="py-3 px-4 font-semibold">Required</th>
                <th className="py-3 px-4 font-semibold">Default Value</th>
                <th className="py-3 px-4 font-semibold">Order</th>
                <th className="py-3 px-4 font-semibold text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/40">
              {fields.map((f) => (
                <tr
                  key={f.id}
                  className={`transition-colors ${
                    theme === 'dark' ? 'hover:bg-slate-800/40' : 'hover:bg-slate-50'
                  }`}
                >
                  <td className="py-3 px-4">
                    <span className="font-semibold text-slate-200">{f.name}</span>
                    <span className="block font-mono text-[11px] text-slate-400">
                      key: {f.field_key}
                    </span>
                  </td>
                  <td className="py-3 px-4">
                    <span className="px-2 py-0.5 rounded-md text-[10px] font-mono bg-slate-800 text-emerald-400 border border-slate-700">
                      {f.type}
                    </span>
                    {f.options && f.options.length > 0 && (
                      <span className="block text-[10px] text-slate-400 mt-0.5">
                        Options: {f.options.join(', ')}
                      </span>
                    )}
                  </td>
                  <td className="py-3 px-4">
                    {f.is_required ? (
                      <span className="text-emerald-400 font-semibold text-[11px]">Yes</span>
                    ) : (
                      <span className="text-slate-400 text-[11px]">Optional</span>
                    )}
                  </td>
                  <td className="py-3 px-4 font-mono text-slate-300">
                    {f.default_value || '—'}
                  </td>
                  <td className="py-3 px-4 font-mono text-slate-400">
                    {f.display_order}
                  </td>
                  <td className="py-3 px-4 text-right">
                    <button
                      onClick={() => {
                        if (confirm(`Delete custom field "${f.name}"?`)) {
                          deleteMutation.mutate(f.id);
                        }
                      }}
                      className="p-1.5 rounded-lg hover:bg-rose-950/40 text-slate-400 hover:text-rose-400 transition-colors"
                      title="Delete Field"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* CREATE MODAL */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-black/70 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div
            className={`w-full max-w-lg rounded-2xl border shadow-2xl overflow-hidden p-6 ${
              theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
            }`}
          >
            <div className="flex items-center justify-between border-b border-inherit pb-3 mb-4">
              <h3 className="font-bold text-sm flex items-center gap-2">
                <Plus className="h-4 w-4 text-emerald-400" />
                Add Custom Field to {SUPPORTED_ENTITIES.find((e) => e.id === selectedEntity)?.label}
              </h3>
              <button
                onClick={() => setIsModalOpen(false)}
                className="p-1 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-slate-200"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleCreate} className="space-y-4 text-xs">
              <div>
                <label className="block font-medium text-slate-300 mb-1">
                  Field Display Label *
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. VAT Registration ID, Budget Tier"
                  value={name}
                  onChange={(e) => handleNameChange(e.target.value)}
                  className="w-full px-3 py-2 rounded-xl border border-slate-700 bg-transparent focus:outline-none focus:border-emerald-500 text-slate-200"
                />
              </div>

              <div>
                <label className="block font-medium text-slate-300 mb-1">
                  Field Key (Internal Identifier)
                </label>
                <input
                  type="text"
                  placeholder="vat_registration_id"
                  value={fieldKey}
                  onChange={(e) => setFieldKey(e.target.value)}
                  className="w-full px-3 py-2 rounded-xl border border-slate-700 bg-transparent font-mono focus:outline-none focus:border-emerald-500 text-slate-200"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-slate-300 mb-1">
                    Field Data Type *
                  </label>
                  <select
                    value={type}
                    onChange={(e) => setType(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-700 bg-slate-900 focus:outline-none focus:border-emerald-500 text-slate-200"
                  >
                    {FIELD_TYPES.map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.label}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block font-medium text-slate-300 mb-1">
                    Display Order
                  </label>
                  <input
                    type="number"
                    min={1}
                    value={displayOrder}
                    onChange={(e) => setDisplayOrder(Number(e.target.value))}
                    className="w-full px-3 py-2 rounded-xl border border-slate-700 bg-transparent focus:outline-none focus:border-emerald-500 text-slate-200"
                  />
                </div>
              </div>

              {(type === 'select' || type === 'multi_select') && (
                <div>
                  <label className="block font-medium text-slate-300 mb-1">
                    Options (Comma separated)
                  </label>
                  <input
                    type="text"
                    placeholder="Small, Medium, Enterprise, Fortune 500"
                    value={optionsStr}
                    onChange={(e) => setOptionsStr(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl border border-slate-700 bg-transparent focus:outline-none focus:border-emerald-500 text-slate-200"
                  />
                </div>
              )}

              <div>
                <label className="block font-medium text-slate-300 mb-1">
                  Default Value (Optional)
                </label>
                <input
                  type="text"
                  placeholder="e.g. Standard"
                  value={defaultValue}
                  onChange={(e) => setDefaultValue(e.target.value)}
                  className="w-full px-3 py-2 rounded-xl border border-slate-700 bg-transparent focus:outline-none focus:border-emerald-500 text-slate-200"
                />
              </div>

              <div className="flex items-center gap-2 pt-2">
                <input
                  type="checkbox"
                  id="isRequired"
                  checked={isRequired}
                  onChange={(e) => setIsRequired(e.target.checked)}
                  className="h-4 w-4 rounded accent-emerald-500 cursor-pointer"
                />
                <label htmlFor="isRequired" className="text-slate-300 cursor-pointer">
                  Required field (must be completed during record entry)
                </label>
              </div>

              <div className="pt-3 border-t border-inherit flex items-center justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-3 py-1.5 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createMutation.isPending}
                  className="px-4 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold flex items-center gap-1.5 shadow-xs transition-colors"
                >
                  {createMutation.isPending ? (
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  ) : (
                    <Plus className="h-3.5 w-3.5" />
                  )}
                  <span>Create Field</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
