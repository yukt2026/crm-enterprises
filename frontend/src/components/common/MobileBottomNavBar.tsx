import React from 'react';
import {
  LayoutDashboard,
  Users2,
  Inbox,
  ClipboardList,
  Menu,
} from 'lucide-react';

interface MobileBottomNavBarProps {
  currentView: string;
  onNavigate: (view: any) => void;
  onOpenMenu: () => void;
  isMenuOpen: boolean;
}

export const MobileBottomNavBar: React.FC<MobileBottomNavBarProps> = ({
  currentView,
  onNavigate,
  onOpenMenu,
  isMenuOpen,
}) => {
  const navItems = [
    {
      id: 'dashboard',
      label: 'Home',
      icon: LayoutDashboard,
      active: currentView === 'dashboard' && !isMenuOpen,
    },
    {
      id: 'leads',
      label: 'Leads',
      icon: Users2,
      active: currentView === 'leads' && !isMenuOpen,
    },
    {
      id: 'inbox',
      label: 'Inbox',
      icon: Inbox,
      active: currentView === 'inbox' && !isMenuOpen,
    },
    {
      id: 'tasks',
      label: 'Tasks',
      icon: ClipboardList,
      active: currentView === 'tasks' && !isMenuOpen,
    },
  ];

  return (
    <nav
      aria-label="Mobile Bottom Navigation"
      className="md:hidden fixed bottom-0 left-0 right-0 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 z-40 px-2 py-1 flex items-center justify-around shadow-lg select-none"
      style={{ paddingBottom: 'max(0.35rem, env(safe-area-inset-bottom))' }}
    >
      {navItems.map((item) => {
        const Icon = item.icon;
        return (
          <button
            key={item.id}
            onClick={() => onNavigate(item.id)}
            className={`flex flex-col items-center justify-center min-w-[56px] min-h-[48px] py-1 px-2 rounded-lg transition-colors ${
              item.active
                ? 'text-blue-600 dark:text-blue-400 font-semibold'
                : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
            }`}
          >
            <Icon className={`w-5 h-5 mb-0.5 ${item.active ? 'stroke-[2.5]' : 'stroke-[1.8]'}`} />
            <span className="text-[10px] tracking-tight">{item.label}</span>
          </button>
        );
      })}

      <button
        onClick={onOpenMenu}
        aria-label="Open navigation menu"
        className={`flex flex-col items-center justify-center min-w-[56px] min-h-[48px] py-1 px-2 rounded-lg transition-colors ${
          isMenuOpen
            ? 'text-blue-600 dark:text-blue-400 font-semibold'
            : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
        }`}
      >
        <Menu className={`w-5 h-5 mb-0.5 ${isMenuOpen ? 'stroke-[2.5]' : 'stroke-[1.8]'}`} />
        <span className="text-[10px] tracking-tight">Menu</span>
      </button>
    </nav>
  );
};

export default MobileBottomNavBar;
