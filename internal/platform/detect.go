package platform

// Detect returns the platform implementation for the current OS.
// Build tags select the appropriate newPlatform() function.
func Detect() Platform {
	return newPlatform()
}
