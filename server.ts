import express, { Request, Response, NextFunction } from 'express';
import path from 'path';
import { createServer as createViteServer } from 'vite';
import { crmDB } from './server_db';
import { GoogleGenAI } from '@google/genai';
import {
  User,
  Lead,
  Deal,
  Invoice,
  SupportTicket,
  ActivityLog,
  Notification,
  UserRole
} from './src/types';

const app = express();
const PORT = 3000;

// Enable JSON body parsing
app.use(express.json({ limit: '10mb' }));

// Graceful Gemini API client initialization
let aiClient: GoogleGenAI | null = null;
const API_KEY = process.env.GEMINI_API_KEY;

if (API_KEY && API_KEY !== 'MY_GEMINI_API_KEY') {
  try {
    aiClient = new GoogleGenAI({
      apiKey: API_KEY,
      httpOptions: {
        headers: {
          'User-Agent': 'aistudio-build',
        },
      },
    });
    console.log('Gemini AI Client initialized successfully.');
  } catch (error) {
    console.error('Error initializing Gemini Client:', error);
  }
} else {
  console.log('Gemini API key is not configured. Falling back to high-fidelity simulated intelligence.');
}

// Security: Custom Audit Log middleware
function auditLogger(action: string, module: string, details: string) {
  return (req: Request, res: Response, next: NextFunction) => {
    const userId = req.headers['x-user-id'] as string || 'system';
    const db = crmDB.get();
    const user = db.users.find((u) => u.id === userId);
    const userName = user ? user.name : 'Unknown User';
    
    const newLog: ActivityLog = {
      id: `act-${Date.now()}-${Math.random().toString(36).substr(2, 5)}`,
      userId,
      userName,
      action,
      module,
      details,
      ipAddress: req.ip || '127.0.0.1',
      createdAt: new Date().toISOString()
    };

    crmDB.update((database) => {
      database.activityLogs.unshift(newLog);
    });
    next();
  };
}

// Dynamic Action Audit Logger Helper
function logActionDirect(userId: string, action: string, module: string, details: string, ip: string = '127.0.0.1') {
  const db = crmDB.get();
  const user = db.users.find((u) => u.id === userId);
  const userName = user ? user.name : 'System';

  const newLog: ActivityLog = {
    id: `act-${Date.now()}-${Math.random().toString(36).substr(2, 5)}`,
    userId,
    userName,
    action,
    module,
    details,
    ipAddress: ip,
    createdAt: new Date().toISOString()
  };

  crmDB.update((database) => {
    database.activityLogs.unshift(newLog);
  });
}

// Authentication Middleware
function authenticate(req: Request, res: Response, next: NextFunction) {
  const token = req.headers['authorization'];
  const userId = req.headers['x-user-id'] as string;

  if (!token || !userId) {
    return res.status(401).json({ error: 'Authentication required' });
  }

  const db = crmDB.get();
  const user = db.users.find((u) => u.id === userId);
  if (!user || user.status !== 'active') {
    return res.status(403).json({ error: 'User is inactive or not found' });
  }

  // Inject user info into request headers
  req.headers['x-user-role'] = user.role;
  next();
}

// RBAC Authorization Middleware
function authorize(allowedRoles: UserRole[]) {
  return (req: Request, res: Response, next: NextFunction) => {
    const userRole = req.headers['x-user-role'] as UserRole;
    if (!userRole || !allowedRoles.includes(userRole)) {
      return res.status(403).json({ error: 'Unauthorized: Access denied for your role' });
    }
    next();
  };
}

// --- REST API ENDPOINTS ---

// Auth APIs
app.post('/api/auth/login', (req, res) => {
  const { email, password } = req.body;
  if (!email || !password) {
    return res.status(400).json({ error: 'Email and password are required' });
  }

  const db = crmDB.get();
  const user = db.users.find((u) => u.email.toLowerCase() === email.toLowerCase());

  if (!user || user.passwordHash !== password) {
    return res.status(401).json({ error: 'Invalid email or password' });
  }

  if (user.status !== 'active') {
    return res.status(403).json({ error: 'Account is inactive. Contact Administrator' });
  }

  // Handle Two Factor Authentication Simulation
  if (user.twoFactorEnabled) {
    return res.json({
      requires2FA: true,
      userId: user.id,
      message: 'Two-factor Authentication code sent (Simulated OTP: 123456)'
    });
  }

  const sessionToken = `token-${Math.random().toString(36).substr(2)}-${user.id}`;
  logActionDirect(user.id, 'USER_LOGIN', 'Auth', `User logged in from IP ${req.ip || '127.0.0.1'}`);

  res.json({
    token: sessionToken,
    user: {
      id: user.id,
      name: user.name,
      email: user.email,
      role: user.role,
      status: user.status,
      department: user.department,
      twoFactorEnabled: user.twoFactorEnabled,
      avatar: user.avatar
    }
  });
});

