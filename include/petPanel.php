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
/* ===== Pet Panel: smart state fetch (zero server changes) ==================
   If __GAKUMON_DATA__ isn't present here, we fetch gakumon.php as text and
   extract the JSON object assigned to window.__GAKUMON_DATA__.
============================================================================ */
(function(){
  if (window.__PETPANEL_STATE_SCRAPER__) return; 
  window.__PETPANEL_STATE_SCRAPER__ = true;

  // 1) Try local globals first (if page already has them)
  function getLocalState() {
    const g = window.__GAKUMON_DATA__;
    if (g && g.inventory && g.pet) return g;
    const s = window.serverData;
    if (s && s.inventory && s.pet) return s;
    return null;
  }

  // 2) Extract { ... } assigned to __GAKUMON_DATA__ from HTML text
  function extractGakuDataFromHTML(html) {
    // Find the <script> which sets window.__GAKUMON_DATA__ = { ... };
    const scriptMatches = html.match(/<script[^>]*>[\s\S]*?<\/script>/gi) || [];
    for (const tag of scriptMatches) {
      if (!/__GAKUMON_DATA__\s*=/.test(tag)) continue;
      // Grab the JS inside the tag
      const js = tag.replace(/^<script[^>]*>/i, '').replace(/<\/script>$/i, '');

      // Find the first '{' after the equals sign, then parse a balanced JSON object
      const eqIdx = js.indexOf('__GAKUMON_DATA__');
      const afterEq = js.indexOf('=', eqIdx);
      if (afterEq === -1) continue;
      let i = js.indexOf('{', afterEq);
      if (i === -1) continue;

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
              const jsonText = js.slice(i, j + 1);
              try {
                return JSON.parse(jsonText); // JSON_HEX_* is valid JSON, so parse works
              } catch(e) { /* try next script */ }
            }
          }
        }
      }
    }
    return null;
  }

  async function fetchGakuStateViaHTML() {
    try {
      const res = await fetch('gakumon.php', { credentials: 'same-origin' });
      const html = await res.text();
      return extractGakuDataFromHTML(html);
    } catch { return null; }
  }

  // 3) Wrap the original sync to inject state if missing
  async function ensureStateThenSync() {
    // If the original PetPanelSync exists, we’ll call its refresh afterward.
    const hasOriginal = !!(window.PetPanelSync && window.PetPanelSync.refresh);

    // If state already present, just refresh
    if (getLocalState()) {
      hasOriginal && window.PetPanelSync.refresh();
      return;
    }

    // Fetch state from gakumon.php and attach it as a global for the existing code to use
    const state = await fetchGakuStateViaHTML();
    if (state && state.inventory && state.pet) {
      // Make it available to the first snippet (no changes needed there)
      window.__GAKUMON_DATA__ = state;
      window.serverData = Object.assign(window.serverData || {}, {
        userId: state.userId,
        pet: state.pet,
        inventory: state.inventory
      });
      // Now ask the original sync to render with real data
      hasOriginal && window.PetPanelSync.refresh();
    } else {
      // No state found — nothing to draw, but nothing to break either
      // console.info('PetPanel: could not load GAKUMON state');
    }
  }

  // Run after DOM is ready (and after the first snippet attached its refresh)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ensureStateThenSync);
  } else {
    ensureStateThenSync();
  }

  // If the user equips/unequips in another tab, refresh here too
  window.addEventListener('storage', function (e) {
    if (!e.key || !e.key.startsWith('gaku_equipped_')) return;
    ensureStateThenSync();
  });
})();
</script>
