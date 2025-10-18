<?php
session_start();
require_once 'config/config.php'; // Database Connection

$pageTitle = 'GAKUMON';

// Mobile detection function
function isMobileDevice() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $mobileKeywords = [
        'mobile', 'android', 'silk', 'kindle', 'blackberry', 'iphone', 'ipod',
        'ipad', 'webos', 'symbian', 'windows phone', 'phone'
    ];
    
    foreach ($mobileKeywords as $keyword) {
        if (stripos($userAgent, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

$isMobile = isMobileDevice();

// MOBILE or DESKTOP includes
if ($isMobile) {
    $pageCSS = 'CSS/mobile/gakumonStyle.css';
    $pageJS  = 'JS/mobile/gakumonScript.js';
} else {
    $pageCSS = 'CSS/desktop/gakumonStyle.css';
    $pageJS  = 'JS/desktop/gakumonScript.js';
}


// ---- Resolve user_id and gakucoins ----
$userId    = null;
$gakucoins = 0;
if (!empty($_SESSION['sUser'])) {
    if ($stmt = $connection->prepare("SELECT user_id, gakucoins FROM tbl_user WHERE username = ?")) {
        $stmt->bind_param("s", $_SESSION['sUser']);
        $stmt->execute();
        $stmt->bind_result($userId, $gakucoins);
        $stmt->fetch();
        $stmt->close();
    }
}

// ---- Shop items (normalize DB types to UI categories) ----
$shopItems = [];
$sql = "
SELECT
  item_id AS id,
  CASE item_type
    WHEN 'accessory' THEN 'accessories'
    WHEN 'wallpaper' THEN 'decorations'
    ELSE item_type
  END AS type,
  item_name AS name,
  price,
  image_url AS icon,
  accessory_image_url,
  energy_restore
FROM tbl_shop_items
ORDER BY item_id
";

if ($res = $connection->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        // Coerce numeric fields
        $row['id']             = (int)$row['id'];
        $row['price']          = (int)$row['price'];
        $row['energy_restore'] = isset($row['energy_restore']) ? (int)$row['energy_restore'] : null;
        $shopItems[] = $row;
    }
    $res->free();
}

// ---- Pet data (custom name, energy, age, image) ----
$petData = null;
if ($userId) {
    $sql = "
        SELECT up.custom_name, up.energy_level, up.created_at,
               p.pet_name, p.image_url
          FROM tbl_user_pet up
          JOIN tbl_pet p ON p.pet_id = up.pet_id
         WHERE up.user_id = ?
         ORDER BY up.created_at DESC
         LIMIT 1
    ";
    if ($stmt = $connection->prepare($sql)) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($custom, $energy, $createdAt, $petName, $imgUrl);
        if ($stmt->fetch()) {
            $ageDays  = (new DateTime())->diff(new DateTime($createdAt))->days;
            $petData = [
                'name'      => $custom ?: $petName,
                'type'      => $petName,
                'age'       => (int)$ageDays,
                'energy'    => (int)$energy,
                'maxEnergy' => 100,
                'imageUrl'  => $imgUrl
            ];
        }
        $stmt->close();
    }
}

/* =========================
   INVENTORY (from DB)
   - Foods: multiple, tracked by quantity (tbl_user_foods)
   - Accessories: own once (tbl_user_accessories)
   Types are normalized to your UI tabs: accessory→accessories, wallpaper→decorations
   ========================= */
$inventory = [];

if ($userId) {
    // --- FOODS ---
    $sqlFoods = "
        SELECT 
            f.item_id,
            f.quantity,
            COALESCE(si.item_name, '')       AS name,
            COALESCE(si.item_type, 'food')   AS item_type,
            COALESCE(si.image_url, '')       AS icon,
            COALESCE(si.price, 0)            AS price,
            COALESCE(si.energy_restore, 0)   AS energy_restore
        FROM tbl_user_foods f
        JOIN tbl_shop_items si ON si.item_id = f.item_id
        WHERE f.user_id = ?
    ";
    if ($stmt = $connection->prepare($sqlFoods)) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $type = strtolower(trim($row['item_type']));
            if ($type === 'accessory') $type = 'accessories';
            if ($type === 'wallpaper') $type = 'decorations';

            $inventory[] = [
                'id'             => (int)$row['item_id'],
                'name'           => $row['name'],
                'type'           => $type,
                'price'          => (int)$row['price'],
                'icon'           => $row['icon'],
                'energy_restore' => (int)$row['energy_restore'],
                'owned'          => (int)$row['quantity'],
                'equipped'       => false,
            ];
        }
        $stmt->close();
    }

    // --- ACCESSORIES (own once; maybe equipped) ---
    $sqlAcc = "
        SELECT 
            a.item_id,
            COALESCE(a.is_equipped, 0)        AS is_equipped,
            COALESCE(si.item_name, '')        AS name,
            COALESCE(si.item_type, 'accessory') AS item_type,
            COALESCE(si.image_url, '')        AS icon,
            COALESCE(si.accessory_image_url, '') AS accessory_image_url,
            COALESCE(si.price, 0)             AS price
        FROM tbl_user_accessories a
        JOIN tbl_shop_items si ON si.item_id = a.item_id
        WHERE a.user_id = ?
    ";

    if ($stmt = $connection->prepare($sqlAcc)) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $type = strtolower(trim($row['item_type']));
            if ($type === 'accessory') $type = 'accessories';
            if ($type === 'wallpaper') $type = 'decorations';

            $inventory[] = [
                'id'             => (int)$row['item_id'],
                'name'           => $row['name'],
                'type'           => $type,
                'price'          => (int)$row['price'],
                'icon'           => $row['icon'],
                'accessory_image_url' => $row['accessory_image_url'],
                'energy_restore' => null,
                'owned'          => 1,
                'equipped'       => (bool)$row['is_equipped'],
            ];
        }
        $stmt->close();
    }
}

