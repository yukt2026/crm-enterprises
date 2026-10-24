import React, { useState, useEffect } from 'react';
import { useCRMStore } from './store/crmStore';
import Layout from './components/Layout';
import ModulesView from './components/ModulesView';
import {
  Lock,
  Mail,
  User,
  ShieldAlert,
  Building,
  CheckCircle2,
  Sparkles,
  Info
} from 'lucide-react';

export default function App() {
  const {
    user,
    isLoggedIn,
    requires2FA,
    userId2FA,
    login,
    register,
    verify2FA,
    forgotPassword,
    toasts,
    removeToast,
    theme,
    fetchReports,
    fetchSettings,
    fetchRolesPermissions,
    fetchModuleRecords
  } = useCRMStore();

  const [activeModule, setActiveModule] = useState<string>('dashboard');
  const [authTab, setAuthTab] = useState<'login' | 'register' | 'forgot'>('login');

  // Input States
  const [email, setEmail] = useState('john@nexus.com');
  const [password, setPassword] = useState('AdminSecurePass2026!');
  const [name, setName] = useState('');
  const [company, setCompany] = useState('');
  const [otp, setOtp] = useState('');

  // Sync theme class
  useEffect(() => {
    document.documentElement.classList.toggle('dark', theme === 'dark');
  }, [theme]);

  // Auto-fetch data on sign-in
  useEffect(() => {
    if (isLoggedIn) {
      fetchReports();
      fetchSettings();
      fetchRolesPermissions();
      // Pre-seed core lists
      fetchModuleRecords('leads', 1);
      fetchModuleRecords('deals', 1);
      fetchModuleRecords('companies', 1);
      fetchModuleRecords('customers', 1);
      fetchModuleRecords('contacts', 1);
      fetchModuleRecords('invoices', 1);
      fetchModuleRecords('tickets', 1);
      fetchModuleRecords('employees', 1);
      fetchModuleRecords('meetings', 1);
      fetchModuleRecords('activityLogs', 1);
    }
  }, [isLoggedIn]);

  const handleLoginSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email || !password) return;
    await login(email, password);
  };

  const handleRegisterSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name || !email || !password || !company) return;
    const success = await register(name, email, password, company);
    if (success) {
      setAuthTab('login');
      setEmail(email);
      setPassword(password);
    }
  };

  const handle2FAVerifySubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!userId2FA || !otp) return;
    await verify2FA(userId2FA, otp);
  };

  const handleForgotPasswordSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await forgotPassword(email);
    setAuthTab('login');
  };

  // --- RENDERING LOGIN GATE ---
  if (!isLoggedIn) {
    return (
      <div className={`min-h-screen flex items-center justify-center p-6 transition-colors duration-300 ${
        theme === 'dark' ? 'bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-950'
      }`}>
        
        {/* Toast Alerts Overlay */}
        <div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full">
          {toasts.map((toast) => (
            <div
              key={toast.id}
              className={`p-4 rounded-xl shadow-2xl border flex items-center justify-between gap-3 text-xs animate-in slide-in-from-bottom duration-200 ${
                toast.type === 'success'
                  ? 'bg-emerald-950/95 border-emerald-800 text-emerald-300'
                  : toast.type === 'error'
                  ? 'bg-rose-950/95 border-rose-800 text-rose-300'
                  : 'bg-slate-900/95 border-slate-800 text-slate-300'
              } backdrop-blur-md`}
            >
              <span>{toast.message}</span>
              <button onClick={() => removeToast(toast.id)} className="text-xs font-bold hover:text-white shrink-0 ml-4">✕</button>
            </div>
          ))}
        </div>

        <div className={`w-full max-w-md rounded-2xl border shadow-2xl overflow-hidden transition-all duration-200 ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
        }`}>
          {/* Header branding block */}
          <div className={`p-8 text-center border-b border-inherit ${theme === 'dark' ? 'bg-slate-900/30' : 'bg-slate-100'}`}>
            <div className="h-12 w-12 rounded-xl bg-emerald-500 flex items-center justify-center mx-auto mb-4 text-white font-extrabold text-xl shadow-lg shadow-emerald-500/20">
              Æ
            </div>
            <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-emerald-400 to-teal-500 bg-clip-text text-transparent">
              Acme Enterprise SaaS
            </h1>
            <p className={`text-xs mt-1 ${theme === 'dark' ? 'text-slate-400' : 'text-slate-700 font-medium'}`}>Unified Sales Pipelines & Roster Operations</p>
          </div>

          {/* TWO-FACTOR SECURITY VERIFICATION WINDOW */}
          {requires2FA ? (
            <form onSubmit={handle2FAVerifySubmit} className="p-8 space-y-4">
              <div className={`p-4 rounded-xl text-xs flex gap-3 border ${
                theme === 'dark'
                  ? 'bg-emerald-950/20 border-emerald-800/30 text-emerald-400'
                  : 'bg-emerald-50 border-emerald-200 text-emerald-800'
              }`}>
                <Info className="w-5 h-5 shrink-0" />
                <div>
                  <p className="font-bold">Two-Factor Passcode Dispatched</p>
                  <p className="mt-1">For development clearance, verify access using the standard test code: <span className={`font-mono font-bold px-1.5 py-0.5 rounded border ${
                    theme === 'dark'
                      ? 'bg-emerald-500/20 text-white border-emerald-500/30'
                      : 'bg-emerald-100 text-emerald-900 border-emerald-200'
                  }`}>123456</span>.</p>
                </div>
              </div>

              <div>
                <label className={`text-[10px] font-bold uppercase tracking-wider block mb-1.5 ${theme === 'dark' ? 'text-slate-400' : 'text-slate-800'}`}>Verification Passcode</label>
                <div className="relative">
                  <input
                    type="text" required
                    value={otp}
                    onChange={(e) => setOtp(e.target.value)}
                    placeholder="Enter 6-digit PIN"
                    className={`w-full px-4 py-3 text-sm rounded-xl border font-mono text-center tracking-widest text-lg font-bold transition-colors ${
                      theme === 'dark'
                        ? 'bg-slate-950/20 border-slate-700 text-slate-100 placeholder-slate-600'
                        : 'bg-slate-100 border-slate-300 text-slate-900 placeholder-slate-400'
                    }`}
                  />
                </div>
              </div>

              <button
                type="submit"
                className="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-xl text-sm font-bold shadow-lg shadow-emerald-500/15 transition"
              >
                Verify & Log In
              </button>
            </form>
          ) : (
            <>
              {/* Login tab panel switches */}
              <div className="flex border-b border-inherit text-xs font-bold uppercase tracking-wider">
                <button
                  type="button"
                  onClick={() => setAuthTab('login')}
                  className={`flex-1 py-3 text-center border-b-2 transition ${
                    authTab === 'login'
                      ? 'border-emerald-500 text-emerald-500 bg-emerald-500/5'
                      : theme === 'dark' ? 'border-transparent text-slate-500 hover:text-slate-400' : 'border-transparent text-slate-600 hover:text-slate-800'
                  }`}
                >
                  Sign In
                </button>
                <button
                  type="button"
                  onClick={() => setAuthTab('register')}
                  className={`flex-1 py-3 text-center border-b-2 transition ${
                    authTab === 'register'
                      ? 'border-emerald-500 text-emerald-500 bg-emerald-500/5'
                      : theme === 'dark' ? 'border-transparent text-slate-500 hover:text-slate-400' : 'border-transparent text-slate-600 hover:text-slate-800'
                  }`}
                >
                  Create Account
                </button>
              </div>

              {/* Login Block */}
              {authTab === 'login' && (
                <form onSubmit={handleLoginSubmit} className="p-8 space-y-4">
                  <div className={`p-3 rounded-xl text-[11px] space-y-1 border transition-colors ${
                    theme === 'dark'
                      ? 'bg-slate-800/20 border-slate-800 text-slate-400'
                      : 'bg-slate-50 border-slate-200 text-slate-800'
                  }`}>
                    <p className={`font-bold flex items-center gap-1.5 ${theme === 'dark' ? 'text-slate-300' : 'text-slate-800'}`}>
                      <Sparkles className="h-3 w-3 text-emerald-500 dark:text-emerald-400 animate-pulse" />
                      <span>SaaS Developer Preset Credentials:</span>
                    </p>
                    <p>Email: <span className={`font-mono select-all px-1 py-0.5 rounded ${theme === 'dark' ? 'bg-slate-950 text-white' : 'bg-slate-200 text-slate-800 border border-slate-300'}`}>john@nexus.com</span></p>
                    <p>Password: <span className={`font-mono select-all px-1 py-0.5 rounded ${theme === 'dark' ? 'bg-slate-950 text-white' : 'bg-slate-200 text-slate-800 border border-slate-300'}`}>AdminSecurePass2026!</span></p>
                  </div>

                  <div>
                    <label className={`text-[10px] font-bold uppercase tracking-wider block mb-1.5 ${theme === 'dark' ? 'text-slate-400' : 'text-slate-800'}`}>Corporate Email</label>
                    <div className="relative">
                      <Mail className="absolute left-3 top-3 h-4.5 w-4.5 text-slate-500" />
                      <input
                        type="email" required
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        className={`w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border transition-colors ${
                          theme === 'dark'
                            ? 'bg-slate-950/20 border-slate-700 text-slate-100 placeholder-slate-600'
                            : 'bg-slate-100 border-slate-300 text-slate-900 placeholder-slate-500'
                        }`}
                        placeholder="john@nexus.com"
                      />
                    </div>
                  </div>

                  <div>
                    <div className="flex items-center justify-between mb-1.5">
                      <label className={`text-[10px] font-bold uppercase tracking-wider block ${theme === 'dark' ? 'text-slate-400' : 'text-slate-800'}`}>Password</label>
                      <button
                        type="button"
                        onClick={() => setAuthTab('forgot')}
                        className="text-[10px] font-semibold text-emerald-600 dark:text-emerald-500 hover:underline"
                      >
                        Forgot?
                      </button>
                    </div>
                    <div className="relative">
                      <Lock className="absolute left-3 top-3 h-4.5 w-4.5 text-slate-500" />
                      <input
                        type="password" required
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        className={`w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border transition-colors ${
                          theme === 'dark'
                            ? 'bg-slate-950/20 border-slate-700 text-slate-100 placeholder-slate-600'
                            : 'bg-slate-100 border-slate-300 text-slate-900 placeholder-slate-500'
                        }`}
                      />
                    </div>
                  </div>

                  <button
                    type="submit"
                    className="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-xl text-sm font-bold shadow-lg shadow-emerald-500/15 transition mt-2 cursor-pointer"
                  >
                    Authenticate Session
                  </button>
                </form>
              )}

              {/* Registration Block */}
              {authTab === 'register' && (
                <form onSubmit={handleRegisterSubmit} className="p-8 space-y-4">
                  <div>
                    <label className={`text-[10px] font-bold uppercase tracking-wider block mb-1.5 ${theme === 'dark' ? 'text-slate-400' : 'text-slate-800'}`}>Full Name</label>
                    <div className="relative">
                      <User className="absolute left-3 top-3 h-4.5 w-4.5 text-slate-500" />
                      <input
                        type="text" required
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        className={`w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border transition-colors ${
                          theme === 'dark'
                            ? 'bg-slate-950/20 border-slate-700 text-slate-100 placeholder-slate-600'
                            : 'bg-slate-100 border-slate-300 text-slate-900 placeholder-slate-500'
                        }`}
                        placeholder="Elena Rostova"
                      />
                    </div>
                  </div>

                  <div>
                    <label className={`text-[10px] font-bold uppercase tracking-wider block mb-1.5 ${theme === 'dark' ? 'text-slate-400' : 'text-slate-800'}`}>Corporate Email</label>
                    <div className="relative">
                      <Mail className="absolute left-3 top-3 h-4.5 w-4.5 text-slate-500" />
                      <input
                        type="email" required
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        className={`w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border transition-colors ${
                          theme === 'dark'
                            ? 'bg-slate-950/20 border-slate-700 text-slate-100 placeholder-slate-600'
                            : 'bg-slate-100 border-slate-300 text-slate-900 placeholder-slate-500'
                        }`}
                        placeholder="elena@acme.com"
                      />
                    </div>
                  </div>

                  <div>
                    <label className={`text-[10px] font-bold uppercase tracking-wider block mb-1.5 ${theme === 'dark' ? 'text-slate-400' : 'text-slate-800'}`}>Password</label>
                    <div className="relative">
                      <Lock className="absolute left-3 top-3 h-4.5 w-4.5 text-slate-500" />
                      <input
                        type="password" required
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        className={`w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border transition-colors ${
                          theme === 'dark'
                            ? 'bg-slate-950/20 border-slate-700 text-slate-100 placeholder-slate-600'
                            : 'bg-slate-100 border-slate-300 text-slate-900 placeholder-slate-500'
                        }`}
                      />
                    </div>
                  </div>

                  <div>
                    <label className={`text-[10px] font-bold uppercase tracking-wider block mb-1.5 ${theme === 'dark' ? 'text-slate-400' : 'text-slate-800'}`}>Company Title</label>
                    <div className="relative">
                      <Building className="absolute left-3 top-3 h-4.5 w-4.5 text-slate-500" />
                      <input
                        type="text" required
                        value={company}
                        onChange={(e) => setCompany(e.target.value)}
                        className={`w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border transition-colors ${
                          theme === 'dark'
                            ? 'bg-slate-950/20 border-slate-700 text-slate-100 placeholder-slate-600'
                            : 'bg-slate-100 border-slate-300 text-slate-900 placeholder-slate-500'
                        }`}
                        placeholder="Acme Corporation"
                      />
                    </div>
                  </div>

                  <button
                    type="submit"
                    className="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-xl text-sm font-bold shadow-lg shadow-emerald-500/15 transition mt-2 cursor-pointer"
                  >
                    Register Organization
                  </button>
                </form>
              )}

              {/* Forgot Password Block */}
              {authTab === 'forgot' && (
                <form onSubmit={handleForgotPasswordSubmit} className="p-8 space-y-4">
                  <div className={`p-4 rounded-xl text-xs flex gap-3 border ${
                    theme === 'dark'
                      ? 'bg-amber-500/10 border-amber-500/20 text-amber-400'
                      : 'bg-amber-50 border-amber-200 text-amber-900 font-medium'
                  }`}>
                    <Info className="w-5 h-5 shrink-0" />
                    <div>
                      <p className="font-bold text-amber-950 dark:text-amber-400">Password Recovery</p>
                      <p className="mt-1">Provide your registered email to dispatch reset instructions.</p>
                    </div>
                  </div>

                  <div>
                    <label className={`text-[10px] font-bold uppercase tracking-wider block mb-1.5 ${theme === 'dark' ? 'text-slate-400' : 'text-slate-800'}`}>Corporate Email</label>
                    <div className="relative">
                      <Mail className="absolute left-3 top-3 h-4.5 w-4.5 text-slate-500" />
                      <input
                        type="email" required
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        className={`w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border transition-colors ${
                          theme === 'dark'
                            ? 'bg-slate-950/20 border-slate-700 text-slate-100 placeholder-slate-600'
                            : 'bg-slate-100 border-slate-300 text-slate-900 placeholder-slate-500'
                        }`}
                        placeholder="john@nexus.com"
                      />
                    </div>
                  </div>

                  <button
                    type="submit"
                    className="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-xl text-sm font-bold shadow-lg shadow-emerald-500/15 transition mt-2 cursor-pointer"
                  >
                    Dispatch Password Reset
                  </button>

                  <button
                    type="button"
                    onClick={() => setAuthTab('login')}
                    className={`w-full text-xs font-semibold text-center mt-2 transition-colors cursor-pointer ${
                      theme === 'dark' ? 'text-slate-400 hover:text-slate-200' : 'text-slate-600 hover:text-slate-900'
                    }`}
                  >
                    Back to Sign In
                  </button>
                </form>
              )}
            </>
          )}
        </div>
      </div>
    );
  }

  // --- RENDERING MAIN CRM MODULE IF SIGNED IN ---
  return (
    <Layout activeModule={activeModule} setActiveModule={setActiveModule}>
      <ModulesView activeModule={activeModule} />
    </Layout>
  );
}
