package model

// Project represents a discovered project with its metadata.
type Project struct {
	Name   string
	Path   string
	Group  string
	Type   string // "ddt", "composer", "node", "standard"
	Branch string
	Clean  bool
}
