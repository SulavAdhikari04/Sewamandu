# Booking Location Feature — Implementation Guide

This document explains the Map / Pin Location feature added to Sewamandu: customers pick a service location when booking, and providers view that pin from their dashboard.

---

## 1. Goal

Mimic Pathao / inDrive-style location picking:

1. **Customer** can search a place, use device GPS, or tap/drag a pin on a map.
2. That location is **stored with the booking**.
3. **Provider** can open **See location** on Manage Bookings and Bookings — the map appears in an **in-page modal** (same idea as View Profile on the book page), not a new browser tab.

Everything uses **free** tools (no Google Maps billing, no Mapbox key).

---

## 2. Stack (zero paid APIs)

| Piece | Technology | Role |
|--------|------------|------|
| Map UI | [Leaflet.js](https://leafletjs.com/) (CDN) | Render map, markers, clicks, drag |
| Map tiles | OpenStreetMap | Free map imagery |
| Device location | Browser `navigator.geolocation` | “Use my location” button |
| Place search / reverse geocode | [Nominatim](https://nominatim.openstreetmap.org/) (via PHP proxy) | Typeahead search + address from coordinates |
| Optional navigation | Google Maps deep link | `https://www.google.com/maps?q=lat,lng` from the modal only |

---

## 3. Database changes

### Columns added to `bookings`

| Column | Type | Purpose |
|--------|------|---------|
| `latitude` | `DECIMAL(10,7) NULL` | Pin latitude |
| `longitude` | `DECIMAL(10,7) NULL` | Pin longitude |
| `location_label` | `VARCHAR(255) NULL` | Human-readable place from search/reverse geocode (e.g. “Baneshwor, Kathmandu”) |

Existing `address` is still used for **extra details** (landmarks, flat, gate notes). It is not the map pin itself.

### Why columns on `bookings` (not a new table)?

Each booking is a one-off job location. A separate `locations` table would add joins and complexity without benefit for this requirement.

### How columns are created

Helper in `components/BookingStatus.php`:

- `ensureBookingLocationColumns($conn)` — runs `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` for the three columns.
- Called from `book-service.php` and `provider-dashboard.php` on page load so existing DBs get the columns automatically.

Validation helper:

- `parseBookingCoordinates($latitude, $longitude)` — checks numeric values and ranges (`lat` −90…90, `lng` −180…180). Returns `null` if invalid.

---

## 4. Customer booking flow (`pages/book-service.php`)

### UI (Step 1 — book form)

Replaced the old single required “Your Address” textarea-as-location with:

1. **Search box** — type an area/landmark; suggestions appear in a dropdown.
2. **GPS button** — asks for browser permission and drops the pin at the device location.
3. **Map** (`#booking-map`) — tap to place pin; drag pin to fine-tune.
4. **Selected location** line — shows the current label or coordinates.
5. **Hidden fields** — `latitude`, `longitude`, `location_label` submitted with the form.
6. **Address details** — optional-ish textarea for gate/floor notes (no longer the only location source).

Default map center: **Kathmandu** (`27.7172, 85.3240`).

### Server-side (Step 1 submit — `show_providers`)

- Reads `latitude`, `longitude`, `location_label`, `address`.
- If coordinates are missing/invalid → error: ask the user to pin a location; stay on form.
- If valid → continue to provider list as before.

### Server-side (Step 2 submit — `book_with_provider`)

- Hidden fields carry `latitude`, `longitude`, `location_label`, `address` into the confirm step.
- Insert into `bookings` includes those columns along with the existing booking fields.
- UI also shows a short **location confirmation** line above the provider cards.

### Nominatim proxies (same page, AJAX)

Browsers should not call Nominatim directly with a proper User-Agent, so PHP proxies the requests:

| Endpoint | Query | Behavior |
|----------|--------|----------|
| `book-service.php?ajax=place_search&q=...` | Search text (min 2 chars) | Returns JSON list of `{ label, lat, lng }`, limited to Nepal (`countrycodes=np`) |
| `book-service.php?ajax=reverse_geocode&lat=...&lng=...` | Coordinates | Returns `{ label }` display name for the pin |

`nominatimRequest($url)` uses cURL when available, otherwise `file_get_contents` with a User-Agent header: `Sewamandu/1.0 (local booking app)`.

### Front-end map script (book page)

- Initializes Leaflet when `#booking-map` exists.
- Debounced search (~350 ms) → place_search → click suggestion → move pin.
- Map click / marker drag → update hidden lat/lng; reverse geocode fills label when appropriate.
- Form submit blocked if lat/lng empty.
- Profile modal script for **View Profile** is unchanged in behavior.

### Styles

Added in `css/booking.css`: `.location-picker`, search row, suggestions list, GPS button, `.booking-map`, hints, confirmation banner.

---

## 5. Provider dashboard (`pages/provider-dashboard.php`)

### Queries

Both booking lists now select location fields:

- **Manage Bookings** (`pending_provider`): `address`, `latitude`, `longitude`, `location_label`
- **Bookings** (all statuses for this provider): same fields

### “See location” button

Shown only when that row has lat/lng.

- Placed in **Actions** (Manage Bookings) next to Approve/Reject.
- Placed in a **Location** column (Bookings section).

Button data attributes:

- `data-lat`, `data-lng`, `data-label`, `data-address`

### Modal (in-page, not new tab)

Modeled after the book-service **View Profile** overlay:

- Backdrop + centered panel
- Close via ×, backdrop click, or Escape
- Title: “Service location”
- Label + optional address text
- Read-only Leaflet map with a fixed pin
- Link: **Open in Google Maps** (external navigation only; primary view stays on dashboard)

Map instance is reused; `invalidateSize()` runs after open so tiles size correctly inside the modal.

### Styles

Added in `css/provider-dashboard.css`: `.see-location-btn`, `.location-modal*`, map height, Google Maps link.

Leaflet CSS/JS loaded from unpkg CDN in the provider dashboard `<head>`.

---

## 6. End-to-end flow

```
Customer opens book-service.php
        │
        ├─ Search place ──► PHP Nominatim proxy ──► pick suggestion
        ├─ Use GPS ───────► browser geolocation
        └─ Tap / drag map ─► pin + optional reverse geocode
        │
        ▼
  Hidden: latitude, longitude, location_label
  Text: address details
        │
        ▼
  Show providers → Confirm booking
        │
        ▼
  INSERT INTO bookings (... address, latitude, longitude, location_label ...)
        │
        ▼
Provider dashboard → Manage Bookings / Bookings
        │
        └─ See location → modal with Leaflet pin (+ optional Google Maps link)
```

---

## 7. Files touched

| File | Change |
|------|--------|
| `components/BookingStatus.php` | `ensureBookingLocationColumns()`, `parseBookingCoordinates()` |
| `pages/book-service.php` | Map UI, proxies, validation, INSERT with coords |
| `pages/provider-dashboard.php` | SELECT location fields, See location buttons, modal + JS |
| `css/booking.css` | Customer location picker styles |
| `css/provider-dashboard.css` | See location button + modal styles |

No paid API keys or config files were required.

---

## 8. Behaviour notes & limits

1. **HTTPS / localhost** — Browsers allow geolocation on secure contexts; localhost is fine for local XAMPP testing.
2. **Older bookings** — Rows booked before this feature have `NULL` lat/lng; **See location** is hidden / shows “No pin”.
3. **Nominatim** — Free and rate-limited; search is Nepal-biased (`countrycodes=np`). Heavy production use may need a self-hosted Nominatim or another free geocoder later.
4. **Tiles** — OSM public tiles are fine for moderate traffic; scale later with another free tile provider if needed (Leaflet config change only).
5. **Not included** — Live tracking, turn-by-turn inside the app, provider–customer distance matching, saved “Home/Work” places.

---

## 9. How to verify manually

1. Log in as a **customer** → Book a Service.
2. Search for a Kathmandu area or click **GPS** / tap the map.
3. Optionally add address details → Show Providers → Confirm Booking.
4. Log in as that **provider** → Manage Bookings / Bookings.
5. Click **See location** → modal should open with the pin; close with × or Escape.
6. Optionally click **Open in Google Maps** to confirm the deep link.

---

## 10. Related UX decision

**See location** deliberately uses an overlay modal like **View Profile** on `book-service.php`, so the provider stays on the dashboard and does not navigate away to a new tab for the primary map view.
