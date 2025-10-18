<div class="pet-panel">
    <div class="pet-header">
        <div class="pet-info">
            <div class="pet-type">
                <?php echo isset($petData['pet_name']) ? htmlspecialchars($petData['pet_name']) : 'No Pet'; ?>
            </div>
            <div class="pet-name">
                <?php echo isset($petData['custom_name']) ? htmlspecialchars($petData['custom_name']) : 'Unnamed'; ?>
            </div>
            <div class="pet-age">
                <?php echo isset($petData['days_old']) ? htmlspecialchars($petData['days_old']) . ' days' : '0 days'; ?>
            </div>
        </div>
        <a href="gakumon.php" style="text-decoration: none;" class="pet-level">PLAY</a>
    </div>
    
    <!-- GAKUCOINS DISPLAY - POSITIONED PROPERLY -->
    <div class="gakucoins-display">
        <?php 
        // Get gakucoins from session user data
        $gakucoins = 0;
        if (isset($_SESSION['sUser'])) {
            $username = $_SESSION['sUser'];
            $coinsStmt = $connection->prepare("SELECT gakucoins FROM tbl_user WHERE username = ?");
            $coinsStmt->bind_param("s", $username);
            $coinsStmt->execute();
            $coinsResult = $coinsStmt->get_result();
            
            if ($coinsRow = $coinsResult->fetch_assoc()) {
                $gakucoins = $coinsRow['gakucoins'];
            }
            $coinsStmt->close();
        }
        echo htmlspecialchars($gakucoins) . ' GAKUCOINS';
        ?>
    </div>
    
    <div class="pet-content">
        <div class="pet-image">
            <?php if (isset($petData['pet_name'])): ?>
                <img src="<?php echo htmlspecialchars($petData['image_url']); ?>" 
                    alt="<?php echo htmlspecialchars($petData['pet_name']); ?> Avatar">
            <?php else: ?>
                <img src="IMG/Pets/default.png" alt="No Pet">
            <?php endif; ?>
        </div>
    </div>
    
    <div class="energy-bar-container">
        <div class="energy-label">
            <div class="gakumonEnergy">Gakumon Energy</div>
            <div class="percent">
                <?php echo isset($petData['energy_level']) ? htmlspecialchars($petData['energy_level']) . '%' : '100%'; ?>
            </div>
        </div>
        <div class="energy-bar">
            <div class="energy-progress" 
                style="width: <?php echo isset($petData['energy_level']) ? htmlspecialchars($petData['energy_level']) : '100'; ?>%;">
            </div>
        </div>
    </div>
</div>

