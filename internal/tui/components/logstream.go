package components

import (
	"bufio"
	"context"
	"io"
	"time"

	tea "github.com/charmbracelet/bubbletea"
	"github.com/christhomas/docker-dev-tools/internal/docker"
	"github.com/docker/docker/pkg/stdcopy"
)

// containerTab identifies which container a log message came from.
type containerTab int

const (
	tabProxy containerTab = iota
	tabConfigGen
	tabDNS
	tabStatus
	numTabs
)

// logLinesMsg delivers a batch of log lines from a container stream.
type logLinesMsg struct {
	tab   containerTab
	lines []string
}

// logStreamErrMsg reports a stream error (EOF, context cancelled, etc.).
type logStreamErrMsg struct {
	tab containerTab
	err error
}

// startLogStream opens a Docker container log stream and returns a channel
// that delivers batches of log lines. Each batch contains up to 50 lines or
// is flushed every 100ms, whichever comes first. The goroutine exits when
// ctx is cancelled or the stream ends.
func startLogStream(d *docker.Client, containerName string, ctx context.Context) (<-chan []string, error) {
	reader, err := d.ContainerLogs(ctx, containerName, true)
	if err != nil {
		return nil, err
	}

	ch := make(chan []string, 8)

	go func() {
		defer reader.Close()
		defer close(ch)

		// Demux the Docker multiplexed stream (stdout+stderr) into a single pipe.
		pr, pw := io.Pipe()
		go func() {
			defer pw.Close()
			// Merge stdout and stderr into one stream.
			_, _ = stdcopy.StdCopy(pw, pw, reader)
		}()

		// Read lines from the demuxed pipe.
		scanner := bufio.NewScanner(pr)
		lineCh := make(chan string, 64)
		go func() {
			defer close(lineCh)
			for scanner.Scan() {
				select {
				case lineCh <- scanner.Text():
				case <-ctx.Done():
					return
				}
			}
		}()

		var batch []string
		flush := time.NewTicker(100 * time.Millisecond)
		defer flush.Stop()

		for {
			select {
			case line, ok := <-lineCh:
				if !ok {
					if len(batch) > 0 {
						select {
						case ch <- batch:
						case <-ctx.Done():
						}
					}
					return
				}
				batch = append(batch, line)
				if len(batch) >= 50 {
					select {
					case ch <- batch:
					case <-ctx.Done():
						return
					}
					batch = nil
				}
			case <-flush.C:
				if len(batch) > 0 {
					select {
					case ch <- batch:
					case <-ctx.Done():
						return
					}
					batch = nil
				}
			case <-ctx.Done():
				return
			}
		}
	}()

	return ch, nil
}

// waitForLogLines returns a tea.Cmd that blocks until the next batch of lines
// arrives from a log stream channel, then delivers it as a logLinesMsg.
func waitForLogLines(ch <-chan []string, tab containerTab) tea.Cmd {
	return func() tea.Msg {
		lines, ok := <-ch
		if !ok {
			return logStreamErrMsg{tab: tab, err: io.EOF}
		}
		return logLinesMsg{tab: tab, lines: lines}
	}
}
