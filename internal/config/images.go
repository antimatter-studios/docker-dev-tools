package config

import (
	"fmt"
	"strings"
)

// ReconcileImage decides which image reference a service should use on startup.
//
// The build args say which images THIS ddt is compatible with: a release stamps the
// digest each component had when it was built, so a binary always runs the components
// it was released against. That matters because some pairs are only correct together —
// a proxy image whose template expects a value its generator does not yet supply
// renders a config nginx will not load.
//
// The config file is where that lands, and the rule is: the binary wins, unless the
// file says the image is the user's own.
//
//	"proxy": {
//	    "docker_image": "ghcr.io/…/docker-proxy:my-local-build",
//	    "custom_image": true          ← ddt leaves it alone
//	}
//
// Without that flag an edited value is replaced, so the returned note exists to make
// the replacement visible rather than silent — the failure people actually hit is
// editing docker_image, forgetting the flag, and wondering why their image stopped
// being used.
//
// next is the reference to use; note is empty unless something changed.
func ReconcileImage(service, current, expected string, custom bool) (next, note string) {
	switch {
	case custom:
		// Declared as the user's own: never touched, whatever the binary expects.
		return current, ""
	case expected == "":
		// Nothing stamped — a build from source. Leave the file as it is rather than
		// blanking a working configuration.
		return current, ""
	case current == expected:
		return current, ""
	case current == "":
		return expected, ""
	default:
		return expected, fmt.Sprintf(
			"%s: replaced docker_image (was %s) with %s — add \"custom_image\": true beside it to keep your own",
			service, shortRef(current), shortRef(expected))
	}
}

// shortRef abbreviates an image reference for display. A digest-pinned ghcr.io
// reference is ~110 characters, so a note naming two of them wraps several times in a
// terminal and buries the one thing it is trying to say. The registry and org are
// identical across every image ddt runs, and 12 hex digits identify a digest as well
// here as they do in git.
//
// Only for messages — the config file always records the full reference, which is what
// docker is given and what anyone editing it needs to see.
func shortRef(ref string) string {
	short := ref
	if i := strings.LastIndex(short, "/"); i >= 0 {
		short = short[i+1:]
	}
	if i := strings.Index(short, "@sha256:"); i >= 0 {
		if hex := short[i+len("@sha256:"):]; len(hex) > 12 {
			short = short[:i] + "@sha256:" + hex[:12]
		}
	}
	return short
}
