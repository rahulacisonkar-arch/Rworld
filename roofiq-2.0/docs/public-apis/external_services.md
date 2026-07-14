# Public APIs Integrations

The system integrates public government registries and weather sensors to enrich property assessments.

---

## 1. Integrated Services

- **U.S. Census Geocoder API**: Conducts geocoding and returns County boundaries.
  - *URL*: `https://geocoding.geo.census.gov/geocoder/`
- **FEMA NFHL GIS API**: Determines flood zone classifications (Zone X, Zone A) using spatial intersection queries.
  - *URL*: `https://hazards.fema.gov/gis/nfhl/rest/services/`
- **Open-Meteo Weather API**: Retrieves local historical wind speeds and solar radiation summaries.
  - *URL*: `https://api.open-meteo.com/v1/`
- **NREL PVWatts v8 API**: Computes average monthly solar production yields.
  - *URL*: `https://developer.nrel.gov/api/pvwatts/`

---

## 2. Retry Policies

All clients use HTTP request interceptors enforcing:
- **Max Retries**: 3 attempts.
- **Backoff Interval**: Exponential delay (1s, 2s, 4s).
- **Offline Defaults**: In case of public service downtime, clients return baseline defaults.
