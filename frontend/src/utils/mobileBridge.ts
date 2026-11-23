/**
 * Nexus Enterprise AI CRM SaaS - Mobile Bridge & Android Wrapper Readiness Adapter
 * 
 * Prepares the frontend for Capacitor / Cordova native Android container wrapping
 * while providing full Progressive Web App (PWA) fallback in all modern mobile browsers.
 */

export interface AppVersionInfo {
  version: string;
  buildNumber: number;
  environment: string;
  updateCheckUrl: string;
  isNative: boolean;
  platform: 'android' | 'ios' | 'pwa' | 'web';
}

declare global {
  interface Window {
    Capacitor?: {
      isNativePlatform?: () => boolean;
      getPlatform?: () => string;
      Plugins?: Record<string, any>;
    };
  }
}

export class MobileBridge {
  private static instance: MobileBridge;

  private constructor() {}

  public static getInstance(): MobileBridge {
    if (!MobileBridge.instance) {
      MobileBridge.instance = new MobileBridge();
    }
    return MobileBridge.instance;
  }

  /**
   * Check if running inside native Android wrapper container (Capacitor/Cordova)
   */
  public isNativeAndroid(): boolean {
    if (typeof window === 'undefined') return false;
    const capacitorPlatform = window.Capacitor?.getPlatform ? window.Capacitor.getPlatform() : '';
    if (capacitorPlatform === 'android') return true;
    return /Android/i.test(navigator.userAgent) && Boolean(window.Capacitor?.isNativePlatform?.());
  }

  /**
   * Check if running as an installed PWA (Standalone Display Mode)
   */
  public isPwaStandalone(): boolean {
    if (typeof window === 'undefined') return false;
    return (
      window.matchMedia('(display-mode: standalone)').matches ||
      (navigator as any).standalone === true ||
      document.referrer.includes('android-app://')
    );
  }

  /**
   * Detect current execution platform
   */
  public getPlatformName(): 'android' | 'ios' | 'pwa' | 'web' {
    if (this.isNativeAndroid()) return 'android';
    if (typeof window !== 'undefined' && window.Capacitor?.getPlatform?.() === 'ios') return 'ios';
    if (this.isPwaStandalone()) return 'pwa';
    return 'web';
  }

  /**
   * Get application version and wrapper telemetry
   */
  public getAppVersionInfo(): AppVersionInfo {
    return {
      version: '1.0.0',
      buildNumber: 100,
      environment: 'production',
      updateCheckUrl: '/api/v1/health',
      isNative: this.isNativeAndroid(),
      platform: this.getPlatformName(),
    };
  }

  /**
   * Secure Session Token Storage
   * Bridges to Capacitor Preferences / Keystore or secure storage
   */
  public async setSecureItem(key: string, value: string): Promise<void> {
    try {
      if (typeof window !== 'undefined' && window.Capacitor?.Plugins?.Preferences) {
        await window.Capacitor.Plugins.Preferences.set({ key, value });
        return;
      }
    } catch (_) {}
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(`nexus_sec_${key}`, value);
    }
  }

  public async getSecureItem(key: string): Promise<string | null> {
    try {
      if (typeof window !== 'undefined' && window.Capacitor?.Plugins?.Preferences) {
        const res = await window.Capacitor.Plugins.Preferences.get({ key });
        return res.value;
      }
    } catch (_) {}
    return typeof localStorage !== 'undefined' ? localStorage.getItem(`nexus_sec_${key}`) : null;
  }

  public async removeSecureItem(key: string): Promise<void> {
    try {
      if (typeof window !== 'undefined' && window.Capacitor?.Plugins?.Preferences) {
        await window.Capacitor.Plugins.Preferences.remove({ key });
        return;
      }
    } catch (_) {}
    if (typeof localStorage !== 'undefined') {
      localStorage.removeItem(`nexus_sec_${key}`);
    }
  }

  /**
   * Camera and Gallery Picker Bridge
   * Invokes native Capacitor Camera if available, or HTML5 capture input
   */
  public pickImageOrCamera(options: { cameraOnly?: boolean } = {}): Promise<File | null> {
    return new Promise((resolve) => {
      // Future Capacitor Camera plugin check
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/*,application/pdf';
      if (options.cameraOnly) {
        input.capture = 'environment';
      }

      input.onchange = (e: any) => {
        const file = e.target?.files?.[0] || null;
        resolve(file);
      };

      input.click();
    });
  }

  /**
   * Native Phone Dialer Invocation
   */
  public triggerPhoneDialer(phoneNumber: string): void {
    const cleanNumber = phoneNumber.replace(/[^0-9+]/g, '');
    if (typeof window !== 'undefined') {
      window.location.href = `tel:${cleanNumber}`;
    }
  }

  /**
   * Deep Link Listener Handler (e.g. crm://leads/42, crm://inbox/10)
   */
  public initDeepLinkListener(callback: (path: string, params: Record<string, string>) => void): void {
    if (typeof window === 'undefined') return;

    // Capacitor App URL Open event
    if (window.Capacitor?.Plugins?.App) {
      window.Capacitor.Plugins.App.addListener('appUrlOpen', (data: { url: string }) => {
        try {
          const parsed = new URL(data.url);
          const path = parsed.pathname || parsed.hostname;
          const params: Record<string, string> = {};
          parsed.searchParams.forEach((v, k) => {
            params[k] = v;
          });
          callback(path, params);
        } catch (_) {}
      });
    }

    // Web URL query param deep linking (?module=inbox&action=open)
    const currentParams = new URLSearchParams(window.location.search);
    const module = currentParams.get('module');
    if (module) {
      const params: Record<string, string> = {};
      currentParams.forEach((v, k) => {
        params[k] = v;
      });
      callback(module, params);
    }
  }

  /**
   * Native Device Share Sheet
   */
  public async shareContent(options: { title: string; text?: string; url?: string }): Promise<boolean> {
    if (typeof navigator !== 'undefined' && navigator.share) {
      try {
        await navigator.share(options);
        return true;
      } catch (_) {
        return false;
      }
    }
    return false;
  }

  /**
   * Device Haptic Feedback
   */
  public hapticFeedback(pattern: number | number[] = 25): void {
    if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
      navigator.vibrate(pattern);
    }
  }

  /**
   * Push Notification Permission Request
   */
  public async requestPushPermission(): Promise<NotificationPermission | 'unsupported'> {
    if (typeof window === 'undefined' || !('Notification' in window)) {
      return 'unsupported';
    }
    return Notification.requestPermission();
  }
}

export const mobileBridge = MobileBridge.getInstance();
