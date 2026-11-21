import React, { useState } from 'react';
import { Download, Check, Star, Users, ExternalLink, Loader2, Package, Palette } from 'lucide-react';

export const PluginThemeCard = ({ item, onAction }) => {
  const [bannerError, setBannerError] = useState(false);
  const [iconError, setIconError] = useState(false);
  const [isActing, setIsActing] = useState(false);

  if (!item) return null;

  const isTheme = item.type === 'theme';
  const name = item.name || item.slug || (isTheme ? 'Theme' : 'Plugin');
  const slug = item.slug || item.stylesheet || '';
  const author = item.author || '';
  const version = item.version || '';
  const rating = item.rating;
  const installs = item.active_installs;
  const description = item.description || '';
  const isActive = Boolean(item.is_active);
  const isInstalled = Boolean(item.is_installed);
  const banner = item.banner;
  const icon = item.icon;

  const externalUrl = slug
    ? isTheme
      ? `https://wordpress.org/themes/${slug}/`
      : `https://wordpress.org/plugins/${slug}/`
    : null;

  const handleActionClick = async (actionType) => {
    if (isActing) return;
    setIsActing(true);
    try {
      if (onAction) {
        await onAction(actionType, item);
      }
    } finally {
      setIsActing(false);
    }
  };

  return (
    <div className="w-full rounded-2xl border border-border bg-white overflow-hidden transition-all duration-150 hover:border-black/25 flex flex-col">
      {/* Cover Photo / Banner */}
      <div className="relative w-full h-24 sm:h-28 bg-[#f3f4f6] border-b border-border overflow-hidden flex items-center justify-center">
        {banner && !bannerError ? (
          <img
            src={banner}
            alt={name}
            onError={() => setBannerError(true)}
            className="w-full h-full object-cover"
            loading="lazy"
          />
        ) : (
          <div className="w-full h-full bg-[#f8fafc] flex items-center justify-center text-gray-400">
            {isTheme ? (
              <Palette className="w-7 h-7 stroke-[1.25] text-gray-400" />
            ) : (
              <Package className="w-7 h-7 stroke-[1.25] text-gray-400" />
            )}
          </div>
        )}

        {/* Top-Right Badges: Type and external link */}
        <div className="absolute top-2 right-2 flex items-center gap-1.5">
          <span className="text-[10px] font-medium tracking-wide uppercase px-2 py-0.5 rounded-md bg-white/90 text-gray-700 border border-black/10">
            {isTheme ? 'Theme' : 'Plugin'}
          </span>
          {externalUrl && (
            <a
              href={externalUrl}
              target="_blank"
              rel="noopener noreferrer"
              title="View on WordPress.org"
              className="w-6 h-6 rounded-md bg-white/90 hover:bg-white text-gray-700 hover:text-black border border-black/10 flex items-center justify-center transition-colors"
            >
              <ExternalLink className="w-3 h-3" />
            </a>
          )}
        </div>
      </div>

      {/* Card Body */}
      <div className="px-3.5 pt-0 pb-3 flex-1 flex flex-col">
        {/* Floating Logo Icon */}
        <div className="-mt-6 mb-2 flex items-end justify-between gap-2">
          <div className="w-12 h-12 rounded-xl bg-white border border-border p-1 flex items-center justify-center flex-shrink-0 relative z-10">
            {icon && !iconError ? (
              <img
                src={icon}
                alt=""
                onError={() => setIconError(true)}
                className="w-full h-full object-contain rounded-lg"
                loading="lazy"
              />
            ) : (
              <div className="w-full h-full rounded-lg bg-gray-100 text-gray-800 font-bold text-base flex items-center justify-center">
                {name.charAt(0).toUpperCase()}
              </div>
            )}
          </div>

          {/* Stats: Rating & Active Installs */}
          <div className="flex items-center gap-1.5 flex-wrap justify-end pb-0.5">
            {rating ? (
              <span className="inline-flex items-center gap-0.5 text-[10px] font-medium text-amber-800 bg-amber-50 border border-amber-200/60 px-1.5 py-0.5 rounded">
                <Star className="w-2.5 h-2.5 fill-amber-500 text-amber-500" />
                <span>{rating}</span>
              </span>
            ) : null}

            {installs ? (
              <span className="inline-flex items-center gap-1 text-[10px] font-medium text-gray-600 bg-surface-muted border border-border px-1.5 py-0.5 rounded">
                <Users className="w-2.5 h-2.5 text-gray-500" />
                <span>{installs}</span>
              </span>
            ) : null}
          </div>
        </div>

        {/* Title & Author */}
        <div className="min-w-0">
          <h4 className="text-xs font-bold text-[#030712] truncate leading-tight" title={name}>
            {name}
          </h4>
          <div className="flex items-center gap-2 text-[10px] text-gray-500 mt-0.5">
            {author && <span className="truncate">by {author}</span>}
            {version && <span>v{version}</span>}
          </div>
        </div>

        {/* Short Description */}
        {description && (
          <p className="text-[11px] text-gray-600 line-clamp-2 leading-relaxed mt-1.5 flex-1">
            {description}
          </p>
        )}

        {/* Footer Actions */}
        <div className="pt-2.5 mt-2.5 border-t border-border flex items-center justify-between gap-2">
          <span className="text-[10px] text-gray-500 truncate">
            {slug}
          </span>

          <div className="flex items-center gap-1.5 flex-shrink-0">
            {isActive ? (
              <span className="inline-flex items-center gap-1 text-[10px] font-medium text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md">
                <Check className="w-3 h-3 text-emerald-600" />
                <span>Active</span>
              </span>
            ) : isInstalled ? (
              <button
                type="button"
                disabled={isActing}
                onClick={() => handleActionClick(isTheme ? 'switch_theme' : 'activate')}
                className="wpchat-custom-btn inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-medium text-gray-800 bg-gray-100 hover:bg-gray-200 border border-border rounded-lg transition-colors duration-150 disabled:opacity-50"
              >
                {isActing ? (
                  <Loader2 className="w-3 h-3 animate-spin" />
                ) : (
                  <Check className="w-3 h-3 text-gray-600" />
                )}
                <span>{isTheme ? 'Switch Theme' : 'Activate'}</span>
              </button>
            ) : (
              <button
                type="button"
                disabled={isActing}
                onClick={() => handleActionClick('install')}
                className="wpchat-custom-btn inline-flex items-center gap-1.5 px-3 py-1 text-[11px] font-medium text-white bg-[#030712] hover:bg-gray-800 border border-black rounded-lg transition-colors duration-150 disabled:opacity-50"
              >
                {isActing ? (
                  <Loader2 className="w-3 h-3 animate-spin" />
                ) : (
                  <Download className="w-3 h-3" />
                )}
                <span>{isTheme ? 'Install & Switch' : 'Install & Activate'}</span>
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
