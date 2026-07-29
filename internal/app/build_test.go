package app

import (
	"strings"
	"testing"
	"time"
)

// The bug this pair of methods exists to fix: String() began with the program name, and
// cobra's version template prefixes its own, so `ddt --version` said
// "ddt version ddt 2.2.0 (…) built …".
func TestStringDoesNotRepeatTheProgramName(t *testing.T) {
	b := BuildInfo{Version: "2.2.0", Commit: "a528ffd1cb217ea9fe076e91862ffec16bd550f8"}
	if got := b.String(); got != "2.2.0" {
		t.Errorf("String() = %q, want just the version", got)
	}
	if strings.Contains(b.String(), "ddt") {
		t.Errorf("String() names the program: %q", b.String())
	}
}

func TestStringFallsBackToDev(t *testing.T) {
	if got := (BuildInfo{}).String(); got != "dev" {
		t.Errorf("String() = %q, want %q for an unstamped build", got, "dev")
	}
}

func TestDetailsShortensTheCommit(t *testing.T) {
	b := BuildInfo{Version: "2.2.0", Commit: "a528ffd1cb217ea9fe076e91862ffec16bd550f8"}
	got := b.Details()
	if !strings.Contains(got, "commit  a528ffd\n") {
		t.Errorf("commit not shortened to 7 characters:\n%s", got)
	}
	if strings.Contains(got, "a528ffd1") {
		t.Errorf("full sha still present:\n%s", got)
	}
}

// A build from source has no commit or date, and goreleaser writes "none" when it has
// nothing. Printing "commit  none" is worse than printing nothing.
func TestDetailsOmitsWhatItDoesNotKnow(t *testing.T) {
	for _, commit := range []string{"", "none", "unknown"} {
		got := BuildInfo{Version: "dev", Commit: commit}.Details()
		if strings.Contains(got, "commit") {
			t.Errorf("commit=%q produced a commit line:\n%s", commit, got)
		}
		if strings.Contains(got, "built") {
			t.Errorf("commit=%q produced a built line with no date:\n%s", commit, got)
		}
		// The toolchain and platform are always known, so that line is always there.
		if !strings.Contains(got, "go      ") {
			t.Errorf("commit=%q lost the go line:\n%s", commit, got)
		}
		for _, line := range strings.Split(strings.TrimRight(got, "\n"), "\n") {
			if strings.TrimSpace(strings.SplitN(strings.TrimSpace(line), " ", 2)[1]) == "" {
				t.Errorf("a label was printed with nothing after it: %q", line)
			}
		}
	}
}

func TestDetailsShowsTheDateWithItsAge(t *testing.T) {
	stamp := time.Now().UTC().Add(-3 * time.Hour).Format(time.RFC3339)
	got := BuildInfo{Version: "2.2.0", Date: stamp}.Details()
	if !strings.Contains(got, "3 hours ago") {
		t.Errorf("age missing or wrong:\n%s", got)
	}
	if !strings.Contains(got, "UTC") {
		t.Errorf("date not rendered readably:\n%s", got)
	}
	if strings.Contains(got, stamp) {
		t.Errorf("raw RFC3339 stamp shown verbatim:\n%s", got)
	}
}

// An unparseable stamp should still be shown. Hiding it because it is in an unexpected
// shape loses the only record of when the binary was built.
func TestDetailsKeepsAnUnparseableDate(t *testing.T) {
	got := BuildInfo{Version: "2.2.0", Date: "last Tuesday"}.Details()
	if !strings.Contains(got, "last Tuesday") {
		t.Errorf("unparseable stamp was dropped:\n%s", got)
	}
}

func TestAge(t *testing.T) {
	for _, c := range []struct {
		d    time.Duration
		want string
	}{
		{30 * time.Second, "just now"},
		{time.Minute, "1 minute ago"},
		{5 * time.Minute, "5 minutes ago"},
		{time.Hour, "1 hour ago"},
		{3 * time.Hour, "3 hours ago"},
		{25 * time.Hour, "1 day ago"},
		{72 * time.Hour, "3 days ago"},
		{-time.Hour, "clock skew"},
	} {
		if got := age(c.d); got != c.want {
			t.Errorf("age(%s) = %q, want %q", c.d, got, c.want)
		}
	}
}