app.post('/api/auth/verify-2fa', (req, res) => {
  const { userId, code } = req.body;
  if (!userId || !code) {
    return res.status(400).json({ error: 'User ID and OTP code are required' });
  }

  const db = crmDB.get();
  const user = db.users.find((u) => u.id === userId);

  if (!user) {
    return res.status(404).json({ error: 'User not found' });
  }

  // Standard static OTP fallback for premium test ease
  if (code !== '123456') {
    return res.status(400).json({ error: 'Invalid verification code' });
  }

  const sessionToken = `token-${Math.random().toString(36).substr(2)}-${user.id}`;
  logActionDirect(user.id, 'USER_LOGIN_2FA', 'Auth', 'User successfully passed 2FA verification.');

  res.json({
    token: sessionToken,
    user: {
      id: user.id,
      name: user.name,
      email: user.email,
      role: user.role,
      status: user.status,
      department: user.department,
      twoFactorEnabled: user.twoFactorEnabled,
      avatar: user.avatar
    }
  });
});

app.post('/api/auth/register', (req, res) => {
  const { name, email, password, companyName } = req.body;
  if (!name || !email || !password) {
    return res.status(400).json({ error: 'Name, email and password are required' });
  }

  const db = crmDB.get();
  const exists = db.users.some((u) => u.email.toLowerCase() === email.toLowerCase());
  if (exists) {
    return res.status(400).json({ error: 'Email already registered' });
  }

  const newUser = {
    id: `usr-${Date.now()}`,
    name,
    email,
    role: 'Employee' as UserRole, // default role for self-registered users
    status: 'active' as const,
    department: 'Engineering',
    twoFactorEnabled: false,
    createdAt: new Date().toISOString(),
    passwordHash: password
  };

  crmDB.update((database) => {
    database.users.push(newUser);
  });

  logActionDirect(newUser.id, 'USER_REGISTER', 'Auth', `Newly self-registered under ${companyName || 'Standard Enterprise'}`);

  res.json({
    message: 'Registration successful! Verification email sent (Simulated).',
    userId: newUser.id
  });
});

app.post('/api/auth/forgot-password', (req, res) => {
  const { email } = req.body;
  const db = crmDB.get();
  const user = db.users.find((u) => u.email.toLowerCase() === email?.toLowerCase());

  if (!user) {
    return res.status(404).json({ error: 'No user account found with that email' });
  }

  res.json({ message: 'Password reset link sent to your registered email (Simulated).' });
});

app.post('/api/auth/reset-password', (req, res) => {
  const { token, newPassword } = req.body;
  if (!token || !newPassword) {
    return res.status(400).json({ error: 'Token and new password are required' });
  }
  res.json({ message: 'Your password has been reset successfully (Simulated).' });
});

app.get('/api/auth/profile', authenticate, (req, res) => {
  const userId = req.headers['x-user-id'] as string;
  const db = crmDB.get();
  const user = db.users.find((u) => u.id === userId);

  if (!user) {
    return res.status(404).json({ error: 'User not found' });
  }

  res.json(user);
});

app.put('/api/auth/profile', authenticate, (req, res) => {
  const userId = req.headers['x-user-id'] as string;
  const { name, email, department, twoFactorEnabled, avatar } = req.body;

  crmDB.update((database) => {
    const user = database.users.find((u) => u.id === userId);
    if (user) {
      if (name) user.name = name;
      if (email) user.email = email;
      if (department) user.department = department;
      if (twoFactorEnabled !== undefined) user.twoFactorEnabled = twoFactorEnabled;
      if (avatar) user.avatar = avatar;
    }
  });

  logActionDirect(userId, 'PROFILE_UPDATE', 'Auth', 'User updated account profile details.');
  const db = crmDB.get();
  res.json(db.users.find((u) => u.id === userId));
});

app.put('/api/auth/change-password', authenticate, (req, res) => {
  const userId = req.headers['x-user-id'] as string;
  const { currentPassword, newPassword } = req.body;

  const db = crmDB.get();
  const user = db.users.find((u) => u.id === userId);

  if (!user || user.passwordHash !== currentPassword) {
    return res.status(400).json({ error: 'Current password is incorrect' });
  }

  crmDB.update((database) => {
    const u = database.users.find((usr) => usr.id === userId);
    if (u) u.passwordHash = newPassword;
  });

  logActionDirect(userId, 'PASSWORD_CHANGE', 'Auth', 'User successfully changed account access password.');
  res.json({ message: 'Password changed successfully' });
});

