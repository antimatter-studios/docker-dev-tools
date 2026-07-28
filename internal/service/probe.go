package service

import (
	"crypto/tls"
	"fmt"
	"net/http"
	"strings"
	"sync"
	"time"
)

// ProbeResult holds the outcome of probing a single proxied service.
type ProbeResult struct {
	StatusCode int    // HTTP status code, 0 if connection failed
	Status     string // human-readable status text (e.g. "200", "error")
	Fallback   bool   // true if response came from the proxy's fallback page
}

// ProbeServices checks each proxied service URL in parallel and returns
// a result for each entry (same order as input).
func ProbeServices(entries []ProxyStatusEntry) []ProbeResult {
	client := &http.Client{
		Timeout: 3 * time.Second,
		Transport: &http.Transport{
			TLSClientConfig: &tls.Config{InsecureSkipVerify: true},
		},
		CheckRedirect: func(req *http.Request, via []*http.Request) error {
			return http.ErrUseLastResponse
		},
	}

	results := make([]ProbeResult, len(entries))
	var wg sync.WaitGroup
	wg.Add(len(entries))

	for i, e := range entries {
		go func(idx int, entry ProxyStatusEntry) {
			defer wg.Done()
			url := entry.Proto + "://" + entry.Host
			if entry.Path != "" && entry.Path != "/" {
				url += entry.Path
			}
			resp, err := client.Get(url)
			if err != nil {
				results[idx] = ProbeResult{Status: "error"}
				return
			}
			defer func() { _ = resp.Body.Close() }()
			results[idx] = ProbeResult{
				StatusCode: resp.StatusCode,
				Status:     fmt.Sprintf("%d", resp.StatusCode),
				Fallback:   IsProxyFallback(resp),
			}
		}(i, e)
	}

	wg.Wait()
	return results
}

// IsProxyFallback returns true if the response came from the proxy's own
// fallback error page rather than an actual upstream service. The docker-proxy
// image sets x-proxy-upstream-* headers when serving its fallback page.
func IsProxyFallback(resp *http.Response) bool {
	for key := range resp.Header {
		if strings.HasPrefix(strings.ToLower(key), "x-proxy-upstream") {
			return true
		}
	}
	return false
}
