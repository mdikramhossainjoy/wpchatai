import React, { useState, useEffect, useCallback, memo } from 'react';
import {
  Key,
  Shield,
  Cpu,
  RefreshCw,
  Loader2,
  Code2,
  Trash2,
  Power,
  Copy,
  Check,
  CheckCircle2,
  AlertTriangle,
  Activity,
  Wrench,
  Download,
  RotateCcw,
  Sliders,
  Sparkles,
  Server,
  Database,
  Clock,
  FileText,
} from 'lucide-react';
import { useToast } from './Toast';
import { CustomSelect } from './CustomSelect';
import { CustomRadio, CustomCheckbox } from './CustomControls';
import { ConfirmModal } from './ConfirmModal';
import { Logo } from './Logo';
import { Pagination } from './Pagination';

const PROVIDERS = [
  { id: 'gemini', name: 'Google Gemini', description: 'Google official models (Gemini Flash / Pro)' },
  { id: 'openrouter', name: 'OpenRouter (Free Models)', description: 'Curated free models on OpenRouter (Nemotron, Gemma, Cohere, ERNIE)' },
  { id: 'nvidia', name: 'NVIDIA NIM (Free Endpoints)', description: 'Accelerated free NIM models (Nemotron 3.5, Mistral, Gemma 4)' },
  { id: 'custom', name: 'Custom OpenAI-Compatible', description: 'Any custom endpoint (Ollama, Groq, Together, vLLM, OpenAI)' },
];

const DEFAULT_MODELS = {
  gemini: [
    { id: 'gemini-flash-latest', name: 'gemini-flash-latest (Recommended)', description: 'Fast and reliable tool use' },
    { id: 'gemini-3-flash-preview', name: 'gemini-3-flash-preview', description: 'Next-Gen high intelligence' },
    { id: 'gemini-flash-lite-latest', name: 'gemini-flash-lite-latest', description: 'Ultra-low latency' },
    { id: 'gemini-pro-latest', name: 'gemini-pro-latest', description: 'Complex code and logic' },
    { id: 'gemini-2.5-flash', name: 'gemini-2.5-flash', description: 'Balanced Flash' },
  ],
  openrouter: [
    { id: 'openrouter/free', name: 'openrouter/free (Auto Best Free)', description: 'Dynamic automatic router for available free models' },
    { id: 'nvidia/nemotron-3.5-lightning:free', name: 'NVIDIA Nemotron 3.5 Lightning (Free)', description: 'Fast, ultra-efficient reasoning on free tier' },
    { id: 'google/gemma-4-31b-it:free', name: 'Google Gemma 4 31B (Free)', description: 'High capacity open weights on free tier' },
    { id: 'cohere/north-mini-code:free', name: 'Cohere North Mini Code (Free)', description: 'Specialized coding assistant on free tier' },
    { id: 'nvidia/nemotron-3.5-super-vl:free', name: 'NVIDIA Nemotron 3.5 Super VL (Free)', description: 'Multimodal and vision on free tier' },
    { id: 'baidu/ernie-5.5-flash-preview:free', name: 'Baidu ERNIE 5.5 Flash (Free)', description: 'High speed inference on free tier' },
  ],
  nvidia: [
    { id: 'nvidia/nemotron-3.5-lightning-30b-a3b', name: 'nvidia/nemotron-3.5-lightning-30b-a3b (Recommended Free NIM)', description: 'High speed instruction model on NVIDIA Free NIM' },
    { id: 'mistralai/mistral-large-4-1t-a52b', name: 'mistralai/mistral-large-4-1t-a52b (Free NIM)', description: 'Frontier reasoning on NVIDIA Free NIM' },
    { id: 'google/gemma-4-31b-it', name: 'google/gemma-4-31b-it (Free NIM)', description: 'Google frontier instruct model on NVIDIA Free NIM' },
    { id: 'moonshotai/kimi-k3.1-pro', name: 'moonshotai/kimi-k3.1-pro (Free NIM)', description: 'Long context and reasoning on NVIDIA Free NIM' },
    { id: 'z-ai/glm-5.3-flash', name: 'z-ai/glm-5.3-flash (Free NIM)', description: 'Ultra-fast inference on NVIDIA Free NIM' },
    { id: 'minimaxai/minimax-m3.1-flash', name: 'minimaxai/minimax-m3.1-flash (Free NIM)', description: 'Fast agentic reasoning on NVIDIA Free NIM' },
  ],
  custom: [
    { id: 'gpt-4o', name: 'gpt-4o (Recommended)', description: 'OpenAI flagship model' },
    { id: 'gpt-4o-mini', name: 'gpt-4o-mini', description: 'Fast and lightweight' },
    { id: 'deepseek-chat', name: 'deepseek-chat', description: 'DeepSeek OpenAI endpoint' },
    { id: 'llama-3.3-70b', name: 'llama-3.3-70b', description: 'Local / Ollama / vLLM' },
  ],
};

const DANGEROUS_TOOLS = [
  { id: 'wp_execute_php', name: 'wp_execute_php', desc: 'Direct PHP code run' },
  { id: 'wp_delete_post', name: 'wp_delete_post', desc: 'Delete posts and pages' },
  { id: 'wp_manage_plugins', name: 'wp_manage_plugins', desc: 'Install or delete plugins' },
  { id: 'wp_manage_themes', name: 'wp_manage_themes', desc: 'Switch or change themes' },
  { id: 'wp_update_option', name: 'wp_update_option', desc: 'Modify site options' },
  { id: 'wp_manage_users', name: 'wp_manage_users', desc: 'Create or change user roles' },
];