// Settings APIs
app.get('/api/crm/settings', authenticate, (req, res) => {
  res.json(crmDB.get().settings);
});

app.put('/api/crm/settings', authenticate, authorize(['Super Admin', 'Admin']), (req, res) => {
  const userId = req.headers['x-user-id'] as string;
  crmDB.update((database) => {
    database.settings = { ...database.settings, ...req.body };
  });
  logActionDirect(userId, 'SETTINGS_UPDATE', 'Settings', 'Updated global SaaS Enterprise parameters.');
  res.json(crmDB.get().settings);
});

// Notifications
app.get('/api/crm/notifications', authenticate, (req, res) => {
  const userId = req.headers['x-user-id'] as string;
  const list = crmDB.get().notifications.filter((n) => n.userId === userId || n.userId === 'all');
  res.json(list);
});

app.put('/api/crm/notifications/:id/read', authenticate, (req, res) => {
  const { id } = req.params;
  crmDB.update((database) => {
    const n = database.notifications.find((notif) => notif.id === id);
    if (n) n.read = true;
  });
  res.json({ success: true });
});

// Unified CRM Modules Routing Factory Generator
function createCRMEndpoints(moduleName: keyof ReturnType<typeof crmDB.get>, allowedRoles: UserRole[], searchFields: string[]) {
  // GET listing with filters, pagination, sorting and searching
  app.get(`/api/crm/${moduleName}`, authenticate, (req, res) => {
    const db = crmDB.get();
    let data = db[moduleName] as any[];

    // Apply text search
    const q = req.query.q as string;
    if (q) {
      const lower = q.toLowerCase();
      data = data.filter((item) =>
        searchFields.some((field) => {
          const val = item[field];
          return val && String(val).toLowerCase().includes(lower);
        })
      );
    }

    // Apply filtering
    Object.keys(req.query).forEach((key) => {
      if (key !== 'q' && key !== 'limit' && key !== 'page' && key !== 'sort_by' && key !== 'sort_order') {
        const val = req.query[key];
        if (val) {
          data = data.filter((item) => String(item[key]) === String(val));
        }
      }
    });

    // Apply sorting
    const sortBy = (req.query.sort_by as string) || 'createdAt';
    const sortOrder = (req.query.sort_order as string) || 'desc';
    data.sort((a, b) => {
      let valA = a[sortBy];
      let valB = b[sortBy];
      if (typeof valA === 'string') {
        return sortOrder === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
      } else {
        return sortOrder === 'asc' ? (valA || 0) - (valB || 0) : (valB || 0) - (valA || 0);
      }
    });

    // Pagination
    const page = parseInt(req.query.page as string) || 1;
    const limit = parseInt(req.query.limit as string) || 10;
    const total = data.length;
    const start = (page - 1) * limit;
    const paginated = data.slice(start, start + limit);

    res.json({
      data: paginated,
      total,
      page,
      limit,
      totalPages: Math.ceil(total / limit)
    });
  });

  // GET details
  app.get(`/api/crm/${moduleName}/:id`, authenticate, (req, res) => {
    const db = crmDB.get();
    const data = db[moduleName] as any[];
    const item = data.find((i) => i.id === req.params.id);
    if (!item) {
      return res.status(404).json({ error: `Item not found in ${moduleName}` });
    }
    res.json(item);
  });

  // POST create
  app.post(`/api/crm/${moduleName}`, authenticate, authorize(allowedRoles), (req, res) => {
    const userId = req.headers['x-user-id'] as string;
    const newItem = {
      id: `${moduleName.substr(0, 4)}-${Date.now()}`,
      createdAt: new Date().toISOString(),
      ...req.body
    };

    crmDB.update((database) => {
      (database[moduleName] as any[]).push(newItem);
    });

    logActionDirect(userId, `CREATE_${moduleName.toUpperCase()}`, String(moduleName), `Added item: ${newItem.name || newItem.title || newItem.id}`);
    res.json(newItem);
  });

  // PUT update
  app.put(`/api/crm/${moduleName}/:id`, authenticate, authorize(allowedRoles), (req, res) => {
    const userId = req.headers['x-user-id'] as string;
    const { id } = req.params;

    crmDB.update((database) => {
      const arr = database[moduleName] as any[];
      const idx = arr.findIndex((i) => i.id === id);
      if (idx !== -1) {
        arr[idx] = { ...arr[idx], ...req.body, id }; // retain ID
      }
    });

    logActionDirect(userId, `UPDATE_${moduleName.toUpperCase()}`, String(moduleName), `Modified item ID: ${id}`);
    const db = crmDB.get();
    const updated = (db[moduleName] as any[]).find((i) => i.id === id);
    res.json(updated);
  });

  // DELETE single
  app.delete(`/api/crm/${moduleName}/:id`, authenticate, authorize(allowedRoles), (req, res) => {
    const userId = req.headers['x-user-id'] as string;
    const { id } = req.params;

    crmDB.update((database) => {
      const arr = database[moduleName] as any[];
      const idx = arr.findIndex((i) => i.id === id);
      if (idx !== -1) {
        arr.splice(idx, 1);
      }
    });

    logActionDirect(userId, `DELETE_${moduleName.toUpperCase()}`, String(moduleName), `Removed item ID: ${id}`);
    res.json({ success: true, message: 'Item deleted successfully' });
  });

  // Bulk Delete
  app.post(`/api/crm/${moduleName}/bulk-delete`, authenticate, authorize(allowedRoles), (req, res) => {
    const userId = req.headers['x-user-id'] as string;
    const { ids } = req.body;

    if (!ids || !Array.isArray(ids)) {
      return res.status(400).json({ error: 'IDs list is required' });
    }

    crmDB.update((database) => {
      const arr = database[moduleName] as any[];
      database[moduleName] = arr.filter((item) => !ids.includes(item.id)) as any;
    });

    logActionDirect(userId, `BULK_DELETE_${moduleName.toUpperCase()}`, String(moduleName), `Bulk removed ${ids.length} records.`);
    res.json({ success: true });
  });
}

