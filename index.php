<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EV Charger Explorer</title>
<link rel="stylesheet" href="./assets/css/style.css">
<link rel="stylesheet" href="./assets/fontawesome/css/all.min.css"> 
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <div class="top-header">
        <h1><i class="fas fa-bolt"></i> EV Charger Explorer</h1>
        <p>Locate charging points for electric vehicles</p>
    </div>
    <div class="query-section" style="position:relative;margin-bottom:10px;">
        <div class="query-input" style="display:inline-block;width:60%;position:relative;">
            <input type="text" id="place-query" placeholder="Look for a place..." style="width:100%;padding:8px">
            <div class="query-dropdown" style="z-index:9999;display:none;" id="place-dropdown"></div>
        </div>
        <button class="control-btn main-btn" id="query-btn" style="padding:8px 12px;margin-left:8px;"><i class="fas fa-magnifying-glass"></i> Locate</button>
        <button class="control-btn alt-btn" id="pos-btn" style="padding:8px 12px;margin-left:8px;"><i class="fas fa-location-dot"></i> My Position</button>
    </div>
    <div class="main-layout">
        <div class="chargers-panel">
            <h2 style="text-align:center;">Charging Points</h2>
            <!-- Counter will be inserted above -->
            <div id="chargers-area">
                <div class="progress-msg" style="color:#4299e1;"><i class="fas fa-spinner fa-spin"></i> Loading points...</div>
            </div>
        </div>
        <div class="geo-view"><div id="geo-map"></div></div>
    </div>
    <div class="bottom-note" style="margin-top:12px">
        <p>EV Charger Explorer &copy; <span id="current-year"></span></p>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.getElementById('current-year').textContent = new Date().getFullYear();

const defaultLat = 31.5204, defaultLon = 74.3587; // Lahore
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

// Counter element above stations list
const counterDiv = document.createElement('div');
counterDiv.style.padding = '10px 12px';
counterDiv.style.fontWeight = '600';
counterDiv.style.color = '#4299e1';
stationsList.parentNode.insertBefore(counterDiv, stationsList);

function showLoading(msg = 'Loading...') {
    counterDiv.textContent = '';
    stationsList.innerHTML = `<div class="progress-msg"><i class="fas fa-spinner fa-spin"></i> ${msg}</div>`;
}
function showError(msg) {
    counterDiv.textContent = '';
    stationsList.innerHTML = `<div class="progress-msg" style="color:#4299e1">${msg}</div>`;
}

// ===== Load stations function =====
async function loadStations(lat, lon, cityName='Location') {
    showLoading(`Loading stations near ${cityName}...`);
    markersLayer.clearLayers();

    try {
        const res = await fetch(`proxy.php?action=load_stations&lat=${lat}&lon=${lon}&distance=50&maxresults=50`);
        if(!res.ok) throw new Error('Network error');

        const stations = await res.json();
        console.log(stations);

        if(!Array.isArray(stations) || stations.length === 0) {
            showError(`No stations found near ${cityName}`);
            return;
        }

        // Show total stations
        counterDiv.textContent = `${stations.length} stations found in ${cityName} (50 km Range)`;

        stationsList.innerHTML = '';

        stations.forEach(station => {
            const div = document.createElement('div');
            div.className = 'charger-card';
            div.innerHTML = `
                <div class="charger-heading">${station.title}</div>
                <div class="charger-loc">${station.address}</div>
                <div class="charger-stats">
                    <span>${station.distance ?? 'N/A'} km</span>
                    <span style="float:right">${station.state === 'ready' ? 'Ready' : 'In Use'}</span>
                </div>`;
            div.addEventListener('click', () => {
                map.flyTo([station.lat, station.lon], 15);
                revealPopupForStation(station);
            });
            stationsList.appendChild(div);

            if(station.lat && station.lon){
                const marker = L.marker([station.lat, station.lon], {icon: defaultIcon})
                    .addTo(markersLayer)
                    .bindPopup(`<div><h3>${station.title}</h3><p>${station.address}</p><p>${station.distance ?? ''} km</p></div>`);
                marker._stationId = station.id;
            }
        });
    } catch(e) {
        console.error(e);
        showError('Failed to load stations');
    }
}

function revealPopupForStation(station) {
    markersLayer.eachLayer(layer => {
        if(layer._stationId && layer._stationId === station.id) layer.openPopup();
    });
}

// ===== Search suggestions =====
searchInput.addEventListener('input', async function(){
    const query = this.value.trim();
    if(!query){ suggestionsDiv.style.display='none'; return; }

    try {
        const res = await fetch(`proxy.php?action=search&query=${encodeURIComponent(query)}`);
        const data = await res.json();
        if(!Array.isArray(data) || data.length === 0){ suggestionsDiv.style.display='none'; return; }

        suggestionsDiv.innerHTML = '';
        data.forEach(item => {
            const div = document.createElement('div');
            div.className = 'dropdown-option';
            div.textContent = item.display_name.split(',')[0];
            div.addEventListener('click', () => {
                const lat = parseFloat(item.lat), lon = parseFloat(item.lon);
                map.setView([lat, lon], 13);
                searchInput.value = item.display_name.split(',')[0];
                suggestionsDiv.style.display = 'none';
                loadStations(lat, lon, searchInput.value);
            });
            suggestionsDiv.appendChild(div);
        });
        suggestionsDiv.style.display = 'block';
    } catch(e){
        console.error(e);
        suggestionsDiv.style.display = 'none';
    }
});

// ===== Search button click =====
searchBtn.addEventListener('click', () => {
    const first = suggestionsDiv.querySelector('.dropdown-option');
    if(first) first.click();
});

// ===== Current position =====
posBtn.addEventListener('click', () => {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser.');
        return;
    }

    // Only allow HTTPS or localhost
    if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
        alert('Current location only works on HTTPS or localhost.');
        return;
    }

    navigator.geolocation.getCurrentPosition(pos => {
        const { latitude, longitude } = pos.coords;
        L.marker([latitude, longitude], { icon: defaultIcon })
            .addTo(markersLayer)
            .bindPopup('<b>My Current Location</b>').openPopup();
        map.setView([latitude, longitude], 14);
        loadStations(latitude, longitude, 'My Position');
    }, err => {
        alert('Failed to get location. Showing default city.');
        map.setView([defaultLat, defaultLon], 12);
        loadStations(defaultLat, defaultLon, 'Lahore');
    }, { enableHighAccuracy: true, timeout: 10000 });
});

// ===== Initial load =====
loadStations(defaultLat, defaultLon, "Lahore");
</script>
</body>
</html>
