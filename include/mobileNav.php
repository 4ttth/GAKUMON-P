<?php
    // Get the current page name to determine active nav item
    $currentPage = basename($_SERVER['PHP_SELF']);
    $activeHome = '';
    $activeLessons = '';
    $activeQuizzes = '';
    $activeSensei = '';
    $activeAccount = '';

    // Set active class based on current page
    switch($currentPage) {
        case 'homepage.php':
            $activeHome = 'active';
            break;
        case 'lessons.php':
            $activeLessons = 'active';
            break;
        case 'quizzes.php':
            $activeQuizzes = 'active';
            break;
        case 'sensei.php':
            $activeSensei = 'active';
            break;
        case 'accountMobile.php':
            $activeAccount = 'active';
            break;
    }
?>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-bottom-nav">
    <!-- Navigation Items -->
    <div class="mobile-nav-items">
        <a href="homepage.php" class="mobile-nav-item <?php echo $activeHome; ?>">
            <i class="bi bi-house-door-fill"></i>
            <span class="mobile-nav-text">Home</span>
        </a>
        
        <a href="lessons.php" class="mobile-nav-item <?php echo $activeLessons; ?>">
            <i class="bi bi-book-half"></i>
            <span class="mobile-nav-text">Lessons</span>
        </a>
        
        <!-- Pet Dome in the middle -->
        <div class="mobile-pet-dome-nav">
            <?php if (isset($petData['pet_name'])): ?>
                <a href="gakumon.php" class="pet-dome-link">
                    <img src="<?php echo htmlspecialchars($petData['image_url']); ?>" 
                        alt="<?php echo htmlspecialchars($petData['pet_name']); ?>" 
                        class="pet-dome-image contain"> <!-- Use 'contain' class -->
                </a>
            <?php else: ?>
                <a href="gakumon.php" class="pet-dome-link">
                    <img src="IMG/Pets/default.png" alt="No Pet" class="pet-dome-image contain">
                </a>
            <?php endif; ?>
        </div>
        
        <a href="quizzes.php" class="mobile-nav-item <?php echo $activeQuizzes; ?>">
            <i class="bi bi-lightbulb-fill"></i>
            <span class="mobile-nav-text">Quizzes</span>
        </a>
        
        <!-- Account Button - Now redirects to accountMobile.php -->
        <a href="accountMobile.php" class="mobile-nav-item <?php echo $activeAccount; ?>">
            <i class="bi bi-person-circle"></i>
            <span class="mobile-nav-text">Account</span>
        </a>
    </div>
</nav>

<script>
/* =======================================================================
   Mobile PetPanel Sync (no desktop changes)
   - Mirrors equipped accessories on mobile pages that include the Pet Dome
   - Scrapes /gakumon.php for __GAKUMON_DATA__ when not already present
   - Reads localStorage key: gaku_equipped_<userId>_<petType>
   - Overlays /IMG/Accessories/<petType>/<file> with generic fallback
   ======================================================================= */