// Generate the CRM controllers dynamically
createCRMEndpoints('leads', ['Super Admin', 'Admin', 'Manager', 'Sales Executive'], ['name', 'company', 'email', 'phone', 'source']);
createCRMEndpoints('companies', ['Super Admin', 'Admin', 'Manager', 'Sales Executive'], ['name', 'industry', 'website', 'address']);
createCRMEndpoints('customers', ['Super Admin', 'Admin', 'Manager', 'Sales Executive'], ['name', 'email', 'phone']);
createCRMEndpoints('contacts', ['Super Admin', 'Admin', 'Manager', 'Sales Executive'], ['name', 'email', 'phone', 'role']);
createCRMEndpoints('deals', ['Super Admin', 'Admin', 'Manager', 'Sales Executive'], ['name', 'assignedTo', 'companyName']);
createCRMEndpoints('products', ['Super Admin', 'Admin', 'Manager', 'Accountant'], ['name', 'sku', 'category']);
createCRMEndpoints('services', ['Super Admin', 'Admin', 'Manager', 'Accountant'], ['name', 'category']);
createCRMEndpoints('quotations', ['Super Admin', 'Admin', 'Manager', 'Sales Executive', 'Accountant'], ['quoteNumber', 'customerName']);
createCRMEndpoints('invoices', ['Super Admin', 'Admin', 'Manager', 'Accountant'], ['invoiceNumber', 'customerName']);
createCRMEndpoints('payments', ['Super Admin', 'Admin', 'Manager', 'Accountant'], ['invoiceNumber', 'customerName', 'method']);
createCRMEndpoints('projects', ['Super Admin', 'Admin', 'Manager', 'Employee'], ['name', 'customerName']);
createCRMEndpoints('tasks', ['Super Admin', 'Admin', 'Manager', 'Employee', 'Sales Executive', 'Support Executive'], ['title', 'assignedTo', 'projectName']);
createCRMEndpoints('meetings', ['Super Admin', 'Admin', 'Manager', 'Employee', 'Sales Executive', 'Support Executive'], ['title', 'attendees']);
createCRMEndpoints('employees', ['Super Admin', 'Admin', 'HR'], ['name', 'email', 'designation', 'department']);
createCRMEndpoints('attendance', ['Super Admin', 'Admin', 'HR', 'Employee'], ['employeeName', 'date', 'status']);
createCRMEndpoints('leaves', ['Super Admin', 'Admin', 'HR', 'Employee'], ['employeeName', 'type', 'status']);
createCRMEndpoints('tickets', ['Super Admin', 'Admin', 'Manager', 'Support Executive'], ['subject', 'customerName', 'assignedTo']);
createCRMEndpoints('users', ['Super Admin', 'Admin'], ['name', 'email', 'role', 'department']);
createCRMEndpoints('activityLogs', ['Super Admin', 'Admin'], ['userName', 'action', 'details', 'module']);