<script>
/* === Pet Panel Sync (no edits to existing code) ============================
   Mirrors equipped items from gakumon.php to any page with a pet preview.
   Safe to include multiple times (one-time guard).
============================================================================ */
(function () {
  if (window.__PETPANEL_SYNC_LOADED__) return; 
  window.__PETPANEL_SYNC_LOADED__ = true;

  // ---- Config you probably already have in your project -------------------
  // Tries to use the same globals as gakumonScript.js if available.
  const serverData = (window.__GAKUMON_DATA__ || window.serverData || {}) || {};

  // Where to fetch inventory + pet info if we need accessory file names
  const STATE_ENDPOINT = 'include/gakumonState.inc.php';

  // Where accessories live; keep consistent with your existing folder scheme
  const ACCESSORIES_BASE = 'IMG/Accessories';

  // ---- Helpers ------------------------------------------------------------
  function getUserId() {
    return serverData.userId || 'anon';
  }
  function getPetTypeFromGlobals() {
    // Prefer explicit type from serverData if present
    return (serverData.pet && serverData.pet.type) ? String(serverData.pet.type) : null;
  }
  function getEquipStorageKey(userId, petType) {
    const uid = userId || 'anon';
    const pt  = petType || 'pet';
    return `gaku_equipped_${uid}_${pt}`;
  }
  function readLocalEquipped(petTypeHint) {
    // Preferred: use exact key from globals (userId + petType)
    const uid = getUserId();
    const pt  = getPetTypeFromGlobals() || petTypeHint || 'pet';
    let ids = tryReadIds(getEquipStorageKey(uid, pt));
    if (ids) return ids;

    // Fallback: scan for any key that matches this pet type
    const prefix = `gaku_equipped_${uid}_`;
    const keys = Object.keys(localStorage).filter(k => k.startsWith(prefix));
    for (const k of keys) {
      // pet type is the suffix
      const foundType = k.slice(prefix.length);
      if (!petTypeHint || String(foundType).toLowerCase() === String(petTypeHint).toLowerCase()) {
        const altIds = tryReadIds(k);
        if (altIds) return altIds;
      }
    }
    // Last-ditch: first gaku_equipped_ key we find
    const anyKeys = Object.keys(localStorage).filter(k => k.startsWith('gaku_equipped_'));
    for (const k of anyKeys) {
      const altIds = tryReadIds(k);
      if (altIds) return altIds;
    }
    return [];
  }
  function tryReadIds(key) {
    try {
      const raw = localStorage.getItem(key);
      if (!raw) return null;
      const arr = JSON.parse(raw);
      return Array.isArray(arr) ? arr.map(n => Number(n)) : null;
    } catch { return null; }
  }
  async function fetchState() {
    try {
      const res = await fetch(STATE_ENDPOINT, { method: 'GET' });
      return await res.json();
    } catch { return null; }
  }

  // Ensure we have a container to place accessory layers
  function ensureAccessoryLayerHost() {
    // 1) If your layout already has #accessoryLayers, use it
    let host = document.getElementById('accessoryLayers');
    if (host) return host;

    // 2) If you have #petImage with an <img>, create a layer stack on top
    const petImgWrap = document.getElementById('petImage');
    if (petImgWrap) {
      host = document.createElement('div');
      host.id = 'accessoryLayers';
      Object.assign(host.style, {
        position: 'absolute',
        inset: '0',
        pointerEvents: 'none'
      });
      // Make sure the parent is positioned
      const style = window.getComputedStyle(petImgWrap);
      if (style.position === 'static') petImgWrap.style.position = 'relative';
      petImgWrap.appendChild(host);
      return host;
    }

    // 3) Try a generic fallback: look for a visible .pet-image or [data-pet-image]
    const generic = document.querySelector('.pet-image, [data-pet-image]');
    if (generic) {
      host = document.createElement('div');
      host.id = 'accessoryLayers';
      Object.assign(host.style, {
        position: 'absolute',
        inset: '0',
        pointerEvents: 'none'
      });
      const style = window.getComputedStyle(generic);
      if (style.position === 'static') generic.style.position = 'relative';
      generic.appendChild(host);
      return host;
    }

    return null; // no suitable place; exit quietly
  }

  function renderAccessories(opts) {
    const { host, equippedIds, inventory, petType } = opts;
    if (!host || !Array.isArray(equippedIds) || !Array.isArray(inventory)) return;

    // Build quick lookup for id -> item
    const byId = new Map(inventory.map(i => [Number(i.id), i]));
    host.innerHTML = '';

    const layers = equippedIds
      .map(id => byId.get(Number(id)))
      .filter(Boolean)
      .filter(item => (String(item.type).toLowerCase() === 'accessories'));

    layers.forEach((item, idx) => {
      const img = document.createElement('img');
      img.className = 'accessory-layer';
      img.alt = item.name || 'Accessory';
      img.title = item.name || 'Accessory';
      Object.assign(img.style, {
        position: 'absolute',
        inset: '0',
        width: '100%',
        height: '100%',
        objectFit: 'contain',
        pointerEvents: 'none',
        zIndex: String(10 + idx)
      });

      // Try pet-specific path first, then fall back to generic
      const file = item.accessory_image_url || '';
      const specific = `${ACCESSORIES_BASE}/${petType}/${file}`;
      const generic  = `${ACCESSORIES_BASE}/${file}`;

      img.onerror = function () {
        if (this.src !== generic) this.src = generic;
      };
      img.src = specific;

      host.appendChild(img);
    });
  }

  async function syncNow() {
    // 1) Learn pet type + inventory, preferably from the endpoint (mirrors gakumonScript data shape)
    const host = ensureAccessoryLayerHost();
    if (!host) return;

    const state = await fetchState(); // may be null
    const petType = (state && state.pet && state.pet.type)
                  || getPetTypeFromGlobals()
                  || 'pet';

    const inventory = Array.isArray(state?.inventory) ? state.inventory : [];

    // 2) Load equipped IDs from the *same* localStorage slot used in gakumonScript
    const equippedIds = readLocalEquipped(petType);

    // 3) Render overlays
    renderAccessories({ host, equippedIds, inventory, petType });
  }

  // Kick off on DOM ready; expose a manual refresher
  document.addEventListener('DOMContentLoaded', syncNow);
  window.PetPanelSync = Object.assign(window.PetPanelSync || {}, { refresh: syncNow });

  // If the user equips/unequips in another tab, reflect changes here
  window.addEventListener('storage', function (e) {
    if (!e.key || !e.key.startsWith('gaku_equipped_')) return;
    syncNow();
  });
})();
</script>
