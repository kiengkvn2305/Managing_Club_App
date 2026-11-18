import { escapeHtml as e, initials } from './utils.js';

export function imageUrl(kind, id, version, clubId) {
  if (!version) return '';
  const v = encodeURIComponent(version);
  if (kind === 'account') return `/api/v1/auth/avatar?v=${v}`;
  if (kind === 'public-club' && /^\d+$/.test(String(id))) return `/api/v1/discover/clubs/${encodeURIComponent(id)}/avatar?v=${v}`;
  if (!/^\d+$/.test(String(clubId))) return '';
  const base = `/api/v1/clubs/${encodeURIComponent(clubId)}`;
  if (kind === 'club' || kind === 'background') return `${base}/images/${kind === 'club' ? 'avatar' : 'background'}?v=${v}`;
  if (kind === 'player' && /^\d+$/.test(String(id))) return `${base}/members/${encodeURIComponent(id)}/avatar?v=${v}`;
  return '';
}

export function avatar(name, url = '', extraClass = '') {
  return `<span class="avatar ${e(extraClass)}"><span>${e(initials(name || ''))}</span>${url ? `<img src="${e(url)}" alt="" loading="lazy">` : ''}</span>`;
}
