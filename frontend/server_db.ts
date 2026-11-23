import fs from 'fs';
import path from 'path';
import {
  User,
  Lead,
  Company,
  Customer,
  Contact,
  Deal,
  Product,
  Service,
  Quotation,
  Invoice,
  Payment,
  Project,
  Task,
  Meeting,
  Employee,
  Attendance,
  Leave,
  SupportTicket,
  ActivityLog,
  Notification,
  SystemSettings,
  UserRole
} from './src/types';

const DB_PATH = path.join(process.cwd(), 'crm_db.json');

interface CRMDatabase {
  users: (User & { passwordHash: string; otpSecret?: string })[];
  leads: Lead[];
  companies: Company[];
  customers: Customer[];
  contacts: Contact[];
  deals: Deal[];
  products: Product[];
  services: Service[];
  quotations: Quotation[];
  invoices: Invoice[];
  payments: Payment[];
  projects: Project[];
  tasks: Task[];
  meetings: Meeting[];
  employees: Employee[];
  attendance: Attendance[];
  leaves: Leave[];
  tickets: SupportTicket[];
  activityLogs: ActivityLog[];
  notifications: Notification[];
  settings: SystemSettings;
}

const DEFAULT_SETTINGS: SystemSettings = {
  companyName: 'Acme Enterprise Inc.',
  companyEmail: 'ops@acme.com',
  companyPhone: '+1 (555) 019-2834',
  taxRate: 15,
  currency: 'USD',
  theme: 'light'
};

