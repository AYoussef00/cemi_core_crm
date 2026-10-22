# Customer map (location pin picker)

**Phase:** 2 — Prompt 3  
**Library:** [Leaflet](https://leafletjs.com/) (npm `leaflet`) — lightweight, no API key required for default tiles.

## Defaults

| Setting | Env | Default |
|---|---|---|
| Enable interactive map | `MAPS_ENABLED` | `true` |
| Tile URL (XYZ) | `MAPS_TILE_URL` | OpenStreetMap `https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png` |
| Attribution HTML | `MAPS_ATTRIBUTION` | OSM copyright link |
| Default center | `MAPS_DEFAULT_LAT` / `MAPS_DEFAULT_LNG` | Riyadh ≈ 24.7136, 46.6753 |
| Zoom | `MAPS_DEFAULT_ZOOM` / `MAPS_MAX_ZOOM` | 11 / 19 |

Config file: `config/maps.php`.

## Behaviour

- Click map → fills latitude/longitude inputs (when user can update).
- Manual lat/lng entry always available (accessibility + offline tiles).
- **No geocoding** — addresses are never sent to a third-party geocode API.
- If `MAPS_ENABLED=false` or Leaflet/tiles fail, UI shows a fallback message and keeps the form.

## Credentials

Do not commit paid provider keys. If you switch tile URL to a keyed provider, put the key only in `.env` (never in Blade/JS source in the repo).

## OSM usage note

OpenStreetMap tile usage has [fair-use](https://operations.osmfoundation.org/policies/tiles/) expectations for production. For heavy traffic, point `MAPS_TILE_URL` at your own tile proxy or another free/configured provider.
