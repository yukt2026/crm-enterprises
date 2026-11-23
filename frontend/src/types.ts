/**
 * Enterprise CRM Type Definitions
 * Strict Type Safety for all CRM modules
 */

export type UserRole =
  | 'Super Admin'
  | 'Admin'
  | 'Manager'
  | 'Sales Executive'
  | 'Support Executive'
  | 'HR'
  | 'Employee'
  | 'Accountant';

export interface User {
  id: string;
  name: string;
  email: string;
  role: UserRole;
  avatar?: string;
  status: 'active' | 'inactive';
  department?: string;
  twoFactorEnabled?: boolean;
  lastActive?: string;
  createdAt: string;
}

export interface RolePermission {
  role: UserRole;
  description: string;
  permissions: string[];
}

export interface Lead {
  id: string;
  name: string;
  company: string;
  email: string;
  phone: string;
  value: number;
  status: 'new' | 'contacted' | 'qualified' | 'lost';
  source: string;
  assignedTo: string; // User ID or Name
  notes: string;
  createdAt: string;
}

export interface Company {
  id: string;
  name: string;
  industry: string;
  website: string;
  size: string; // e.g. "1-10", "11-50", "51-200", "200+"
  revenue: string; // annual revenue
  address: string;
  phone: string;
  createdAt: string;
}

export interface Customer {
  id: string;
  name: string;
  companyId: string;
  companyName?: string;
  email: string;
  phone: string;
  status: 'active' | 'inactive';
  assignedTo: string;
  createdAt: string;
}

export interface Contact {
  id: string;
  name: string;
  companyId: string;
  companyName?: string;
  email: string;
  phone: string;
  role: string; // Job title/role
  isPrimary: boolean;
  createdAt: string;
}

export interface Deal {
  id: string;
  name: string;
  companyId: string;
  companyName?: string;
  value: number;
  stage: 'qualification' | 'proposal' | 'negotiation' | 'won' | 'lost';
  probability: number; // percentage
  closeDate: string;
  assignedTo: string;
  createdAt: string;
}

export interface Product {
  id: string;
  name: string;
  sku: string;
  price: number;
  category: string;
  status: 'active' | 'inactive';
  description: string;
}

export interface Service {
  id: string;
  name: string;
  billingCycle: 'monthly' | 'yearly' | 'one-time';
  price: number;
  category: string;
  status: 'active' | 'inactive';
  description: string;
}

export interface InvoiceItem {
  id: string;
  name: string;
  quantity: number;
  price: number;
  total: number;
}

export interface Quotation {
  id: string;
  quoteNumber: string;
  dealId?: string;
  dealName?: string;
  customerId: string;
  customerName?: string;
  items: InvoiceItem[];
  subtotal: number;
  tax: number;
  total: number;
  status: 'draft' | 'sent' | 'accepted' | 'declined';
  validUntil: string;
  createdAt: string;
}

export interface Invoice {
  id: string;
  invoiceNumber: string;
  customerId: string;
  customerName?: string;
  items: InvoiceItem[];
  subtotal: number;
  tax: number;
  total: number;
  status: 'draft' | 'sent' | 'paid' | 'overdue';
  dueDate: string;
  createdAt: string;
}

export interface Payment {
  id: string;
  invoiceId: string;
  invoiceNumber: string;
  customerName: string;
  amount: number;
  method: 'Credit Card' | 'Bank Transfer' | 'PayPal' | 'Stripe' | 'Check';
  status: 'success' | 'failed' | 'pending';
  paymentDate: string;
}

export interface Project {
  id: string;
  name: string;
  customerId: string;
  customerName?: string;
  status: 'not_started' | 'in_progress' | 'completed' | 'on_hold';
  startDate: string;
  endDate: string;
  teamMembers: string[]; // names or user IDs
  progress: number; // 0 - 100
  createdAt: string;
}

export interface Task {
  id: string;
  title: string;
  description: string;
  priority: 'low' | 'medium' | 'high';
  status: 'todo' | 'in_progress' | 'done';
  assignedTo: string;
  dueDate: string;
  projectId?: string;
  projectName?: string;
  createdAt: string;
}

export interface Meeting {
  id: string;
  title: string;
  description: string;
  date: string;
  time: string;
  attendees: string[]; // names/emails
  meetLink?: string;
  notes?: string;
  createdAt: string;
}

export interface Employee {
  id: string;
  userId?: string;
  name: string;
  email: string;
  department: 'Sales' | 'Marketing' | 'Support' | 'Engineering' | 'HR' | 'Finance' | 'Operations';
  designation: string;
  dateOfJoining: string;
  salary: number;
  status: 'active' | 'suspended' | 'terminated';
}

export interface Attendance {
  id: string;
  employeeId: string;
  employeeName: string;
  date: string;
  checkIn: string;
  checkOut?: string;
  status: 'present' | 'absent' | 'late' | 'leave';
}

export interface Leave {
  id: string;
  employeeId: string;
  employeeName: string;
  type: 'Casual' | 'Sick' | 'Unpaid' | 'Maternity' | 'Paternity';
  startDate: string;
  endDate: string;
  status: 'pending' | 'approved' | 'rejected';
  reason: string;
  createdAt: string;
}

export interface SupportTicket {
  id: string;
  customerId: string;
  customerName?: string;
  subject: string;
  description: string;
  priority: 'low' | 'medium' | 'high';
  status: 'open' | 'in_progress' | 'resolved' | 'closed';
  assignedTo: string;
  createdAt: string;
}

export interface ActivityLog {
  id: string;
  userId: string;
  userName: string;
  action: string;
  module: string;
  details: string;
  ipAddress: string;
  createdAt: string;
}

export interface Notification {
  id: string;
  userId: string;
  title: string;
  message: string;
  read: boolean;
  createdAt: string;
}

export interface SystemSettings {
  companyName: string;
  companyEmail: string;
  companyPhone: string;
  taxRate: number; // e.g. 15 for 15%
  currency: string; // USD, EUR, etc.
  theme: 'light' | 'dark';
}