// Generates complete working seed data
function generateSeedData(): CRMDatabase {
  const users: (User & { passwordHash: string })[] = [
    {
      id: 'usr-john',
      name: 'John Nexus',
      email: 'john@nexus.com',
      role: 'Super Admin',
      status: 'active',
      department: 'Executive',
      twoFactorEnabled: true,
      lastActive: new Date().toISOString(),
      createdAt: '2026-01-10T08:00:00.000Z',
      passwordHash: 'AdminSecurePass2026!'
    },
    {
      id: 'usr-1',
      name: 'Ranjeet Maurya',
      email: 'ranjeetmaurya2033@gmail.com',
      role: 'Super Admin',
      status: 'active',
      department: 'Executive',
      twoFactorEnabled: true,
      lastActive: new Date().toISOString(),
      createdAt: '2026-01-10T08:00:00.000Z',
      passwordHash: 'admin123' // Simulating secure check
    },
    {
      id: 'usr-2',
      name: 'Sarah Jenkins',
      email: 'sarah.j@acme.com',
      role: 'Admin',
      status: 'active',
      department: 'Operations',
      lastActive: new Date().toISOString(),
      createdAt: '2026-01-12T09:30:00.000Z',
      passwordHash: 'admin123'
    },
    {
      id: 'usr-3',
      name: 'David Miller',
      email: 'david.m@acme.com',
      role: 'Manager',
      status: 'active',
      department: 'Sales',
      lastActive: new Date().toISOString(),
      createdAt: '2026-01-15T10:15:00.000Z',
      passwordHash: 'sales123'
    },
    {
      id: 'usr-4',
      name: 'Elena Rostova',
      email: 'elena.r@acme.com',
      role: 'Sales Executive',
      status: 'active',
      department: 'Sales',
      lastActive: new Date().toISOString(),
      createdAt: '2026-01-20T11:00:00.000Z',
      passwordHash: 'sales123'
    },
    {
      id: 'usr-5',
      name: 'Michael Chang',
      email: 'michael.c@acme.com',
      role: 'Support Executive',
      status: 'active',
      department: 'Support',
      lastActive: new Date().toISOString(),
      createdAt: '2026-01-22T14:45:00.000Z',
      passwordHash: 'support123'
    },
    {
      id: 'usr-6',
      name: 'Laura Bennett',
      email: 'laura.b@acme.com',
      role: 'HR',
      status: 'active',
      department: 'HR',
      lastActive: new Date().toISOString(),
      createdAt: '2026-01-25T09:00:00.000Z',
      passwordHash: 'hr123'
    },
    {
      id: 'usr-7',
      name: 'James Cooper',
      email: 'james.c@acme.com',
      role: 'Accountant',
      status: 'active',
      department: 'Finance',
      lastActive: new Date().toISOString(),
      createdAt: '2026-02-01T08:30:00.000Z',
      passwordHash: 'finance123'
    },
    {
      id: 'usr-8',
      name: 'Robert Stark',
      email: 'robert.s@acme.com',
      role: 'Employee',
      status: 'active',
      department: 'Engineering',
      lastActive: new Date().toISOString(),
      createdAt: '2026-02-10T09:15:00.000Z',
      passwordHash: 'emp123'
    }
  ];

  const companies: Company[] = [
    {
      id: 'comp-1',
      name: 'Stark Industries',
      industry: 'Technology & Aerospace',
      website: 'https://starkindustries.com',
      size: '200+',
      revenue: '$500M+',
      address: '10880 Wilshire Blvd, Los Angeles, CA',
      phone: '+1 (310) 555-0145',
      createdAt: '2026-01-15T08:00:00.000Z'
    },
    {
      id: 'comp-2',
      name: 'Wayne Enterprises',
      industry: 'Defense & Conglomerate',
      website: 'https://waynecorp.com',
      size: '200+',
      revenue: '$1B+',
      address: '1007 Mountain Drive, Gotham City, NJ',
      phone: '+1 (201) 555-0199',
      createdAt: '2026-01-18T09:00:00.000Z'
    },
    {
      id: 'comp-3',
      name: 'Cyberdyne Systems',
      industry: 'Artificial Intelligence',
      website: 'https://cyberdyne.ai',
      size: '51-200',
      revenue: '$80M',
      address: '18111 Nordhoff St, Northridge, CA',
      phone: '+1 (818) 555-0123',
      createdAt: '2026-01-22T10:30:00.000Z'
    },
    {
      id: 'comp-4',
      name: 'Umbrella Corporation',
      industry: 'Biotechnology & Pharma',
      website: 'https://umbrellacorp.ph',
      size: '200+',
      revenue: '$300M',
      address: 'Raccoon City Corporate Park, IL',
      phone: '+1 (312) 555-0181',
      createdAt: '2026-02-01T11:00:00.000Z'
    }
  ];

  const leads: Lead[] = [
    {
      id: 'lead-1',
      name: 'Tony Stark',
      company: 'Stark Industries',
      email: 'tony@starkindustries.com',
      phone: '+1 (310) 555-0145',
      value: 120000,
      status: 'qualified',
      source: 'Inbound Inquiry',
      assignedTo: 'Ranjeet Maurya',
      notes: 'Interested in enterprise cloud computing security suites for the main labs.',
      createdAt: '2026-06-15T09:00:00.000Z'
    },
    {
      id: 'lead-2',
      name: 'Bruce Wayne',
      company: 'Wayne Enterprises',
      email: 'bruce@waynecorp.com',
      phone: '+1 (201) 555-0199',
      value: 250000,
      status: 'new',
      source: 'Referral',
      assignedTo: 'Elena Rostova',
      notes: 'Requires scalable supply-chain automation dashboard with high security protocols.',
      createdAt: '2026-06-20T10:00:00.000Z'
    },
    {
      id: 'lead-3',
      name: 'Miles Dyson',
      company: 'Cyberdyne Systems',
      email: 'mdyson@cyberdyne.ai',
      phone: '+1 (818) 555-0123',
      value: 45000,
      status: 'contacted',
      source: 'Google Search',
      assignedTo: 'Elena Rostova',
      notes: 'Initial call completed. Looking for robust DevOps consulting services.',
      createdAt: '2026-06-22T14:30:00.000Z'
    },
    {
      id: 'lead-4',
      name: 'Albert Wesker',
      company: 'Umbrella Corporation',
      email: 'awesker@umbrellacorp.ph',
      phone: '+1 (312) 555-0181',
      value: 95000,
      status: 'lost',
      source: 'Cold Outreach',
      assignedTo: 'Elena Rostova',
      notes: 'Deal lost. Unresponsive after pricing proposal.',
      createdAt: '2026-06-05T11:00:00.000Z'
    },
    {
      id: 'lead-5',
      name: 'Peter Parker',
      company: 'Daily Bugle',
      email: 'peter.parker@dailybugle.com',
      phone: '+1 (212) 555-0192',
      value: 5000,
      status: 'new',
      source: 'Webinar',
      assignedTo: 'David Miller',
      notes: 'Inquired about simple cloud photo library integration for journalists.',
      createdAt: '2026-07-01T09:15:00.000Z'
    }
  ];

  const customers: Customer[] = [
    {
      id: 'cust-1',
      name: 'Tony Stark',
      companyId: 'comp-1',
      companyName: 'Stark Industries',
      email: 'tony@starkindustries.com',
      phone: '+1 (310) 555-0145',
      status: 'active',
      assignedTo: 'Ranjeet Maurya',
      createdAt: '2026-01-20T08:00:00.000Z'
    },
    {
      id: 'cust-2',
      name: 'Lucius Fox',
      companyId: 'comp-2',
      companyName: 'Wayne Enterprises',
      email: 'lfox@waynecorp.com',
      phone: '+1 (201) 555-0188',
      status: 'active',
      assignedTo: 'Elena Rostova',
      createdAt: '2026-02-15T09:00:00.000Z'
    },
    {
      id: 'cust-3',
      name: 'Miles Dyson',
      companyId: 'comp-3',
      companyName: 'Cyberdyne Systems',
      email: 'mdyson@cyberdyne.ai',
      phone: '+1 (818) 555-0123',
      status: 'active',
      assignedTo: 'Elena Rostova',
      createdAt: '2026-03-10T10:00:00.000Z'
    }
  ];

  const contacts: Contact[] = [
    {
      id: 'cont-1',
      name: 'Pepper Potts',
      companyId: 'comp-1',
      companyName: 'Stark Industries',
      email: 'pepper@starkindustries.com',
      phone: '+1 (310) 555-0146',
      role: 'CEO',
      isPrimary: true,
      createdAt: '2026-01-20T08:30:00.000Z'
    },
    {
      id: 'cont-2',
      name: 'Lucius Fox',
      companyId: 'comp-2',
      companyName: 'Wayne Enterprises',
      email: 'lfox@waynecorp.com',
      phone: '+1 (201) 555-0188',
      role: 'VP & COO',
      isPrimary: true,
      createdAt: '2026-02-15T09:15:00.000Z'
    },
    {
      id: 'cont-3',
      name: 'Alfred Pennyworth',
      companyId: 'comp-2',
      companyName: 'Wayne Enterprises',
      email: 'alfred@waynecorp.com',
      phone: '+1 (201) 555-0111',
      role: 'Executive Assistant',
      isPrimary: false,
      createdAt: '2026-02-18T14:00:00.000Z'
    }
  ];

  const deals: Deal[] = [
    {
      id: 'deal-1',
      name: 'Stark Lab Security Suite',
      companyId: 'comp-1',
      companyName: 'Stark Industries',
      value: 120000,
      stage: 'won',
      probability: 100,
      closeDate: '2026-02-28',
      assignedTo: 'Ranjeet Maurya',
      createdAt: '2026-01-22T08:00:00.000Z'
    },
    {
      id: 'deal-2',
      name: 'Wayne Logistics Dashboard',
      companyId: 'comp-2',
      companyName: 'Wayne Enterprises',
      value: 250000,
      stage: 'negotiation',
      probability: 80,
      closeDate: '2026-08-30',
      assignedTo: 'Elena Rostova',
      createdAt: '2026-02-20T09:00:00.000Z'
    },
    {
      id: 'deal-3',
      name: 'Cyberdyne AI Compute Hub',
      companyId: 'comp-3',
      companyName: 'Cyberdyne Systems',
      value: 80000,
      stage: 'proposal',
      probability: 60,
      closeDate: '2026-09-15',
      assignedTo: 'Elena Rostova',
      createdAt: '2026-03-12T10:00:00.000Z'
    },
    {
      id: 'deal-4',
      name: 'Stark Drone AI Upgrade',
      companyId: 'comp-1',
      companyName: 'Stark Industries',
      value: 350000,
      stage: 'qualification',
      probability: 20,
      closeDate: '2026-11-01',
      assignedTo: 'David Miller',
      createdAt: '2026-06-18T11:00:00.000Z'
    }
  ];

  const products: Product[] = [
    {
      id: 'prod-1',
      name: 'Enterprise Cybersecurity Shield',
      sku: 'SEC-ENT-01',
      price: 25000,
      category: 'Software',
      status: 'active',
      description: 'Unified network, client, and container firewall threat monitoring.'
    },
    {
      id: 'prod-2',
      name: 'AI Operations Center Dashboard',
      sku: 'AIOP-DASH-05',
      price: 45000,
      category: 'Software',
      status: 'active',
      description: 'Fully integrated predictive analysis pipeline visualization.'
    },
    {
      id: 'prod-3',
      name: 'Secure Vault Appliance',
      sku: 'HARD-VAULT-99',
      price: 15000,
      category: 'Hardware',
      status: 'active',
      description: 'Physical HSM (Hardware Security Module) rack cabinet with remote encryption keys.'
    }
  ];

  const services: Service[] = [
    {
      id: 'serv-1',
      name: 'SaaS Cloud Hosting & Support',
      billingCycle: 'monthly',
      price: 1500,
      category: 'Infrastructure',
      status: 'active',
      description: 'High availability AWS cluster container maintenance and 24/7 priority support.'
    },
    {
      id: 'serv-2',
      name: 'Security Auditing & Penetration Testing',
      billingCycle: 'one-time',
      price: 18000,
      category: 'Consulting',
      status: 'active',
      description: 'Full whitebox penetration audit with comprehensive standard CVE-report.'
    }
  ];

  const quotations: Quotation[] = [
    {
      id: 'quote-1',
      quoteNumber: 'QT-2026-001',
      dealId: 'deal-1',
      dealName: 'Stark Lab Security Suite',
      customerId: 'cust-1',
      customerName: 'Tony Stark',
      items: [
        { id: 'prod-1', name: 'Enterprise Cybersecurity Shield', quantity: 4, price: 25000, total: 100000 },
        { id: 'serv-2', name: 'Security Auditing & Penetration Testing', quantity: 1, price: 18000, total: 18000 }
      ],
      subtotal: 118000,
      tax: 17700,
      total: 135700,
      status: 'accepted',
      validUntil: '2026-03-31',
      createdAt: '2026-01-25T08:00:00.000Z'
    },
    {
      id: 'quote-2',
      quoteNumber: 'QT-2026-002',
      dealId: 'deal-2',
      dealName: 'Wayne Logistics Dashboard',
      customerId: 'cust-2',
      customerName: 'Lucius Fox',
      items: [
        { id: 'prod-2', name: 'AI Operations Center Dashboard', quantity: 5, price: 45000, total: 225000 }
      ],
      subtotal: 225000,
      tax: 33750,
      total: 258750,
      status: 'sent',
      validUntil: '2026-09-30',
      createdAt: '2026-02-22T09:00:00.000Z'
    }
  ];

  const invoices: Invoice[] = [
    {
      id: 'inv-1',
      invoiceNumber: 'INV-2026-001',
      customerId: 'cust-1',
      customerName: 'Tony Stark',
      items: [
        { id: 'prod-1', name: 'Enterprise Cybersecurity Shield', quantity: 4, price: 25000, total: 100000 },
        { id: 'serv-2', name: 'Security Auditing & Penetration Testing', quantity: 1, price: 18000, total: 18000 }
      ],
      subtotal: 118000,
      tax: 17700,
      total: 135700,
      status: 'paid',
      dueDate: '2026-03-15',
      createdAt: '2026-02-01T08:00:00.000Z'
    },
    {
      id: 'inv-2',
      invoiceNumber: 'INV-2026-002',
      customerId: 'cust-2',
      customerName: 'Lucius Fox',
      items: [
        { id: 'prod-2', name: 'AI Operations Center Dashboard', quantity: 5, price: 45000, total: 225000 }
      ],
      subtotal: 225000,
      tax: 33750,
      total: 258750,
      status: 'sent',
      dueDate: '2026-07-25',
      createdAt: '2026-06-25T09:00:00.000Z'
    },
    {
      id: 'inv-3',
      invoiceNumber: 'INV-2026-003',
      customerId: 'cust-3',
      customerName: 'Miles Dyson',
      items: [
        { id: 'prod-3', name: 'Secure Vault Appliance', quantity: 1, price: 15000, total: 15000 }
      ],
      subtotal: 15000,
      tax: 2250,
      total: 17250,
      status: 'overdue',
      dueDate: '2026-06-15',
      createdAt: '2026-05-15T10:00:00.000Z'
    }
  ];

  const payments: Payment[] = [
    {
      id: 'pay-1',
      invoiceId: 'inv-1',
      invoiceNumber: 'INV-2026-001',
      customerName: 'Tony Stark',
      amount: 135700,
      method: 'Bank Transfer',
      status: 'success',
      paymentDate: '2026-03-12T14:30:00.000Z'
    }
  ];

  const projects: Project[] = [
    {
      id: 'proj-1',
      name: 'Lab Security Core Implementation',
      customerId: 'cust-1',
      customerName: 'Tony Stark',
      status: 'completed',
      startDate: '2026-03-01',
      endDate: '2026-04-15',
      teamMembers: ['Ranjeet Maurya', 'Robert Stark'],
      progress: 100,
      createdAt: '2026-02-28T08:00:00.000Z'
    },
    {
      id: 'proj-2',
      name: 'AI Operations Command Board',
      customerId: 'cust-3',
      customerName: 'Miles Dyson',
      status: 'in_progress',
      startDate: '2026-04-01',
      endDate: '2026-09-30',
      teamMembers: ['Elena Rostova', 'Robert Stark'],
      progress: 45,
      createdAt: '2026-03-25T09:00:00.000Z'
    }
  ];

  const tasks: Task[] = [
    {
      id: 'task-1',
      title: 'Review Network Protocol Design Docs',
      description: 'Audit Cybershield firewall rule config scripts with Wayne security leads.',
      priority: 'high',
      status: 'done',
      assignedTo: 'Ranjeet Maurya',
      dueDate: '2026-06-28',
      projectId: 'proj-1',
      projectName: 'Lab Security Core Implementation',
      createdAt: '2026-06-25T08:00:00.000Z'
    },
    {
      id: 'task-2',
      title: 'Setup Kubernetes Cluster Ingress Nodes',
      description: 'Provision AWS container infrastructure for multi-region backup failovers.',
      priority: 'medium',
      status: 'in_progress',
      assignedTo: 'Robert Stark',
      dueDate: '2026-07-15',
      projectId: 'proj-2',
      projectName: 'AI Operations Command Board',
      createdAt: '2026-06-26T09:00:00.000Z'
    },
    {
      id: 'task-3',
      title: 'Draft Financial Report Q2',
      description: 'Compile paid invoices and calculate sales tax adjustments for HR audit.',
      priority: 'high',
      status: 'todo',
      assignedTo: 'James Cooper',
      dueDate: '2026-07-05',
      createdAt: '2026-06-30T10:00:00.000Z'
    }
  ];

  const meetings: Meeting[] = [
    {
      id: 'meet-1',
      title: 'Tony Stark Security Review',
      description: 'Debrief on firewall performance stats and discuss upgraded drone AI scope.',
      date: '2026-07-05',
      time: '14:00',
      attendees: ['ranjeetmaurya2033@gmail.com', 'tony@starkindustries.com', 'david.m@acme.com'],
      meetLink: 'https://meet.google.com/abc-defg-hij',
      createdAt: '2026-07-01T08:00:00.000Z'
    },
    {
      id: 'meet-2',
      title: 'Lucius Fox Pipeline Discussion',
      description: 'Negotiate custom logistics telemetry modules for the tactical armor line.',
      date: '2026-07-08',
      time: '10:30',
      attendees: ['elena.r@acme.com', 'lfox@waynecorp.com'],
      meetLink: 'https://meet.google.com/xyz-qprs-tuv',
      createdAt: '2026-07-02T09:00:00.000Z'
    }
  ];

  const employees: Employee[] = [
    {
      id: 'emp-1',
      userId: 'usr-1',
      name: 'Ranjeet Maurya',
      email: 'ranjeetmaurya2033@gmail.com',
      department: 'Engineering',
      designation: 'VP of Product & Engineering',
      dateOfJoining: '2026-01-10',
      salary: 180000,
      status: 'active'
    },
    {
      id: 'emp-2',
      userId: 'usr-4',
      name: 'Elena Rostova',
      email: 'elena.r@acme.com',
      department: 'Sales',
      designation: 'Senior Lead Sales Account Executive',
      dateOfJoining: '2026-01-20',
      salary: 95000,
      status: 'active'
    },
    {
      id: 'emp-3',
      userId: 'usr-8',
      name: 'Robert Stark',
      email: 'robert.s@acme.com',
      department: 'Engineering',
      designation: 'Staff Security DevOps Specialist',
      dateOfJoining: '2026-02-10',
      salary: 125000,
      status: 'active'
    }
  ];

  const attendance: Attendance[] = [
    {
      id: 'att-1',
      employeeId: 'emp-1',
      employeeName: 'Ranjeet Maurya',
      date: '2026-07-01',
      checkIn: '08:55',
      checkOut: '18:05',
      status: 'present'
    },
    {
      id: 'att-2',
      employeeId: 'emp-2',
      employeeName: 'Elena Rostova',
      date: '2026-07-01',
      checkIn: '09:12',
      checkOut: '17:45',
      status: 'present'
    },
    {
      id: 'att-3',
      employeeId: 'emp-3',
      employeeName: 'Robert Stark',
      date: '2026-07-01',
      checkIn: '09:40',
      checkOut: '18:15',
      status: 'late'
    }
  ];

  const leaves: Leave[] = [
    {
      id: 'leave-1',
      employeeId: 'emp-2',
      employeeName: 'Elena Rostova',
      type: 'Casual',
      startDate: '2026-07-10',
      endDate: '2026-07-12',
      status: 'pending',
      reason: 'Attending family wedding.',
      createdAt: '2026-06-29T08:00:00.000Z'
    },
    {
      id: 'leave-2',
      employeeId: 'emp-3',
      employeeName: 'Robert Stark',
      type: 'Sick',
      startDate: '2026-06-12',
      endDate: '2026-06-13',
      status: 'approved',
      reason: 'Flu symptoms.',
      createdAt: '2026-06-11T09:00:00.000Z'
    }
  ];

  const tickets: SupportTicket[] = [
    {
      id: 'tkt-1',
      customerId: 'cust-1',
      customerName: 'Tony Stark',
      subject: 'Container Threat Analyzer Connection Timeout',
      description: 'Receiving continuous 504 timeouts when executing massive container safety checks via local agent dashboard. Needs deep trace analyzer inspection.',
      priority: 'high',
      status: 'in_progress',
      assignedTo: 'Michael Chang',
      createdAt: '2026-06-28T09:00:00.000Z'
    },
    {
      id: 'tkt-2',
      customerId: 'cust-2',
      customerName: 'Lucius Fox',
      subject: 'Inquiry on Enterprise SOC Integration API',
      description: 'Looking to link Wayne SOC operations stream directly into our CRM activity endpoint. Is there a custom token authorization route available?',
      priority: 'medium',
      status: 'open',
      assignedTo: 'Michael Chang',
      createdAt: '2026-07-01T10:00:00.000Z'
    }
  ];

  const activityLogs: ActivityLog[] = [
    {
      id: 'act-1',
      userId: 'usr-1',
      userName: 'Ranjeet Maurya',
      action: 'USER_LOGIN',
      module: 'Auth',
      details: 'Super Admin logged in successfully with 2FA verification.',
      ipAddress: '192.168.1.10',
      createdAt: '2026-07-02T08:00:00.000Z'
    },
    {
      id: 'act-2',
      userId: 'usr-1',
      userName: 'Ranjeet Maurya',
      action: 'INVOICE_PAID',
      module: 'Finance',
      details: 'Marked invoice INV-2026-001 as paid. Payment of $135,700 received.',
      ipAddress: '192.168.1.10',
      createdAt: '2026-07-02T08:30:00.000Z'
    }
  ];

  const notifications: Notification[] = [
    {
      id: 'notif-1',
      userId: 'usr-1',
      title: 'New Lead Assigned',
      message: 'You have been assigned to Lead: Peter Parker.',
      read: false,
      createdAt: '2026-07-01T09:15:00.000Z'
    },
    {
      id: 'notif-2',
      userId: 'usr-1',
      title: 'High Priority Ticket Created',
      message: 'Tony Stark submitted ticket: Container Threat Analyzer Connection Timeout.',
      read: false,
      createdAt: '2026-06-28T09:00:00.000Z'
    }
  ];

  return {
    users,
    leads,
    companies,
    customers,
    contacts,
    deals,
    products,
    services,
    quotations,
    invoices,
    payments,
    projects,
    tasks,
    meetings,
    employees,
    attendance,
    leaves,
    tickets,
    activityLogs,
    notifications,
    settings: DEFAULT_SETTINGS
  };
}