export const SettingsPage = ({ config }) => {
  const { showToast } = useToast();
  const [activeTab, setActiveTab] = useState('general');

  // Provider & architecture state
  const [aiProvider, setAiProvider] = useState(config.aiProvider || 'gemini');
  const [customBaseUrl, setCustomBaseUrl] = useState(config.customBaseUrl || 'https://api.openai.com/v1');

  // General settings state
  const [apiKey, setApiKey] = useState('');
  const [hasApiKey, setHasApiKey] = useState(config.hasApiKey);
  const [hasKeys, setHasKeys] = useState({
    gemini: !!config.hasGeminiKey,
    openrouter: !!config.hasOpenrouterKey,
    nvidia: !!config.hasNvidiaKey,
    custom: !!config.hasCustomKey,
  });
  const [approvalMode, setApprovalMode] = useState(config.defaultMode || 'copilot');
  const [model, setModel] = useState(config.activeModel || 'gemini-flash-latest');
  const [availableModels, setAvailableModels] = useState(DEFAULT_MODELS[config.aiProvider || 'gemini'] || DEFAULT_MODELS.gemini);
  const [temperature, setTemperature] = useState(0.4);
  const [maxTokens, setMaxTokens] = useState(4096);
  const [systemPrompt, setSystemPrompt] = useState('');

  // Ping test state
  const [isPinging, setIsPinging] = useState(false);
  const [pingResult, setPingResult] = useState(null);

  // Security state
  const [allowedRole, setAllowedRole] = useState('administrator');
  const [killswitch, setKillswitch] = useState('off');
  const [blockedTools, setBlockedTools] = useState([]);

  // UI state
  const [isSaving, setIsSaving] = useState(false);
  const [isFetchingModels, setIsFetchingModels] = useState(false);

  // Lazy data caches to prevent lag on tab switching
  const [loadedTabs, setLoadedTabs] = useState({
    snippets: false,
    audit: false,
    diagnostics: false,
  });

  // Snippets state
  const [snippets, setSnippets] = useState([]);
  const [isLoadingSnippets, setIsLoadingSnippets] = useState(false);
  const [onlyActive, setOnlyActive] = useState(false);
  const [copiedId, setCopiedId] = useState(null);
  const [snippetPage, setSnippetPage] = useState(1);
  const snippetLimit = 6;

  // Audit log state
  const [auditLogs, setAuditLogs] = useState([]);
  const [totalLogs, setTotalLogs] = useState(0);
  const [auditPage, setAuditPage] = useState(1);
  const [auditLimit, setAuditLimit] = useState(20);
  const [totalAuditPages, setTotalAuditPages] = useState(1);
  const [isLoadingAudit, setIsLoadingAudit] = useState(false);
  const [undoingId, setUndoingId] = useState(null);

  // Diagnostics & maintenance state
  const [diagStats, setDiagStats] = useState(null);
  const [isLoadingDiag, setIsLoadingDiag] = useState(false);
  const [isPurging, setIsPurging] = useState(null);

  // Confirmation modal state
  const [confirmModal, setConfirmModal] = useState({
    isOpen: false,
    title: '',
    message: '',
    confirmText: 'Confirm',
    variant: 'danger',
    isLoading: false,
    onConfirm: null,
  });

  useEffect(() => {
    loadSettings();
    fetchModels(null, false);
  }, [config]);

  // Lazy tab loader: only loads once per session unless refreshed
  useEffect(() => {
    if (activeTab === 'snippets' && !loadedTabs.snippets) {
      fetchSnippets();
    } else if (activeTab === 'audit' && !loadedTabs.audit) {
      fetchAuditLogs();
    } else if (activeTab === 'diagnostics' && !loadedTabs.diagnostics) {
      fetchDiagnostics();
    }
  }, [activeTab, loadedTabs]);

  const loadSettings = () => {
    fetch(`${config.restUrl}settings`, {
      headers: { 'X-WP-Nonce': config.nonce },
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.ai_provider) {
          setAiProvider(data.ai_provider);
          setAvailableModels(DEFAULT_MODELS[data.ai_provider] || DEFAULT_MODELS.gemini);
        }
        if (data.custom_base_url) setCustomBaseUrl(data.custom_base_url);
        if (data.approval_mode) setApprovalMode(data.approval_mode);
        if (data.model) setModel(data.model);
        else if (data.gemini_model) setModel(data.gemini_model);
        if (data.has_api_key) setHasApiKey(true);
        setHasKeys({
          gemini: !!data.has_gemini_key,
          openrouter: !!data.has_openrouter_key,
          nvidia: !!data.has_nvidia_key,
          custom: !!data.has_custom_key,
        });
        if (data.temperature !== undefined) setTemperature(data.temperature);
        if (data.max_tokens !== undefined) setMaxTokens(data.max_tokens);
        if (data.system_prompt !== undefined) setSystemPrompt(data.system_prompt);
        if (data.allowed_role) setAllowedRole(data.allowed_role);
        if (data.killswitch) setKillswitch(data.killswitch);
        if (Array.isArray(data.blocked_tools)) setBlockedTools(data.blocked_tools);
      })
      .catch(() => showToast('Failed to load settings', 'error'));
  };

  const handleProviderChange = (newProv) => {
    setAiProvider(newProv);
    const defs = DEFAULT_MODELS[newProv] || DEFAULT_MODELS.gemini;
    setAvailableModels(defs);
    setModel(defs[0]?.id || '');
    setPingResult(null);
    fetchModels(null, false, newProv, customBaseUrl);
  };

  const fetchModels = async (keyOverride = null, forceRefresh = false, providerOverride = null, baseUrlOverride = null) => {
    setIsFetchingModels(true);
    try {
      const activeProv = providerOverride || aiProvider;
      const activeKey  = keyOverride || apiKey;
      const activeUrl  = baseUrlOverride || customBaseUrl;

      let url = `${config.restUrl}models?provider=${encodeURIComponent(activeProv)}`;
      if (activeKey) url += `&api_key=${encodeURIComponent(activeKey)}`;
      if (activeProv === 'custom' && activeUrl) url += `&base_url=${encodeURIComponent(activeUrl)}`;
      if (forceRefresh) url += '&force=1';

      const res = await fetch(url, {
        headers: { 'X-WP-Nonce': config.nonce },
      });
      if (res.ok) {
        const data = await res.json();
        if (data.models && data.models.length > 0) {
          setAvailableModels(data.models);
          if (forceRefresh) {
            showToast(`Synced ${data.models.length} models for ${data.provider || activeProv}`, 'success');
          }
        }
      }
    } catch {
      showToast('Used standard model catalog', 'info');
    } finally {
      setIsFetchingModels(false);
    }
  };

  const handlePing = async () => {
    setIsPinging(true);
    setPingResult(null);
    try {
      const res = await fetch(`${config.restUrl}ping`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        body: JSON.stringify({
          provider: aiProvider,
          api_key: apiKey.trim() || undefined,
          model: model,
          base_url: aiProvider === 'custom' ? customBaseUrl.trim() : undefined,
        }),
      });
      const data = await res.json();
      setPingResult(data);
      if (res.ok && data.success) {
        showToast(`Ping OK: ${data.latency}ms`, 'success');
      } else {
        showToast(data.error || 'Ping failed', 'error');
      }
    } catch {
      setPingResult({ success: false, error: 'Network request failed' });
      showToast('Ping failed', 'error');
    } finally {
      setIsPinging(false);
    }
  };

  const handleSave = async (e) => {
    if (e) e.preventDefault();
    setIsSaving(true);

    try {
      const res = await fetch(`${config.restUrl}settings`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        body: JSON.stringify({
          ai_provider: aiProvider,
          custom_base_url: customBaseUrl.trim(),
          api_key: apiKey.trim(),
          approval_mode: approvalMode,
          model: model,
          temperature: parseFloat(temperature),
          max_tokens: parseInt(maxTokens, 10),
          system_prompt: systemPrompt,
          allowed_role: allowedRole,
          killswitch: killswitch,
          blocked_tools: blockedTools,
        }),
      });

      const data = await res.json();
      if (res.ok) {
        showToast('Settings saved', 'success');
        if (data.has_api_key) setHasApiKey(true);
        setHasKeys({
          gemini: !!data.has_gemini_key,
          openrouter: !!data.has_openrouter_key,
          nvidia: !!data.has_nvidia_key,
          custom: !!data.has_custom_key,
        });
      } else {
        showToast(data.error || 'Failed saving settings', 'error');
      }
    } catch {
      showToast('Error saving settings', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const getProviderKeyPlaceholder = () => {
    const isSaved = hasKeys[aiProvider] || hasApiKey;
    if (isSaved) return '•••••••••••••••••••••••••••••••• (Configured)';
    switch (aiProvider) {
      case 'openrouter':
        return 'sk-or-v1-...';
      case 'nvidia':
        return 'nvapi-...';
      case 'custom':
        return 'sk-... (Optional for local Ollama/vLLM)';
      case 'gemini':
      default:
        return 'AIzaSy...';
    }
  };

  const getProviderHelp = () => {
    switch (aiProvider) {
      case 'openrouter':
        return (
          <>
            API key from{' '}
            <a
              href="https://openrouter.ai/keys"
              target="_blank"
              rel="noreferrer"
              className="underline text-[#030712] font-medium"
            >
              openrouter.ai/keys
            </a>
            . 470+ models ready.
          </>
        );
      case 'nvidia':
        return (
          <>
            API key from{' '}
            <a
              href="https://build.nvidia.com"
              target="_blank"
              rel="noreferrer"
              className="underline text-[#030712] font-medium"
            >
              build.nvidia.com
            </a>
            . Fast NIM inference.
          </>
        );
      case 'custom':
        return (
          <>
            Standard OpenAI-compatible endpoint (Ollama, Groq, Together, vLLM).
          </>
        );
      case 'gemini':
      default:
        return (
          <>
            Free API key from{' '}
            <a
              href="https://aistudio.google.com/app/apikey"
              target="_blank"
              rel="noreferrer"
              className="underline text-[#030712] font-medium"
            >
              Google AI Studio
            </a>
            .
          </>
        );
    }
  };

  // Snippets
  const fetchSnippets = () => {
    setIsLoadingSnippets(true);
    fetch(`${config.restUrl}snippets`, {
      headers: { 'X-WP-Nonce': config.nonce },
    })
      .then((res) => res.json())
      .then((data) => {
        setSnippets(data.snippets || []);
        setLoadedTabs((prev) => ({ ...prev, snippets: true }));
      })
      .catch(() => showToast('Failed to load snippets', 'error'))
      .finally(() => setIsLoadingSnippets(false));
  };

  const handleToggleSnippet = async (snippetId, currentActive) => {
    try {
      const res = await fetch(`${config.restUrl}snippets/toggle`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        body: JSON.stringify({ snippet_id: snippetId, active: !currentActive }),
      });
      if (res.ok) {
        showToast(`Snippet ${!currentActive ? 'active' : 'paused'}`, 'success');
        setSnippets((prev) =>
          prev.map((s) => (s.id === snippetId ? { ...s, is_active: !currentActive } : s))
        );
      }
    } catch {
      showToast('Toggle failed', 'error');
    }
  };

  const handleDeleteSnippet = (snippetId) => {
    setConfirmModal({
      isOpen: true,
      title: 'Delete Code Snippet',
      message: 'Delete this code snippet permanently? This will safely remove it from active site hooks.',
      confirmText: 'Delete Snippet',
      variant: 'danger',
      isLoading: false,
      onConfirm: async () => {
        try {
          const res = await fetch(`${config.restUrl}snippets/${snippetId}`, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': config.nonce },
          });
          if (res.ok) {
            showToast('Snippet deleted', 'info');
            setSnippets((prev) => prev.filter((s) => s.id !== snippetId));
          }
        } catch {
          showToast('Delete failed', 'error');
        } finally {
          setConfirmModal((prev) => ({ ...prev, isOpen: false }));
        }
      },
    });
  };

  // Audit Logs
  const fetchAuditLogs = (targetPage = auditPage, targetLimit = auditLimit) => {
    setIsLoadingAudit(true);
    fetch(`${config.restUrl}audit-log?page=${targetPage}&limit=${targetLimit}`, {
      headers: { 'X-WP-Nonce': config.nonce },
    })
      .then((res) => res.json())
      .then((data) => {
        setAuditLogs(data.logs || []);
        const total = data.total || 0;
        setTotalLogs(total);
        setTotalAuditPages(data.total_pages || Math.ceil(total / targetLimit) || 1);
        setLoadedTabs((prev) => ({ ...prev, audit: true }));
      })
      .catch(() => showToast('Failed to load audit logs', 'error'))
      .finally(() => setIsLoadingAudit(false));
  };

  const handleUndo = (auditId) => {
    setConfirmModal({
      isOpen: true,
      title: 'Rollback Action',
      message: 'Revert this site change back to its prior state? The previous database values will be restored.',
      confirmText: 'Revert Action',
      variant: 'primary',
      isLoading: false,
      onConfirm: async () => {
        setUndoingId(auditId);
        try {
          const res = await fetch(`${config.restUrl}action/undo`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': config.nonce,
            },
            body: JSON.stringify({ audit_id: auditId }),
          });
          const data = await res.json();
          if (res.ok && data.success) {
            showToast('Action undone', 'success');
            fetchAuditLogs();
          } else {
            showToast(data.message || 'Undo failed', 'error');
          }
        } catch {
          showToast('Undo failed', 'error');
        } finally {
          setUndoingId(null);
          setConfirmModal((prev) => ({ ...prev, isOpen: false }));
        }
      },
    });
  };

  // Diagnostics
  const fetchDiagnostics = () => {
    setIsLoadingDiag(true);
    fetch(`${config.restUrl}maintenance/stats`, {
      headers: { 'X-WP-Nonce': config.nonce },
    })
      .then((res) => res.json())
      .then((data) => {
        setDiagStats(data);
        setLoadedTabs((prev) => ({ ...prev, diagnostics: true }));
      })
      .catch(() => showToast('Failed loading stats', 'error'))
      .finally(() => setIsLoadingDiag(false));
  };

  const handlePurge = (target, label) => {
    setConfirmModal({
      isOpen: true,
      title: `Purge ${label}`,
      message: `Are you sure you want to purge ${label}? This cannot be undone.`,
      confirmText: 'Purge Now',
      variant: 'danger',
      isLoading: false,
      onConfirm: async () => {
        setIsPurging(target);
        try {
          const res = await fetch(`${config.restUrl}maintenance/purge`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': config.nonce,
            },
            body: JSON.stringify({ target }),
          });
          const data = await res.json();
          if (res.ok) {
            showToast(data.message || 'Purged', 'success');
            fetchDiagnostics();
          } else {
            showToast(data.message || 'Purge failed', 'error');
          }
        } catch {
          showToast('Purge failed', 'error');
        } finally {
          setIsPurging(null);
          setConfirmModal((prev) => ({ ...prev, isOpen: false }));
        }
      },
    });
  };

  const toggleBlockedTool = (toolId) => {
    setBlockedTools((prev) =>
      prev.includes(toolId) ? prev.filter((id) => id !== toolId) : [...prev, toolId]
    );
  };

  return (
    <div id="wpchat-settings-page-container" className="w-full max-w-none mt-3 space-y-4 font-sans select-text">
      {/* Top Header Card */}
      <div className="w-full bg-white border border-border rounded-xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Logo variant="dark" size="lg" className="w-8 h-8" alt="WPChat" />
          <div>
            <h2 className="text-sm font-semibold text-[#030712] flex items-center gap-2">
              <span>WPChat Settings</span>
              <span className="px-1.5 py-0.5 text-[10px] font-semibold text-gray-600 bg-surface-muted border border-border rounded">
                v{config?.pluginVersion || '1.0.0'}
              </span>
            </h2>
            <p className="text-xs text-gray-500 mt-0.5">
              Manage AI provider, security, snippets, and tools.
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2.5">
          <div className="flex items-center gap-1.5 px-2.5 py-1 bg-surface-muted border border-border rounded-lg text-xs font-medium text-gray-600">
            <span
              className={`w-2 h-2 rounded-full ${
                killswitch === 'on' ? 'bg-amber-500' : hasApiKey ? 'bg-emerald-500' : 'bg-gray-300'
              }`}
            />
            <span>{killswitch === 'on' ? 'Paused' : hasApiKey ? 'Ready' : 'No Key'}</span>
          </div>

          <button
            type="button"
            onClick={handleSave}
            disabled={isSaving}
            className="wpchat-custom-btn inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-[#030712] hover:bg-black border border-black/15 hover:border-black/20 rounded-lg transition-colors duration-150 disabled:opacity-50"
          >
            {isSaving && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
            <span>Save Settings</span>
          </button>
        </div>
      </div>

      {/* UNIFIED CARD: Tabs Header + Tab Content flush together with NO GAP */}
      <div className="w-full bg-white border border-border rounded-xl overflow-hidden">
        {/* Seamless Tab Navigation Header - strict no-arrow, zero-gap design */}
        <div
          className="wpchat-tabs-bar flex items-center border-b border-border bg-[#f8f9fa] px-2 pt-2 gap-1 overflow-x-auto overflow-y-hidden select-none"
          style={{ overflowY: 'hidden', scrollbarWidth: 'none' }}
        >
          <button
            type="button"
            onClick={() => setActiveTab('general')}
            className={`wpchat-tab-btn flex items-center gap-2 px-3.5 py-2.5 text-xs font-semibold rounded-t-lg transition-all border-b-2 outline-none focus:outline-none whitespace-nowrap ${
              activeTab === 'general'
                ? 'border-[#030712] text-[#030712] bg-white'
                : 'border-transparent text-gray-500 hover:text-black hover:border-black/25 hover:bg-white/50'
            }`}
          >
            <Key className="w-3.5 h-3.5" />
            <span>General</span>
          </button>

          <button
            type="button"
            onClick={() => setActiveTab('security')}
            className={`wpchat-tab-btn flex items-center gap-2 px-3.5 py-2.5 text-xs font-semibold rounded-t-lg transition-all border-b-2 outline-none focus:outline-none whitespace-nowrap ${
              activeTab === 'security'
                ? 'border-[#030712] text-[#030712] bg-white'
                : 'border-transparent text-gray-500 hover:text-black hover:border-black/25 hover:bg-white/50'
            }`}
          >
            <Shield className="w-3.5 h-3.5" />
            <span>Security & Roles</span>
          </button>

          <button
            type="button"
            onClick={() => setActiveTab('snippets')}
            className={`wpchat-tab-btn flex items-center gap-2 px-3.5 py-2.5 text-xs font-semibold rounded-t-lg transition-all border-b-2 outline-none focus:outline-none whitespace-nowrap ${
              activeTab === 'snippets'
                ? 'border-[#030712] text-[#030712] bg-white'
                : 'border-transparent text-gray-500 hover:text-black hover:border-black/25 hover:bg-white/50'
            }`}
          >
            <Code2 className="w-3.5 h-3.5" />
            <span>Code Studio</span>
          </button>

          <button
            type="button"
            onClick={() => setActiveTab('audit')}
            className={`wpchat-tab-btn flex items-center gap-2 px-3.5 py-2.5 text-xs font-semibold rounded-t-lg transition-all border-b-2 outline-none focus:outline-none whitespace-nowrap ${
              activeTab === 'audit'
                ? 'border-[#030712] text-[#030712] bg-white'
                : 'border-transparent text-gray-500 hover:text-black hover:border-black/25 hover:bg-white/50'
            }`}
          >
            <Activity className="w-3.5 h-3.5" />
            <span>Activity Log</span>
          </button>

          <button
            type="button"
            onClick={() => setActiveTab('diagnostics')}
            className={`wpchat-tab-btn flex items-center gap-2 px-3.5 py-2.5 text-xs font-semibold rounded-t-lg transition-all border-b-2 outline-none focus:outline-none whitespace-nowrap ${
              activeTab === 'diagnostics'
                ? 'border-[#030712] text-[#030712] bg-white'
                : 'border-transparent text-gray-500 hover:text-black hover:border-black/25 hover:bg-white/50'
            }`}
          >
            <Wrench className="w-3.5 h-3.5" />
            <span>Maintenance</span>
          </button>
        </div>

        {/* Tab Interior Body - Zero Gap, Smooth 60fps renders */}
        <div className="p-5 sm:p-6">
          {/* TAB 1: GENERAL */}
          {activeTab === 'general' && (
            <div className="space-y-6">
              {/* AI Provider & Architecture Selector */}
              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1.5">
                  AI Provider
                </label>
                <CustomSelect
                  id="page-ai-provider"
                  options={PROVIDERS}
                  value={aiProvider}
                  onChange={handleProviderChange}
                  placeholder="Select AI Provider"
                />
                <p className="mt-1.5 text-[11px] text-gray-400">
                  Select Gemini, OpenRouter, NVIDIA NIM, or custom endpoint.
                </p>
              </div>

              {/* Custom Base URL (if custom provider selected) */}
              {aiProvider === 'custom' && (
                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1.5">
                    Base URL
                  </label>
                  <input
                    id="wpchat-custom-base-url"
                    type="text"
                    value={customBaseUrl}
                    onChange={(e) => setCustomBaseUrl(e.target.value)}
                    placeholder="https://api.openai.com/v1"
                    className="wpchat-custom-input w-full text-xs bg-surface-muted border border-border rounded-lg px-3.5 py-2.5 text-[#030712] placeholder-gray-400 focus:outline-none focus:border-gray-400 focus:bg-white transition-colors duration-150"
                  />
                  <p className="mt-1.5 text-[11px] text-gray-400">
                    URL with /v1 path (e.g. <code className="text-[#030712]">http://localhost:11434/v1</code> for Ollama).
                  </p>
                </div>
              )}

              {/* API Key & Live Ping Test */}
              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1.5">
                  {aiProvider === 'gemini'
                    ? 'Google Gemini API Key'
                    : aiProvider === 'openrouter'
                    ? 'OpenRouter API Key'
                    : aiProvider === 'nvidia'
                    ? 'NVIDIA NIM API Key'
                    : 'API Key (Optional for local models)'}
                </label>
                <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                  <input
                    id="wpchat-page-api-key"
                    type="password"
                    value={apiKey}
                    onChange={(e) => setApiKey(e.target.value)}
                    placeholder={getProviderKeyPlaceholder()}
                    className="wpchat-custom-input flex-1 text-xs bg-surface-muted border border-border rounded-lg px-3.5 py-2.5 text-[#030712] placeholder-gray-400 focus:outline-none focus:border-gray-400 focus:bg-white transition-colors duration-150"
                  />

                  <button
                    type="button"
                    id="wpchat-page-ping-btn"
                    disabled={isPinging || (!apiKey.trim() && !hasKeys[aiProvider] && !hasApiKey && aiProvider !== 'custom')}
                    onClick={handlePing}
                    className="wpchat-custom-btn inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold text-gray-800 bg-surface-muted hover:bg-surface-secondary border border-border rounded-lg transition-colors duration-150 disabled:opacity-50"
                  >
                    {isPinging ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Activity className="w-3.5 h-3.5" />}
                    <span>Ping</span>
                  </button>

                  <button
                    type="button"
                    id="wpchat-page-verify-btn"
                    disabled={isFetchingModels || (!apiKey.trim() && !hasKeys[aiProvider] && !hasApiKey && aiProvider !== 'custom')}
                    onClick={() => fetchModels(apiKey.trim(), true)}
                    className="wpchat-custom-btn inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold text-gray-800 bg-surface-muted hover:bg-surface-secondary border border-border rounded-lg transition-colors duration-150 disabled:opacity-50"
                  >
                    {isFetchingModels ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <RefreshCw className="w-3.5 h-3.5" />}
                    <span>Sync</span>
                  </button>
                </div>

                {pingResult && (
                  <div className="mt-2 flex items-center gap-2">
                    {pingResult.success ? (
                      <span className="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md">
                        <Check className="w-3 h-3 text-emerald-600" />
                        <span>Ping OK: {pingResult.latency} ms roundtrip ({pingResult.model})</span>
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium text-red-700 bg-red-50 border border-red-200 rounded-md">
                        <AlertTriangle className="w-3 h-3 text-red-600" />
                        <span>{pingResult.error}</span>
                      </span>
                    )}
                  </div>
                )}

                <p className="mt-1.5 text-[11px] text-gray-500">
                  {getProviderHelp()}
                </p>
              </div>

              {/* Model Selection */}
              <div>
                <div className="flex items-center justify-between mb-1.5">
                  <label className="text-xs font-medium text-gray-700 flex items-center gap-1.5">
                    <Cpu className="w-3.5 h-3.5 text-gray-500" />
                    <span>
                      {aiProvider === 'gemini'
                        ? 'Gemini Model'
                        : aiProvider === 'openrouter'
                        ? 'OpenRouter Model'
                        : aiProvider === 'nvidia'
                        ? 'NVIDIA Model'
                        : 'Endpoint Model'}
                    </span>
                  </label>
                  <span className="text-[10px] text-gray-400 font-medium">
                    {availableModels.length} models ready
                  </span>
                </div>
                <CustomSelect
                  id="page-active-model"
                  options={availableModels}
                  value={model}
                  onChange={setModel}
                  placeholder="Select Model"
                />
              </div>

              {/* Sliders: Temperature & Max Tokens */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="p-3.5 bg-surface-muted/50 border border-border rounded-xl">
                  <div className="flex items-center justify-between mb-2">
                    <label className="text-xs font-medium text-gray-700">Creativity</label>
                    <span className="text-xs font-semibold text-[#030712] bg-white border border-border px-2 py-0.5 rounded">
                      {temperature}
                    </span>
                  </div>
                  <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.05"
                    value={temperature}
                    onChange={(e) => setTemperature(e.target.value)}
                    className="w-full accent-black cursor-pointer"
                  />
                  <span className="block text-[10px] text-gray-400 mt-1">
                    Lower = precise; higher = creative.
                  </span>
                </div>

                <div className="p-3.5 bg-surface-muted/50 border border-border rounded-xl">
                  <div className="flex items-center justify-between mb-2">
                    <label className="text-xs font-medium text-gray-700">Max Length</label>
                    <span className="text-xs font-semibold text-[#030712] bg-white border border-border px-2 py-0.5 rounded">
                      {maxTokens} tokens
                    </span>
                  </div>
                  <input
                    type="range"
                    min="512"
                    max="8192"
                    step="256"
                    value={maxTokens}
                    onChange={(e) => setMaxTokens(e.target.value)}
                    className="w-full accent-black cursor-pointer"
                  />
                  <span className="block text-[10px] text-gray-400 mt-1">
                    Max tokens per response.
                  </span>
                </div>
              </div>

              {/* Custom Site Persona */}
              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1.5 flex items-center gap-1.5">
                  <Sparkles className="w-3.5 h-3.5 text-gray-500" />
                  <span>Custom Persona</span>
                </label>
                <textarea
                  id="wpchat-system-prompt"
                  rows={4}
                  value={systemPrompt}
                  onChange={(e) => setSystemPrompt(e.target.value)}
                  placeholder="Instructions or persona for WPChat."
                  className="wpchat-custom-input w-full text-xs bg-surface-muted border border-border rounded-lg p-3 text-[#030712] placeholder-gray-400 font-sans focus:outline-none focus:border-gray-400 focus:bg-white transition-colors duration-150"
                />
              </div>

              {/* Default Approval Mode */}
              <div>
                <label className="block text-xs font-medium text-gray-700 mb-2 flex items-center gap-1.5">
                  <Shield className="w-3.5 h-3.5 text-gray-500" />
                  <span>Default Policy</span>
                </label>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-2.5">
                  <CustomRadio
                    id="page-mode-copilot"
                    name="wpchat-page-approval-mode"
                    value="copilot"
                    checked={approvalMode === 'copilot'}
                    onChange={setApprovalMode}
                    label="Copilot (Recommended)"
                    description="Runs safe actions directly. Confirms big changes."
                  />
                  <CustomRadio
                    id="page-mode-always-ask"
                    name="wpchat-page-approval-mode"
                    value="always_ask"
                    checked={approvalMode === 'always_ask'}
                    onChange={setApprovalMode}
                    label="Always Ask"
                    description="Confirms before every change."
                  />
                  <CustomRadio
                    id="page-mode-turbo"
                    name="wpchat-page-approval-mode"
                    value="turbo"
                    checked={approvalMode === 'turbo'}
                    onChange={setApprovalMode}
                    label="Turbo"
                    description="Runs directly without asking. Undo anytime in chat."
                  />
                </div>
              </div>
            </div>
          )}

          {/* TAB 2: SECURITY & PERMISSIONS */}
          {activeTab === 'security' && (
            <div className="space-y-6">
              {/* Emergency Kill-Switch */}
              <div className="p-4 border border-amber-200 bg-amber-50/50 rounded-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div>
                  <div className="flex items-center gap-2">
                    <AlertTriangle className="w-4 h-4 text-amber-700" />
                    <span className="text-xs font-semibold text-amber-900">
                      Emergency Pause
                    </span>
                  </div>
                  <p className="text-xs text-amber-800 mt-1">
                    Pause all write actions across site. AI will only read.
                  </p>
                </div>

                <button
                  type="button"
                  onClick={() => setKillswitch((prev) => (prev === 'on' ? 'off' : 'on'))}
                  className={`px-4 py-2 text-xs font-semibold rounded-lg border transition-colors flex-shrink-0 ${
                    killswitch === 'on'
                      ? 'bg-amber-600 text-white border-amber-700'
                      : 'bg-white text-gray-800 border-border hover:bg-gray-50'
                  }`}
                >
                  {killswitch === 'on' ? 'Paused (Click to resume)' : 'Active (Click to pause)'}
                </button>
              </div>

              {/* Minimum Role Access */}
              <div>
                <label className="block text-xs font-medium text-gray-700 mb-2">
                  Allowed User Role
                </label>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                  <CustomRadio
                    id="role-admin"
                    name="wpchat-allowed-role"
                    value="administrator"
                    checked={allowedRole === 'administrator'}
                    onChange={setAllowedRole}
                    label="Admin Only"
                    description="Site admins only."
                  />
                  <CustomRadio
                    id="role-editor"
                    name="wpchat-allowed-role"
                    value="editor"
                    checked={allowedRole === 'editor'}
                    onChange={setAllowedRole}
                    label="Editor & Up"
                    description="Editors and admins."
                  />
                  <CustomRadio
                    id="role-author"
                    name="wpchat-allowed-role"
                    value="author"
                    checked={allowedRole === 'author'}
                    onChange={setAllowedRole}
                    label="Author & Up"
                    description="Authors and up."
                  />
                </div>
              </div>

              {/* Tool Blacklist */}
              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1.5">
                  Blocked Tools
                </label>
                <p className="text-xs text-gray-500 mb-3">
                  Selected tools cannot run.
                </p>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                  {DANGEROUS_TOOLS.map((tool) => (
                    <div
                      key={tool.id}
                      className="p-3 border border-border rounded-xl bg-surface-muted/30 flex items-start gap-2.5"
                    >
                      <CustomCheckbox
                        id={`tool-block-${tool.id}`}
                        checked={blockedTools.includes(tool.id)}
                        onChange={() => toggleBlockedTool(tool.id)}
                        label={tool.name}
                        description={tool.desc}
                      />
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* TAB 3: CODE STUDIO */}
          {activeTab === 'snippets' && (
            <div className="space-y-4">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-border gap-2">
                <div>
                  <h3 className="text-xs font-semibold text-[#030712] flex items-center gap-2">
                    <Code2 className="w-4 h-4 text-gray-700" />
                    <span>Code Snippets</span>
                  </h3>
                  <p className="text-xs text-gray-500 mt-0.5">
                    Safe hooks and styles managed by WPChat.
                  </p>
                </div>

                <div className="flex items-center gap-3">
                  <CustomCheckbox
                    id="page-filter-active"
                    checked={onlyActive}
                    onChange={setOnlyActive}
                    label="Active only"
                  />
                  <button
                    type="button"
                    onClick={fetchSnippets}
                    title="Reload snippets"
                    className="p-1 text-gray-400 hover:text-black rounded"
                  >
                    <RefreshCw className={`w-3.5 h-3.5 ${isLoadingSnippets ? 'animate-spin' : ''}`} />
                  </button>
                  <span className="text-[11px] font-medium text-gray-500 bg-surface-muted border border-border px-2 py-0.5 rounded">
                    {snippets.length} Total
                  </span>
                </div>
              </div>

              <div className="space-y-3">
                {isLoadingSnippets && snippets.length === 0 ? (
                  <div className="text-center py-8 text-xs text-gray-400">Loading snippets...</div>
                ) : snippets.length === 0 ? (
                  <div className="text-center py-8 text-xs text-gray-400">
                    No snippets yet. Ask WPChat to create PHP or CSS.
                  </div>
                ) : (
                  (() => {
                    const filtered = snippets.filter((s) => (!onlyActive ? true : s.is_active));
                    const totalPages = Math.ceil(filtered.length / snippetLimit);
                    const paged = filtered.slice(
                      (snippetPage - 1) * snippetLimit,
                      snippetPage * snippetLimit
                    );

                    if (paged.length === 0) {
                      return (
                        <div className="text-center py-8 text-xs text-gray-400">
                          No matching snippets.
                        </div>
                      );
                    }

                    return (
                      <>
                        {paged.map((snippet) => (
                          <div
                            key={snippet.id}
                            className="p-3.5 border border-border rounded-xl bg-surface-muted/40 hover:bg-surface-muted transition-colors text-xs"
                          >
                            <div className="flex items-center justify-between gap-2 mb-2">
                              <div className="flex items-center gap-2 min-w-0">
                                <span className="font-semibold text-[#030712] truncate">
                                  {snippet.title}
                                </span>
                                <span className="px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-800 bg-white border border-border rounded">
                                  {snippet.code_type}
                                </span>
                              </div>

                              <div className="flex items-center gap-2 flex-shrink-0">
                                <button
                                  type="button"
                                  onClick={() => {
                                    navigator.clipboard.writeText(snippet.code_content);
                                    setCopiedId(snippet.id);
                                    setTimeout(() => setCopiedId(null), 2000);
                                  }}
                                  className="p-1 text-gray-400 hover:text-gray-600 rounded"
                                  title="Copy"
                                >
                                  {copiedId === snippet.id ? (
                                    <Check className="w-3.5 h-3.5 text-emerald-600" />
                                  ) : (
                                    <Copy className="w-3.5 h-3.5" />
                                  )}
                                </button>
                                <button
                                  type="button"
                                  onClick={() => handleToggleSnippet(snippet.id, snippet.is_active)}
                                  className={`p-1 rounded ${
                                    snippet.is_active
                                      ? 'text-emerald-600 hover:bg-emerald-50'
                                      : 'text-gray-400 hover:bg-gray-100'
                                  }`}
                                  title={snippet.is_active ? 'Pause' : 'Activate'}
                                >
                                  <Power className="w-3.5 h-3.5" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => handleDeleteSnippet(snippet.id)}
                                  className="p-1 text-gray-400 hover:text-red-500 rounded"
                                  title="Delete"
                                >
                                  <Trash2 className="w-3.5 h-3.5" />
                                </button>
                              </div>
                            </div>

                            <pre className="text-[11px] font-mono bg-white border border-border rounded-lg p-2.5 overflow-x-auto text-gray-800 max-h-32">
                              <code>{snippet.code_content}</code>
                            </pre>
                          </div>
                        ))}

                        {totalPages > 1 && (
                          <Pagination
                            page={snippetPage}
                            totalPages={totalPages}
                            totalItems={filtered.length}
                            pageSize={snippetLimit}
                            onPageChange={setSnippetPage}
                          />
                        )}
                      </>
                    );
                  })()
                )}
              </div>
            </div>
          )}

          {/* TAB 4: ACTIVITY & AUDIT LOG */}
          {activeTab === 'audit' && (
            <div className="space-y-4">
              <div className="flex items-center justify-between pb-3 border-b border-border">
                <div>
                  <h3 className="text-xs font-semibold text-[#030712] flex items-center gap-2">
                    <Activity className="w-4 h-4 text-gray-700" />
                    <span>Activity Log</span>
                  </h3>
                  <p className="text-xs text-gray-500 mt-0.5">
                    Action history with 1-click undo.
                  </p>
                </div>

                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={fetchAuditLogs}
                    title="Reload audit logs"
                    className="p-1.5 text-gray-500 hover:text-black border border-border rounded-lg bg-surface-muted"
                  >
                    <RefreshCw className={`w-3 h-3 ${isLoadingAudit ? 'animate-spin' : ''}`} />
                  </button>
                  <a
                    href={`${config.restUrl}audit-log/export?_wpnonce=${config.nonce}`}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gray-700 bg-surface-muted hover:bg-surface-secondary border border-border rounded-lg transition-colors"
                  >
                    <Download className="w-3 h-3" />
                    <span>Export CSV</span>
                  </a>
                  <span className="text-[11px] font-medium text-gray-500 bg-surface-muted border border-border px-2 py-0.5 rounded">
                    {totalLogs} Events
                  </span>
                </div>
              </div>

              {isLoadingAudit && auditLogs.length === 0 ? (
                <div className="text-center py-8 text-xs text-gray-400">Loading audit log...</div>
              ) : auditLogs.length === 0 ? (
                <div className="text-center py-8 text-xs text-gray-400">No actions yet.</div>
              ) : (
                <>
                  <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs">
                    <thead>
                      <tr className="border-b border-border text-gray-400 text-[11px]">
                        <th className="py-2 px-3">Date</th>
                        <th className="py-2 px-3">User</th>
                        <th className="py-2 px-3">Action</th>
                        <th className="py-2 px-3">Status</th>
                        <th className="py-2 px-3 text-right">Rollback</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      {auditLogs.map((log) => (
                        <tr key={log.id} className="hover:bg-surface-muted/40 transition-colors">
                          <td className="py-2.5 px-3 font-medium text-[11px] text-gray-500 whitespace-nowrap">
                            {log.created_at}
                          </td>
                          <td className="py-2.5 px-3 font-medium text-[#030712] whitespace-nowrap">
                            {log.user_name}
                          </td>
                          <td className="py-2.5 px-3 font-medium text-[11px] text-gray-800">
                            {log.tool_name}
                          </td>
                          <td className="py-2.5 px-3 whitespace-nowrap">
                            <span
                              className={`inline-block px-1.5 py-0.5 text-[10px] rounded font-medium ${
                                log.status === 'executed'
                                  ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                  : log.status === 'undone'
                                  ? 'bg-gray-100 text-gray-600 border border-gray-200'
                                  : 'bg-red-50 text-red-700 border border-red-200'
                              }`}
                            >
                              {log.status}
                            </span>
                          </td>
                          <td className="py-2.5 px-3 text-right whitespace-nowrap">
                            {log.status === 'executed' && log.previous_state && (
                              <button
                                type="button"
                                disabled={undoingId === log.id}
                                onClick={() => handleUndo(log.id)}
                                className="inline-flex items-center gap-1 text-[11px] text-gray-600 hover:text-black font-semibold transition-colors"
                              >
                                {undoingId === log.id ? (
                                  <Loader2 className="w-3 h-3 animate-spin" />
                                ) : (
                                  <RotateCcw className="w-3 h-3" />
                                )}
                                <span>Undo</span>
                              </button>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                <Pagination
                  page={auditPage}
                  totalPages={totalAuditPages}
                  totalItems={totalLogs}
                  pageSize={auditLimit}
                  onPageChange={(newPage) => {
                    setAuditPage(newPage);
                    fetchAuditLogs(newPage, auditLimit);
                  }}
                  onPageSizeChange={(newSize) => {
                    setAuditLimit(newSize);
                    setAuditPage(1);
                    fetchAuditLogs(1, newSize);
                  }}
                  pageSizeOptions={[10, 20, 50]}
                />
              </>
            )}
            </div>
          )}

          {/* TAB 5: DIAGNOSTICS & MAINTENANCE */}
          {activeTab === 'diagnostics' && (
            <div className="space-y-6">
              <div className="flex items-center justify-between pb-3 border-b border-border">
                <div>
                  <h3 className="text-xs font-semibold text-[#030712] flex items-center gap-2">
                    <Server className="w-4 h-4 text-gray-700" />
                    <span>System Health</span>
                  </h3>
                  <p className="text-xs text-gray-500 mt-0.5">
                    Server specs and database usage.
                  </p>
                </div>

                <button
                  type="button"
                  onClick={fetchDiagnostics}
                  title="Reload diagnostics"
                  className="p-1.5 text-gray-500 hover:text-black border border-border rounded-lg bg-surface-muted"
                >
                  <RefreshCw className={`w-3 h-3 ${isLoadingDiag ? 'animate-spin' : ''}`} />
                </button>
              </div>

              {isLoadingDiag && !diagStats ? (
                <div className="text-center py-8 text-xs text-gray-400">Loading system stats...</div>
              ) : diagStats ? (
                <>
                  {/* Server Environment Cards */}
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div className="p-3 bg-surface-muted/40 border border-border rounded-xl">
                      <span className="block text-[10px] text-gray-400 uppercase font-semibold">PHP Version</span>
                      <span className="block text-sm font-semibold text-[#030712] mt-0.5">
                        {diagStats.system.php_version}
                      </span>
                    </div>
                    <div className="p-3 bg-surface-muted/40 border border-border rounded-xl">
                      <span className="block text-[10px] text-gray-400 uppercase font-semibold">MySQL Version</span>
                      <span className="block text-sm font-semibold text-[#030712] mt-0.5 truncate">
                        {diagStats.system.mysql_version}
                      </span>
                    </div>
                    <div className="p-3 bg-surface-muted/40 border border-border rounded-xl">
                      <span className="block text-[10px] text-gray-400 uppercase font-semibold">Memory Limit</span>
                      <span className="block text-sm font-semibold text-[#030712] mt-0.5">
                        {diagStats.system.memory_limit}
                      </span>
                    </div>
                    <div className="p-3 bg-surface-muted/40 border border-border rounded-xl">
                      <span className="block text-[10px] text-gray-400 uppercase font-semibold">WP-Cron Jobs</span>
                      <span className="block text-sm font-semibold text-[#030712] mt-0.5">
                        {diagStats.system.cron_count} active
                      </span>
                    </div>
                  </div>

                  {/* Database Storage Cards */}
                  <div>
                    <h4 className="text-xs font-medium text-gray-700 mb-2 flex items-center gap-1.5">
                      <Database className="w-3.5 h-3.5 text-gray-500" />
                      <span>Database Storage</span>
                    </h4>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                      <div className="p-3 bg-surface-muted/40 border border-border rounded-xl">
                        <span className="block text-[10px] text-gray-400 font-medium">Chat Sessions</span>
                        <span className="block text-sm font-semibold text-[#030712] mt-0.5">
                          {diagStats.database.sessions_count}
                        </span>
                      </div>
                      <div className="p-3 bg-surface-muted/40 border border-border rounded-xl">
                        <span className="block text-[10px] text-gray-400 font-medium">Stored Messages</span>
                        <span className="block text-sm font-semibold text-[#030712] mt-0.5">
                          {diagStats.database.messages_count}
                        </span>
                      </div>
                      <div className="p-3 bg-surface-muted/40 border border-border rounded-xl">
                        <span className="block text-[10px] text-gray-400 font-medium">Audit Entries</span>
                        <span className="block text-sm font-semibold text-[#030712] mt-0.5">
                          {diagStats.database.audit_count}
                        </span>
                      </div>
                      <div className="p-3 bg-surface-muted/40 border border-border rounded-xl">
                        <span className="block text-[10px] text-gray-400 font-medium">Total Size</span>
                        <span className="block text-sm font-semibold text-[#030712] mt-0.5">
                          {diagStats.database.total_size_kb} KB
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* One-Click Maintenance Purges */}
                  <div className="pt-2 border-t border-border">
                    <h4 className="text-xs font-medium text-gray-700 mb-2 flex items-center gap-1.5">
                      <Wrench className="w-3.5 h-3.5 text-gray-500" />
                      <span>Maintenance</span>
                    </h4>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                      <button
                        type="button"
                        disabled={isPurging !== null}
                        onClick={() => handlePurge('transients', 'Action Tokens and Transients')}
                        className="p-3 border border-border rounded-xl text-left bg-surface-muted/30 hover:bg-surface-muted transition-colors disabled:opacity-50"
                      >
                        <span className="block text-xs font-semibold text-[#030712]">Flush Transients</span>
                        <span className="block text-[11px] text-gray-500 mt-0.5">
                          Clear cache and temporary tokens.
                        </span>
                      </button>

                      <button
                        type="button"
                        disabled={isPurging !== null}
                        onClick={() => handlePurge('all_chats', 'All Chat Sessions')}
                        className="p-3 border border-border rounded-xl text-left bg-surface-muted/30 hover:bg-surface-muted transition-colors disabled:opacity-50"
                      >
                        <span className="block text-xs font-semibold text-[#030712]">Clear Past Chats</span>
                        <span className="block text-[11px] text-gray-500 mt-0.5">
                          Delete all chat sessions.
                        </span>
                      </button>

                      <button
                        type="button"
                        disabled={isPurging !== null}
                        onClick={() => handlePurge('audit_logs', 'Audit Log History')}
                        className="p-3 border border-border rounded-xl text-left bg-surface-muted/30 hover:bg-surface-muted transition-colors disabled:opacity-50"
                      >
                        <span className="block text-xs font-semibold text-[#030712]">Flush Audit Log</span>
                        <span className="block text-[11px] text-gray-500 mt-0.5">
                          Clear activity history.
                        </span>
                      </button>
                    </div>
                  </div>
                </>
              ) : null}
            </div>
          )}
        </div>
      </div>

      {/* Footer Info */}
      <div className="pt-1 text-xs text-gray-400 flex flex-col sm:flex-row items-center justify-between gap-1">
        <span>Author: {config?.authorName || 'MD Ikram Hossain Joy'}</span>
        <span>WPChat v{config?.pluginVersion || '1.0.0'}</span>
      </div>

      <ConfirmModal
        isOpen={confirmModal.isOpen}
        title={confirmModal.title}
        message={confirmModal.message}
        confirmText={confirmModal.confirmText}
        variant={confirmModal.variant}
        isLoading={confirmModal.isLoading}
        onConfirm={confirmModal.onConfirm}
        onCancel={() => setConfirmModal((prev) => ({ ...prev, isOpen: false }))}
      />
    </div>
  );
};
