//go:build darwin

package platform

import (
	"crypto/sha1" //nolint:gosec // the keychain tools identify certificates by SHA-1
	"encoding/hex"
	"encoding/json"
	"encoding/pem"
	"errors"
	"fmt"
	"os"
	"os/exec"
	"strings"

	"github.com/christhomas/docker-dev-tools/internal/tui/styles"
)

// systemKeychain holds the roots every user on the machine trusts. Safari, Chrome and
// native apps all consult it.
const systemKeychain = "/Library/Keychains/System.keychain"

func (d *darwinPlatform) TrustCA(certPath string) error {
	fmt.Fprintln(os.Stderr, styles.SudoNotice("Trusting ddt's development CA",
		fmt.Sprintf("File: %s", certPath),
		fmt.Sprintf("Keychain: %s", systemKeychain)))
	cmd := exec.Command("sudo", "security", "add-trusted-cert", "-d", "-r", "trustRoot", "-k", systemKeychain, certPath)
	if out, err := cmd.CombinedOutput(); err != nil {
		return fmt.Errorf("adding the CA to the System keychain: %s: %w", strings.TrimSpace(string(out)), err)
	}
	return nil
}

func (d *darwinPlatform) UntrustCA(certPath string) error {
	fingerprint, err := certFingerprint(certPath)
	if err != nil {
		return err
	}
	fmt.Fprintln(os.Stderr, styles.SudoNotice("Removing ddt's development CA",
		fmt.Sprintf("Keychain: %s", systemKeychain),
		fmt.Sprintf("SHA-1: %s", fingerprint)))
	// The trust setting first, then the certificate itself. Either may already be gone.
	_ = exec.Command("sudo", "security", "remove-trusted-cert", "-d", certPath).Run()
	if out, err := exec.Command("sudo", "security", "delete-certificate", "-Z", fingerprint, systemKeychain).CombinedOutput(); err != nil {
		return fmt.Errorf("removing the CA from the System keychain: %s: %w", strings.TrimSpace(string(out)), err)
	}
	return nil
}

func (d *darwinPlatform) IsCATrusted(certPath string) (bool, error) {
	fingerprint, err := certFingerprint(certPath)
	if err != nil {
		return false, err
	}
	out, err := exec.Command("security", "find-certificate", "-a", "-Z", systemKeychain).Output()
	if err != nil {
		return false, fmt.Errorf("reading the System keychain: %w", err)
	}
	return strings.Contains(string(out), "SHA-1 hash: "+fingerprint), nil
}

// TrustCAInSimulators adds the certificate to every booted iOS simulator. Each keeps a
// trust store of its own, so trusting the CA on the Mac is not enough for them, and a
// simulator created later needs this again.
func (d *darwinPlatform) TrustCAInSimulators(certPath string) (int, error) {
	out, err := exec.Command("xcrun", "simctl", "list", "--json", "devices", "booted").Output()
	if err != nil {
		return 0, fmt.Errorf("listing booted simulators (is Xcode installed?): %w", err)
	}
	udids, err := bootedSimulators(out)
	if err != nil {
		return 0, err
	}
	for i, udid := range udids {
		if out, err := exec.Command("xcrun", "simctl", "keychain", udid, "add-root-cert", certPath).CombinedOutput(); err != nil {
			return i, fmt.Errorf("adding the CA to simulator %s: %s: %w", udid, strings.TrimSpace(string(out)), err)
		}
	}
	return len(udids), nil
}

// bootedSimulators picks the booted devices out of `simctl list --json devices`.
func bootedSimulators(listJSON []byte) ([]string, error) {
	var list struct {
		Devices map[string][]struct {
			UDID  string `json:"udid"`
			State string `json:"state"`
		} `json:"devices"`
	}
	if err := json.Unmarshal(listJSON, &list); err != nil {
		return nil, fmt.Errorf("reading the simulator list: %w", err)
	}
	var udids []string
	for _, devices := range list.Devices {
		for _, dev := range devices {
			if dev.State == "Booted" {
				udids = append(udids, dev.UDID)
			}
		}
	}
	return udids, nil
}

// certFingerprint returns the SHA-1 of the PEM certificate at path in upper-case hex,
// the form `security` prints and accepts.
func certFingerprint(path string) (string, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return "", err
	}
	block, _ := pem.Decode(data)
	if block == nil || block.Type != "CERTIFICATE" {
		return "", errors.New(path + " holds no certificate")
	}
	sum := sha1.Sum(block.Bytes) //nolint:gosec // an identifier, not a security check
	return strings.ToUpper(hex.EncodeToString(sum[:])), nil
}
