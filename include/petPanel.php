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
// Drop-in patch: safe path resolver + skip empties
(function () {
  // Put this near the top of the snippet (under your ACCESSORIES_BASE)
  function resolveAccessorySrc(item, petType) {
    const cand =
      (item && (item.accessory_image_url || item.icon || item.image_url || item.image)) || '';

    // nothing to load? don't attempt any request
    if (!cand || String(cand).trim() === '') return null;

    const v = String(cand).trim();

    // absolute URL
    if (/^https?:\/\//i.test(v)) return v;

    // absolute path on same origin
    if (v.startsWith('/')) return v;

    // looks like a relative path (already includes folders)
    if (v.includes('/')) {
      // normalize common "IMG/..." case
      return v.startsWith('IMG/') ? `/${v}` : v;
    }

    // filename only -> default to IMG/Accessories/<petType>/<file>
    return `IMG/Accessories/${petType}/${v}`;
  }

  // Replace your renderAccessories with this safe version
  function renderAccessories(opts) {
    const { host, equippedIds, inventory, petType } = opts;
    if (!host || !Array.isArray(equippedIds) || !Array.isArray(inventory)) return;
    if (!equippedIds.length) { host.innerHTML = ''; return; }

    const byId = new Map(inventory.map(i => [Number(i.id), i]));
    host.innerHTML = '';

    equippedIds.forEach((id, idx) => {
      const item = byId.get(Number(id));
      if (!item) return;
      if (String(item.type).toLowerCase() !== 'accessories') return;

      const src = resolveAccessorySrc(item, petType);
      if (!src) return; // <-- skip if we don't have a real file

      const img = document.createElement('img');
      img.className = 'accessory-layer';
      img.alt = item.name || 'Accessory';
      Object.assign(img.style, {
        position: 'absolute',
        inset: '0',
        width: '100%',
        height: '100%',
        objectFit: 'contain',
        pointerEvents: 'none',
        zIndex: String(10 + idx)
      });
      img.src = src;
      host.appendChild(img);
    });
  }

  // expose back to the original snippet (no other edits required)
  window.PetPanelSync = Object.assign(window.PetPanelSync || {}, { _resolveAccessorySrc: resolveAccessorySrc });
})();
</script>