include 'include/header.php';

// NO NAVBAR INCLUDED - Just the game

// Get user data if needed
if (isset($_SESSION['sUser'])) {
    $username = $_SESSION['sUser'];
}

/* ---- Expose server data to JS (for gakumonScript.js) ---- */
$serverData = [
    'user'      => ['id' => $userId, 'username' => $_SESSION['sUser'] ?? null],
    'currency'  => (int)$gakucoins,
    'pet'       => $petData ?: ['id' => null, 'energy' => 0, 'maxEnergy' => 100, 'imageUrl' => null, 'age' => 0, 'name' => ''],
    'shopItems' => $shopItems,
    'inventory' => $inventory,
    'endpoints' => [
        'buy'  => 'include/gakumonBuy.inc.php',
        'feed' => 'include/gakumonFeed.inc.php',
    ],
];
?>

<script>
  // one source of truth for data + device flag
  window.__GAKUMON_DATA__ = <?= json_encode($serverData, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
  window.__GAK_IS_MOBILE__ = <?= $isMobile ? 'true' : 'false' ?>;
</script>

<script src="<?= htmlspecialchars($pageJS, ENT_QUOTES) ?>" defer></script>

<script>
  // Never overwrite if something else already set it earlier
  window.serverData = window.serverData || {};

  // Endpoints (so fetch URLs are stable)
  window.serverData.endpoints = {
    buy:  'include/gakumonBuy.inc.php',
    feed: 'include/gakumonFeed.inc.php'
  };

  // Currency (tbl_user.gakucoins)
  window.serverData.currency = <?= (int)($gakucoins ?? 0) ?>;

  // Pet (tbl_user_pet joined to tbl_pet); fallback if none
  window.serverData.pet = <?= json_encode(
    $petData ?: ['name'=>'MOHI','type'=>'CHRIX','age'=>0,'energy'=>100,'maxEnergy'=>100,'imageUrl'=>null],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  ) ?>;

  // Shop items (from tbl_shop_items; types already normalized in your query)
  window.serverData.shopItems = <?= json_encode(
    $shopItems ?? [],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  ) ?>;

  // Inventory (foods/accessories/wallpapers joined to tbl_shop_items)
  window.serverData.inventory = <?= json_encode(
    $inventory ?? [],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  ) ?>;

  // (Optional tiny sanity log—remove if you like)
  // console.log('serverData', window.serverData);
</script>

<script>
// Compat shim: unify userId so getEquipStorageKey() uses the real ID (not 'anon')
(function () {
  // Prefer already-set objects
  const g = (window.__GAKUMON_DATA__ = window.__GAKUMON_DATA__ || {});
  const s = (window.serverData     = window.serverData     || {});

  // Pull id from whichever place you already populate
  const idFromData =
    s.userId ||
    g.userId ||
    (s.user && s.user.id) ||
    (g.user && g.user.id);

  if (idFromData != null && idFromData !== '') {
    // Set both so all scripts agree on the same key
    s.userId = idFromData;
    g.userId = idFromData;
  }

  // Optional: if localStorage is empty but DB says some items are equipped,
  // seed LS once so reloads are consistent on mobile.
  try {
    const petType = (s.pet && s.pet.type) || (g.pet && g.pet.type) || 'pet';
    const key = `gaku_equipped_${idFromData || 'anon'}_${petType}`;
    if (!localStorage.getItem(key)) {
      const inv = (s.inventory || g.inventory || []);
      const equippedIds = inv
        .filter(i => (String(i.type).toLowerCase() === 'accessories' || String(i.type).toLowerCase() === 'decorations') && i.equipped)
        .map(i => Number(i.id));
      if (equippedIds.length) {
        localStorage.setItem(key, JSON.stringify(equippedIds));
      }
    }
  } catch {}
})();
</script>


<!-- IMPORTANT: this must come AFTER the block above -->
<script src="<?= htmlspecialchars($pageJS, ENT_QUOTES) ?>"></script>

<!-- Main layout - Full screen game without navbar -->
<div class="game-fullscreen">
    <!-- Game Container -->
    <div class="game-container">
        <!-- Header - Stacked vertically -->
        <div class="game-header">
            <div class="header-top">
                <button class="back-button" onclick="window.history.back()">
                    < &nbsp; Back
                </button>
            </div>
            <div class="header-bottom">
                <div class="currency-display">
                    <span id="currencyAmount">1,247</span> Gakucoins
                </div>
            </div>
        </div>

        <!-- Floating Pet Info Box -->
        <div class="floating-pet-info">
            <div class="pet-header">
                <div class="pet-info">
                    <div class="pet-type">Virtual Pet</div>
                    <div class="pet-name" id="petName">MOHI</div>
                    <div class="pet-stats">
                        <div class="stat-item"><span id="petAge">16</span> DAYS</div>
                    </div>
                </div>
            </div>
            <div class="energy-bar-container">
                <div class="energy-label">
                    <div class="gakumonEnergy">GAKUMON ENERGY</div>
                </div>
                <div class="energy-bar">
                    <div class="energy-fill" id="energyFill"></div>
                </div>
            </div>
        </div>

        <!-- Main Game Area -->
        <div class="game-main">
            <!-- Room Environment -->
            <div class="room-environment">
                <!-- Left Side - Sofa -->
                <div class="room-item sofa">
                    
                </div>
                
                <!-- Center - Window -->
                <div class="room-item window">
                    
                </div>
                
                <!-- Right Side - Shelf and Plant -->
                <div class="room-item shelf">
                    
                </div>
                <div class="room-item plant">
                    
                </div>
                
                <!-- Decoration Layers -->
                <div id="decorationLayers">
                    <!-- Decorations will be dynamically inserted here -->
                </div>
            </div>

            <!-- Center Pet Display -->
            <div class="pet-display-area">
                <div class="pet-container">
                    <div class="pet-image" id="petImage">
                        <!-- Base Pet -->
                        <img src="IMG/Pets/Mochi.PNG" alt="Your Pet" class="pet-base">
                        
                        <!-- Accessory Layers -->
                        <div id="accessoryLayers">
                            <!-- Accessories will be dynamically inserted here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Bar -->
        <div class="items-bar-section">
            <div class="items-bar-container">
                <div class="items-bar-header">
                    <h3></h3>
                        <button class="shop-button" id="openShop">
                            <img src="IMG/GameAssets/shop.png" alt="Store">
                        </button>
                </div>

                <!-- Category Tabs -->
                <div class="category-tabs">
                    <button class="category-tab active" data-category="food">
                        <img src="IMG/GameAssets/1.png" alt="Food" class="tab-icon">
                    </button>
                    <button class="category-tab" data-category="accessories">
                        <img src="IMG/GameAssets/2.png" alt="Accessories" class="tab-icon">
                    </button>
                </div>

                <!-- Scrollable Items Area -->
                <div class="items-scrollable" id="itemsScrollable">
                    <!-- Items populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Shop Modal -->
<div class="modal-overlay" id="shopModal">
    <div class="modal-content large-modal">
        <div class="modal-header">
            <img src="IMG/GameAssets/GAKUSTORE.png" alt="Gaku Store Sign" class="gakustore">
            <button class="close-modal" id="closeShop">×</button>
        </div>

        <!-- Shop Category Tabs -->
        <div class="shop-category-tabs">
            <button class="shop-category-tab active" data-shop-category="all">
                <img src="IMG/GameAssets/ALL.png" alt="All Items" class="shop-tab-icon">
            </button>
            <button class="shop-category-tab" data-shop-category="food">
                <img src="IMG/GameAssets/1.png" alt="Food" class="shop-tab-icon">
            </button>
            <button class="shop-category-tab" data-shop-category="accessories">
                <img src="IMG/GameAssets/2.png" alt="Accessories" class="shop-tab-icon">
            </button>
        </div>

        <div class="shop-items-grid" id="shopItemsGrid">
            <!-- Shop items populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Background music (optional) -->
<audio id="pageLoadSound" preload="auto" loop>
    <source src="IMG/GameAssets/bgSound.mp3" type="audio/mpeg">
</audio>

<!-- Click sound -->
<audio id="clickSound" preload="auto">
    <source src="IMG/GameAssets/cl.mp3" type="audio/mpeg">
</audio>

<script>
    // Pass PHP data to JS if needed
    window.serverData = <?= json_encode($serverData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    // Click sound functionality
    document.addEventListener('DOMContentLoaded', function() {
        const clickSound = document.getElementById('clickSound');
        
        // Play click sound on any click
        document.addEventListener('click', function(event) {
            if (event.target.closest('.no-click-sound')) {
                return;
            }
            
            clickSound.currentTime = 0;
            clickSound.play().catch(error => {
                console.log('Click sound failed:', error);
            });
        });
        
        const bgMusic = document.getElementById('pageLoadSound');
        let musicStarted = false;
        
        document.addEventListener('click', function startMusicOnce() {
            if (!musicStarted && bgMusic) {
                bgMusic.loop = true;
                bgMusic.play().then(() => {
                    musicStarted = true;
                    console.log('Background music started');
                }).catch(error => {
                    console.log('Background music failed:', error);
                });
                document.removeEventListener('click', startMusicOnce);
            }
        });
    });
</script>

<?php include 'include/footer.php'; ?>