(function(){
  // Run only on mobile-ish environments
  const isMobileEnv = (window.__GAK_IS_MOBILE__ === true)
                   || (typeof matchMedia === 'function' && (matchMedia('(pointer:coarse)').matches || matchMedia('(max-width: 768px)').matches))
                   || (window.innerWidth <= 768);
  if (!isMobileEnv) return;

  if (window.__MOBILE_PETPANEL_SYNC__) return;
  window.__MOBILE_PETPANEL_SYNC__ = true;

  const GAKUMON_PAGE = '/gakumon.php';
  const ACCESSORIES_BASE = '/IMG/Accessories';

  // ---------- Find the Pet Dome container to overlay ----------
  function findPetContainer() {
    // common ids/classes first
    let el = document.querySelector('#petImage') ||
             document.querySelector('.pet-image') ||
             document.querySelector('#petDome') ||
             document.querySelector('.pet-dome') ||
             document.querySelector('#pet-dome');

    // fallback: any container that has an IMG that looks like a pet base
    if (!el) {
      const petImg = Array.from(document.images).find(img =>
        /\/IMG\/Pets\//i.test(img.src) || img.classList.contains('pet-base')
      );
      if (petImg) el = petImg.parentElement;
    }

    // last fallback: anything that looks like a pet container
    if (!el) {
      el = document.querySelector('.pet-container') ||
           document.querySelector('.pet-display-area') ||
           document.querySelector('[data-pet-image]');
    }

    return el || null;
  }

  function ensureOverlayHost() {
    const wrap = findPetContainer();
    if (!wrap) return null;

    let host = document.getElementById('mobileAccessoryLayers');
    if (host) return host;

    const cs = getComputedStyle(wrap);
    if (cs.position === 'static') wrap.style.position = 'relative';

    host = document.createElement('div');
    host.id = 'mobileAccessoryLayers';
    Object.assign(host.style, {
      position: 'absolute',
      inset: '0',
      pointerEvents: 'none'
    });
    wrap.appendChild(host);
    return host;
  }

  // ---------- State: read or scrape from /gakumon.php ----------
  function getLocalState(){
    const g = window.__GAKUMON_DATA__;
    if (g?.inventory && g?.pet) return g;
    const s = window.serverData;
    if (s?.inventory && s?.pet) return s;
    return null;
  }

  function extractGakuDataFromHTML(html) {
    const scripts = html.match(/<script[^>]*>[\s\S]*?<\/script>/gi) || [];
    for (const tag of scripts) {
      if (!/__GAKUMON_DATA__\s*=/.test(tag)) continue;
      const js = tag.replace(/^<script[^>]*>/i, '').replace(/<\/script>$/i, '');
      const k = js.indexOf('__GAKUMON_DATA__'); if (k === -1) continue;
      const eq = js.indexOf('=', k);            if (eq === -1) continue;
      let i = js.indexOf('{', eq);              if (i === -1) continue;

      let depth = 0, inStr = false, esc = false;
      for (let j = i; j < js.length; j++) {
        const ch = js[j];
        if (inStr) {
          if (esc) { esc = false; continue; }
          if (ch === '\\') { esc = true; continue; }
          if (ch === '"') inStr = false;
        } else {
          if (ch === '"') inStr = true;
          else if (ch === '{') depth++;
          else if (ch === '}') {
            depth--;
            if (depth === 0) {
              try { return JSON.parse(js.slice(i, j + 1)); } catch {}
            }
          }
        }
      }
    }
    return null;
  }

  async function ensureState() {
    let state = getLocalState();
    if (state) return state;
    try {
      const res = await fetch(GAKUMON_PAGE, { credentials: 'same-origin' });
      const html = await res.text();
      state = extractGakuDataFromHTML(html) || null;
      if (state) {
        window.__GAKUMON_DATA__ = state;
        window.serverData = Object.assign(window.serverData || {}, {
          userId: state.userId || state.user?.id,
          pet: state.pet,
          inventory: state.inventory
        });
      }
    } catch {}
    return state;
  }

  // ---------- Storage helpers ----------
  function storageKeyFor(uid, petType) {
    return `gaku_equipped_${uid || 'anon'}_${petType || 'pet'}`;
  }

  function readEquippedIds(uid, petType){
    // Preferred: exact key
    let raw = localStorage.getItem(storageKeyFor(uid, petType));
    if (raw) { try { const arr = JSON.parse(raw); if (Array.isArray(arr)) return arr.map(Number); } catch {} }
    // Fallback: any key for this user (or any) that matches the pet type
    const keys = Object.keys(localStorage).filter(k => k.startsWith('gaku_equipped_'));
    const byType = keys.filter(k => k.endsWith(`_${petType}`));
    for (const k of (byType.length ? byType : keys)) {
      try { const arr = JSON.parse(localStorage.getItem(k) || '[]'); if (Array.isArray(arr)) return arr.map(Number); } catch {}
    }
    return [];
  }

  // ---------- URL resolver ----------
  function resolveAccessorySrc(item, petType){
    const cand = (item?.accessory_image_url || item?.icon || item?.image_url || item?.image || '').trim();
    if (!cand) return null;
    if (/^https?:\/\//i.test(cand)) return cand;        // absolute URL
    if (cand.startsWith('/')) return cand;              // absolute path
    if (cand.includes('/')) return cand.startsWith('IMG/') ? `/${cand}` : cand; // relative with folders
    return `${ACCESSORIES_BASE}/${petType}/${cand}`;    // filename only → pet-specific folder
  }

  // ---------- Render ----------
  function render(state){
    const host = ensureOverlayHost();
    if (!host || !state?.inventory || !state?.pet) return;

    const userId  = state.userId || state.user?.id || window.serverData?.userId;
    const petType = state.pet.type || 'pet';
    const equipped = readEquippedIds(userId, petType);
    host.innerHTML = '';
    if (!equipped.length) return;

    const byId = new Map(state.inventory.map(i => [Number(i.id), i]));
    let z = 100;
    equipped.forEach(id => {
      const item = byId.get(Number(id));
      if (!item) return;
      if (String(item.type).toLowerCase() !== 'accessories') return;
      const src = resolveAccessorySrc(item, petType);
      if (!src) return;

      const img = new Image();
      img.alt = item.name || 'Accessory';
      Object.assign(img.style, {
        position: 'absolute',
        inset: '0',
        width: '100%',
        height: '100%',
        objectFit: 'contain',
        pointerEvents: 'none',
        zIndex: String(z++)
      });
      img.src = src;
      host.appendChild(img);
    });
  }

  async function boot(){
    const state = await ensureState();
    render(state);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  // Keep in sync if user equips/unequips in another tab
  window.addEventListener('storage', (e)=>{
    if (!e.key || !e.key.startsWith('gaku_equipped_')) return;
    boot();
  });

  // Expose a manual refresh for debugging
  window.MobilePetPanelSync = { refresh: boot };
})();
</script>
