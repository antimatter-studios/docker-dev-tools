package service

import (
	"fmt"
	"os/exec"
	"strings"
)

// GitService provides git operations.
type GitService struct{}

// NewGitService creates a new git service.
func NewGitService() *GitService {
	return &GitService{}
}

// Pull runs git pull in the given directory.
func (s *GitService) Pull(dir string) error {
	cmd := exec.Command("git", "pull")
	cmd.Dir = dir
	if out, err := cmd.CombinedOutput(); err != nil {
		return fmt.Errorf("git pull in %s: %s: %w", dir, strings.TrimSpace(string(out)), err)
	}
	return nil
}

// Push runs git push in the given directory.
func (s *GitService) Push(dir string) error {
	cmd := exec.Command("git", "push")
	cmd.Dir = dir
	if out, err := cmd.CombinedOutput(); err != nil {
		return fmt.Errorf("git push in %s: %s: %w", dir, strings.TrimSpace(string(out)), err)
	}
	return nil
}

// CurrentBranch returns the current git branch for a directory.
func (s *GitService) CurrentBranch(dir string) (string, error) {
	cmd := exec.Command("git", "rev-parse", "--abbrev-ref", "HEAD")
	cmd.Dir = dir
	out, err := cmd.Output()
	if err != nil {
		return "", fmt.Errorf("getting branch in %s: %w", dir, err)
	}
	return strings.TrimSpace(string(out)), nil
}

// IsClean checks if the working directory has no uncommitted changes.
func (s *GitService) IsClean(dir string) (bool, error) {
	cmd := exec.Command("git", "status", "--porcelain")
	cmd.Dir = dir
	out, err := cmd.Output()
	if err != nil {
		return false, fmt.Errorf("checking status in %s: %w", dir, err)
	}
	return len(strings.TrimSpace(string(out))) == 0, nil
}

// Clone clones a repository to a target directory.
func (s *GitService) Clone(url, dir, branch string) error {
	args := []string{"clone"}
	if branch != "" {
		args = append(args, "-b", branch)
	}
	args = append(args, url, dir)

	cmd := exec.Command("git", args...)
	if out, err := cmd.CombinedOutput(); err != nil {
		return fmt.Errorf("cloning %s: %s: %w", url, strings.TrimSpace(string(out)), err)
	}
	return nil
}
