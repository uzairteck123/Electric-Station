<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EV Charger Explorer</title>
<!-- Css link -->
<link rel="stylesheet" href="./assets/css/style.css">
<!-- FontAwesome Css -->
<link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
</head>

<body>
<div class="wrapper">
    <div class="top-header">
        <h1><i class="fas fa-bolt"></i> EV Charger Explorer</h1>
        <p>Locate charging points for electric vehicles worldwide</p>
    </div>
    <div class="query-section">
        <div class="query-input">
            <input type="text" id="place-query" placeholder="Look for a place...">
            <div class="query-dropdown" style="z-index:9999;" id="place-dropdown"></div>
        </div>
        <button class="control-btn main-btn" id="query-btn"><i class="fas fa-magnifying-glass"></i> Locate</button>
        <button class="control-btn alt-btn" id="pos-btn"><i class="fas fa-location-dot"></i> My Position</button>
    </div>
    <div class="main-layout">
        <div class="chargers-panel">
            <h2 style="text-align:center;">Charging Points</h2>
            <div id="chargers-area">
                <div class="progress-msg"><i class="fas fa-spinner fa-spin"></i> Loading points...</div>
            </div>
        </div>
        <div class="geo-view"><div id="geo-map"></div></div>
    </div>
    <div class="info-overlay" id="charger-info">
        <div class="overlay-box">
            <span class="dismiss-overlay" id="hide-info">&times;</span>
            <div class="overlay-top">
                <h2 id="info-charger-heading">Point Heading</h2>
                <span class="charger-state state-ready" id="info-charger-state">Ready</span>
            </div>
            <div class="charger-desc">
                <p><i class="fas fa-map-pin"></i> <span id="info-charger-loc">Sample Loc, Area</span></p>
                <p><i class="fas fa-ruler-combined"></i> <span id="info-charger-range">2.5 km away</span></p>
                <p><i class="fas fa-clock"></i> <span id="info-charger-schedule">Open 24/7</span></p>
                <p><i class="fas fa-dollar-sign"></i> <span id="info-charger-fee">$0.28/kWh</span></p>
                <div class="ports-area">
                    <h3>Port Variants</h3>
                    <div id="info-ports"></div>
                </div>
                <div class="controls-area">
                    <button class="overlay-control guide-control"><i class="fas fa-compass"></i> Guide Me</button>
                    <button class="overlay-control store-control"><i class="far fa-heart"></i> Store Point</button>
                </div>
            </div>
        </div>
    </div>
    <div class="bottom-note">
        <p>EV Charger Explorer &copy; <span id="current-year"></span>  | Explore EV charging options</p>
    </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>

