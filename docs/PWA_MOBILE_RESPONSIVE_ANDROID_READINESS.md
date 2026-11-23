# PWA, Mobile-Responsive Experience & Future Android App Readiness

## 1. Overview
This document details the progressive web application (PWA) architecture, responsive mobile experience, safe asset caching strategies, security restrictions, and future native Android wrapper (Capacitor/Cordova) readiness for the Nexus Enterprise AI CRM SaaS.

---

## 2. PWA Specification & Web App Manifest

The PWA manifest is located at `frontend/public/manifest.webmanifest` and referenced in `frontend/index.html`:

- **Application Name**: `Nexus Enterprise AI CRM`
- **Short Name**: `Nexus CRM`
- **Display Mode**: `standalone` (removes browser URL bar and browser frame for a native application feel)
- **Orientation**: `portrait-primary`
- **Theme Color**: `#0f172a` (Slate 900)
- **Background Color**: `#020617` (Slate 950)
- **App Icons**: High-resolution SVG icon and maskable icons located in `/icons/icon.svg`
- **Quick Action Shortcuts**:
  - `/inbox` (Omnichannel Inbox)
  - `/leads` (Leads Pipeline)
  - `/tasks` (Tasks Manager)
  - `/dashboard` (Dashboard)

---

## 3. Service Worker & Safe Caching Security Architecture

The Service Worker (`frontend/public/sw.js`) provides reliable offline shell delivery while enforcing strict security rules for multi-tenant enterprise CRM data:

### Strict Security Policy
> [!IMPORTANT]
> **Zero Local Caching of Sensitive CRM API Records**:
> To guarantee enterprise tenant confidentiality and regulatory compliance (GDPR, DPDP, SOC2), the Service Worker explicitly inspects request URLs and headers.
> Any request matching `/api/*`, `/api/v1/*`, containing `Authorization: Bearer <token>`, or with HTTP methods other than `GET` is **never cached** into `CacheStorage`. It operates exclusively as a direct network pass-through.

### Static Asset Strategy
- **Pre-cached Application Shell**: `/`, `/index.html`, `/manifest.webmanifest`, `/icons/icon.svg`.
- **Static Assets (JS, CSS, SVGs, Fonts, Images)**: Stale-While-Revalidate caching pattern with versioned cache name (`nexus-crm-static-v1.0.0`).
- **Cache Invalidation**: On activation, outdated caches are pruned automatically.
- **Push Notifications & Deep Linking**: Implements `push` and `notificationclick` listeners with client window matching and URL routing.

---

## 4. Mobile User Experience & Touch Optimization

The frontend provides a first-class mobile user experience without duplicating backend business logic:

1. **Persistent Mobile Bottom Navigation Bar (`MobileBottomNavBar`)**:
   - Visible on small viewports (`< 768px`).
   - Direct access to high-frequency actions: **Home**, **Leads**, **Inbox**, **Tasks**, and **Menu** (triggers full drawer).
   - Safe-area bottom inset padding (`env(safe-area-inset-bottom)`) for edge-to-edge Android and iOS displays.
   - All touch targets satisfy the $\ge 44\text{px} \times 44\text{px}$ accessibility standard.
2. **Mobile Sidebar Drawer**:
   - Smooth backdrop overlay with tap-to-dismiss behavior.
   - Auto-closes upon navigation item selection on mobile screens.
3. **PWA Install Banner (`PwaInstallBanner`)**:
   - Listens to `beforeinstallprompt` event and displays a non-intrusive install action.
   - Respects user dismissal with session suppression.
4. **Offline Connection Indicator (`OfflineStatusIndicator`)**:
   - Detects network transitions via `navigator.onLine` and `online`/`offline` event listeners.
   - Displays clear warning when offline informing the user that sensitive CRM data is not cached locally.
   - Provides reassuring visual feedback when internet connectivity is restored.
5. **Omnichannel Inbox Mobile Screen Split**:
   - On mobile screens, conversation list and message detail views are cleanly separated.
   - Selecting a conversation automatically transitions to the message view with a "Back to Conversations" header button.

---

## 5. Mobile Bridge & Future Android Wrapper Readiness

The `mobileBridge` adapter (`frontend/src/utils/mobileBridge.ts`) serves as an abstraction layer between web APIs and native Android container APIs (such as Capacitor):

### Implemented Bridge Capabilities
- **Platform Detection**: Identifies whether the app runs as `web`, `pwa`, `android`, or `ios` without throwing in non-browser or SSR environments.
- **Version Telemetry**: Exposes `getAppVersionInfo()` returning `version`, `buildNumber`, `environment`, and health check update endpoints.
- **Secure Storage Adapter**: Routes authentication tokens through `Capacitor.Plugins.Preferences` if running inside a native container, falling back to isolated browser storage.
- **Camera & File Attachments**: Supports `pickImageOrCamera()` with environment camera capture support (`capture="environment"`).
- **Native Phone Dialer**: Triggers device telephony via `triggerPhoneDialer(phone)` (`tel:` URL scheme).
- **Deep Linking**: Handles custom URI schemes (`crm://leads/123`, `crm://inbox/45`) and URL search params via `initDeepLinkListener`.
- **System Share Sheet**: Triggers `navigator.share` / native share dialog with graceful fallback.
- **Haptic Feedback**: Invokes `navigator.vibrate` for tactile confirmation on user interactions.
- **Push Notification Permission**: Requests system-level push notification authorization via `requestPushPermission()`.

### Capacitor Wrapper Deployment Steps (Future Phase)
When ready to build a native Android APK / AAB package:
```bash
# 1. Install Capacitor core and Android platform in frontend/
cd frontend
npm install @capacitor/core @capacitor/android @capacitor/preferences @capacitor/camera @capacitor/push-notifications
npx cap init "Nexus Enterprise CRM" "com.nexus.crm" --web-dir dist

# 2. Add Android native platform
npx cap add android

# 3. Build web assets and sync to Android project
npm run build
npx cap sync android

# 4. Open Android Studio to build signed APK or bundle
npx cap open android
```
*(Backend requires zero modifications as all business logic and authentication endpoints remain unified.)*

---

## 6. Verification & Quality Assurance

- **Integration Verification**: Step 40 in `frontend/src/tests/apiIntegration.test.ts` validates manifest structure, Service Worker security policies, and mobile bridge telemetry.
  - Result: **41/41 integration tests passing**.
- **Production Build**: `npm.cmd run build` passes with zero TypeScript or bundling errors.
- **Backend Test Suite**: `php artisan test` passes with **173 passed (1085 assertions)**.