// Custom leave approval
app.put('/api/crm/leaves/:id/approve', authenticate, authorize(['Super Admin', 'Admin', 'HR']), (req, res) => {
  const { id } = req.params;
  const { status } = req.body; // 'approved' or 'rejected'
  const userId = req.headers['x-user-id'] as string;

  crmDB.update((database) => {
    const l = database.leaves.find((item) => item.id === id);
    if (l) {
      l.status = status;
      // also log action
      logActionDirect(userId, 'LEAVE_RESOLVED', 'Leaves', `Resolved leave request for ${l.employeeName} to state: ${status}`);
    }
  });

  res.json({ success: true });
});

// Custom attendance checkin/checkout
app.post('/api/crm/attendance/checkin', authenticate, (req, res) => {
  const userId = req.headers['x-user-id'] as string;
  const db = crmDB.get();
  const user = db.users.find((u) => u.id === userId);
  if (!user) return res.status(404).json({ error: 'User not found' });

  const employee = db.employees.find((e) => e.email === user.email);
  if (!employee) return res.status(400).json({ error: 'No associated employee profile found' });

  const today = new Date().toISOString().split('T')[0];
  const existing = db.attendance.find((a) => a.employeeId === employee.id && a.date === today);

  if (existing) {
    return res.status(400).json({ error: 'Already checked in for today' });
  }

  const now = new Date();
  const checkInTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
  
  // Late check-in definition (after 9:30 AM)
  const isLate = now.getHours() > 9 || (now.getHours() === 9 && now.getMinutes() > 30);

  const newAttendance = {
    id: `att-${Date.now()}`,
    employeeId: employee.id,
    employeeName: employee.name,
    date: today,
    checkIn: checkInTime,
    status: (isLate ? 'late' : 'present') as any
  };

  crmDB.update((database) => {
    database.attendance.unshift(newAttendance);
  });

  logActionDirect(userId, 'ATTENDANCE_CHECKIN', 'Attendance', `Checked in for duty today at ${checkInTime}.`);
  res.json(newAttendance);
});

app.post('/api/crm/attendance/checkout', authenticate, (req, res) => {
  const userId = req.headers['x-user-id'] as string;
  const db = crmDB.get();
  const user = db.users.find((u) => u.id === userId);
  if (!user) return res.status(404).json({ error: 'User not found' });

  const employee = db.employees.find((e) => e.email === user.email);
  if (!employee) return res.status(400).json({ error: 'No associated employee profile found' });

  const today = new Date().toISOString().split('T')[0];
  const now = new Date();
  const checkOutTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;

  crmDB.update((database) => {
    const a = database.attendance.find((item) => item.employeeId === employee.id && item.date === today);
    if (a) {
      a.checkOut = checkOutTime;
    }
  });

  logActionDirect(userId, 'ATTENDANCE_CHECKOUT', 'Attendance', `Checked out from duty today at ${checkOutTime}.`);
  res.json({ success: true, checkOut: checkOutTime });
});

// Roles permissions list mapping
app.get('/api/crm/roles-permissions', authenticate, (req, res) => {
  const rolesWithPermissions = [
    {
      role: 'Super Admin',
      description: 'Full unmitigated access to all business departments, platform parameters, database backups and user authorization tiers.',
      permissions: ['*']
    },
    {
      role: 'Admin',
      description: 'Broad administrative controls, configuration updates, and multi-department record oversight.',
      permissions: ['users.read', 'users.write', 'leads.*', 'deals.*', 'invoices.*', 'support.*', 'reports.read']
    },
    {
      role: 'Manager',
      description: 'Departmental leadership capabilities, assign activities, approve leaves, audit CRM reports.',
      permissions: ['leads.*', 'deals.*', 'projects.*', 'leaves.approve', 'reports.read']
    },
    {
      role: 'Sales Executive',
      description: 'Active lead acquisition, quotation compilation, customer onboarding and pipeline progression.',
      permissions: ['leads.read', 'leads.write', 'customers.*', 'contacts.*', 'deals.*', 'quotations.*']
    },
    {
      role: 'Support Executive',
      description: 'Assigned ticket resolution, customer troubleshooting streams, and helpdesk logging.',
      permissions: ['customers.read', 'contacts.read', 'tickets.*']
    },
    {
      role: 'HR',
      description: 'Oversight of employee rosters, attendance tracking, and leave application authorizations.',
      permissions: ['employees.*', 'attendance.*', 'leaves.*']
    },
    {
      role: 'Employee',
      description: 'Standard active project delivery, checklist completion, and self-service attendance clocking.',
      permissions: ['projects.read', 'tasks.read', 'tasks.write', 'attendance.clock', 'leaves.apply']
    },
    {
      role: 'Accountant',
      description: 'Monitors paid invoices, ledger reconciliation, sales tax filing, and product listing pricing.',
      permissions: ['invoices.*', 'payments.*', 'products.*', 'services.*', 'reports.read']
    }
  ];
  res.json(rolesWithPermissions);
});

