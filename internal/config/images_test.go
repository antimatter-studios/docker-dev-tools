package config

import (
	"strings"
	"testing"
)

func TestReconcileImage(t *testing.T) {
	const (
		expected = "ghcr.io/antimatter-studios/docker-proxy@sha256:aaa"
		older    = "ghcr.io/antimatter-studios/docker-proxy@sha256:bbb"
	)

	// The case this whole mechanism exists for: a config written by an older ddt
	// keeps whatever it recorded, so a binary that pins digests would still run
	// :latest for every existing user unless startup replaces it.
	t.Run("a stale value is replaced and reported", func(t *testing.T) {
		next, note := ReconcileImage("proxy", "ghcr.io/antimatter-studios/docker-proxy:latest", expected, false)
		if next != expected {
			t.Errorf("next = %q, want the binary's %q", next, expected)
		}
		if note == "" {
			t.Error("replacement was silent; it must say what it did")
		}
	})

	// Editing docker_image and forgetting the flag is the mistake people make, so the
	// note has to name the flag that prevents it.
	t.Run("the note explains how to keep a custom image", func(t *testing.T) {
		_, note := ReconcileImage("proxy", "my-local-build", expected, false)
		if note == "" {
			t.Fatal("no note")
		}
		// Abbreviated, but the distinguishing part of each reference survives — the
		// note is useless if it cannot say which image replaced which.
		for _, want := range []string{"custom_image", "my-local-build", "docker-proxy@sha256:aaa"} {
			if !strings.Contains(note, want) {
				t.Errorf("note %q does not mention %q", note, want)
			}
		}
	})

	t.Run("custom_image is never touched", func(t *testing.T) {
		next, note := ReconcileImage("proxy", "my-local-build", expected, true)
		if next != "my-local-build" {
			t.Errorf("next = %q, want the user's own image", next)
		}
		if note != "" {
			t.Errorf("note = %q, want silence when nothing changed", note)
		}
	})

	// A build from source stamps nothing. Blanking a working configuration because
	// the binary has no opinion would be worse than leaving it alone.
	t.Run("an unstamped build leaves the config alone", func(t *testing.T) {
		next, note := ReconcileImage("proxy", older, "", false)
		if next != older || note != "" {
			t.Errorf("next = %q note = %q, want the existing value untouched", next, note)
		}
	})

	// Shipped broken in 2.2.0: the defaults in defaults.go are `:latest`, so a build
	// from source has expected = "…:latest" rather than "". That is not "no opinion",
	// it is the opposite opinion, and it UNPINNED a config a release had pinned —
	// running a locally built ddt once replaced all three digests and announced it.
	//
	// Only a digest is an instruction. A floating tag means this binary does not know
	// which build it wants, so it must not overrule one that did.
	t.Run("a source build does not unpin a pinned config", func(t *testing.T) {
		next, note := ReconcileImage("proxy", expected, "ghcr.io/antimatter-studios/docker-proxy:latest", false)
		if next != expected {
			t.Errorf("next = %q, want the pinned digest %q left alone", next, expected)
		}
		if note != "" {
			t.Errorf("note = %q, want silence — nothing should have changed", note)
		}
	})

	// The same rule must not stop a release from correcting a `:latest` config, which is
	// the case the mechanism exists for.
	t.Run("a pinned build still corrects a floating config", func(t *testing.T) {
		next, note := ReconcileImage("proxy", "ghcr.io/antimatter-studios/docker-proxy:latest", expected, false)
		if next != expected || note == "" {
			t.Errorf("next = %q note = %q, want the digest applied and reported", next, note)
		}
	})

	// A real digest reference is ~110 characters, so a note naming two of them is a
	// ~230-character line that wraps several times and hides its own point.
	t.Run("a note stays readable with real-length references", func(t *testing.T) {
		const (
			latest = "ghcr.io/antimatter-studios/docker-proxy:latest"
			digest = "ghcr.io/antimatter-studios/docker-proxy@sha256:" +
				"9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08"
		)
		_, note := ReconcileImage("proxy", latest, digest, false)
		// Two 80-column lines. The guard is against the unabbreviated form, which is
		// ~230 characters; it is not a budget for the wording.
		if len(note) > 160 {
			t.Errorf("note is %d chars, too long to read in a terminal:\n%s", len(note), note)
		}
		if strings.Contains(note, "9f86d081884c7d659a2") {
			t.Errorf("full digest was not abbreviated:\n%s", note)
		}
		for _, want := range []string{"proxy:", "docker-proxy:latest", "docker-proxy@sha256:9f86d0818", "custom_image"} {
			if !strings.Contains(note, want) {
				t.Errorf("note %q does not mention %q", note, want)
			}
		}
	})

	t.Run("no change means no note", func(t *testing.T) {
		if _, note := ReconcileImage("proxy", expected, expected, false); note != "" {
			t.Errorf("note = %q, want none", note)
		}
	})

	t.Run("an empty config takes the expected value quietly", func(t *testing.T) {
		next, note := ReconcileImage("proxy", "", expected, false)
		if next != expected {
			t.Errorf("next = %q, want %q", next, expected)
		}
		if note != "" {
			t.Errorf("note = %q — filling a blank is not a replacement worth reporting", note)
		}
	})
}
