/**
 * Frontend Application Logic for System Management Portal (PlasticDX)
 * Complete flow: Registration -> Login -> User Profile -> RBAC -> Admin Workspace
 * Includes defensive null/undefined guards, dual-environment routing (Server/Dev),
 * attached system guide documents management, and fault-tolerant rendering.
 */

(function () {
    'use strict';

    const state = {
        systems: [],
        documents: [],
        users: [],
        currentModalDocs: [], // Attached documents for currently edited system
        stats: {
            total_systems: 0,
            server_systems: 0,
            dev_systems: 0,
            total_documents: 0,
            total_users: 0
        },
        user: {
            id: 0,
            username: 'guest',
            employee_code: 'GUEST',
            full_name: 'Khách truy cập',
            email: '',
            phone: '',
            role: 'user',
            department: 'Truy cập chung',
            is_logged: false
        },
        isAdmin: false,
        csrfToken: '',
        activeView: 'portal',        // 'portal' | 'admin'
        adminSubTab: 'systems',      // 'systems' | 'docs' | 'users'
        systemFilterGroup: 'ALL',    // 'ALL' | 'SERVER' | 'DEVELOPMENT'
        systemSearchQuery: '',
        selectedSystemId: null,
        pendingAdminRedirect: false,
        apiError: null
    };

    // Unified Lucide Vector Icon Set
    const ICON_SVGS = {
        factory: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"></path><path d="M17 18h1"></path><path d="M12 18h1"></path><path d="M7 18h1"></path></svg>',
        qr_code: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="5" height="5" x="3" y="3" rx="1"></rect><rect width="5" height="5" x="16" y="3" rx="1"></rect><rect width="5" height="5" x="3" y="16" rx="1"></rect><path d="M21 16h-3a2 2 0 0 0-2 2v3"></path><path d="M21 21v.01"></path><path d="M12 7v3a2 2 0 0 1-2 2H7"></path><path d="M3 12h.01"></path><path d="M12 3h.01"></path><path d="M12 16v.01"></path><path d="M16 12h1"></path><path d="M21 12v.01"></path><path d="M12 21v-1"></path></svg>',
        monitoring: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"></rect><line x1="8" x2="16" y1="21" y2="21"></line><line x1="12" x2="12" y1="17" y2="21"></line><path d="M6 12l3-3 3 2 4-4"></path></svg>',
        tune: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="21" x2="14" y1="4" y2="4"></line><line x1="10" x2="3" y1="4" y2="4"></line><line x1="21" x2="12" y1="12" y2="12"></line><line x1="8" x2="3" y1="12" y2="12"></line><line x1="21" x2="16" y1="20" y2="20"></line><line x1="12" x2="3" y1="20" y2="20"></line><line x1="14" x2="14" y1="2" y2="6"></line><line x1="8" x2="8" y1="10" y2="14"></line><line x1="16" x2="16" y1="18" y2="22"></line></svg>',
        album: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="3"></circle><path d="M12 2v3"></path><path d="M12 19v3"></path></svg>',
        palette: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"></circle><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"></circle><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"></circle><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"></circle><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"></path></svg>',
        document_scanner: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><rect width="10" height="12" x="7" y="6" rx="1"></rect><path d="M9 10h6"></path><path d="M9 14h4"></path></svg>',
        analytics: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path></svg>',
        rocket_launch: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"></path><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"></path></svg>',
        grid: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>',
        storage: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5V19A9 3 0 0 0 21 19V5"></path><path d="M3 12A9 3 0 0 0 21 12"></path></svg>',
        build: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>',
        inventory: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 9.4 7.55 4.24"></path><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.29 7 12 12 20.71 7"></polyline><line x1="12" x2="12" y1="22" y2="12"></line></svg>',
        security: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path><path d="m9 12 2 2 4-4"></path></svg>'
    };

    const UI_ICONS = {
        book: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path></svg>',
        arrowRight: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>',
        external: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"></path><path d="M7 7h10v10"></path></svg>',
        edit: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>',
        trash: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>',
        server: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2" ry="2"></rect><rect width="20" height="8" x="2" y="14" rx="2" ry="2"></rect><line x1="6" x2="6.01" y1="6" y2="6"></line><line x1="6" x2="6.01" y1="18" y2="18"></line></svg>',
        dev: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>',
        check: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>',
        alert: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line></svg>'
    };

    // =========================================================================
    // DEFENSIVE DOM & CLASSLIST HELPERS (Null/Undefined Safe)
    // =========================================================================

    function resolveElement(elOrId) {
        if (!elOrId) return null;
        if (typeof elOrId === 'string') {
            return document.getElementById(elOrId);
        }
        if (typeof elOrId === 'object' && elOrId.nodeType === 1) {
            return elOrId;
        }
        return null;
    }

    function safeClassAdd(elOrId, ...classNames) {
        const el = resolveElement(elOrId);
        if (el && el.classList && typeof el.classList.add === 'function') {
            classNames.forEach(cls => {
                if (cls) el.classList.add(cls);
            });
        }
    }

    function safeClassRemove(elOrId, ...classNames) {
        const el = resolveElement(elOrId);
        if (el && el.classList && typeof el.classList.remove === 'function') {
            classNames.forEach(cls => {
                if (cls) el.classList.remove(cls);
            });
        }
    }

    function safeClassContains(elOrId, className) {
        const el = resolveElement(elOrId);
        if (el && el.classList && typeof el.classList.contains === 'function' && className) {
            return el.classList.contains(className);
        }
        return false;
    }

    function safeClassToggle(elOrId, className, force) {
        const el = resolveElement(elOrId);
        if (el && el.classList && typeof el.classList.toggle === 'function' && className) {
            return force !== undefined
                ? el.classList.toggle(className, Boolean(force))
                : el.classList.toggle(className);
        }
        return false;
    }

    function safeSetText(elOrId, text) {
        const el = resolveElement(elOrId);
        if (el) {
            el.textContent = text !== null && text !== undefined ? String(text) : '';
        }
    }

    function safeSetValue(elOrId, val) {
        const el = resolveElement(elOrId);
        if (el && 'value' in el) {
            el.value = val !== null && val !== undefined ? String(val) : '';
        }
    }

    function safeGetValue(elOrId, fallback = '') {
        const el = resolveElement(elOrId);
        if (el && 'value' in el && el.value !== null && el.value !== undefined) {
            return String(el.value);
        }
        return fallback;
    }

    function openModal(modalId) {
        safeClassAdd(modalId, 'open');
    }

    function closeModal(modalId) {
        safeClassRemove(modalId, 'open');
    }

    function safeText(val, fallback = '') {
        if (val === null || val === undefined || val === 'null' || val === 'undefined') {
            return fallback;
        }
        const trimmed = String(val).trim();
        return trimmed === '' ? fallback : trimmed;
    }

    function escapeHtml(str) {
        const clean = safeText(str, '');
        return clean
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getIconSvg(iconName) {
        return ICON_SVGS[iconName] || ICON_SVGS.grid;
    }

    function getInitials(fullName) {
        const clean = safeText(fullName, 'U');
        const parts = clean.split(/\s+/);
        if (parts.length >= 2 && parts[0][0] && parts[parts.length - 1][0]) {
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }
        return clean.substring(0, 2).toUpperCase();
    }

    function showToast(message, type = 'success') {
        const container = resolveElement('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast-msg ${type === 'error' ? 'toast-error' : 'toast-success'}`;
        toast.innerHTML = `
            <span style="color:${type === 'error' ? 'var(--danger)' : 'var(--server-color)'};">
                ${type === 'error' ? UI_ICONS.alert : UI_ICONS.check}
            </span>
            <span>${escapeHtml(message)}</span>
        `;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.2s ease';
            setTimeout(() => {
                if (toast && toast.parentNode) {
                    toast.remove();
                }
            }, 200);
        }, 3200);
    }

    /**
     * Resolve Server (Production) and Development (Dev/Test) URLs independently
     */
    function resolveEnvironmentUrls(sys) {
        if (!sys || typeof sys !== 'object') {
            return { serverUrl: '', devUrl: '' };
        }

        if (sys.url_server !== undefined || sys.url_dev !== undefined) {
            return {
                serverUrl: safeText(sys.url_server, ''),
                devUrl: safeText(sys.url_dev, '')
            };
        }

        const rawUrl = safeText(sys.url, '');
        if (rawUrl.includes('|')) {
            const parts = rawUrl.split('|');
            return {
                serverUrl: safeText(parts[0], ''),
                devUrl: safeText(parts[1], '')
            };
        }

        if (sys.group_type === 'DEVELOPMENT') {
            return {
                serverUrl: '',
                devUrl: rawUrl
            };
        }

        return {
            serverUrl: rawUrl,
            devUrl: ''
        };
    }

    /**
     * Recalculate stats from state.systems & state.documents
     */
    function syncStatsFromData(rawStats) {
        const systemsList = Array.isArray(state.systems) ? state.systems : [];
        const docsList = Array.isArray(state.documents) ? state.documents : [];
        const usersList = Array.isArray(state.users) ? state.users : [];

        let computedServer = 0;
        let computedDev = 0;
        systemsList.forEach(s => {
            const { serverUrl, devUrl } = resolveEnvironmentUrls(s);
            if (serverUrl && !devUrl) {
                computedServer++;
            } else if (!serverUrl && devUrl) {
                computedDev++;
            } else if (serverUrl && devUrl) {
                computedServer++;
                computedDev++;
            }
        });

        state.stats = {
            total_systems: (rawStats && typeof rawStats.total_systems === 'number') ? rawStats.total_systems : systemsList.length,
            server_systems: (rawStats && typeof rawStats.server_systems === 'number') ? rawStats.server_systems : computedServer,
            dev_systems: (rawStats && typeof rawStats.dev_systems === 'number') ? rawStats.dev_systems : computedDev,
            total_documents: (rawStats && typeof rawStats.total_documents === 'number') ? rawStats.total_documents : docsList.length,
            total_users: (rawStats && typeof rawStats.total_users === 'number') ? rawStats.total_users : usersList.length
        };
    }

    /**
     * Load portal data from backend API with comprehensive error handling
     */
    async function loadPortalData() {
        try {
            state.apiError = null;
            const response = await fetch('api.php?action=get_portal_data', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status} (${response.statusText})`);
            }

            let data = null;
            try {
                data = await response.json();
            } catch (parseErr) {
                console.error('[PortalApp] Failed to parse JSON from api.php:', parseErr);
                throw new Error('Phản hồi từ máy chủ không đúng định dạng JSON.');
            }

            if (!data || !data.success) {
                const errMsg = (data && data.message) ? data.message : 'Không thể tải dữ liệu Portal.';
                console.error('[PortalApp] API returned unsuccessful status:', data);
                state.apiError = errMsg;
                showToast(errMsg, 'error');
                renderAll();
                return;
            }

            state.systems = Array.isArray(data.systems) ? data.systems : [];
            state.documents = Array.isArray(data.documents) ? data.documents : [];
            state.users = Array.isArray(data.users) ? data.users : [];
            state.user = (data.user && typeof data.user === 'object') ? data.user : state.user;
            state.isAdmin = Boolean(data.is_admin);
            state.csrfToken = safeText(data.csrf_token, state.csrfToken);

            syncStatsFromData(data.stats);
            renderAll();
        } catch (err) {
            console.error('[PortalApp] Error loading portal data:', err);
            state.apiError = 'Không thể kết nối đến máy chủ để tải dữ liệu hệ thống. Vui lòng kiểm tra kết nối và thử lại.';
            showToast('Không thể tải dữ liệu từ máy chủ. Vui lòng thử lại.', 'error');
            renderAll();
        }
    }

    function renderAll() {
        try {
            renderHeaderControls();
        } catch (err) {
            console.error('[PortalApp] Error in renderHeaderControls:', err);
        }

        try {
            renderFilterCounts();
        } catch (err) {
            console.error('[PortalApp] Error in renderFilterCounts:', err);
        }

        try {
            renderMinimalSystemsGrid();
        } catch (err) {
            console.error('[PortalApp] Error in renderMinimalSystemsGrid:', err);
        }

        try {
            renderDocumentSystemSelect();
        } catch (err) {
            console.error('[PortalApp] Error in renderDocumentSystemSelect:', err);
        }

        try {
            renderAdminWorkspace();
        } catch (err) {
            console.error('[PortalApp] Error in renderAdminWorkspace:', err);
        }
    }

    /**
     * Update header controls according to user session & RBAC rules
     */
    function renderHeaderControls() {
        const isLogged = Boolean(state.user && state.user.is_logged);
        const btnOpenLogin = resolveElement('btnOpenLoginModal');
        const btnOpenRegister = resolveElement('btnOpenRegisterModal');
        const btnProfileTrigger = resolveElement('btnUserProfileTrigger');
        const btnLogout = resolveElement('btnLogout');
        const btnToggleAdminView = resolveElement('btnToggleAdminView');
        const btnQuickAddSystem = resolveElement('btnQuickAddSystem');
        const footerAdminLink = resolveElement('footerAdminAuthTrigger');

        if (btnOpenLogin) btnOpenLogin.style.display = isLogged ? 'none' : 'inline-flex';
        if (btnOpenRegister) btnOpenRegister.style.display = isLogged ? 'none' : 'inline-flex';
        if (btnLogout) btnLogout.style.display = isLogged ? 'inline-flex' : 'none';

        if (btnProfileTrigger) {
            if (isLogged) {
                btnProfileTrigger.style.display = 'inline-flex';
                safeSetText('headerUserAvatar', getInitials(state.user.full_name));
                safeSetText('headerUserName', safeText(state.user.full_name, state.user.username));
                const roleEl = resolveElement('headerUserRoleTag');
                if (roleEl) {
                    roleEl.textContent = state.isAdmin ? 'Admin' : 'User';
                    safeClassToggle(roleEl, 'tag-admin', state.isAdmin);
                }
            } else {
                btnProfileTrigger.style.display = 'none';
            }
        }

        if (btnToggleAdminView) {
            btnToggleAdminView.style.display = state.isAdmin ? 'inline-flex' : 'none';
        }
        if (btnQuickAddSystem) {
            btnQuickAddSystem.style.display = state.isAdmin ? 'inline-flex' : 'none';
        }
        if (footerAdminLink) {
            footerAdminLink.style.display = (isLogged && !state.isAdmin) ? 'none' : 'inline-block';
        }

        if (!state.isAdmin && state.activeView === 'admin') {
            toggleAdminView('portal');
        }
    }

    /**
     * Update counts inside segmented filter buttons
     */
    function renderFilterCounts() {
        safeSetText('countFilterAll', Number(state.stats.total_systems || 0));
        safeSetText('countFilterServer', Number(state.stats.server_systems || 0));
        safeSetText('countFilterDev', Number(state.stats.dev_systems || 0));
    }

    function getFilteredSystems() {
        const list = Array.isArray(state.systems) ? state.systems : [];
        const q = safeText(state.systemSearchQuery, '').toLowerCase();

        return list.filter(sys => {
            if (!sys || typeof sys !== 'object') return false;

            const { serverUrl, devUrl } = resolveEnvironmentUrls(sys);
            if (state.systemFilterGroup === 'SERVER' && !serverUrl) {
                return false;
            }
            if (state.systemFilterGroup === 'DEVELOPMENT' && !devUrl) {
                return false;
            }

            if (q !== '') {
                const hay = `${safeText(sys.name)} ${safeText(sys.description)} ${safeText(sys.department)} ${safeText(sys.system_code)}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });
    }

    /**
     * Core Portal Routing Rule:
     * - Only Server URL: Opens Server directly, does NOT show popup.
     * - Only Dev URL: Opens Dev directly, does NOT show popup.
     * - Both URLs: Opens popup modal to select environment.
     * - Neither: Shows toast error notice.
     */
    function handleSystemClick(systemId) {
        const sys = state.systems.find(s => s && Number(s.id) === Number(systemId));
        if (!sys) return;

        const { serverUrl, devUrl } = resolveEnvironmentUrls(sys);
        const hasServer = Boolean(serverUrl && serverUrl.trim() && serverUrl !== '#');
        const hasDev = Boolean(devUrl && devUrl.trim() && devUrl !== '#');

        if (hasServer && !hasDev) {
            window.open(serverUrl, '_blank', 'noopener,noreferrer');
            return;
        }

        if (!hasServer && hasDev) {
            window.open(devUrl, '_blank', 'noopener,noreferrer');
            return;
        }

        if (hasServer && hasDev) {
            openEnvironmentModal(systemId);
            return;
        }

        showToast('Hệ thống chưa được cấu hình đường dẫn truy cập.', 'error');
    }

    /**
     * Render a single System Card safely.
     */
    function renderSingleSystemCard(sys) {
        if (!sys || typeof sys !== 'object') return '';

        const sysId = Number(sys.id) || 0;
        const isActive = Number(sys.is_active ?? 1) === 1;
        const sysName = safeText(sys.name, 'Hệ thống');
        const sysDesc = safeText(sys.description, 'Hệ thống thành phần trong Portal.');
        const sysIcon = safeText(sys.icon, 'grid');

        const { serverUrl, devUrl } = resolveEnvironmentUrls(sys);
        const hasServer = Boolean(serverUrl && serverUrl.trim() && serverUrl !== '#');
        const hasDev = Boolean(devUrl && devUrl.trim() && devUrl !== '#');

        let cardTitle = '';
        let badgeHtml = '';
        let launchText = 'Truy cập';

        if (hasServer && !hasDev) {
            cardTitle = `Nhấn để truy cập trực tiếp ${sysName} trên Server`;
            badgeHtml = '<span class="card-env-pill pill-server">Server</span>';
            launchText = 'Mở Server';
        } else if (!hasServer && hasDev) {
            cardTitle = `Nhấn để truy cập trực tiếp ${sysName} trên Development`;
            badgeHtml = '<span class="card-env-pill pill-dev">Dev</span>';
            launchText = 'Mở Dev';
        } else {
            cardTitle = `Nhấn để chọn môi trường truy cập ${sysName}`;
            badgeHtml = '<span class="card-env-pill pill-dual">Server • Dev</span>';
            launchText = 'Truy cập';
        }

        const docCount = state.documents.filter(d => d && Number(d.system_id) === Number(sysId)).length;
        const docBadge = docCount > 0 ? ` (${docCount})` : '';

        const adminQuickBtn = state.isAdmin ? `
            <div class="card-admin-quick" onclick="event.stopPropagation();">
                <button type="button" class="btn btn-sm btn-outline"
                        onclick="window.PortalApp.openEditSystemModal(${sysId})"
                        title="Chỉnh sửa hệ thống">
                    ${UI_ICONS.edit}
                </button>
            </div>
        ` : '';

        return `
            <article class="system-card ${!isActive ? 'card-inactive' : ''}"
                     onclick="window.PortalApp.handleSystemClick(${sysId})"
                     title="${escapeHtml(cardTitle)}">
                ${adminQuickBtn}
                <div class="card-main">
                    <div class="system-icon-wrap">
                        ${getIconSvg(sysIcon)}
                    </div>
                    <div class="card-info">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:6px; margin-bottom:4px;">
                            <h3 class="system-title" style="margin-bottom:0;">${escapeHtml(sysName)}</h3>
                            ${badgeHtml}
                        </div>
                        <p class="system-desc">${escapeHtml(sysDesc)}</p>
                    </div>
                </div>

                <div class="card-actions-bar">
                    <button type="button" class="btn-card-doc"
                            onclick="window.PortalApp.openSystemDocsModal(${sysId}, event)"
                            title="Xem tài liệu hướng dẫn của ${escapeHtml(sysName)}">
                        ${UI_ICONS.book}
                        <span>Tài liệu${docBadge}</span>
                    </button>

                    <span class="card-launch-hint">
                        <span>${launchText}</span>
                        ${UI_ICONS.arrowRight}
                    </span>
                </div>
            </article>
        `;
    }

    /**
     * Render Minimal System Cards Grid with safe fallbacks
     */
    function renderMinimalSystemsGrid() {
        const grid = resolveElement('gridUnifiedSystems') || resolveElement('gridAllServer');
        if (!grid) return;

        if (state.apiError && (!Array.isArray(state.systems) || state.systems.length === 0)) {
            grid.innerHTML = `
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <div style="color:var(--danger); margin-bottom:8px; font-weight:700;">
                        Không thể tải danh sách hệ thống
                    </div>
                    <div style="margin-bottom:14px;">
                        ${escapeHtml(state.apiError)}
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.PortalApp.reloadData()">
                        Thử tải lại dữ liệu
                    </button>
                </div>
            `;
            return;
        }

        if (!Array.isArray(state.systems) || state.systems.length === 0) {
            grid.innerHTML = `
                <div class="empty-state" style="grid-column: 1 / -1;">
                    Chưa có hệ thống nào được cấu hình
                </div>
            `;
            return;
        }

        const filtered = getFilteredSystems();
        if (filtered.length === 0) {
            const hasSearch = safeText(state.systemSearchQuery, '') !== '';
            const emptyMsg = hasSearch
                ? 'Không tìm thấy hệ thống nào phù hợp với từ khóa tìm kiếm.'
                : 'Chưa có hệ thống nào được cấu hình';
            grid.innerHTML = `
                <div class="empty-state" style="grid-column: 1 / -1;">
                    ${escapeHtml(emptyMsg)}
                </div>
            `;
            return;
        }

        const cardsHtml = [];
        for (let i = 0; i < filtered.length; i++) {
            try {
                const cardMarkup = renderSingleSystemCard(filtered[i]);
                if (cardMarkup) {
                    cardsHtml.push(cardMarkup);
                }
            } catch (cardErr) {
                console.error('[PortalApp] Error rendering system card at index', i, filtered[i], cardErr);
            }
        }

        if (cardsHtml.length === 0) {
            grid.innerHTML = `
                <div class="empty-state" style="grid-column: 1 / -1;">
                    Chưa có hệ thống nào được cấu hình
                </div>
            `;
            return;
        }

        grid.innerHTML = cardsHtml.join('');
    }

    /**
     * Open Environment Selection Popup Modal (when both Server & Dev URLs exist)
     */
    function openEnvironmentModal(systemId) {
        const sys = state.systems.find(s => s && Number(s.id) === Number(systemId));
        if (!sys) return;

        state.selectedSystemId = sys.id;
        const { serverUrl, devUrl } = resolveEnvironmentUrls(sys);

        const iconEl = resolveElement('envModalIcon');
        const bodyChoices = resolveElement('envModalChoices');
        const adminFooter = resolveElement('envModalAdminActions');

        if (iconEl) iconEl.innerHTML = getIconSvg(sys.icon);
        safeSetText('envModalTitle', safeText(sys.name, 'Hệ thống'));
        safeSetText('envModalDesc', safeText(sys.description, ''));

        if (bodyChoices) {
            bodyChoices.innerHTML = `
                <a href="${escapeHtml(serverUrl)}" target="_blank" rel="noopener"
                   class="env-choice-card env-server env-recommended"
                   onclick="window.PortalApp.closeModal('envModalBackdrop');">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div class="env-icon-box">${UI_ICONS.server}</div>
                        <span class="env-badge-active">Chính thức</span>
                    </div>
                    <div>
                        <div class="env-title">
                            <span>Server (Production)</span>
                            ${UI_ICONS.external}
                        </div>
                        <p class="env-sub">Hệ thống vận hành chính thức phục vụ sản xuất hàng ngày.</p>
                    </div>
                </a>

                <a href="${escapeHtml(devUrl)}" target="_blank" rel="noopener"
                   class="env-choice-card env-dev"
                   onclick="window.PortalApp.closeModal('envModalBackdrop');">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div class="env-icon-box">${UI_ICONS.dev}</div>
                        <span class="env-badge-active">Dev / Test</span>
                    </div>
                    <div>
                        <div class="env-title">
                            <span>Development (Dev/Test)</span>
                            ${UI_ICONS.external}
                        </div>
                        <p class="env-sub">Môi trường phát triển, kiểm thử tính năng mới và đánh giá.</p>
                    </div>
                </a>
            `;
        }

        if (adminFooter) {
            if (state.isAdmin) {
                adminFooter.style.display = 'flex';
                adminFooter.innerHTML = `
                    <button type="button" class="btn btn-sm btn-outline"
                            onclick="window.PortalApp.closeModal('envModalBackdrop'); window.PortalApp.openEditSystemModal(${sys.id});">
                        ${UI_ICONS.edit} <span>Cấu hình hệ thống</span>
                    </button>
                `;
            } else {
                adminFooter.style.display = 'none';
            }
        }

        openModal('envModalBackdrop');
    }

    /**
     * Open Documentation Modal directly from a System Card
     */
    function openSystemDocsModal(systemId, event) {
        if (event && typeof event.stopPropagation === 'function') {
            event.stopPropagation();
            event.preventDefault();
        }

        const sys = state.systems.find(s => s && Number(s.id) === Number(systemId));
        if (!sys) return;

        state.selectedSystemId = sys.id;
        const sysName = safeText(sys.name, 'Hệ thống');
        const listContainer = resolveElement('sysDocsModalList');
        const btnAddDocForSys = resolveElement('btnAddDocForCurrentSystem');

        safeSetText('sysDocsModalTitle', `Tài liệu hướng dẫn: ${sysName}`);
        safeSetText('sysDocsModalSub', 'Chọn tài liệu bên dưới để mở trực tiếp (PDF, Word, Video hoặc Website hướng dẫn).');

        if (btnAddDocForSys) {
            btnAddDocForSys.style.display = state.isAdmin ? 'inline-flex' : 'none';
            btnAddDocForSys.onclick = () => {
                closeModal('systemDocsModalBackdrop');
                openEditSystemModal(sys.id);
            };
        }

        const linkedDocs = state.documents.filter(d => d && Number(d.system_id) === Number(sys.id));
        const fallbackTopic = (sys.system_code === 'SYS-PRINTBOX') ? 'printbox-manual' : 'portal-overview';

        let itemsHtml = '';
        if (linkedDocs.length > 0) {
            itemsHtml = linkedDocs.map(doc => {
                try {
                    const docTypeLabel = (doc.doc_type === 'WEBSITE' || doc.doc_type === 'INTERNAL') ? 'WEBSITE' : safeText(doc.doc_type, 'PDF');
                    const adminActions = state.isAdmin ? `
                        <div style="display:flex; gap:4px;" onclick="event.stopPropagation(); event.preventDefault();">
                            <button type="button" class="btn btn-sm btn-outline"
                                    onclick="window.PortalApp.closeModal('systemDocsModalBackdrop'); window.PortalApp.openEditDocModal(${doc.id});"
                                    title="Sửa tài liệu">
                                ${UI_ICONS.edit}
                            </button>
                            <button type="button" class="btn btn-sm btn-danger-soft"
                                    onclick="window.PortalApp.deleteDocument(${doc.id})"
                                    title="Xóa tài liệu">
                                ${UI_ICONS.trash}
                            </button>
                        </div>
                    ` : '';

                    return `
                        <a href="${escapeHtml(doc.file_url)}" target="_blank" rel="noopener" class="doc-modal-item">
                            <div style="min-width:0; flex:1;">
                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                    <span class="doc-type-badge">${escapeHtml(docTypeLabel)}</span>
                                    <strong style="font-size:14px; color:var(--text-main); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        ${escapeHtml(safeText(doc.title, 'Tài liệu hướng dẫn'))}
                                    </strong>
                                </div>
                                <div style="font-size:12.5px; color:var(--text-muted);">
                                    ${escapeHtml(safeText(doc.description, 'Nhấn để mở tài liệu hướng dẫn'))}
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                ${adminActions}
                                <span style="color:var(--primary);">${UI_ICONS.external}</span>
                            </div>
                        </a>
                    `;
                } catch (docErr) {
                    console.error('[PortalApp] Error rendering doc item:', doc, docErr);
                    return '';
                }
            }).join('');
        } else {
            itemsHtml = `
                <a href="guide.php?topic=${encodeURIComponent(fallbackTopic)}" target="_blank" rel="noopener" class="doc-modal-item">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <span class="doc-type-badge">WEBSITE</span>
                            <strong style="font-size:14px; color:var(--text-main);">
                                Hướng dẫn sử dụng ${escapeHtml(sysName)}
                            </strong>
                        </div>
                        <div style="font-size:12.5px; color:var(--text-muted);">
                            Trang hướng dẫn vận hành nội bộ và quy trình thao tác chuẩn trên hệ thống.
                        </div>
                    </div>
                    <span style="color:var(--primary);">${UI_ICONS.external}</span>
                </a>
            `;
        }

        if (listContainer) {
            listContainer.innerHTML = itemsHtml;
        }

        openModal('systemDocsModalBackdrop');
    }

    /**
     * Open Auth Modal ('login' or 'register')
     */
    function openAuthModal(mode = 'login', forAdminAction = false) {
        state.pendingAdminRedirect = Boolean(forAdminAction);
        const noticeEl = resolveElement('adminAuthNotice');

        if (noticeEl) {
            noticeEl.style.display = forAdminAction ? 'block' : 'none';
        }
        safeSetValue('loginRequireAdmin', forAdminAction ? '1' : '0');

        switchAuthTab(mode);
        openModal('authModalBackdrop');
    }

    function switchAuthTab(mode) {
        const formLogin = resolveElement('portalLoginForm');
        const formRegister = resolveElement('portalRegisterForm');

        if (mode === 'register') {
            safeClassRemove('tabBtnAuthLogin', 'active');
            safeClassAdd('tabBtnAuthRegister', 'active');
            if (formLogin) formLogin.style.display = 'none';
            if (formRegister) formRegister.style.display = 'block';
            safeSetText('authModalTitle', 'Đăng ký Tài khoản Mới');
        } else {
            safeClassAdd('tabBtnAuthLogin', 'active');
            safeClassRemove('tabBtnAuthRegister', 'active');
            if (formLogin) formLogin.style.display = 'block';
            if (formRegister) formRegister.style.display = 'none';
            safeSetText('authModalTitle', 'Đăng nhập Hệ thống');
        }
    }

    /**
     * Submit Account Registration Form
     */
    async function submitRegisterForm(e) {
        e.preventDefault();

        const fullName = safeGetValue('regFullName').trim();
        const username = safeGetValue('regUsername').trim();
        const empCode = safeGetValue('regEmployeeCode').trim();
        const email = safeGetValue('regEmail').trim();
        const phone = safeGetValue('regPhone').trim();
        const dept = safeGetValue('regDepartment', 'Sản xuất & QC').trim();
        const password = safeGetValue('regPassword');
        const passwordConfirm = safeGetValue('regPasswordConfirm');

        if (fullName.length < 2) {
            showToast('Vui lòng nhập Họ và tên tối thiểu 2 ký tự.', 'error');
            return;
        }
        if (!/^[a-zA-Z0-9_.]{3,30}$/.test(username)) {
            showToast('Tên đăng nhập phải từ 3–30 ký tự (chữ cái không dấu, số, dấu chấm hoặc gạch dưới).', 'error');
            return;
        }
        if (password.length < 6) {
            showToast('Mật khẩu phải có tối thiểu 6 ký tự.', 'error');
            return;
        }
        if (password !== passwordConfirm) {
            showToast('Mật khẩu xác nhận không khớp.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'register');
        formData.append('full_name', fullName);
        formData.append('username', username);
        formData.append('employee_code', empCode);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('department', dept);
        formData.append('password', password);
        formData.append('password_confirm', passwordConfirm);

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Lỗi khi đăng ký tài khoản.', 'error');
                return;
            }

            const regForm = resolveElement('portalRegisterForm');
            if (regForm && typeof regForm.reset === 'function') {
                regForm.reset();
            }
            closeModal('authModalBackdrop');
            showToast(data.message);

            await loadPortalData();
            toggleAdminView('portal');
        } catch (err) {
            console.error('[PortalApp] Error in submitRegisterForm:', err);
            showToast('Lỗi kết nối máy chủ khi đăng ký.', 'error');
        }
    }

    /**
     * Submit Login Form
     */
    async function submitLoginForm(e) {
        e.preventDefault();
        const username = safeGetValue('loginUsername').trim();
        const password = safeGetValue('loginPassword');
        const requireAdmin = safeGetValue('loginRequireAdmin', '0');

        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('username', username);
        formData.append('password', password);
        formData.append('require_admin', requireAdmin);

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Đăng nhập không thành công.', 'error');
                return;
            }

            closeModal('authModalBackdrop');
            safeSetValue('loginPassword', '');
            showToast(data.message);

            await loadPortalData();

            if (state.pendingAdminRedirect && state.isAdmin) {
                state.pendingAdminRedirect = false;
                toggleAdminView('admin');
            } else {
                state.pendingAdminRedirect = false;
                toggleAdminView('portal');
            }
        } catch (err) {
            console.error('[PortalApp] Error in submitLoginForm:', err);
            showToast('Lỗi kết nối máy chủ khi đăng nhập.', 'error');
        }
    }

    /**
     * Open User Profile Modal
     */
    function openProfileModal() {
        if (!state.user || !state.user.is_logged) {
            openAuthModal('login', false);
            return;
        }

        const u = state.user;
        safeSetText('profAvatarInitials', getInitials(u.full_name));
        safeSetText('profBannerName', safeText(u.full_name, u.username));
        safeSetText(
            'profBannerMeta',
            `@${safeText(u.username)} • Mã NV: ${safeText(u.employee_code, 'NV-001')} • Quyền: ${u.role === 'admin' ? 'Quản trị viên (Admin)' : 'Người dùng (User)'}`
        );

        safeSetValue('profFullName', safeText(u.full_name));
        safeSetValue('profUsername', safeText(u.username));
        safeSetValue('profEmployeeCode', safeText(u.employee_code));
        safeSetValue('profDepartment', safeText(u.department, 'Sản xuất & QC'));
        safeSetValue('profEmail', safeText(u.email));
        safeSetValue('profPhone', safeText(u.phone));
        safeSetValue('profCreatedAt', safeText(u.created_at, 'Đang hoạt động'));
        safeSetValue('profCurrentPassword', '');
        safeSetValue('profNewPassword', '');

        openModal('profileModalBackdrop');
    }

    /**
     * Submit Profile Update Form
     */
    async function submitProfileForm(e) {
        e.preventDefault();

        const formData = new FormData();
        formData.append('action', 'update_profile');
        formData.append('csrf_token', state.csrfToken);
        formData.append('full_name', safeGetValue('profFullName').trim());
        formData.append('employee_code', safeGetValue('profEmployeeCode').trim());
        formData.append('department', safeGetValue('profDepartment').trim());
        formData.append('email', safeGetValue('profEmail').trim());
        formData.append('phone', safeGetValue('profPhone').trim());
        formData.append('current_password', safeGetValue('profCurrentPassword'));
        formData.append('new_password', safeGetValue('profNewPassword'));

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Không thể cập nhật hồ sơ.', 'error');
                return;
            }
            closeModal('profileModalBackdrop');
            showToast(data.message);
            await loadPortalData();
        } catch (err) {
            console.error('[PortalApp] Error in submitProfileForm:', err);
            showToast('Lỗi kết nối máy chủ.', 'error');
        }
    }

    function renderDocumentSystemSelect() {
        const modalSelect = resolveElement('docFormSystemId');
        if (!modalSelect) return;

        let html = '<option value="">-- Tài liệu chung toàn hệ thống --</option>';
        state.systems.forEach(s => {
            if (!s) return;
            html += `<option value="${s.id}">${escapeHtml(safeText(s.name))}</option>`;
        });
        modalSelect.innerHTML = html;
    }

    /**
     * Render Admin Workspace Tables (Systems, Documents, and Users & RBAC)
     */
    function renderAdminWorkspace() {
        if (!state.isAdmin) return;

        safeSetText('adminCountSystems', state.systems.length);
        safeSetText('adminCountDocs', state.documents.length);
        safeSetText('adminCountUsers', state.users.length);

        const sysTableBody = resolveElement('adminSystemsTableBody');
        const docTableBody = resolveElement('adminDocsTableBody');
        const userTableBody = resolveElement('adminUsersTableBody');

        // 1. Systems Table
        if (sysTableBody) {
            if (state.systems.length === 0) {
                sysTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Chưa có hệ thống nào được cấu hình</td></tr>';
            } else {
                sysTableBody.innerHTML = state.systems.map(sys => {
                    try {
                        const { serverUrl, devUrl } = resolveEnvironmentUrls(sys);
                        const isActive = Number(sys.is_active) === 1;
                        const accessLabel = sys.access_role === 'admin' ? 'Chỉ Admin' : (sys.access_role === 'user' ? 'User đăng nhập' : 'Tất cả');

                        let urlsHtml = '';
                        if (serverUrl) {
                            urlsHtml += `<div style="font-size:11.5px;"><span class="env-pill-label pill-server" style="font-size:9.5px; padding:1px 5px; margin-right:4px;">Server</span><code style="color:var(--server-color);">${escapeHtml(serverUrl)}</code></div>`;
                        }
                        if (devUrl) {
                            urlsHtml += `<div style="font-size:11.5px; margin-top:3px;"><span class="env-pill-label pill-dev" style="font-size:9.5px; padding:1px 5px; margin-right:4px;">Dev</span><code style="color:var(--dev-color);">${escapeHtml(devUrl)}</code></div>`;
                        }
                        if (!urlsHtml) {
                            urlsHtml = '<span style="color:var(--text-muted); font-size:12px;">Chưa cấu hình URL</span>';
                        }

                        let envBadgeHtml = '';
                        if (serverUrl && devUrl) {
                            envBadgeHtml = '<span class="doc-type-badge pill-dual" style="border:none;">Server • Dev</span>';
                        } else if (serverUrl) {
                            envBadgeHtml = '<span class="doc-type-badge pill-server" style="border:none;">SERVER</span>';
                        } else {
                            envBadgeHtml = '<span class="doc-type-badge pill-dev" style="border:none;">DEVELOPMENT</span>';
                        }

                        return `
                            <tr>
                                <td><strong>#${sys.display_order}</strong></td>
                                <td>
                                    <div style="font-weight:700; color:var(--text-main);">${escapeHtml(safeText(sys.name))}</div>
                                    <div style="font-size:12px; color:var(--text-muted);">
                                        ${escapeHtml(safeText(sys.system_code))} • ${escapeHtml(safeText(sys.department))}
                                    </div>
                                </td>
                                <td>${urlsHtml}</td>
                                <td>${envBadgeHtml}</td>
                                <td><span style="font-size:12px; font-weight:600;">${accessLabel}</span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline"
                                            onclick="window.PortalApp.toggleSystemActive(${sys.id})">
                                        ${isActive ? 'Đang hoạt động' : 'Tạm ẩn'}
                                    </button>
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <button type="button" class="btn btn-sm btn-outline"
                                                onclick="window.PortalApp.openEditSystemModal(${sys.id})">
                                            ${UI_ICONS.edit} <span>Sửa</span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger-soft"
                                                onclick="window.PortalApp.deleteSystem(${sys.id})">
                                            ${UI_ICONS.trash}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    } catch (err) {
                        console.error('[PortalApp] Error rendering admin system row:', err);
                        return '';
                    }
                }).join('');
            }
        }

        // 2. Documents Table
        if (docTableBody) {
            if (state.documents.length === 0) {
                docTableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Chưa có tài liệu nào.</td></tr>';
            } else {
                docTableBody.innerHTML = state.documents.map(doc => {
                    try {
                        const docTypeLabel = (doc.doc_type === 'WEBSITE' || doc.doc_type === 'INTERNAL') ? 'WEBSITE' : safeText(doc.doc_type, 'PDF');
                        return `
                            <tr>
                                <td><strong>#${doc.display_order}</strong></td>
                                <td>
                                    <div style="font-weight:600; color:var(--text-main);">${escapeHtml(safeText(doc.title))}</div>
                                    <div style="font-size:12px; color:var(--text-muted);">${escapeHtml(safeText(doc.description))}</div>
                                </td>
                                <td><span class="doc-type-badge">${escapeHtml(docTypeLabel)}</span></td>
                                <td>${escapeHtml(safeText(doc.system_name, 'Tài liệu chung'))}</td>
                                <td><code style="font-size:12px; color:var(--primary);">${escapeHtml(safeText(doc.file_url))}</code></td>
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <button type="button" class="btn btn-sm btn-outline"
                                                onclick="window.PortalApp.openEditDocModal(${doc.id})">
                                            ${UI_ICONS.edit}
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger-soft"
                                                onclick="window.PortalApp.deleteDocument(${doc.id})">
                                            ${UI_ICONS.trash}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    } catch (err) {
                        console.error('[PortalApp] Error rendering admin doc row:', err);
                        return '';
                    }
                }).join('');
            }
        }

        // 3. Users & RBAC Table
        if (userTableBody) {
            if (state.users.length === 0) {
                userTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Chưa có người dùng nào.</td></tr>';
            } else {
                userTableBody.innerHTML = state.users.map(u => {
                    try {
                        const isUserAdmin = u.role === 'admin';
                        const isActive = Number(u.is_active) === 1;
                        const isSelf = Number(u.id) === Number(state.user.id);

                        return `
                            <tr>
                                <td><strong>${escapeHtml(safeText(u.employee_code, 'NV-' + u.id))}</strong></td>
                                <td>
                                    <div style="font-weight:700; color:var(--text-main);">${escapeHtml(safeText(u.full_name))}</div>
                                    <div style="font-size:12px; color:var(--text-muted);">@${escapeHtml(safeText(u.username))}</div>
                                </td>
                                <td>${escapeHtml(safeText(u.department, 'Sản xuất & QC'))}</td>
                                <td>
                                    <div style="font-size:12.5px;">${escapeHtml(safeText(u.email))}</div>
                                    <div style="font-size:12px; color:var(--text-muted);">${escapeHtml(safeText(u.phone))}</div>
                                </td>
                                <td>
                                    <span class="doc-type-badge" style="background:${isUserAdmin ? 'var(--primary-soft)' : 'var(--bg-subtle)'}; color:${isUserAdmin ? 'var(--primary)' : 'var(--text-secondary)'};">
                                        ${isUserAdmin ? 'Quản trị viên (Admin)' : 'Người dùng (User)'}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:12px; font-weight:600; color:${isActive ? 'var(--server-color)' : 'var(--danger)'};">
                                        ${isActive ? '● Hoạt động' : '○ Đã khóa'}
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <button type="button" class="btn btn-sm btn-outline"
                                                onclick="window.PortalApp.openEditUserModal(${u.id})"
                                                title="Xem chi tiết & Sửa người dùng">
                                            ${UI_ICONS.edit} <span>Chi tiết</span>
                                        </button>
                                        ${!isSelf ? `
                                            <button type="button" class="btn btn-sm btn-outline"
                                                    onclick="window.PortalApp.toggleUserRole(${u.id})"
                                                    title="Đổi vai trò User <-> Admin">
                                                ${isUserAdmin ? 'Hạ xuống User' : 'Cấp quyền Admin'}
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline"
                                                    onclick="window.PortalApp.toggleUserActive(${u.id})">
                                                ${isActive ? 'Khóa' : 'Mở khóa'}
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger-soft"
                                                    onclick="window.PortalApp.deleteUser(${u.id})"
                                                    title="Xóa tài khoản">
                                                ${UI_ICONS.trash}
                                            </button>
                                        ` : '<span style="font-size:11.5px; color:var(--text-muted); padding:4px 6px;">(Đang đăng nhập)</span>'}
                                    </div>
                                </td>
                            </tr>
                        `;
                    } catch (err) {
                        console.error('[PortalApp] Error rendering admin user row:', err);
                        return '';
                    }
                }).join('');
            }
        }
    }

    /**
     * Switch Admin Sub-Tabs ('systems' | 'docs' | 'users')
     */
    function switchAdminSubTab(subTab) {
        state.adminSubTab = subTab;
        document.querySelectorAll('[data-admin-tab]').forEach(btn => {
            safeClassToggle(btn, 'active', btn.dataset && btn.dataset.adminTab === subTab);
        });

        const panelSystems = resolveElement('adminPanelSystems');
        const panelDocs = resolveElement('adminPanelDocs');
        const panelUsers = resolveElement('adminPanelUsers');

        if (panelSystems) panelSystems.style.display = subTab === 'systems' ? 'block' : 'none';
        if (panelDocs) panelDocs.style.display = subTab === 'docs' ? 'block' : 'none';
        if (panelUsers) panelUsers.style.display = subTab === 'users' ? 'block' : 'none';
    }

    /**
     * Toggle between User Portal View and Admin Workspace
     */
    function toggleAdminView(forceView) {
        const nextView = forceView || (state.activeView === 'admin' ? 'portal' : 'admin');

        if (nextView === 'admin' && !state.isAdmin) {
            openAuthModal('login', true);
            return;
        }

        state.activeView = nextView;
        const viewPortal = resolveElement('viewPortalMain');
        const viewAdmin = resolveElement('viewAdminMain');
        const btnToggle = resolveElement('btnToggleAdminView');

        if (nextView === 'admin' && state.isAdmin) {
            if (viewPortal) viewPortal.style.display = 'none';
            if (viewAdmin) viewAdmin.style.display = 'block';
            safeClassAdd(btnToggle, 'btn-primary');
            safeClassRemove(btnToggle, 'btn-outline');
        } else {
            if (viewPortal) viewPortal.style.display = 'block';
            if (viewAdmin) viewAdmin.style.display = 'none';
            safeClassRemove(btnToggle, 'btn-primary');
            safeClassAdd(btnToggle, 'btn-outline');
        }
    }

    function renderIconPicker(selectedIcon) {
        const container = resolveElement('sysIconPicker');
        const hiddenInput = resolveElement('sysFormIcon');
        if (!container || !hiddenInput) return;

        hiddenInput.value = selectedIcon || 'grid';
        container.innerHTML = Object.keys(ICON_SVGS).map(key => `
            <button type="button" class="icon-option-btn ${key === hiddenInput.value ? 'selected' : ''}"
                    data-icon="${key}" title="${key}">
                ${ICON_SVGS[key]}
            </button>
        `).join('');

        container.querySelectorAll('.icon-option-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                hiddenInput.value = btn.dataset.icon || 'grid';
                container.querySelectorAll('.icon-option-btn').forEach(b => safeClassRemove(b, 'selected'));
                safeClassAdd(btn, 'selected');
            });
        });
    }

    /**
     * Attached System Documents Repeater inside System Modal
     */
    function renderModalDocsList() {
        const container = resolveElement('sysFormDocsContainer');
        if (!container) return;

        if (!state.currentModalDocs || state.currentModalDocs.length === 0) {
            container.innerHTML = `
                <div class="doc-empty-notice">
                    Chưa có tài liệu nào đính kèm cho hệ thống này. Nhấn <strong>"+ Thêm tài liệu"</strong> bên trên để đính kèm PDF, Word, Video hoặc Website hướng dẫn.
                </div>
            `;
            return;
        }

        container.innerHTML = state.currentModalDocs.map((doc, idx) => {
            const titleVal = escapeHtml(doc.title || '');
            const urlVal = escapeHtml(doc.file_url || doc.url || '');
            const rawType = (doc.doc_type || 'PDF').toUpperCase();
            const typeVal = (rawType === 'WEBSITE' || rawType === 'INTERNAL') ? 'WEBSITE' : rawType;

            return `
                <div class="modal-doc-row" data-doc-idx="${idx}">
                    <input type="text" class="form-control form-control-sm doc-row-title"
                           placeholder="Tên tài liệu *" value="${titleVal}"
                           data-field="title" data-idx="${idx}" required>
                    <select class="form-control form-control-sm doc-row-type"
                            data-field="doc_type" data-idx="${idx}">
                        <option value="PDF" ${typeVal === 'PDF' ? 'selected' : ''}>PDF</option>
                        <option value="WORD" ${typeVal === 'WORD' ? 'selected' : ''}>Word</option>
                        <option value="VIDEO" ${typeVal === 'VIDEO' ? 'selected' : ''}>Video</option>
                        <option value="WEBSITE" ${typeVal === 'WEBSITE' ? 'selected' : ''}>Website</option>
                    </select>
                    <input type="text" class="form-control form-control-sm doc-row-url"
                           placeholder="Đường dẫn URL / file tài liệu *" value="${urlVal}"
                           data-field="file_url" data-idx="${idx}" required>
                    <button type="button" class="btn btn-sm btn-danger-soft"
                            onclick="window.PortalApp.removeModalDocRow(${idx})"
                            title="Xóa tài liệu này" style="padding:6px;">
                        ${UI_ICONS.trash}
                    </button>
                </div>
            `;
        }).join('');
    }

    function syncModalDocFromDom() {
        const container = resolveElement('sysFormDocsContainer');
        if (!container) return;

        const rows = container.querySelectorAll('.modal-doc-row');
        state.currentModalDocs = [];
        rows.forEach(row => {
            const titleInput = row.querySelector('.doc-row-title');
            const typeSelect = row.querySelector('.doc-row-type');
            const urlInput = row.querySelector('.doc-row-url');
            if (titleInput && urlInput) {
                state.currentModalDocs.push({
                    title: titleInput.value.trim(),
                    doc_type: typeSelect ? typeSelect.value : 'PDF',
                    file_url: urlInput.value.trim(),
                    description: 'Tài liệu hướng dẫn hệ thống.'
                });
            }
        });
    }

    function addModalDocRow(defaultType = 'PDF') {
        syncModalDocFromDom();
        state.currentModalDocs.push({
            title: '',
            doc_type: defaultType,
            file_url: '',
            description: 'Tài liệu hướng dẫn hệ thống.'
        });
        renderModalDocsList();

        setTimeout(() => {
            const container = resolveElement('sysFormDocsContainer');
            if (container) {
                const inputs = container.querySelectorAll('.doc-row-title');
                if (inputs.length > 0) {
                    inputs[inputs.length - 1].focus();
                }
            }
        }, 50);
    }

    function removeModalDocRow(idx) {
        syncModalDocFromDom();
        if (idx >= 0 && idx < state.currentModalDocs.length) {
            state.currentModalDocs.splice(idx, 1);
        }
        renderModalDocsList();
    }

    /**
     * Open Add System Modal
     */
    function openAddSystemModal(defaultGroup = 'SERVER') {
        if (!state.isAdmin) {
            openAuthModal('login', true);
            return;
        }
        safeSetText('systemModalTitle', 'Thêm Hệ Thống Mới');
        safeSetValue('sysFormId', '0');
        safeSetValue('sysFormCode', '');
        safeSetValue('sysFormName', '');
        safeSetValue('sysFormDesc', '');
        safeSetValue('sysFormUrlServer', '');
        safeSetValue('sysFormUrlDev', '');
        safeSetValue('sysFormGroup', defaultGroup);
        safeSetValue('sysFormStatusLabel', defaultGroup === 'SERVER' ? 'Vận hành chính thức' : 'Đang phát triển');
        safeSetValue('sysFormColor', '#0284c7');
        safeSetValue('sysFormOrder', '10');
        safeSetValue('sysFormVersion', '1.0');
        safeSetValue('sysFormDept', 'Nhà máy Nhựa DX');
        safeSetValue('sysFormAccess', 'all');
        safeSetValue('sysFormActive', '1');

        state.currentModalDocs = [];
        renderModalDocsList();
        renderIconPicker('grid');
        openModal('systemModalBackdrop');
    }

    /**
     * Open Edit System Modal
     */
    function openEditSystemModal(id) {
        if (!state.isAdmin) {
            openAuthModal('login', true);
            return;
        }
        const sys = state.systems.find(s => s && Number(s.id) === Number(id));
        if (!sys) return;

        safeSetText('systemModalTitle', `Cấu Hình Hệ Thống: ${safeText(sys.name)}`);
        safeSetValue('sysFormId', sys.id);
        safeSetValue('sysFormCode', safeText(sys.system_code));
        safeSetValue('sysFormName', safeText(sys.name));
        safeSetValue('sysFormDesc', safeText(sys.description));

        const { serverUrl, devUrl } = resolveEnvironmentUrls(sys);
        safeSetValue('sysFormUrlServer', serverUrl);
        safeSetValue('sysFormUrlDev', devUrl);

        safeSetValue('sysFormGroup', safeText(sys.group_type, 'SERVER'));
        safeSetValue('sysFormStatusLabel', safeText(sys.status_label));
        safeSetValue('sysFormColor', safeText(sys.icon_color, '#0284c7'));
        safeSetValue('sysFormOrder', sys.display_order || 10);
        safeSetValue('sysFormVersion', safeText(sys.version, '1.0'));
        safeSetValue('sysFormDept', safeText(sys.department, 'Nhà máy Nhựa DX'));
        safeSetValue('sysFormAccess', safeText(sys.access_role, 'all'));
        safeSetValue('sysFormActive', String(sys.is_active ?? 1));

        // Load existing documents attached to this system
        state.currentModalDocs = state.documents
            .filter(d => d && Number(d.system_id) === Number(sys.id))
            .map(d => ({
                title: d.title || '',
                doc_type: d.doc_type || 'PDF',
                file_url: d.file_url || '',
                description: d.description || 'Tài liệu hướng dẫn hệ thống.'
            }));

        renderModalDocsList();
        renderIconPicker(sys.icon || 'grid');
        openModal('systemModalBackdrop');
    }

    /**
     * Submit Add / Edit System Form with validation for Server / Dev URLs
     */
    async function submitSystemForm(e) {
        e.preventDefault();

        const name = safeGetValue('sysFormName').trim();
        const urlServer = safeGetValue('sysFormUrlServer').trim();
        const urlDev = safeGetValue('sysFormUrlDev').trim();

        if (!name) {
            showToast('Vui lòng nhập Tên hệ thống.', 'error');
            return;
        }

        // Rule: If both URLs are empty, do not allow saving and show validation message
        if (!urlServer && !urlDev) {
            showToast('Vui lòng nhập ít nhất một đường dẫn truy cập (Server hoặc Development).', 'error');
            return;
        }

        syncModalDocFromDom();
        const validDocs = state.currentModalDocs.filter(d => d && d.title && d.title.trim() && d.file_url && d.file_url.trim());

        const formData = new FormData();
        formData.append('action', 'save_system');
        formData.append('csrf_token', state.csrfToken);
        formData.append('id', safeGetValue('sysFormId', '0'));
        formData.append('system_code', safeGetValue('sysFormCode'));
        formData.append('name', name);
        formData.append('description', safeGetValue('sysFormDesc'));
        formData.append('icon', safeGetValue('sysFormIcon', 'grid'));
        formData.append('icon_color', safeGetValue('sysFormColor', '#0284c7'));
        formData.append('url_server', urlServer);
        formData.append('url_dev', urlDev);
        formData.append('group_type', (urlServer && !urlDev) ? 'SERVER' : ((!urlServer && urlDev) ? 'DEVELOPMENT' : safeGetValue('sysFormGroup', 'SERVER')));
        formData.append('status_label', safeGetValue('sysFormStatusLabel'));
        formData.append('display_order', safeGetValue('sysFormOrder', '10'));
        formData.append('version', safeGetValue('sysFormVersion', '1.0'));
        formData.append('department', safeGetValue('sysFormDept', 'Nhà máy Nhựa DX'));
        formData.append('access_role', safeGetValue('sysFormAccess', 'all'));
        formData.append('is_active', safeGetValue('sysFormActive', '1'));
        formData.append('documents_json', JSON.stringify(validDocs));

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Lỗi khi lưu cấu hình hệ thống.', 'error');
                return;
            }
            closeModal('systemModalBackdrop');
            showToast(data.message);
            await loadPortalData();
        } catch (err) {
            console.error('[PortalApp] Error in submitSystemForm:', err);
            showToast('Không thể kết nối máy chủ.', 'error');
        }
    }

    async function toggleSystemActive(id) {
        const formData = new FormData();
        formData.append('action', 'toggle_system_active');
        formData.append('csrf_token', state.csrfToken);
        formData.append('id', id);

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message, 'error');
                return;
            }
            showToast(data.message);
            await loadPortalData();
        } catch (err) {
            console.error('[PortalApp] Error in toggleSystemActive:', err);
            showToast('Lỗi kết nối máy chủ.', 'error');
        }
    }

    async function deleteSystem(id) {
        const sys = state.systems.find(s => s && Number(s.id) === Number(id));
        if (!sys) return;

        if (!confirm(`Xác nhận xóa hệ thống "${sys.name}"?`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_system');
        formData.append('csrf_token', state.csrfToken);
        formData.append('id', id);

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message, 'error');
                return;
            }
            showToast(data.message);
            await loadPortalData();
        } catch (err) {
            console.error('[PortalApp] Error in deleteSystem:', err);
            showToast('Lỗi kết nối máy chủ.', 'error');
        }
    }

    function openAddDocModal(preselectedSystemId = '') {
        if (!state.isAdmin) {
            openAuthModal('login', true);
            return;
        }
        safeSetText('docModalTitle', 'Thêm Tài Liệu Hướng Dẫn');
        safeSetValue('docFormId', '0');
        safeSetValue('docFormTitle', '');
        safeSetValue('docFormDesc', '');
        safeSetValue('docFormType', 'PDF');
        safeSetValue('docFormSystemId', preselectedSystemId ? String(preselectedSystemId) : '');
        safeSetValue('docFormUrl', '');
        safeSetValue('docFormFile', '');
        safeSetValue('docFormOrder', '10');
        safeSetValue('docFormAccess', 'all');
        safeSetValue('docFormActive', '1');

        openModal('docModalBackdrop');
    }

    function openEditDocModal(id) {
        if (!state.isAdmin) {
            openAuthModal('login', true);
            return;
        }
        const doc = state.documents.find(d => d && Number(d.id) === Number(id));
        if (!doc) return;

        safeSetText('docModalTitle', 'Cập Nhật Tài Liệu Hướng Dẫn');
        safeSetValue('docFormId', doc.id);
        safeSetValue('docFormTitle', safeText(doc.title));
        safeSetValue('docFormDesc', safeText(doc.description));
        safeSetValue('docFormType', safeText(doc.doc_type, 'PDF'));
        safeSetValue('docFormSystemId', doc.system_id || '');
        safeSetValue('docFormUrl', safeText(doc.file_url));
        safeSetValue('docFormFile', '');
        safeSetValue('docFormOrder', doc.display_order || 10);
        safeSetValue('docFormAccess', safeText(doc.access_role, 'all'));
        safeSetValue('docFormActive', String(doc.is_active ?? 1));

        openModal('docModalBackdrop');
    }

    async function submitDocForm(e) {
        e.preventDefault();

        const formData = new FormData();
        formData.append('action', 'save_document');
        formData.append('csrf_token', state.csrfToken);
        formData.append('id', safeGetValue('docFormId', '0'));
        formData.append('title', safeGetValue('docFormTitle'));
        formData.append('description', safeGetValue('docFormDesc'));
        formData.append('doc_type', safeGetValue('docFormType', 'PDF'));
        formData.append('system_id', safeGetValue('docFormSystemId'));
        formData.append('file_url', safeGetValue('docFormUrl'));
        formData.append('display_order', safeGetValue('docFormOrder', '10'));
        formData.append('access_role', safeGetValue('docFormAccess', 'all'));
        formData.append('is_active', safeGetValue('docFormActive', '1'));

        const fileInput = resolveElement('docFormFile');
        if (fileInput && fileInput.files && fileInput.files[0]) {
            formData.append('doc_file', fileInput.files[0]);
        }

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Không thể lưu tài liệu.', 'error');
                return;
            }
            closeModal('docModalBackdrop');
            showToast(data.message);
            await loadPortalData();
        } catch (err) {
            console.error('[PortalApp] Error in submitDocForm:', err);
            showToast('Lỗi kết nối máy chủ.', 'error');
        }
    }

    async function deleteDocument(id) {
        const doc = state.documents.find(d => d && Number(d.id) === Number(id));
        if (!doc) return;

        if (!confirm(`Xác nhận xóa tài liệu "${doc.title}"?`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_document');
        formData.append('csrf_token', state.csrfToken);
        formData.append('id', id);

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message, 'error');
                return;
            }
            showToast(data.message);
            await loadPortalData();
        } catch (err) {
            console.error('[PortalApp] Error in deleteDocument:', err);
            showToast('Lỗi kết nối máy chủ.', 'error');
        }
    }

    /**
     * Admin User Management Modals & Actions
     */
    function openAddUserModal() {
        if (!state.isAdmin) return;
        safeSetText('userModalTitle', 'Thêm Người Dùng Mới');
        safeSetValue('userFormId', '0');
        safeSetValue('userFormFullName', '');
        safeSetValue('userFormUsername', '');
        safeSetValue('userFormEmpCode', '');
        safeSetValue('userFormDepartment', 'Sản xuất & QC');
        safeSetValue('userFormEmail', '');
        safeSetValue('userFormPhone', '');
        safeSetValue('userFormRole', 'user');
        safeSetValue('userFormActive', '1');

        const pwInput = resolveElement('userFormPassword');
        if (pwInput) {
            pwInput.value = '';
            pwInput.placeholder = 'Nhập mật khẩu (tối thiểu 6 ký tự) *';
            pwInput.required = true;
        }

        openModal('userModalBackdrop');
    }

    function openEditUserModal(id) {
        if (!state.isAdmin) return;
        const u = state.users.find(item => item && Number(item.id) === Number(id));
        if (!u) return;

        safeSetText('userModalTitle', `Chi Tiết & Phân Quyền: ${safeText(u.full_name)}`);
        safeSetValue('userFormId', u.id);
        safeSetValue('userFormFullName', safeText(u.full_name));
        safeSetValue('userFormUsername', safeText(u.username));
        safeSetValue('userFormEmpCode', safeText(u.employee_code));
        safeSetValue('userFormDepartment', safeText(u.department, 'Sản xuất & QC'));
        safeSetValue('userFormEmail', safeText(u.email));
        safeSetValue('userFormPhone', safeText(u.phone));
        safeSetValue('userFormRole', safeText(u.role, 'user'));
        safeSetValue('userFormActive', String(u.is_active ?? 1));

        const pwInput = resolveElement('userFormPassword');
        if (pwInput) {
            pwInput.value = '';
            pwInput.placeholder = 'Để trống nếu giữ nguyên mật khẩu hiện tại';
            pwInput.required = false;
        }

        openModal('userModalBackdrop');
    }

    async function submitUserForm(e) {
        e.preventDefault();

        const formData = new FormData();
        formData.append('action', 'save_user');
        formData.append('csrf_token', state.csrfToken);
        formData.append('id', safeGetValue('userFormId', '0'));
        formData.append('full_name', safeGetValue('userFormFullName').trim());
        formData.append('username', safeGetValue('userFormUsername').trim());
        formData.append('employee_code', safeGetValue('userFormEmpCode').trim());
        formData.append('department', safeGetValue('userFormDepartment').trim());
        formData.append('email', safeGetValue('userFormEmail').trim());
        formData.append('phone', safeGetValue('userFormPhone').trim());
        formData.append('role', safeGetValue('userFormRole', 'user'));
        formData.append('is_active', safeGetValue('userFormActive', '1'));
        formData.append('password', safeGetValue('userFormPassword'));

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message, 'error');
                return;
            }
            closeModal('userModalBackdrop');
            showToast(data.message);
            await loadPortalData();
        } catch (err) {
            console.error('[PortalApp] Error in submitUserForm:', err);
            showToast('Lỗi kết nối máy chủ.', 'error');
        }
    }

    async function toggleUserRole(id) {
        const formData = new FormData();
        formData.append('action', 'toggle_user_role');
        formData.append('csrf_token', state.csrfToken);
        formData.append('id', id);

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message, 'error');
                return;
            }
            showToast(data.message);
            await loadPortalData();
        } catch (err) {
            console.error('[PortalApp] Error in toggleUserRole:', err);
            showToast('Lỗi kết nối máy chủ.', 'error');
        }
    }

    async function toggleUserActive(id) {
        const formData = new FormData();
        formData.append('action', 'toggle_user_active');
        formData.append('csrf_token', state.csrfToken);
        formData.append('id', id);

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message, 'error');
                return;
            }
            showToast(data.message);
            await loadPortalData();
        } catch (err) {
            console.error('[PortalApp] Error in toggleUserActive:', err);
            showToast('Lỗi kết nối máy chủ.', 'error');
        }
    }

    async function deleteUser(id) {
        const u = state.users.find(item => item && Number(item.id) === Number(id));
        if (!u) return;

        if (!confirm(`Xác nhận xóa tài khoản "${u.full_name}" (@${u.username})?`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('csrf_token', state.csrfToken);
        formData.append('id', id);

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message, 'error');
                return;
            }
            showToast(data.message);
            await loadPortalData();
        } catch (err) {
            console.error('[PortalApp] Error in deleteUser:', err);
            showToast('Lỗi kết nối máy chủ.', 'error');
        }
    }

    async function performLogout() {
        const formData = new FormData();
        formData.append('action', 'logout');
        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();
            showToast(data.message || 'Đã đăng xuất.');
            await loadPortalData();
            toggleAdminView('portal');
        } catch (err) {
            console.error('[PortalApp] Error in performLogout:', err);
            showToast('Lỗi khi đăng xuất.', 'error');
        }
    }

    function initEvents() {
        // Theme toggle
        const btnTheme = resolveElement('btnThemeToggle');
        if (btnTheme) {
            btnTheme.addEventListener('click', () => {
                const current = document.documentElement.getAttribute('data-theme') || 'light';
                const next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('plasticdx_theme', next);
            });
        }

        // Toggle Admin view
        const btnToggleAdminView = resolveElement('btnToggleAdminView');
        if (btnToggleAdminView) {
            btnToggleAdminView.addEventListener('click', () => toggleAdminView());
        }

        // Admin sub-tabs
        document.querySelectorAll('[data-admin-tab]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.dataset && btn.dataset.adminTab) {
                    switchAdminSubTab(btn.dataset.adminTab);
                }
            });
        });

        // Search input
        const sysSearchInput = resolveElement('systemSearchInput');
        const sysSearchClear = resolveElement('systemSearchClear');
        const sysSearchKbd = resolveElement('systemSearchKbd');

        if (sysSearchInput) {
            sysSearchInput.addEventListener('input', () => {
                state.systemSearchQuery = sysSearchInput.value || '';
                const hasText = state.systemSearchQuery.length > 0;
                if (sysSearchClear) sysSearchClear.style.display = hasText ? 'inline-flex' : 'none';
                if (sysSearchKbd) sysSearchKbd.style.display = hasText ? 'none' : 'inline-block';
                renderMinimalSystemsGrid();
            });
        }
        if (sysSearchClear) {
            sysSearchClear.addEventListener('click', () => {
                if (sysSearchInput) {
                    sysSearchInput.value = '';
                    sysSearchInput.focus();
                }
                state.systemSearchQuery = '';
                sysSearchClear.style.display = 'none';
                if (sysSearchKbd) sysSearchKbd.style.display = 'inline-block';
                renderMinimalSystemsGrid();
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-backdrop').forEach(m => {
                    if (safeClassContains(m, 'open')) {
                        safeClassRemove(m, 'open');
                    }
                });
                return;
            }
            const activeTag = document.activeElement ? document.activeElement.tagName : '';
            const isInputFocused = activeTag === 'INPUT' || activeTag === 'TEXTAREA' || activeTag === 'SELECT';

            if (((e.ctrlKey || e.metaKey) && e.key && e.key.toLowerCase() === 'k') || (!isInputFocused && e.key === '/')) {
                if (sysSearchInput && state.activeView === 'portal') {
                    e.preventDefault();
                    sysSearchInput.focus();
                }
            }
        });

        // Segmented filter pills
        document.querySelectorAll('[data-sys-filter]').forEach(btn => {
            btn.addEventListener('click', () => {
                state.systemFilterGroup = (btn.dataset && btn.dataset.sysFilter) ? btn.dataset.sysFilter : 'ALL';
                document.querySelectorAll('[data-sys-filter]').forEach(b => safeClassRemove(b, 'active'));
                safeClassAdd(btn, 'active');
                renderMinimalSystemsGrid();
            });
        });

        // Header Auth & Profile buttons
        const btnOpenLogin = resolveElement('btnOpenLoginModal');
        if (btnOpenLogin) {
            btnOpenLogin.addEventListener('click', () => openAuthModal('login', false));
        }

        const btnOpenRegister = resolveElement('btnOpenRegisterModal');
        if (btnOpenRegister) {
            btnOpenRegister.addEventListener('click', () => openAuthModal('register', false));
        }

        const btnProfileTrigger = resolveElement('btnUserProfileTrigger');
        if (btnProfileTrigger) {
            btnProfileTrigger.addEventListener('click', openProfileModal);
        }

        const btnLogout = resolveElement('btnLogout');
        if (btnLogout) {
            btnLogout.addEventListener('click', performLogout);
        }

        // Close modals
        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.dataset ? btn.dataset.closeModal : '';
                if (targetId) {
                    closeModal(targetId);
                }
            });
        });

        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) {
                    safeClassRemove(backdrop, 'open');
                }
            });
        });

        // Forms
        const sysForm = resolveElement('systemConfigForm');
        if (sysForm) sysForm.addEventListener('submit', submitSystemForm);

        const docForm = resolveElement('docConfigForm');
        if (docForm) docForm.addEventListener('submit', submitDocForm);

        const userForm = resolveElement('userConfigForm');
        if (userForm) userForm.addEventListener('submit', submitUserForm);

        const loginForm = resolveElement('portalLoginForm');
        if (loginForm) loginForm.addEventListener('submit', submitLoginForm);

        const registerForm = resolveElement('portalRegisterForm');
        if (registerForm) registerForm.addEventListener('submit', submitRegisterForm);

        const profileForm = resolveElement('portalProfileForm');
        if (profileForm) profileForm.addEventListener('submit', submitProfileForm);
    }

    window.PortalApp = {
        handleSystemClick,
        openEnvironmentModal,
        openSystemDocsModal,
        openAuthModal,
        switchAuthTab,
        openProfileModal,
        openAddSystemModal,
        openEditSystemModal,
        deleteSystem,
        openAddDocModal,
        openEditDocModal,
        deleteDocument,
        openAddUserModal,
        openEditUserModal,
        toggleUserRole,
        toggleUserActive,
        deleteUser,
        toggleAdminView,
        switchAdminSubTab,
        addModalDocRow,
        removeModalDocRow,
        openModal,
        closeModal,
        reloadData: loadPortalData,
        fillDemoAccount(user, pass) {
            switchAuthTab('login');
            safeSetValue('loginUsername', user);
            safeSetValue('loginPassword', pass);
        }
    };

    function bootPortal() {
        try {
            initEvents();
            loadPortalData();
        } catch (bootErr) {
            console.error('[PortalApp] Initialization error:', bootErr);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootPortal);
    } else {
        bootPortal();
    }
})();
