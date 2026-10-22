import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

/**
 * Interactive pin picker for customer location.
 * Reads JSON from #customer-map-config. Fails soft when tiles/config missing.
 */
export function initCustomerLocationMap() {
    const root = document.getElementById('customer-location-map');
    const configEl = document.getElementById('customer-map-config');
    if (!root || !configEl) {
        return;
    }

    let config;
    try {
        config = JSON.parse(configEl.textContent || '{}');
    } catch {
        showMapFallback(root, 'تعذر قراءة إعدادات الخريطة. استخدم حقول الإحداثيات يدويًا.');
        return;
    }

    if (!config.enabled) {
        showMapFallback(root, 'الخريطة التفاعلية متوقفة. استخدم العنوان والإحداثيات يدويًا.');
        return;
    }

    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const statusEl = document.getElementById('customer-map-status');

    const hasSaved =
        Number.isFinite(Number(config.lat)) && Number.isFinite(Number(config.lng));
    const center = hasSaved
        ? [Number(config.lat), Number(config.lng)]
        : [
              Number(config.defaultCenter?.lat ?? 24.7136),
              Number(config.defaultCenter?.lng ?? 46.6753),
          ];
    const zoom = hasSaved ? Math.max(Number(config.defaultZoom) || 11, 14) : Number(config.defaultZoom) || 11;

    let map;
    try {
        map = L.map(root, {
            zoomControl: true,
            attributionControl: true,
        }).setView(center, zoom);

        const layer = L.tileLayer(config.tileUrl, {
            attribution: config.attribution || '',
            maxZoom: Number(config.maxZoom) || 19,
        });

        layer.on('tileerror', () => {
            if (statusEl) {
                statusEl.textContent =
                    'تعذر تحميل بلاطات الخريطة. يمكنك إدخال الإحداثيات يدويًا.';
                statusEl.hidden = false;
            }
        });

        layer.addTo(map);
    } catch {
        showMapFallback(root, 'تعذر تهيئة الخريطة. استخدم حقول الإحداثيات يدويًا.');
        return;
    }

    let marker = null;
    if (hasSaved) {
        marker = L.marker(center).addTo(map);
    }

    const placeMarker = (lat, lng) => {
        const latLng = L.latLng(lat, lng);
        if (marker) {
            marker.setLatLng(latLng);
        } else {
            marker = L.marker(latLng).addTo(map);
        }
        map.panTo(latLng);
        if (latInput) latInput.value = Number(lat).toFixed(7);
        if (lngInput) lngInput.value = Number(lng).toFixed(7);
    };

    if (config.interactive) {
        map.on('click', (event) => {
            placeMarker(event.latlng.lat, event.latlng.lng);
        });

        const syncFromInputs = () => {
            const lat = Number(latInput?.value);
            const lng = Number(lngInput?.value);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }
            if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                return;
            }
            placeMarker(lat, lng);
        };

        latInput?.addEventListener('change', syncFromInputs);
        lngInput?.addEventListener('change', syncFromInputs);
    }

    // Leaflet needs a resize after layout (RTL sidebar / mobile stack).
    const refreshSize = () => map.invalidateSize();
    requestAnimationFrame(refreshSize);
    window.addEventListener('resize', refreshSize, { passive: true });
}

function showMapFallback(root, message) {
    root.replaceChildren();
    const note = document.createElement('p');
    note.className = 'customer-map-fallback';
    note.setAttribute('role', 'status');
    note.textContent = message;
    root.appendChild(note);

    const statusEl = document.getElementById('customer-map-status');
    if (statusEl) {
        statusEl.textContent = message;
        statusEl.hidden = false;
    }
}