// Dashboard Analytics Reports
app.get('/api/crm/reports', authenticate, (req, res) => {
  const db = crmDB.get();

  // Statistics Cards computations
  const totalLeads = db.leads.length;
  const activeLeadsCount = db.leads.filter((l) => l.status === 'new' || l.status === 'contacted').length;
  
  const totalDeals = db.deals.length;
  const pipelineValue = db.deals
    .filter((d) => d.stage !== 'won' && d.stage !== 'lost')
    .reduce((sum, d) => sum + d.value, 0);

  const closedWonValue = db.deals
    .filter((d) => d.stage === 'won')
    .reduce((sum, d) => sum + d.value, 0);

  const totalInvoices = db.invoices.length;
  const totalReceivedRevenue = db.payments
    .filter((p) => p.status === 'success')
    .reduce((sum, p) => sum + p.amount, 0);

  const overdueInvoicesValue = db.invoices
    .filter((i) => i.status === 'overdue')
    .reduce((sum, i) => sum + i.total, 0);

  const openTickets = db.tickets.filter((t) => t.status === 'open' || t.status === 'in_progress').length;

  // Revenue analytics per month (Mock aggregated representation)
  const monthlyRevenue = [
    { name: 'Jan', revenue: 45000, cost: 20000, target: 40000 },
    { name: 'Feb', revenue: 75000, cost: 22000, target: 50000 },
    { name: 'Mar', revenue: 135700, cost: 25000, target: 80000 },
    { name: 'Apr', revenue: 95000, cost: 21000, target: 90000 },
    { name: 'May', revenue: 110000, cost: 24000, target: 100000 },
    { name: 'Jun', revenue: 140000, cost: 28000, target: 120000 }
  ];

  // Leads distribution by status
  const leadsDistribution = [
    { name: 'New', value: db.leads.filter((l) => l.status === 'new').length },
    { name: 'Contacted', value: db.leads.filter((l) => l.status === 'contacted').length },
    { name: 'Qualified', value: db.leads.filter((l) => l.status === 'qualified').length },
    { name: 'Lost', value: db.leads.filter((l) => l.status === 'lost').length }
  ];

  // Deals pipeline by stage
  const pipelineDistribution = [
    { name: 'Qualification', value: db.deals.filter((d) => d.stage === 'qualification').length },
    { name: 'Proposal', value: db.deals.filter((d) => d.stage === 'proposal').length },
    { name: 'Negotiation', value: db.deals.filter((d) => d.stage === 'negotiation').length },
    { name: 'Won', value: db.deals.filter((d) => d.stage === 'won').length },
    { name: 'Lost', value: db.deals.filter((d) => d.stage === 'lost').length }
  ];

  // Projects metrics
  const activeProjects = db.projects.filter((p) => p.status === 'in_progress').length;
  const averageProjectProgress = Math.round(
    db.projects.reduce((sum, p) => sum + p.progress, 0) / (db.projects.length || 1)
  );

  res.json({
    summary: {
      totalLeads,
      activeLeadsCount,
      totalDeals,
      pipelineValue,
      closedWonValue,
      totalInvoices,
      totalReceivedRevenue,
      overdueInvoicesValue,
      openTickets,
      activeProjects,
      averageProjectProgress
    },
    charts: {
      monthlyRevenue,
      leadsDistribution,
      pipelineDistribution
    }
  });
});

// Import Backup Configuration API
app.post('/api/crm/import', authenticate, authorize(['Super Admin']), (req, res) => {
  const userId = req.headers['x-user-id'] as string;
  const { module, data } = req.body;

  if (!module || !Array.isArray(data)) {
    return res.status(400).json({ error: 'Module name and valid data array are required.' });
  }

  const db = crmDB.get();
  if (!(module in db)) {
    return res.status(400).json({ error: `Invalid module target: ${module}` });
  }

  crmDB.update((database) => {
    // Append or replace
    const arr = database[module as keyof typeof db] as any[];
    data.forEach((importedItem) => {
      const existingIdx = arr.findIndex((i) => i.id === importedItem.id);
      if (existingIdx !== -1) {
        arr[existingIdx] = { ...arr[existingIdx], ...importedItem };
      } else {
        arr.push(importedItem);
      }
    });
  });

  logActionDirect(userId, 'BACKUP_IMPORT', String(module), `Successfully restored/imported ${data.length} records into ${module}`);
  res.json({ success: true, message: `Successfully imported ${data.length} items into ${module}` });
});