(function() {
  const THRESHOLD = 160; // heuristic for docked devtools
  let devtoolsOpen = false;
  let notified = false;

  function createOverlay() {
    if (document.getElementById('anti-inspect-overlay')) return;
    const o = document.createElement('div');
    o.id = 'anti-inspect-overlay';
    Object.assign(o.style, {
      position: 'fixed',
      inset: '0',
      background: 'rgba(0,0,0,0.97)',
      color: '#fff',
      zIndex: 2147483647,
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      textAlign: 'center',
      padding: '20px',
      fontFamily: 'system-ui, Arial, sans-serif',
      fontSize: '18px'
    });
    o.innerHTML = '<div style="max-width:720px"><strong>⚠️ Developer tools detected</strong><br>Your access to this content is blocked for security reasons.</div>';
    document.body.appendChild(o);
    // prevent interaction with the page behind the overlay
    document.body.style.pointerEvents = 'none';
    o.style.pointerEvents = 'auto';
    // try to prevent selection & right-click on overlay
    o.addEventListener('contextmenu', e => e.preventDefault());
  }

  function removeOverlay() {
    const el = document.getElementById('anti-inspect-overlay');
    if (el) el.remove();
    document.body.style.pointerEvents = '';
  }

  function handleOpen() {
    if (devtoolsOpen) return;
    devtoolsOpen = true;
    createOverlay();
    // optional: you could send a beacon/fetch to server here
    // if (!notified) { navigator.sendBeacon('/devtools_detected', JSON.stringify({ ts: Date.now() })); notified = true; }
  }
  function handleClose() {
    if (!devtoolsOpen) return;
    devtoolsOpen = false;
    removeOverlay();
  }

  // 1) Resize heuristic (works when devtools are docked)
  setInterval(function() {
    try {
      const opened = (window.outerWidth - window.innerWidth > THRESHOLD) ||
                     (window.outerHeight - window.innerHeight > THRESHOLD);
      if (opened) handleOpen(); else handleClose();
    } catch (e) { /* ignore */ }
  }, 500);

  // 2) Debugger timing heuristic (pauses/time-slow if devtools open)
  (function cycDebug() {
    const start = Date.now();
    // this statement may pause when devtools with "pause on exceptions" or debugger is active
    debugger;
    const dt = Date.now() - start;
    if (dt > 100) handleOpen();
    setTimeout(cycDebug, 3000);
  })();

  // 3) Console getter trick (fires when object is inspected in console)
  try {
    const detector = {};
    Object.defineProperty(detector, 'toString', {
      configurable: true,
      get: function() { handleOpen(); return function(){}; }
    });
    // harmless console.log that may trigger getter if console open/inspected
    console.log('%c', detector);
  } catch (e) { /* ignore */ }

  // 4) Block common shortcuts and right-click (deterrent)
  document.addEventListener('contextmenu', e => e.preventDefault());
  document.addEventListener('keydown', function(e) {
    // F12, Ctrl+Shift+I/J, Ctrl+U
    if (e.keyCode === 123 || // F12
       (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74)) || // Ctrl+Shift+I/J
       (e.ctrlKey && e.keyCode === 85) // Ctrl+U
    ) {
      e.preventDefault();
      e.stopPropagation();
    }
    // try to block ESC (27) and other keys if you want (use carefully)
  }, true);

  // extra: small protection for detached devtools — check for console open by measuring width/height change on window
  window.addEventListener('resize', function() {
    const opened = (window.outerWidth - window.innerWidth > THRESHOLD) ||
                   (window.outerHeight - window.innerHeight > THRESHOLD);
    if (opened) handleOpen(); else handleClose();
  });

  // cleanup when page is hidden (optional)
  document.addEventListener('visibilitychange', function() {
    if (document.hidden) { /* you could clear timers here if desired */ }
  });
})();
    /*
    ************************************************************
    🚫 WARNING 🚫
    This script is property of EV Charger Explorer Project.
    Unauthorized copying or usage is not allowed.
    © 2025 | All Rights Reserved.
    ************************************************************
    */
       document.onkeydown = function(e) {
        if (e.ctrlKey && (e.key === "u" || e.key === "U")) {
            e.preventDefault();
        }
        if (e.keyCode === 123) { // F12
            e.preventDefault();
        }
        if (e.ctrlKey && e.shiftKey && (e.key === "i" || e.key === "I")) {
            e.preventDefault();
        }
    };
    // Current Date
    document.getElementById('current-year').textContent = new Date().getFullYear();
    // ===== Map Setup =====
    const defaultLat = 31.5204, defaultLon = 74.3587; // Lahore default
    const map = L.map('geo-map').setView([defaultLat, defaultLon], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    const markersLayer = L.layerGroup().addTo(map);
    const defaultIcon = L.icon({
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        iconSize: [25,41],
        iconAnchor: [12,41],
        popupAnchor: [1,-34]
    });
    const searchInput = document.getElementById('place-query');
    const suggestionsDiv = document.getElementById('place-dropdown');
    const stationsList = document.getElementById('chargers-area');
    const searchBtn = document.getElementById('query-btn');
    const posBtn = document.getElementById('pos-btn');
    const chargerInfo = document.getElementById('charger-info');
    const hideInfo = document.getElementById('hide-info');
    let userMarker; // For current location
    // ===== Search Suggestions =====
    searchInput.addEventListener('input', async () => {
        let query = searchInput.value.trim();
        if(query.length < 2){ suggestionsDiv.style.display='none'; return;}
        try {
            const res = await fetch(`proxy.php?action=suggest&query=${encodeURIComponent(query)}`);
            if(!res.ok) throw new Error('Network error');
            const data = await res.json();
            const uniqueNames = [...new Set(data.map(i=>i.display_name))];
            suggestionsDiv.innerHTML = uniqueNames.slice(0,5).map(item=>{
                const poi = data.find(d=>d.display_name===item);
                const shortName = item.split(',').slice(0,2).join(', ');
                return `<div class="dropdown-option" onclick="selectSuggestion('${item}',${poi.lat},${poi.lon})">${shortName}</div>`;
            }).join('');
            suggestionsDiv.style.display = uniqueNames.length ? 'block' : 'none';
        } catch(e) { console.error(e); suggestionsDiv.style.display='none'; }
    });
    window.selectSuggestion = function(name, lat, lon){
        searchInput.value = name.split(',')[0].trim();
        suggestionsDiv.style.display='none';
        map.setView([lat, lon],13);
        loadStations(lat, lon, name.split(',')[0].trim());
    }
    // ===== Load Stations =====
    async function loadStations(lat, lon, searchName='Location'){
        stationsList.innerHTML = '<div class="progress-msg"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        markersLayer.clearLayers();
        try {
            const res = await fetch(`proxy.php?action=load_stations&lat=${lat}&lon=${lon}&city=${encodeURIComponent(searchName)}`);
            const stations = await res.json();
            if(!stations.length){ stationsList.innerHTML=`<p class="error">No EV stations near ${searchName}</p>`; return;}
            stationsList.innerHTML='';
            stations.forEach(station=>{
                const div = document.createElement('div');
                div.className='charger-card';
                div.innerHTML=`<div class="charger-heading">${station.title}</div>
                            <div class="charger-loc">${station.address}</div>
                            <div class="charger-stats">
                                <span>${station.distance} km</span>
                                <span class="charger-state ${station.state==='ready'?'state-ready':'state-inuse'}">${station.state==='ready'?'Ready':'In Use'}</span>
                            </div>`;
                div.addEventListener('click', ()=>{ map.flyTo([station.lat, station.lon],15); revealCharger(station); });
                stationsList.appendChild(div);
                L.marker([station.lat, station.lon],{icon:defaultIcon}).addTo(markersLayer)
                .bindPopup(`<div><h3>${station.title}</h3><p>${station.address}</p></div>`);
            });
        } catch(e){ console.error(e); stationsList.innerHTML=`<p class="error">Failed to load stations</p>`;}
    }
    // ===== Charger Overlay =====
    window.revealCharger = function(ch){
        document.getElementById('info-charger-heading').textContent=ch.title;
        document.getElementById('info-charger-loc').textContent=ch.address;
        document.getElementById('info-charger-range').textContent=ch.distance+' km';
        document.getElementById('info-charger-schedule').textContent=ch.schedule;
        document.getElementById('info-charger-fee').textContent=ch.fee;
        const stateElem=document.getElementById('info-charger-state');
        stateElem.textContent = ch.state==='ready'?'Ready':'In Use';
        stateElem.className=`charger-state ${ch.state==='ready'?'state-ready':'state-inuse'}`;
        const portsElem=document.getElementById('info-ports');
        portsElem.innerHTML='';
        (ch.ports||[]).forEach(p=>{
            const entry = document.createElement('div');
            entry.className='port-entry';
            entry.innerHTML=`<div><strong>${p.type}</strong><br><small>${p.speed}</small></div><div><span>${p.avail}/${p.total} ready</span></div>`;
            portsElem.appendChild(entry);
        });
        chargerInfo.style.display='flex';
    }
    // ===== Search Button =====
    searchBtn.addEventListener('click', searchLocation);
    searchInput.addEventListener('keypress',e=>{if(e.key==='Enter') searchLocation();});
    async function searchLocation(){
        let query = searchInput.value.trim();
        if(!query){ alert('Enter a city or area!'); return;}
        try {
            const res = await fetch(`proxy.php?action=search&query=${encodeURIComponent(query)}`);
            const data = await res.json();
            if(data.length){
                const {lat,lon,display_name} = data[0];
                map.setView([lat,lon],13);
                searchInput.value = display_name.split(',')[0].trim();
                suggestionsDiv.style.display='none';
                loadStations(lat,lon,display_name.split(',')[0].trim());
            } else { alert('Location not found!'); }
        } catch(e){ console.error(e); alert('Failed to search location'); }
    }
    // ===== My Current Location =====
    posBtn.addEventListener('click', ()=>{
        if(!navigator.geolocation){ alert("Geolocation not supported"); return; }
        navigator.geolocation.getCurrentPosition(
            position => {
                const { latitude, longitude } = position.coords;
                // Remove old marker
                if(userMarker) markersLayer.removeLayer(userMarker);
                // Add new marker
                userMarker = L.marker([latitude, longitude], {icon:defaultIcon})
                    .addTo(markersLayer)
                    .bindPopup('<b>My Current Location</b>').openPopup();

                // Center map
                map.setView([latitude, longitude], 15);

                // Load nearby stations
                loadStations(latitude, longitude, "My Position");
            },
            err => alert("Failed to get location: "+err.message),
            { enableHighAccuracy:true, timeout:10000, maximumAge:0 }
        );
    });
    // ===== Hide Overlay =====
    hideInfo.addEventListener('click',()=>{chargerInfo.style.display='none';});
    window.addEventListener('click',e=>{if(e.target===chargerInfo) chargerInfo.style.display='none';});
    // ===== Default Load =====
    loadStations(defaultLat, defaultLon, "Lahore");
</script>
</body>
</html>
