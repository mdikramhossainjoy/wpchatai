import React, { useState, useEffect } from 'react';
import { X, Key, Shield, Cpu, Loader2, RefreshCw } from 'lucide-react';
import { Logo } from './Logo';
import { useToast } from './Toast';
import { CustomSelect } from './CustomSelect';
import { CustomRadio } from './CustomControls';

const PROVIDERS = [
  { id: 'gemini', name: 'Google Gemini', description: 'Google official models (Gemini Flash / Pro)' },
  { id: 'openrouter', name: 'OpenRouter (Free Models)', description: 'Curated free models on OpenRouter (Nemotron, Gemma, Cohere, ERNIE)' },
  { id: 'nvidia', name: 'NVIDIA NIM (Free Endpoints)', description: 'Accelerated free NIM models (Nemotron 3.5, Mistral, Gemma 4)' },
  { id: 'custom', name: 'Custom OpenAI-Compatible', description: 'Any custom endpoint (Ollama, Groq, Together, vLLM, OpenAI)' },
];

const DEFAULT_MODELS = {
  gemini: [
    { id: 'gemini-flash-latest', name: 'gemini-flash-latest (Recommended)', description: 'Fast and reliable tool use' },
    { id: 'gemini-3-flash-preview', name: 'gemini-3-flash-preview (Next-Gen)', description: 'Latest high-intelligence flash' },
    { id: 'gemini-flash-lite-latest', name: 'gemini-flash-lite-latest', description: 'Ultra-low latency' },
    { id: 'gemini-pro-latest', name: 'gemini-pro-latest', description: 'Complex code & logic reasoning' },
    { id: 'gemini-3.5-flash', name: 'gemini-3.5-flash', description: 'Fast multimodal generation' },
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

export const SettingsModal = ({ isOpen, onClose, config, onSettingsSaved }) => {
  const { showToast } = useToast();
  const [aiProvider, setAiProvider] = useState(config.aiProvider || 'gemini');
  const [customBaseUrl, setCustomBaseUrl] = useState(config.customBaseUrl || 'https://api.openai.com/v1');
  const [apiKey, setApiKey] = useState('');
  const [hasKeys, setHasKeys] = useState({
    gemini: !!config.hasGeminiKey,
    openrouter: !!config.hasOpenrouterKey,
    nvidia: !!config.hasNvidiaKey,
    custom: !!config.hasCustomKey,
  });
  const [approvalMode, setApprovalMode] = useState(config.defaultMode || 'copilot');
  const [model, setModel] = useState(config.activeModel || 'gemini-flash-latest');
  const [availableModels, setAvailableModels] = useState(DEFAULT_MODELS[config.aiProvider || 'gemini'] || DEFAULT_MODELS.gemini);
  const [isSaving, setIsSaving] = useState(false);
  const [isFetchingModels, setIsFetchingModels] = useState(false);

  // Load current settings and models on open
  useEffect(() => {
    if (isOpen) {
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
          setHasKeys({
            gemini: !!data.has_gemini_key,
            openrouter: !!data.has_openrouter_key,
            nvidia: !!data.has_nvidia_key,
            custom: !!data.has_custom_key,
          });
        })
        .catch(() => showToast('Failed to fetch settings', 'error'));

      fetchModels();
    }
  }, [isOpen, config]);

  const handleProviderChange = (newProv) => {
    setAiProvider(newProv);
    const defs = DEFAULT_MODELS[newProv] || DEFAULT_MODELS.gemini;
    setAvailableModels(defs);
    setModel(defs[0]?.id || '');
    setApiKey('');
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
          if (keyOverride || forceRefresh) {
            showToast(`Loaded ${data.models.length} models for ${data.provider || activeProv}`, 'success');
          }
        }
      }
    } catch {
      showToast('Using curated model catalog', 'info');
    } finally {
      setIsFetchingModels(false);
    }
  };

  if (!isOpen) return null;

  const handleSave = async (e) => {
    e.preventDefault();
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
        }),
      });

      const data = await res.json();
      if (res.ok) {
        showToast('Settings saved successfully', 'success');
        onSettingsSaved(data);
        onClose();
      } else {
        showToast(data.error || 'Failed saving settings', 'error');
      }
    } catch (err) {
      showToast('Network error while saving settings', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const getProviderKeyPlaceholder = () => {
    const isSaved = hasKeys[aiProvider] || config.hasApiKey;
    if (isSaved) return '•••••••••••••••••••••••••••••••• (Configured)';
    switch (aiProvider) {
      case 'openrouter':
        return 'sk-or-v1-...';
      case 'nvidia':
        return 'nvapi-...';
      case 'custom':
        return 'sk-... (Optional for local Ollama)';
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
            . High speed NIM inference.
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
            Free key from{' '}
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

  useEffect(() => {
    if (!isOpen) return;
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  return (
    <div
      id="wpchat-settings-modal-backdrop"
      role="dialog"
      aria-modal="true"
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
      className="fixed inset-0 z-[100010] flex items-center justify-center p-3 sm:p-4 wpchat-modal-backdrop animate-in fade-in duration-150"
    >
      <div
        id="wpchat-settings-modal-card"
        className="bg-white border border-border rounded-xl w-full max-w-lg p-4 sm:p-5 animate-in zoom-in-95 duration-100 max-h-[90vh] overflow-y-auto wpchat-scroll"
      >
        {/* Header */}
        <div className="flex items-center justify-between pb-3 border-b border-border mb-4">
          <div className="flex items-center gap-2">
            <Logo variant="dark" size="sm" className="w-5 h-5" alt="WPChat" />
            <h3 className="font-semibold text-xs text-[#030712]">
              WPChat Settings
            </h3>
          </div>
          <button
            id="wpchat-settings-close-btn"
            type="button"
            onClick={onClose}
            className="p-1 text-gray-400 hover:text-gray-600 transition-colors duration-150 rounded"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        <form onSubmit={handleSave} className="space-y-4">
          {/* AI Provider */}
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1.5">
              AI Provider
            </label>
            <CustomSelect
              id="modal-ai-provider"
              options={PROVIDERS}
              value={aiProvider}
              onChange={handleProviderChange}
              placeholder="Select AI Provider"
            />
          </div>

          {/* Custom Base URL (if custom) */}
          {aiProvider === 'custom' && (
            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1.5">
                Base URL
              </label>
              <input
                id="modal-custom-base-url"
                type="text"
                value={customBaseUrl}
                onChange={(e) => setCustomBaseUrl(e.target.value)}
                placeholder="https://api.openai.com/v1"
                className="wpchat-custom-input w-full text-xs bg-surface-muted border border-border rounded-lg px-3 py-2 text-[#030712] placeholder-gray-400 focus:outline-none focus:border-gray-400 focus:bg-white transition-colors duration-150"
              />
            </div>
          )}

          {/* API Key */}
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
            <div className="flex items-center gap-2">
              <input
                id="wpchat-input-api-key"
                type="password"
                value={apiKey}
                onChange={(e) => setApiKey(e.target.value)}
                placeholder={getProviderKeyPlaceholder()}
                className="wpchat-custom-input flex-1 text-xs bg-surface-muted border border-border rounded-lg px-3 py-2 text-[#030712] placeholder-gray-400 focus:outline-none focus:border-gray-400 focus:bg-white transition-colors duration-150"
              />
              <button
                type="button"
                id="wpchat-btn-verify-key"
                disabled={isFetchingModels || (!apiKey.trim() && !hasKeys[aiProvider] && !config.hasApiKey && aiProvider !== 'custom')}
                onClick={() => fetchModels(apiKey.trim(), true)}
                className="wpchat-custom-btn inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-gray-800 bg-surface-muted hover:bg-surface-secondary border border-border rounded-lg transition-colors duration-150 disabled:opacity-50"
                title="Sync models from API"
              >
                {isFetchingModels ? (
                  <Loader2 className="w-3.5 h-3.5 animate-spin" />
                ) : (
                  <RefreshCw className="w-3.5 h-3.5" />
                )}
                <span>Sync</span>
              </button>
            </div>
            <p className="mt-1 text-[11px] text-gray-500">
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
                {availableModels.length} models available
              </span>
            </div>
            <CustomSelect
              id="modal-active-model"
              options={availableModels}
              value={model}
              onChange={setModel}
              placeholder="Select Model"
            />
          </div>

          {/* Default Approval Mode */}
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-2 flex items-center gap-1.5">
              <Shield className="w-3.5 h-3.5 text-gray-500" />
              <span>Default Policy</span>
            </label>
            <div className="space-y-2">
              <CustomRadio
                id="mode-copilot"
                name="wpchat-approval-mode"
                value="copilot"
                checked={approvalMode === 'copilot'}
                onChange={setApprovalMode}
                label="Copilot (Recommended)"
                description="Runs safe actions directly. Confirms big changes."
              />
              <CustomRadio
                id="mode-always-ask"
                name="wpchat-approval-mode"
                value="always_ask"
                checked={approvalMode === 'always_ask'}
                onChange={setApprovalMode}
                label="Always Ask"
                description="Confirms before every change."
              />
              <CustomRadio
                id="mode-turbo"
                name="wpchat-approval-mode"
                value="turbo"
                checked={approvalMode === 'turbo'}
                onChange={setApprovalMode}
                label="Turbo"
                description="Runs directly without asking. Undo anytime in chat."
              />
            </div>
          </div>

          {/* Author Badge */}
          <div className="pt-2 text-[11px] text-gray-400 flex items-center justify-between border-t border-border">
            <span>Author: MD Ikram Hossain Joy</span>
            <span>WPChat v1.0.0</span>
          </div>

          {/* Action Buttons */}
          <div className="flex items-center justify-end gap-2 pt-2">
            <button
              id="wpchat-settings-cancel-btn"
              type="button"
              onClick={onClose}
              className="wpchat-custom-btn px-3.5 py-1.5 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 border border-border hover:border-black/25 rounded-lg transition-colors duration-150"
            >
              Cancel
            </button>
            <button
              id="wpchat-settings-save-btn"
              type="submit"
              disabled={isSaving}
              className="wpchat-custom-btn inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-medium text-white bg-[#030712] hover:bg-black border border-black/15 hover:border-black/20 rounded-lg transition-colors duration-150 disabled:opacity-50"
            >
              {isSaving && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
              <span>Save Settings</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
