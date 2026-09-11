//go:build darwin

package platform

import "testing"

func TestBootedSimulators(t *testing.T) {
	udids, err := bootedSimulators([]byte(`{"devices":{
		"com.apple.CoreSimulator.SimRuntime.iOS-26-0":[
			{"udid":"A","state":"Booted","name":"iPhone 17"},
			{"udid":"B","state":"Shutdown","name":"iPhone Air"}],
		"com.apple.CoreSimulator.SimRuntime.watchOS-26-0":[]}}`))
	if err != nil {
		t.Fatal(err)
	}
	if len(udids) != 1 || udids[0] != "A" {
		t.Errorf("udids = %v, want [A]", udids)
	}
}
