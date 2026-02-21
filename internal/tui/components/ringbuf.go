package components

// RingBuffer is a fixed-capacity circular buffer of strings.
// When full, new appends overwrite the oldest entry.
type RingBuffer struct {
	data  []string
	cap   int
	head  int // next write position
	count int // items stored
}

// NewRingBuffer creates a ring buffer with the given capacity.
func NewRingBuffer(capacity int) *RingBuffer {
	return &RingBuffer{
		data: make([]string, capacity),
		cap:  capacity,
	}
}

// Append adds a line to the buffer, overwriting the oldest if full.
func (r *RingBuffer) Append(line string) {
	r.data[r.head] = line
	r.head = (r.head + 1) % r.cap
	if r.count < r.cap {
		r.count++
	}
}

// Lines returns all stored lines in insertion order (oldest first).
func (r *RingBuffer) Lines() []string {
	if r.count == 0 {
		return nil
	}
	lines := make([]string, r.count)
	start := (r.head - r.count + r.cap) % r.cap
	for i := 0; i < r.count; i++ {
		lines[i] = r.data[(start+i)%r.cap]
	}
	return lines
}

// Len returns the number of items currently stored.
func (r *RingBuffer) Len() int { return r.count }

// Clear empties the buffer.
func (r *RingBuffer) Clear() {
	r.head = 0
	r.count = 0
}
