<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\TrackingSetting;

class TrackingScriptGeneratorService
{
    /**
     * Generate dynamic JavaScript tracker snippet for tenant
     */
    public function generateScript(string $trackingKey, string $apiBaseUrl): string
    {
        $setting = TrackingSetting::withoutGlobalScopes()
            ->where('tracking_key', $trackingKey)
            ->first();

        $isEnabled = $setting ? (bool) $setting->is_enabled : false;
        $requireConsent = $setting ? (bool) $setting->require_consent : false;
        $enabledEvents = json_encode($setting?->enabled_events ?? TrackingSetting::DEFAULT_EVENTS);

        return <<<JS
(function(window, document) {
    'use strict';

    var CONFIG = {
        key: '{$trackingKey}',
        endpoint: '{$apiBaseUrl}/api/v1/tracking/events',
        enabled: {$isEnabled},
        requireConsent: {$requireConsent},
        enabledEvents: {$enabledEvents}
    };

    if (!CONFIG.enabled) return;

    // Session Management (Memory & SessionStorage, zero permanent hardware fingerprinting)
    function getSessionId() {
        var sid = sessionStorage.getItem('_crm_sid');
        if (!sid) {
            sid = 's_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
            sessionStorage.setItem('_crm_sid', sid);
        }
        return sid;
    }

    // UTM Attribution Capture & Persistence
    function captureUtms() {
        var params = new URLSearchParams(window.location.search);
        var utms = {};
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(function(k) {
            var val = params.get(k);
            if (val) {
                utms[k] = val;
                sessionStorage.setItem('_crm_' + k, val);
            } else {
                var stored = sessionStorage.getItem('_crm_' + k);
                if (stored) utms[k] = stored;
            }
        });
        return utms;
    }

    // Consent Check
    function hasConsent() {
        if (!CONFIG.requireConsent) return true;
        return window.crmConsentGranted === true || localStorage.getItem('_crm_consent') === 'true';
    }

    // Event Dispatcher
    function sendEvent(eventName, metadata, leadId) {
        if (!CONFIG.enabledEvents.includes(eventName)) return;
        if (!hasConsent()) return;

        var utms = captureUtms();
        var payload = {
            tracking_key: CONFIG.key,
            session_id: getSessionId(),
            event_name: eventName,
            event_id: 'evt_' + Math.random().toString(36).substring(2, 11) + Date.now().toString(36),
            page_url: window.location.href,
            page_title: document.title,
            referrer: document.referrer || null,
            utm_source: utms.utm_source || null,
            utm_medium: utms.utm_medium || null,
            utm_campaign: utms.utm_campaign || null,
            utm_term: utms.utm_term || null,
            utm_content: utms.utm_content || null,
            consent_given: hasConsent(),
            lead_id: leadId || null,
            metadata: metadata || {}
        };

        var dataStr = JSON.stringify(payload);
        if (navigator.sendBeacon) {
            navigator.sendBeacon(CONFIG.endpoint, new Blob([dataStr], { type: 'application/json' }));
        } else {
            fetch(CONFIG.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: dataStr,
                keepalive: true
            }).catch(function() {});
        }
    }

    // Global Tracker API
    window.crmTrack = function(name, meta) {
        sendEvent(name, meta);
    };

    window.crmSetConsent = function(granted) {
        window.crmConsentGranted = !!granted;
        localStorage.setItem('_crm_consent', granted ? 'true' : 'false');
        if (granted) {
            sendEvent('consent_granted', { timestamp: new Date().toISOString() });
        }
    };

    // Auto-capture Page View
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        sendEvent('page_view');
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            sendEvent('page_view');
        });
    }

    // Auto-capture Form Submissions & Voluntary Contact Identifiers
    document.addEventListener('submit', function(e) {
        var form = e.target;
        var meta = { form_id: form.id || form.getAttribute('name') || 'unnamed_form' };
        
        // Safely extract voluntarily provided email/phone for lead linking
        var emailInput = form.querySelector('input[type="email"], input[name*="email"]');
        var phoneInput = form.querySelector('input[type="tel"], input[name*="phone"]');
        
        if (emailInput && emailInput.value) meta.email = emailInput.value;
        if (phoneInput && phoneInput.value) meta.phone = phoneInput.value;

        sendEvent('form_submission', meta);
    }, true);

    // Auto-capture CTA Clicks
    document.addEventListener('click', function(e) {
        var target = e.target.closest('[data-crm-track], a.btn, button.btn, button[type="submit"]');
        if (target) {
            var label = target.getAttribute('data-crm-track') || target.innerText || target.value || 'cta_button';
            sendEvent('cta_click', {
                label: label.trim().substring(0, 100),
                tag: target.tagName,
                href: target.getAttribute('href') || null
            });
        }
    }, true);

})(window, document);
JS;
    }
}
