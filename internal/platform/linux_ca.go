//go:build linux

package platform

import (
	"bytes"
	"errors"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
)

// caAnchorName is ddt's CA in the system trust store's source directory.
const caAnchorName = "ddt-development-ca.crt"

// caAnchor returns where a CA goes to be trusted system-wide, and the command that
// rebuilds the trust store from there: Debian and Ubuntu first, then Fedora, RHEL and
// Arch.
func caAnchor() (dir, update string, err error) {
	if _, err := exec.LookPath("update-ca-certificates"); err == nil {
		return "/usr/local/share/ca-certificates", "update-ca-certificates", nil
	}
	if _, err := exec.LookPath("update-ca-trust"); err == nil {
		if _, err := os.Stat("/etc/pki/ca-trust/source/anchors"); err == nil {
			return "/etc/pki/ca-trust/source/anchors", "update-ca-trust", nil
		}
		return "/etc/ca-certificates/trust-source/anchors", "update-ca-trust", nil
	}
	return "", "", errors.New("found neither update-ca-certificates nor update-ca-trust")
}

func (l *linuxPlatform) TrustCA(certPath string) error {
	dir, update, err := caAnchor()
	if err != nil {
		return err
	}
	data, err := os.ReadFile(certPath)
	if err != nil {
		return err
	}
	target := filepath.Join(dir, caAnchorName)
	fmt.Fprintln(os.Stderr, styles.SudoNotice("Trusting ddt's development CA",
		fmt.Sprintf("File: %s", target),
		fmt.Sprintf("Command: %s", update)))

	if out, err := exec.Command("sudo", "mkdir", "-p", dir).CombinedOutput(); err != nil {
		return fmt.Errorf("creating %s: %s: %w", dir, strings.TrimSpace(string(out)), err)
	}
	cmd := exec.Command("sudo", "tee", target)
	cmd.Stdin = bytes.NewReader(data)
	var stderr bytes.Buffer
	cmd.Stderr = &stderr
	if err := cmd.Run(); err != nil {
		return fmt.Errorf("writing %s: %s: %w", target, strings.TrimSpace(stderr.String()), err)
	}
	_ = exec.Command("sudo", "chmod", "644", target).Run()
	if out, err := exec.Command("sudo", update).CombinedOutput(); err != nil {
		return fmt.Errorf("%s: %s: %w", update, strings.TrimSpace(string(out)), err)
	}
	return nil
}

func (l *linuxPlatform) UntrustCA(_ string) error {
	dir, update, err := caAnchor()
	if err != nil {
		return err
	}
	target := filepath.Join(dir, caAnchorName)
	if _, err := os.Stat(target); errors.Is(err, os.ErrNotExist) {
		return nil
	}
	fmt.Fprintln(os.Stderr, styles.SudoNotice("Removing ddt's development CA",
		fmt.Sprintf("File: %s", target),
		fmt.Sprintf("Command: %s", update)))
	if out, err := exec.Command("sudo", "rm", "-f", target).CombinedOutput(); err != nil {
		return fmt.Errorf("removing %s: %s: %w", target, strings.TrimSpace(string(out)), err)
	}
	if out, err := exec.Command("sudo", update).CombinedOutput(); err != nil {
		return fmt.Errorf("%s: %s: %w", update, strings.TrimSpace(string(out)), err)
	}
	return nil
}

func (l *linuxPlatform) IsCATrusted(certPath string) (bool, error) {
	dir, _, err := caAnchor()
	if err != nil {
		return false, err
	}
	installed, err := os.ReadFile(filepath.Join(dir, caAnchorName))
	if errors.Is(err, os.ErrNotExist) {
		return false, nil
	}
	if err != nil {
		return false, err
	}
	want, err := os.ReadFile(certPath)
	if err != nil {
		return false, err
	}
	return bytes.Equal(installed, want), nil
}

func (l *linuxPlatform) TrustCAInSimulators(_ string) (int, error) {
	return 0, errors.New("iOS simulators only run on macOS")
}