// Export Module API
app.get('/api/crm/export/:module', authenticate, (req, res) => {
  const { module } = req.params;
  const db = crmDB.get();

  if (!(module in db)) {
    return res.status(400).json({ error: `Invalid module target: ${module}` });
  }

  const format = req.query.format as string;
  const data = db[module as keyof typeof db];

  if (format === 'csv') {
    // Basic high-fidelity CSV compiler
    if (!Array.isArray(data) || data.length === 0) {
      res.setHeader('Content-Type', 'text/csv');
      return res.send('');
    }
    const headers = Object.keys(data[0]);
    const csvRows = [
      headers.join(','),
      ...data.map((row) =>
        headers
          .map((fieldName) => {
            const val = row[fieldName];
            const escaped = ('' + (val ?? '')).replace(/"/g, '""');
            return `"${escaped}"`;
          })
          .join(',')
      )
    ];
    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', `attachment; filename=CRM_Backup_${module}.csv`);
    return res.send(csvRows.join('\r\n'));
  }

  // default to JSON download
  res.setHeader('Content-Type', 'application/json');
  res.setHeader('Content-Disposition', `attachment; filename=CRM_Backup_${module}.json`);
  res.json(data);
});

// Activity logs
app.get('/api/crm/activity-logs', authenticate, authorize(['Super Admin', 'Admin']), (req, res) => {
  const page = parseInt(req.query.page as string) || 1;
  const limit = parseInt(req.query.limit as string) || 20;
  const db = crmDB.get();
  
  const total = db.activityLogs.length;
  const start = (page - 1) * limit;
  const paginated = db.activityLogs.slice(start, start + limit);

  res.json({
    data: paginated,
    total,
    page,
    limit,
    totalPages: Math.ceil(total / limit)
  });
});

// --- SERVER-SIDE GEMINI SMART INTELLIGENCE CODES ---

// 1. AI-powered Lead Scoring and Summary Analysis
app.post('/api/crm/ai/analyze-lead', authenticate, async (req, res) => {
  const { leadId } = req.body;
  if (!leadId) {
    return res.status(400).json({ error: 'Lead ID is required for smart analysis.' });
  }

  const db = crmDB.get();
  const lead = db.leads.find((l) => l.id === leadId);

  if (!lead) {
    return res.status(404).json({ error: 'Lead not found.' });
  }

  const prompt = `You are a Senior Lead Qualification AI Agent at a high-growth CRM SaaS enterprise. 
Perform a comprehensive smart lead score evaluation and lead conversion summary analysis for:
Lead Name: ${lead.name}
Company Name: ${lead.company}
Estimated Financial Contract Value: $${lead.value}
Acquisition Source: ${lead.source}
Lead status: ${lead.status}
Representative Assigned: ${lead.assignedTo}
Internal Notes logged: "${lead.notes}"

Generate a structured response in plain markdown containing:
1. Smart Lead Score (between 1 and 100) based on financial value, current contacted engagement, acquisition channel, and team logs.
2. Conversion Probability Percentage (e.g. 75%)
3. Dynamic Executive Summary (3-4 bullet points highlighting positive buy-signals and critical bottleneck risks)
4. Recommended Next Action Playbook (2 concrete immediate follow-up strategies for the Sales representative)

Ensure the output is highly professional, concise, direct, and actionable. Keep the style sleek. Do not include markdown headers inside your returned paragraphs if possible, use bold indicators.`;

  try {
    if (aiClient) {
      const response = await aiClient.models.generateContent({
        model: 'gemini-3.5-flash',
        contents: prompt
      });

      const analysisText = response.text || 'Unable to generate analysis. Try again.';
      return res.json({ analysis: analysisText });
    } else {
      // High fidelity simulated response for quick fallback
      const simulatedScore = lead.status === 'qualified' ? 88 : lead.status === 'contacted' ? 62 : 35;
      const simulatedProbability = lead.status === 'qualified' ? '82%' : lead.status === 'contacted' ? '50%' : '15%';
      
      const analysisText = `### **Smart Lead Analysis Overview**
- **Simulated Intelligence Score**: **${simulatedScore}/100**
- **Win Probability Estimate**: **${simulatedProbability}**

#### **Executive Summary Highlights:**
* **Strategic Value**: Contract value of **$${lead.value.toLocaleString()}** represents a high-tier enterprise transaction.
* **Buy Signal Evaluation**: Channel **${lead.source}** matches active intent parameters, making engagement pathways highly receptive.
* **Bottleneck Risks**: Current notes logged as *"${lead.notes}"* denote high interest but indicate pending alignment of resources/approvals.

#### **Recommended Representative Action Playbook:**
1. **Accelerate Technical Alignment**: Coordinate a whiteboard architectural blueprint demo showcasing our scalable cybersecurity dashboards.
2. **Offer Flexible Pilot Tiers**: Provide a limited 14-day zero-risk staging environment credentials to validate core threat analysis modules directly.`;

      return res.json({ analysis: analysisText });
    }
  } catch (err: any) {
    console.error('Error in analyze-lead Gemini API:', err);
    return res.status(500).json({ error: 'Failed to compile AI Lead Analysis. ' + err.message });
  }
});

// 2. AI-powered Support Ticket Auto-Responder
app.post('/api/crm/ai/reply-ticket', authenticate, async (req, res) => {
  const { ticketId } = req.body;
  if (!ticketId) {
    return res.status(400).json({ error: 'Ticket ID is required to draft an auto-response.' });
  }

  const db = crmDB.get();
  const ticket = db.tickets.find((t) => t.id === ticketId);

  if (!ticket) {
    return res.status(404).json({ error: 'Ticket not found.' });
  }

  const prompt = `You are an elite, highly professional Senior Technical Support AI Agent at Acme Enterprise CRM. 
Draft a polite, highly specialized, and technically accurate auto-responder email draft to the customer:
Customer Name: ${ticket.customerName || 'Valued Client'}
Ticket Subject: "${ticket.subject}"
Ticket Description: "${ticket.description}"
Assigned Support Executive: ${ticket.assignedTo}
Priority Tier: ${ticket.priority}

Requirements:
- Empathize with their technical issue immediately and maintain a supportive, elite tone.
- Reference their exact issue details directly in the response body.
- Propose 2 logical technical diagnostic troubleshooting actions or reassure them that the support lead is actively reviewing their connection streams.
- Format with an email layout (Subject: ..., Dear ..., Sincerely ..., Team Acme Support).
- Keep it highly professional, clean, and elegant. Do not use generic placeholders like [Your Name], replace them with actual representative names!`;

  try {
    if (aiClient) {
      const response = await aiClient.models.generateContent({
        model: 'gemini-3.5-flash',
        contents: prompt
      });

      const replyDraft = response.text || 'Unable to draft response. Try again.';
      return res.json({ reply: replyDraft });
    } else {
      // High fidelity simulated response fallback
      const replyDraft = `**Subject**: Re: ${ticket.subject} (Ticket #${ticket.id})

Dear ${ticket.customerName || 'Valued Partner'},

Thank you for contacting Acme Enterprise Technical Support. We understand the critical nature of the issue regarding your **${ticket.subject}**, and we apologize for any operational disruption this is causing.

Our Engineering department has actively prioritized this ticket under **${ticket.priority.toUpperCase()}** priority. Our Lead Technician, **${ticket.assignedTo}**, is currently analyzing the system connection streams on your container cluster logs.

While we carry out this investigation, we suggest executing the following preliminary checks:
1. **Verify API Gateway Header Token Limits**: Ensure your gateway ingress doesn't automatically truncate large authorization headers containing custom enterprise permissions.
2. **Execute Local Network Trace**: Run a standard path trace command directly from your dashboard container console to verify if there's an intermediate latency spike on the security firewall layer.

We will provide a formal follow-up diagnostic log update within the next two hours. If you have any additional cluster details or trace dumps, please reply directly to this thread.

Sincerely,

**Acme Enterprise Support Team**
*Automated Intelligence Co-Pilot*`;

      return res.json({ reply: replyDraft });
    }
  } catch (err: any) {
    console.error('Error in reply-ticket Gemini API:', err);
    return res.status(500).json({ error: 'Failed to draft AI response. ' + err.message });
  }
});

// --- VITE DEV AND SPA SERVING CONFIGURATION ---

async function startServer() {
  if (process.env.NODE_ENV !== 'production') {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: 'spa',
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), 'dist');
    app.use(express.static(distPath));
    app.get('*', (req: Request, res: Response) => {
      res.sendFile(path.join(distPath, 'index.html'));
    });
  }

  app.listen(PORT, '0.0.0.0', () => {
    console.log(`Enterprise CRM SaaS server running at http://localhost:${PORT}`);
  });
}

startServer();