export class CRMDatabaseService {
  private db: CRMDatabase;

  constructor() {
    this.db = this.load();
  }

  private load(): CRMDatabase {
    try {
      if (fs.existsSync(DB_PATH)) {
        const fileContent = fs.readFileSync(DB_PATH, 'utf-8');
        const parsed = JSON.parse(fileContent);
        // Ensure john@nexus.com is dynamically registered if missing in existing crm_db.json
        if (parsed && parsed.users && !parsed.users.some((u: any) => u.email.toLowerCase() === 'john@nexus.com')) {
          parsed.users.push({
            id: 'usr-john',
            name: 'John Nexus',
            email: 'john@nexus.com',
            role: 'Super Admin',
            status: 'active',
            department: 'Executive',
            twoFactorEnabled: true,
            lastActive: new Date().toISOString(),
            createdAt: '2026-01-10T08:00:00.000Z',
            passwordHash: 'AdminSecurePass2026!'
          });
          this.saveToDisk(parsed);
        }
        return parsed;
      }
    } catch (e) {
      console.error('Error loading database, generating seeds...', e);
    }
    const seeds = generateSeedData();
    this.saveToDisk(seeds);
    return seeds;
  }

  private saveToDisk(data: CRMDatabase) {
    try {
      fs.writeFileSync(DB_PATH, JSON.stringify(data, null, 2), 'utf-8');
    } catch (e) {
      console.error('Error writing to disk database', e);
    }
  }

  public get(): CRMDatabase {
    return this.db;
  }

  public update(updater: (db: CRMDatabase) => void) {
    updater(this.db);
    this.saveToDisk(this.db);
  }
}

export const crmDB = new CRMDatabaseService();
