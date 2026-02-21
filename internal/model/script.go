package model

// RunConfiguration represents a script execution plan.
type RunConfiguration struct {
	ProjectName string
	ScriptName  string
	Steps       []RunStep
}

// RunStep is a single step in a script execution.
type RunStep struct {
	ProjectName string
	ScriptName  string
	Command     string
}
