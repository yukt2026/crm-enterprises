import React, { useState, useEffect } from 'react';
import { useCRMStore } from '../store/crmStore';

interface ModuleModalsProps {
  module: string;
  isOpen: boolean;
  onClose: () => void;
  editItem?: any;
}

export default function ModuleModals({ module, isOpen, onClose, editItem }: ModuleModalsProps) {
  const { addRecord, updateRecord, theme, moduleData } = useCRMStore();
  const [formData, setFormData] = useState<Record<string, any>>({});

  // Reset form with editing or empty values on mount
  useEffect(() => {
    if (editItem) {
      setFormData(editItem);
    } else {
      // Default empty structures based on module
      setFormData(getDefaultData(module));
    }
  }, [module, editItem, isOpen]);

  if (!isOpen) return null;

  const handleInputChange = (field: string, val: any) => {
    setFormData({ ...formData, [field]: val });
  };

  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      if (editItem) {
        await updateRecord(module, editItem.id, formData);
      } else {
        await addRecord(module, formData);
      }
      onClose();
    } catch (err) {
      console.error('Error submitting form', err);
    }
  };

  const companiesList = moduleData.companies || [];
  const customersList = moduleData.customers || [];
  const invoicesList = moduleData.invoices || [];
  const projectsList = moduleData.projects || [];
  const employeesList = moduleData.employees || [];

  return (
    <div className="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto">
      <div className={`w-full max-w-xl rounded-2xl border p-6 shadow-2xl transition-all duration-200 ${
        theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
      }`}>
        <div className="flex items-center justify-between border-b border-inherit pb-3 mb-4">
          <h2 className="font-bold text-lg capitalize">
            {editItem ? 'Edit' : 'Add New'} {module.replace(/s$/, '')}
          </h2>
          <button onClick={onClose} className="text-sm font-semibold hover:text-emerald-500">
            ✕
          </button>
        </div>

        <form onSubmit={handleFormSubmit} className="space-y-4">
          <div className="max-h-[60vh] overflow-y-auto pr-1 space-y-4">
            
            {/* LEADS & DEALS FIELDS */}
            {module === 'leads' && (
              <>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Lead Name *</label>
                    <input
                      type="text" required
                      value={formData.name || ''}
                      onChange={(e) => handleInputChange('name', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Company *</label>
                    <input
                      type="text" required
                      value={formData.company || ''}
                      onChange={(e) => handleInputChange('company', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Email *</label>
                    <input
                      type="email" required
                      value={formData.email || ''}
                      onChange={(e) => handleInputChange('email', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Phone</label>
                    <input
                      type="text"
                      value={formData.phone || ''}
                      onChange={(e) => handleInputChange('phone', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Contract Value ($) *</label>
                    <input
                      type="number" required
                      value={formData.value || 0}
                      onChange={(e) => handleInputChange('value', parseFloat(e.target.value))}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Lead Source</label>
                    <input
                      type="text"
                      value={formData.source || ''}
                      onChange={(e) => handleInputChange('source', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Pipeline Status</label>
                    <select
                      value={formData.status || 'new'}
                      onChange={(e) => handleInputChange('status', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="new">New</option>
                      <option value="contacted">Contacted</option>
                      <option value="qualified">Qualified</option>
                      <option value="lost">Lost</option>
                    </select>
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Assigned Executive</label>
                    <input
                      type="text"
                      value={formData.assignedTo || ''}
                      onChange={(e) => handleInputChange('assignedTo', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400 block mb-1">Internal Notes</label>
                  <textarea
                    rows={3}
                    value={formData.notes || ''}
                    onChange={(e) => handleInputChange('notes', e.target.value)}
                    className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                  />
                </div>
              </>
            )}

            {/* DEALS MODULE */}
            {module === 'deals' && (
              <>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Deal Name *</label>
                    <input
                      type="text" required
                      value={formData.name || ''}
                      onChange={(e) => handleInputChange('name', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Associated Company</label>
                    <select
                      value={formData.companyId || ''}
                      onChange={(e) => {
                        const comp = companiesList.find((c) => c.id === e.target.value);
                        handleInputChange('companyId', e.target.value);
                        handleInputChange('companyName', comp ? comp.name : '');
                      }}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="">Select Company</option>
                      {companiesList.map((c) => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                      ))}
                    </select>
                  </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Deal Value ($) *</label>
                    <input
                      type="number" required
                      value={formData.value || 0}
                      onChange={(e) => handleInputChange('value', parseFloat(e.target.value))}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Target Close Date</label>
                    <input
                      type="date"
                      value={formData.closeDate || ''}
                      onChange={(e) => handleInputChange('closeDate', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Pipeline Stage</label>
                    <select
                      value={formData.stage || 'qualification'}
                      onChange={(e) => handleInputChange('stage', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="qualification">Qualification</option>
                      <option value="proposal">Proposal</option>
                      <option value="negotiation">Negotiation</option>
                      <option value="won">Won (Closed)</option>
                      <option value="lost">Lost</option>
                    </select>
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Assigned Executive</label>
                    <input
                      type="text"
                      value={formData.assignedTo || ''}
                      onChange={(e) => handleInputChange('assignedTo', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
              </>
            )}

            {/* CUSTOMERS & COMPANIES */}
            {module === 'companies' && (
              <>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Company Name *</label>
                    <input
                      type="text" required
                      value={formData.name || ''}
                      onChange={(e) => handleInputChange('name', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Industry</label>
                    <input
                      type="text"
                      value={formData.industry || ''}
                      onChange={(e) => handleInputChange('industry', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Website URL</label>
                    <input
                      type="text"
                      value={formData.website || ''}
                      onChange={(e) => handleInputChange('website', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Company Size</label>
                    <select
                      value={formData.size || '1-10'}
                      onChange={(e) => handleInputChange('size', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="1-10">1-10 Employees</option>
                      <option value="11-50">11-50 Employees</option>
                      <option value="51-200">51-200 Employees</option>
                      <option value="200+">200+ Employees</option>
                    </select>
                  </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Annual Revenue</label>
                    <input
                      type="text"
                      value={formData.revenue || ''}
                      onChange={(e) => handleInputChange('revenue', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Corporate Phone</label>
                    <input
                      type="text"
                      value={formData.phone || ''}
                      onChange={(e) => handleInputChange('phone', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400 block mb-1">Company Address</label>
                  <input
                    type="text"
                    value={formData.address || ''}
                    onChange={(e) => handleInputChange('address', e.target.value)}
                    className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                  />
                </div>
              </>
            )}

            {/* CUSTOMERS */}
            {module === 'customers' && (
              <>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Customer Name *</label>
                    <input
                      type="text" required
                      value={formData.name || ''}
                      onChange={(e) => handleInputChange('name', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Associated Company</label>
                    <select
                      value={formData.companyId || ''}
                      onChange={(e) => {
                        const comp = companiesList.find((c) => c.id === e.target.value);
                        handleInputChange('companyId', e.target.value);
                        handleInputChange('companyName', comp ? comp.name : '');
                      }}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="">Select Company</option>
                      {companiesList.map((c) => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                      ))}
                    </select>
                  </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Email *</label>
                    <input
                      type="email" required
                      value={formData.email || ''}
                      onChange={(e) => handleInputChange('email', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Phone</label>
                    <input
                      type="text"
                      value={formData.phone || ''}
                      onChange={(e) => handleInputChange('phone', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Account Status</label>
                    <select
                      value={formData.status || 'active'}
                      onChange={(e) => handleInputChange('status', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
                    </select>
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Manager Assigned</label>
                    <input
                      type="text"
                      value={formData.assignedTo || ''}
                      onChange={(e) => handleInputChange('assignedTo', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
              </>
            )}

            {/* INVOICES */}
            {module === 'invoices' && (
              <>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Invoice Number *</label>
                    <input
                      type="text" required
                      value={formData.invoiceNumber || ''}
                      onChange={(e) => handleInputChange('invoiceNumber', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Client Customer</label>
                    <select
                      value={formData.customerId || ''}
                      onChange={(e) => {
                        const cust = customersList.find((c) => c.id === e.target.value);
                        handleInputChange('customerId', e.target.value);
                        handleInputChange('customerName', cust ? cust.name : '');
                      }}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="">Select Customer</option>
                      {customersList.map((c) => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                      ))}
                    </select>
                  </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Invoice Total Amount ($) *</label>
                    <input
                      type="number" required
                      value={formData.total || 0}
                      onChange={(e) => {
                        const sub = parseFloat(e.target.value);
                        handleInputChange('subtotal', sub);
                        handleInputChange('total', sub * 1.15); // Auto tax compound
                        handleInputChange('tax', sub * 0.15);
                      }}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Due Date</label>
                    <input
                      type="date"
                      value={formData.dueDate || ''}
                      onChange={(e) => handleInputChange('dueDate', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400 block mb-1">Invoice Status</label>
                  <select
                    value={formData.status || 'draft'}
                    onChange={(e) => handleInputChange('status', e.target.value)}
                    className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                  >
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="paid">Paid</option>
                    <option value="overdue">Overdue</option>
                  </select>
                </div>
              </>
            )}

            {/* PROJECTS & TASKS */}
            {module === 'projects' && (
              <>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Project Title *</label>
                    <input
                      type="text" required
                      value={formData.name || ''}
                      onChange={(e) => handleInputChange('name', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Associated Customer</label>
                    <select
                      value={formData.customerId || ''}
                      onChange={(e) => {
                        const cust = customersList.find((c) => c.id === e.target.value);
                        handleInputChange('customerId', e.target.value);
                        handleInputChange('customerName', cust ? cust.name : '');
                      }}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="">Select Customer</option>
                      {customersList.map((c) => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                      ))}
                    </select>
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Start Date</label>
                    <input
                      type="date"
                      value={formData.startDate || ''}
                      onChange={(e) => handleInputChange('startDate', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">End Date</label>
                    <input
                      type="date"
                      value={formData.endDate || ''}
                      onChange={(e) => handleInputChange('endDate', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Completion Progress (%)</label>
                    <input
                      type="number" min="0" max="100"
                      value={formData.progress || 0}
                      onChange={(e) => handleInputChange('progress', parseInt(e.target.value))}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Project Status</label>
                    <select
                      value={formData.status || 'not_started'}
                      onChange={(e) => handleInputChange('status', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="not_started">Not Started</option>
                      <option value="in_progress">In Progress</option>
                      <option value="completed">Completed</option>
                      <option value="on_hold">On Hold</option>
                    </select>
                  </div>
                </div>
              </>
            )}

            {/* TASKS */}
            {module === 'tasks' && (
              <>
                <div>
                  <label className="text-xs font-semibold text-slate-400 block mb-1">Task Title *</label>
                  <input
                    type="text" required
                    value={formData.title || ''}
                    onChange={(e) => handleInputChange('title', e.target.value)}
                    className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                  />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Assigned Employee</label>
                    <input
                      type="text"
                      value={formData.assignedTo || ''}
                      onChange={(e) => handleInputChange('assignedTo', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Target Due Date</label>
                    <input
                      type="date"
                      value={formData.dueDate || ''}
                      onChange={(e) => handleInputChange('dueDate', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Priority</label>
                    <select
                      value={formData.priority || 'medium'}
                      onChange={(e) => handleInputChange('priority', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="low">Low</option>
                      <option value="medium">Medium</option>
                      <option value="high">High</option>
                    </select>
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Workflow Status</label>
                    <select
                      value={formData.status || 'todo'}
                      onChange={(e) => handleInputChange('status', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="todo">To Do</option>
                      <option value="in_progress">In Progress</option>
                      <option value="done">Done</option>
                    </select>
                  </div>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400 block mb-1">Task Details</label>
                  <textarea
                    rows={2}
                    value={formData.description || ''}
                    onChange={(e) => handleInputChange('description', e.target.value)}
                    className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                  />
                </div>
              </>
            )}

            {/* SUPPORT TICKETS */}
            {module === 'tickets' && (
              <>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Ticket Subject *</label>
                    <input
                      type="text" required
                      value={formData.subject || ''}
                      onChange={(e) => handleInputChange('subject', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Customer Client</label>
                    <select
                      value={formData.customerId || ''}
                      onChange={(e) => {
                        const cust = customersList.find((c) => c.id === e.target.value);
                        handleInputChange('customerId', e.target.value);
                        handleInputChange('customerName', cust ? cust.name : '');
                      }}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="">Select Customer</option>
                      {customersList.map((c) => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                      ))}
                    </select>
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Ticket Priority</label>
                    <select
                      value={formData.priority || 'medium'}
                      onChange={(e) => handleInputChange('priority', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="low">Low</option>
                      <option value="medium">Medium</option>
                      <option value="high">High</option>
                    </select>
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Assigned Expert</label>
                    <input
                      type="text"
                      value={formData.assignedTo || ''}
                      onChange={(e) => handleInputChange('assignedTo', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400 block mb-1">Workflow Status</label>
                  <select
                    value={formData.status || 'open'}
                    onChange={(e) => handleInputChange('status', e.target.value)}
                    className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                  >
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                  </select>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400 block mb-1">Problem Description</label>
                  <textarea
                    rows={4}
                    value={formData.description || ''}
                    onChange={(e) => handleInputChange('description', e.target.value)}
                    className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                  />
                </div>
              </>
            )}

            {/* MEETINGS */}
            {module === 'meetings' && (
              <>
                <div>
                  <label className="text-xs font-semibold text-slate-400 block mb-1">Meeting Title *</label>
                  <input
                    type="text" required
                    value={formData.title || ''}
                    onChange={(e) => handleInputChange('title', e.target.value)}
                    className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                  />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Date</label>
                    <input
                      type="date"
                      value={formData.date || ''}
                      onChange={(e) => handleInputChange('date', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Time (HH:MM)</label>
                    <input
                      type="text" placeholder="14:00"
                      value={formData.time || ''}
                      onChange={(e) => handleInputChange('time', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400 block mb-1">Google Meet Link</label>
                  <input
                    type="text" placeholder="https://meet.google.com/..."
                    value={formData.meetLink || ''}
                    onChange={(e) => handleInputChange('meetLink', e.target.value)}
                    className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                  />
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400 block mb-1">Agenda Notes</label>
                  <textarea
                    rows={3}
                    value={formData.description || ''}
                    onChange={(e) => handleInputChange('description', e.target.value)}
                    className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                  />
                </div>
              </>
            )}

            {/* LEAVES */}
            {module === 'leaves' && (
              <>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Associated Employee</label>
                    <select
                      value={formData.employeeId || ''}
                      onChange={(e) => {
                        const emp = employeesList.find((item) => item.id === e.target.value);
                        handleInputChange('employeeId', e.target.value);
                        handleInputChange('employeeName', emp ? emp.name : '');
                      }}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="">Select Employee</option>
                      {employeesList.map((emp) => (
                        <option key={emp.id} value={emp.id}>{emp.name}</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Leave Category</label>
                    <select
                      value={formData.type || 'Casual'}
                      onChange={(e) => handleInputChange('type', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="Casual">Casual Leave</option>
                      <option value="Sick">Sick Leave</option>
                      <option value="Unpaid">Unpaid Leave</option>
                    </select>
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Start Date</label>
                    <input
                      type="date"
                      value={formData.startDate || ''}
                      onChange={(e) => handleInputChange('startDate', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">End Date</label>
                    <input
                      type="date"
                      value={formData.endDate || ''}
                      onChange={(e) => handleInputChange('endDate', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400 block mb-1">Approval Status</label>
                  <select
                    value={formData.status || 'pending'}
                    onChange={(e) => handleInputChange('status', e.target.value)}
                    className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                  >
                    <option value="pending">Pending Review</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                  </select>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400 block mb-1">Reason Description</label>
                  <textarea
                    rows={2}
                    value={formData.reason || ''}
                    onChange={(e) => handleInputChange('reason', e.target.value)}
                    className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                  />
                </div>
              </>
            )}

            {/* EMPLOYEES */}
            {module === 'employees' && (
              <>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Full Name *</label>
                    <input
                      type="text" required
                      value={formData.name || ''}
                      onChange={(e) => handleInputChange('name', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Corporate Email *</label>
                    <input
                      type="email" required
                      value={formData.email || ''}
                      onChange={(e) => handleInputChange('email', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Department</label>
                    <select
                      value={formData.department || 'Sales'}
                      onChange={(e) => handleInputChange('department', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="Sales">Sales</option>
                      <option value="Support">Support</option>
                      <option value="Engineering">Engineering</option>
                      <option value="HR">HR</option>
                      <option value="Finance">Finance</option>
                    </select>
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Designation</label>
                    <input
                      type="text"
                      value={formData.designation || ''}
                      onChange={(e) => handleInputChange('designation', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Annual Salary ($) *</label>
                    <input
                      type="number" required
                      value={formData.salary || 0}
                      onChange={(e) => handleInputChange('salary', parseFloat(e.target.value))}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Status</label>
                    <select
                      value={formData.status || 'active'}
                      onChange={(e) => handleInputChange('status', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="active">Active</option>
                      <option value="suspended">Suspended</option>
                    </select>
                  </div>
                </div>
              </>
            )}

            {/* USERS / ROLES */}
            {module === 'users' && (
              <>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Account Name *</label>
                    <input
                      type="text" required
                      value={formData.name || ''}
                      onChange={(e) => handleInputChange('name', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Corporate Email *</label>
                    <input
                      type="email" required
                      value={formData.email || ''}
                      onChange={(e) => handleInputChange('email', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Assigned Role</label>
                    <select
                      value={formData.role || 'Employee'}
                      onChange={(e) => handleInputChange('role', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    >
                      <option value="Super Admin">Super Admin</option>
                      <option value="Admin">Admin</option>
                      <option value="Manager">Manager</option>
                      <option value="Sales Executive">Sales Executive</option>
                      <option value="Support Executive">Support Executive</option>
                      <option value="HR">HR</option>
                      <option value="Employee">Employee</option>
                      <option value="Accountant">Accountant</option>
                    </select>
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Department</label>
                    <input
                      type="text"
                      value={formData.department || ''}
                      onChange={(e) => handleInputChange('department', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400 block mb-1">Account Status</label>
                  <select
                    value={formData.status || 'active'}
                    onChange={(e) => handleInputChange('status', e.target.value)}
                    className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                  >
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
              </>
            )}

            {/* PRODUCTS */}
            {module === 'products' && (
              <>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Product Title *</label>
                    <input
                      type="text" required
                      value={formData.name || ''}
                      onChange={(e) => handleInputChange('name', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">SKU Code *</label>
                    <input
                      type="text" required
                      value={formData.sku || ''}
                      onChange={(e) => handleInputChange('sku', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Price ($) *</label>
                    <input
                      type="number" required
                      value={formData.price || 0}
                      onChange={(e) => handleInputChange('price', parseFloat(e.target.value))}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-400 block mb-1">Category</label>
                    <input
                      type="text"
                      value={formData.category || ''}
                      onChange={(e) => handleInputChange('category', e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-xl border bg-slate-950/20 border-slate-700 text-slate-100"
                    />
                  </div>
                </div>
              </>
            )}

          </div>

          {/* FORM SUBMISSION BAR */}
          <div className="flex items-center justify-end gap-3 border-t border-inherit pt-4 mt-6">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-xl text-sm font-semibold border hover:opacity-80 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-xl text-sm font-bold shadow-lg shadow-emerald-500/10 transition"
            >
              {editItem ? 'Save Modifications' : 'Add Record'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// Default state helpers based on active module type
function getDefaultData(module: string): Record<string, any> {
  const today = new Date().toISOString().split('T')[0];
  switch (module) {
    case 'leads':
      return { name: '', company: '', email: '', phone: '', value: 10000, status: 'new', source: 'Direct Inbound', notes: '', assignedTo: 'Ranjeet Maurya' };
    case 'deals':
      return { name: '', value: 25000, stage: 'qualification', probability: 20, closeDate: today, assignedTo: 'Elena Rostova' };
    case 'companies':
      return { name: '', industry: 'Software', size: '11-50', revenue: '$5M', phone: '', address: '', website: '' };
    case 'customers':
      return { name: '', email: '', phone: '', status: 'active', assignedTo: 'Ranjeet Maurya' };
    case 'invoices':
      return { invoiceNumber: `INV-2026-${Math.floor(Math.random() * 900 + 100)}`, total: 5000, status: 'sent', dueDate: today };
    case 'projects':
      return { name: '', status: 'not_started', progress: 0, startDate: today, endDate: today };
    case 'tasks':
      return { title: '', priority: 'medium', status: 'todo', dueDate: today, description: '', assignedTo: 'Ranjeet Maurya' };
    case 'tickets':
      return { subject: '', priority: 'medium', status: 'open', description: '', assignedTo: 'Michael Chang' };
    case 'meetings':
      return { title: '', date: today, time: '10:00', meetLink: 'https://meet.google.com/abc-defg-hij', description: '' };
    case 'leaves':
      return { type: 'Casual', status: 'pending', startDate: today, endDate: today, reason: '' };
    case 'employees':
      return { name: '', email: '', department: 'Engineering', designation: 'Developer', dateOfJoining: today, salary: 80000, status: 'active' };
    case 'users':
      return { name: '', email: '', role: 'Employee', department: 'Engineering', status: 'active' };
    case 'products':
      return { name: '', sku: `PROD-${Math.floor(Math.random() * 900 + 100)}`, price: 1000, category: 'Software' };
    default:
      return {};
  }
}
