package web

import (
	"encoding/json"
	"net/http"
	"sync"
	"time"
)

// countryToPaisID maps the ISO-3166 country code to the platform's paisId
// (ids from the `paises` table). Only the countries currently supported by
// ClassExpress are present; unknown countries yield 0 (no preselect).
var countryToPaisID = map[string]int{
	"CL": 1, // Chile
	"AR": 2, // Argentina
	"CO": 3, // Colombia
	"PE": 4, // Perú
	"MX": 5, // México
	"ES": 6, // España
	"US": 7, // Estados Unidos
}

// geoEntry holds a cached IP lookup with its expiration time.
type geoEntry struct {
	country string
	expires time.Time
}

// geoCache caches IP -> country lookups in memory so we don't hit the external
// geo service on every landing view. It is guarded by a mutex.
type geoCache struct {
	mu      sync.Mutex
	entries map[string]geoEntry
	ttl     time.Duration
	max     int
}

var sharedGeo = &geoCache{
	entries: make(map[string]geoEntry),
	ttl:     time.Hour,
	max:     20000,
}

// get returns the cached country for ip and whether it was present.
func (g *geoCache) get(ip string) (string, bool) {
	g.mu.Lock()
	defer g.mu.Unlock()
	e, ok := g.entries[ip]
	if !ok {
		return "", false
	}
	if time.Now().After(e.expires) {
		delete(g.entries, ip)
		return "", false
	}
	return e.country, true
}

// set caches country for ip, evicting the oldest entries when over capacity.
func (g *geoCache) set(ip, country string) {
	g.mu.Lock()
	defer g.mu.Unlock()
	g.entries[ip] = geoEntry{country: country, expires: time.Now().Add(g.ttl)}
	if len(g.entries) > g.max {
		now := time.Now()
		for k, v := range g.entries {
			if now.After(v.expires) {
				delete(g.entries, k)
			}
		}
		// If still over capacity, clear expired-anystyle by dropping oldest.
		if len(g.entries) > g.max {
			oldest := time.Now().Add(time.Hour)
			var drop string
			for k, v := range g.entries {
				if v.expires.Before(oldest) {
					oldest = v.expires
					drop = k
				}
			}
			if drop != "" {
				delete(g.entries, drop)
			}
		}
	}
}

// lookupCountry resolves ip to its ISO country code via ip-api.com (free, no
// token). On any error or timeout it returns "" so callers degrade gracefully.
func lookupCountry(ip string) string {
	client := &http.Client{Timeout: 2 * time.Second}
	url := "http://ip-api.com/json/" + ip + "?fields=status,countryCode"
	resp, err := client.Get(url)
	if err != nil {
		return ""
	}
	defer resp.Body.Close()
	var out struct {
		Status      string `json:"status"`
		CountryCode string `json:"countryCode"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return ""
	}
	if out.Status != "success" {
		return ""
	}
	return out.CountryCode
}

// detectedPaisID returns the paisId for ip using the cache, falling back to a
// live lookup. Returns 0 when ip is empty/private or the country is unsupported.
func detectedPaisID(ip string) int {
	if ip == "" {
		return 0
	}
	country, ok := sharedGeo.get(ip)
	if !ok {
		country = lookupCountry(ip)
		if country != "" {
			sharedGeo.set(ip, country)
		}
	}
	return countryToPaisID[country]
}
